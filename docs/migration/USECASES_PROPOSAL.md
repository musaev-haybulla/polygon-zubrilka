# UseCase классы для проекта "Зубрилка"

## Принципы архитектуры UseCase

- **Один класс = одно бизнес-действие**
- **Название = глагол + существительное** (CreatePoem, UploadAudio)
- **Метод execute()** - единственная точка входа
- **Инъекция зависимостей** через конструктор
- **События Laravel** для развязки компонентов
- **DTO для входных данных** - типизация без фанатизма

## Poems (Стихотворения)

### UseCase: CreatePoem
**Namespace**: `App\UseCases\Poems`  
**Базируется на функциях**: 
- `PoemProcessor->processForm()` из classes/PoemProcessor.php:36
- `process_poem.php` логика обработки формы
- SQL операции создания поэм и фрагментов

**Метод execute():**
- Входные: `CreatePoemData $data`
- Возвращает: `Poem` (с отношениями authors, fragments)
- Побочные эффекты: индексация в MeiliSearch, событие PoemCreated

**Зависимости:**
```php
public function __construct(
    private PoemValidator $validator,
    private MeilisearchIndexer $indexer
) {}
```

**Пример реализации:**
```php
public function execute(CreatePoemData $data): Poem
{
    $this->validator->validateCreation($data);
    
    DB::transaction(function () use ($data) {
        $poem = Poem::create([
            'title' => $data->title,
            'year_written' => $data->yearWritten,
            'status' => 'draft',
            'is_divided' => $data->isDivided,
        ]);
        
        $poem->authors()->attach($data->authorIds);
        
        foreach ($data->fragments as $fragmentData) {
            $fragment = $poem->fragments()->create($fragmentData);
            $this->createVerses($fragment, $fragmentData['verses']);
        }
        
        $this->indexer->indexPoem($poem);
        event(new PoemCreated($poem));
        
        return $poem->load(['authors', 'fragments.lines']);
    });
}
```

---

### UseCase: PublishPoem
**Namespace**: `App\UseCases\Poems`  
**Базируется на функциях**: 
- Новая бизнес-логика (в текущем коде нет явной публикации)
- Валидация перед публикацией

**Метод execute():**
- Входные: `Poem $poem`
- Возвращает: `bool`
- Побочные эффекты: обновление индекса поиска, уведомления

**Пример реализации:**
```php
public function execute(Poem $poem): bool
{
    if (!$poem->canBePublished()) {
        throw new ValidationException('Poem cannot be published');
    }
    
    $poem->update(['status' => 'published']);
    $this->indexer->updatePoemIndex($poem);
    event(new PoemPublished($poem));
    
    return true;
}
```

---

### UseCase: DeletePoem
**Namespace**: `App\UseCases\Poems`  
**Базируется на функциях**: 
- Логика удаления из текущих SQL запросов
- Очистка связанных аудиофайлов

**Метод execute():**
- Входные: `Poem $poem`
- Возвращает: `bool`
- Побочные эффекты: удаление файлов, очистка индекса

**Зависимости:**
```php
public function __construct(
    private FileManager $fileManager,
    private MeilisearchIndexer $indexer
) {}
```

---

### UseCase: SearchPoems
**Namespace**: `App\UseCases\Poems`  
**Базируется на функциях**: 
- `SearchService->performFullSearch()` из classes/SearchService.php:21
- `DatabaseHelper` поисковые методы (строки 476-504)
- `search_api.php` логика

**Метод execute():**
- Входные: `SearchFilters $filters`
- Возвращает: `array` (структурированные результаты)
- Побочные эффекты: логирование поисковых запросов

**Пример реализации:**
```php
public function execute(SearchFilters $filters): array
{
    // Поиск через MeiliSearch для быстрых результатов
    $meilisearchResults = $this->indexer->search($filters->query);
    
    // Дополнительная фильтрация через Eloquent
    $dbResults = Poem::query()
        ->published()
        ->when($filters->authorId, fn($q) => $q->byAuthor($filters->authorId))
        ->when($filters->grade, fn($q) => $q->byGrade($filters->grade))
        ->get();
    
    return $this->aggregateResults($meilisearchResults, $dbResults);
}
```

## Audio (Аудио)

### UseCase: UploadAudio
**Namespace**: `App\UseCases\Audio`  
**Базируется на функциях**: 
- `add_audio_step1.php` логика загрузки (строки 50-120)
- `AudioFileHelper` методы (classes/AudioFileHelper.php)
- `AudioSorter` для позиционирования

**Метод execute():**
- Входные: `Fragment $fragment, UploadAudioData $data`
- Возвращает: `Track`
- Побочные эффекты: сохранение файла, создание миниатюр

**Зависимости:**
```php
public function __construct(
    private AudioProcessor $processor,
    private FileManager $fileManager,
    private AudioValidator $validator
) {}
```

**Пример реализации:**
```php
public function execute(Fragment $fragment, UploadAudioData $data): Track
{
    $this->validator->validateUpload($data->file);
    
    // Получение метаданных аудио
    $duration = $this->processor->getDuration($data->file);
    $filename = $this->fileManager->generateUniqueFilename($data->title);
    
    // Сохранение файла
    $path = $this->fileManager->storeAudio($fragment, $data->file, $filename);
    
    // Создание записи
    $track = $fragment->tracks()->create([
        'filename' => $filename,
        'original_filename' => $data->file->getClientOriginalName(),
        'duration' => $duration,
        'is_ai_generated' => $data->isAiGenerated,
        'title' => $data->title,
        'sort_order' => $fragment->tracks()->count() + 1,
        'status' => 'draft',
    ]);
    
    event(new AudioUploaded($track));
    return $track;
}
```

---

### UseCase: TrimAudio
**Namespace**: `App\UseCases\Audio`  
**Базируется на функциях**: 
- `add_audio_step2.php` логика обрезки (строки 30-80)
- FFmpeg операции через AudioFileHelper

**Метод execute():**
- Входные: `Track $track, TrimAudioData $data`
- Возвращает: `void`
- Побочные эффекты: перезапись файла, обновление длительности

**Пример реализации:**
```php
public function execute(Track $track, TrimAudioData $data): void
{
    $this->validator->validateTrimParams($data);
    
    $originalPath = $this->fileManager->getTrackPath($track);
    $tempPath = $this->processor->trimAudio($originalPath, $data->startTime, $data->endTime);
    
    // Замена оригинального файла
    $this->fileManager->replaceFile($originalPath, $tempPath);
    
    // Обновление метаданных
    $newDuration = $this->processor->getDuration($originalPath);
    $track->update(['duration' => $newDuration]);
    
    event(new AudioTrimmed($track, $data));
}
```

---

### UseCase: DeleteAudio
**Namespace**: `App\UseCases\Audio`  
**Базируется на функциях**: 
- `delete_audio.php` логика (строки 20-50)
- `AudioFileHelper->deleteAudioFiles()` (classes/AudioFileHelper.php:398)

**Метод execute():**
- Входные: `Track $track`
- Возвращает: `bool`
- Побочные эффекты: удаление файлов, очистка таймингов

---

### UseCase: RestoreAudio
**Namespace**: `App\UseCases\Audio`  
**Базируется на функциях**: 
- `restore_original_audio.php` логика

**Метод execute():**
- Входные: `Track $track`
- Возвращает: `bool`
- Побочные эффекты: восстановление из backup'а

## Timings (Временная разметка)

### UseCase: InitializeTiming
**Namespace**: `App\UseCases\Timings`  
**Базируется на функциях**: 
- `TimingService->getInitData()` (classes/Services/TimingService.php:25)
- `api/timings/init.php` логика

**Метод execute():**
- Входные: `Track $track`
- Возвращает: `array` (данные для фронтенда)
- Побочные эффекты: нет

**Пример реализации:**
```php
public function execute(Track $track): array
{
    $fragment = $track->fragment;
    $lines = $fragment->lines()->orderBy('position')->get();
    $existingTimings = $track->timings()->with('line')->get();
    
    return [
        'track' => [
            'id' => $track->id,
            'filename' => $track->filename,
            'duration' => $track->duration,
        ],
        'lines' => $lines->map(fn($line) => [
            'id' => $line->id,
            'position' => $line->position,
            'text' => $line->text,
        ]),
        'timings' => $existingTimings->map(fn($timing) => [
            'verse_id' => $timing->verse_id,
            'end_time' => $timing->end_time,
        ]),
    ];
}
```

---

### UseCase: SaveVerseTiming
**Namespace**: `App\UseCases\Timings`  
**Базируется на функциях**: 
- `TimingService->upsertVerseEnd()` (classes/Services/TimingService.php:57)
- `api/timings/line.php` логика

**Метод execute():**
- Входные: `SaveVerseTimingData $data`
- Возвращает: `void`
- Побочные эффекты: upsert в таблицу timings

**Пример реализации:**
```php
public function execute(SaveVerseTimingData $data): void
{
    $this->validator->validateTiming($data);
    
    Timing::updateOrCreate(
        [
            'track_id' => $data->trackId,
            'verse_id' => $data->verseId,
        ],
        [
            'end_time' => $data->endTime,
        ]
    );
    
    event(new VerseTimingSaved($data));
}
```

---

### UseCase: FinalizeTiming
**Namespace**: `App\UseCases\Timings`  
**Базируется на функциях**: 
- `TimingService->finalizeTrack()` (classes/Services/TimingService.php:75)
- `api/timings/finalize.php` логика

**Метод execute():**
- Входные: `Track $track`
- Возвращает: `void`
- Побочные эффекты: активация трека, валидация полноты

**Пример реализации:**
```php
public function execute(Track $track): void
{
    if (!$track->isTimingComplete()) {
        throw new ValidationException('Timing is not complete');
    }
    
    $track->update(['status' => 'active']);
    event(new TimingFinalized($track));
}
```

## Authors (Авторы)

### UseCase: CreateAuthor
**Namespace**: `App\UseCases\Authors`  
**Базируется на функциях**: 
- `PoemProcessor->createAuthor()` (classes/PoemProcessor.php:147)

**Метод execute():**
- Входные: `CreateAuthorData $data`
- Возвращает: `Author`
- Побочные эффекты: индексация для поиска

---

### UseCase: SearchAuthors
**Namespace**: `App\UseCases\Authors`  
**Базируется на функциях**: 
- `DatabaseHelper->searchAuthors()` (classes/DatabaseHelper.php:80)
- `authors_autocomplete.php` логика

**Метод execute():**
- Входные: `string $query`
- Возвращает: `Collection<Author>`
- Побочные эффекты: нет

## Структура файлов UseCase

```
app/UseCases/
├── Poems/
│   ├── CreatePoem.php
│   ├── PublishPoem.php
│   ├── DeletePoem.php
│   ├── SearchPoems.php
│   └── UpdatePoem.php
├── Audio/
│   ├── UploadAudio.php
│   ├── TrimAudio.php
│   ├── DeleteAudio.php
│   └── RestoreAudio.php
├── Timings/
│   ├── InitializeTiming.php
│   ├── SaveVerseTiming.php
│   └── FinalizeTiming.php
└── Authors/
    ├── CreateAuthor.php
    └── SearchAuthors.php
```

## Базовый интерфейс UseCase

```php
<?php

namespace App\UseCases;

interface UseCaseInterface
{
    public function execute($data);
}
```

## Примеры DTO классов

```php
<?php

namespace App\DTOs;

class CreatePoemData
{
    public function __construct(
        public string $title,
        public ?int $yearWritten,
        public bool $isDivided,
        public array $authorIds,
        public array $fragments
    ) {}
}

class UploadAudioData
{
    public function __construct(
        public UploadedFile $file,
        public string $title,
        public bool $isAiGenerated = false
    ) {}
}

class SearchFilters
{
    public function __construct(
        public string $query,
        public ?int $authorId = null,
        public ?string $grade = null,
        public int $limit = 50
    ) {}
}
```

## События Laravel

```php
<?php

namespace App\Events;

class PoemCreated
{
    public function __construct(public Poem $poem) {}
}

class AudioUploaded
{
    public function __construct(public Track $track) {}
}

class TimingFinalized
{
    public function __construct(public Track $track) {}
}
```

## Преимущества UseCase подхода

1. **Единственная ответственность** - каждый класс делает одну вещь
2. **Тестируемость** - легко mock'ать зависимости  
3. **Читаемость** - название класса объясняет действие
4. **Переиспользуемость** - UseCase можно вызвать из разных контроллеров
5. **События** - развязка компонентов через Laravel Events
6. **Валидация** - централизованная в специализированных классах