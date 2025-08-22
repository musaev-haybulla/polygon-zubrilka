# 🎯 Гид по доменному слою в Laravel

Практическое руководство для новичков: как правильно организовать бизнес-логику в Laravel проекте.

## 🤔 Что такое доменный слой?

**Доменный слой** - это место, где живут бизнес-правила вашего приложения.

### ✅ Примеры доменной логики в проекте "Зубрилка":

```php
// 🧠 Бизнес-правила
"Последняя строка стихотворения не размечается таймингом"
"Стихотворение можно публиковать только если у него есть фрагменты"
"Время тайминга не может идти назад"
"Пустые строки в стихах игнорируются"

// 🏗️ Знания о предметной области  
"Что такое стихотворение, фрагмент, строка"
"Как связаны авторы и произведения"
"Что означает 'активный' трек"
"Когда трек считается 'готовым'"

// ⚖️ Инварианты и ограничения
"Обязательные поля и их диапазоны"
"Связи между объектами"
"Последовательность операций"
```

### ❌ НЕ доменная логика:

```php
// 🗄️ Инфраструктура
SQL запросы, сохранение файлов, внешние API

// 🖥️ Презентация  
HTTP обработка, JSON форматирование, валидация форм

// 🔧 Техническая логика
Кеширование, логирование, авторизация
```

## 🏗️ Спектр подходов к доменной логике

### 1. ❌ **Anemic Models** (плохо)

```php
// Модель - только данные
class Poem extends Model 
{
    protected $fillable = ['title', 'status'];
    // Никакой логики
}

// Вся логика в сервисах
class PoemService 
{
    public function publish(Poem $poem) { /* логика */ }
    public function canPublish(Poem $poem) { /* логика */ }
    public function calculateReadingTime(Poem $poem) { /* логика */ }
}
```

**Проблемы:**
- Данные и логика разделены
- Нарушение принципов ООП
- Трудно найти логику
- Дублирование кода

### 2. ✅ **Rich Models** (хорошо для Laravel)

```php
class Poem extends Model 
{
    // Логика рядом с данными
    public function publish(): bool
    {
        if (!$this->canBePublished()) {
            return false;
        }
        
        $this->update(['status' => 'published']);
        event(new PoemPublished($this));
        return true;
    }
    
    public function canBePublished(): bool
    {
        return $this->fragments()->exists() && 
               $this->fragments()->whereHas('verses')->exists();
    }
    
    public function calculateReadingTime(): int
    {
        $wordCount = $this->getWordCount();
        return max(1, ceil($wordCount / 150));
    }
}
```

**Преимущества:**
- Логика там, где данные
- Естественное ООП
- Легко читать и находить
- Laravel-way подход

### 3. ✅ **Domain Services** (для сложных случаев)

```php
class Poem extends Model 
{
    // Простая логика остается в модели
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}

class PoemCreationService 
{
    // Сложная логика выносится в сервис
    public function createFromText(string $title, string $text, array $authorIds): Poem
    {
        return DB::transaction(function() use ($title, $text, $authorIds) {
            $poem = Poem::create(['title' => $title]);
            $this->linkAuthors($poem, $authorIds);
            $this->createFragmentsFromText($poem, $text);
            $this->indexForSearch($poem);
            return $poem;
        });
    }
}
```

## 🎯 Когда использовать что?

### ✅ Rich Models - используйте для:

#### 1. **Простых бизнес-правил**

```php
class Track extends Model 
{
    // ✅ Простая проверка состояния
    public function isTimingComplete(): bool
    {
        $versesCount = $this->fragment->verses()->count();
        $timingsCount = $this->timings()->count();
        
        // Доменное правило: последняя строка не считается
        return $timingsCount >= ($versesCount - 1);
    }
    
    // ✅ Простое действие
    public function activate(): bool
    {
        if (!$this->isTimingComplete()) {
            return false;
        }
        
        $this->update(['status' => 'active']);
        event(new TrackActivated($this));
        return true;
    }
    
    // ✅ Проверка возможности действия
    public function canBeActivated(): bool
    {
        return $this->status === 'draft' && $this->isTimingComplete();
    }
}
```

#### 2. **Вычислений на основе собственных данных**

```php
class Poem extends Model 
{
    // ✅ Расчет на основе своих данных
    public function calculateReadingTime(): int
    {
        $wordCount = $this->fragments
            ->flatMap->verses
            ->sum(fn($line) => str_word_count($line->text));
            
        return max(1, ceil($wordCount / 150)); // 150 слов в минуту
    }
    
    // ✅ Получение представления своих данных
    public function getPreviewVerses(int $limit = 2): array
    {
        return $this->fragments()
            ->orderBy('sort_order')
            ->first()
            ?->verses()
            ->limit($limit)
            ->pluck('text')
            ->toArray() ?? [];
    }
    
    // ✅ Анализ собственного состояния
    public function hasAudio(): bool
    {
        return $this->fragments()->whereHas('tracks', function($q) {
            $q->where('status', 'active');
        })->exists();
    }
}
```

#### 3. **Валидации и проверок**

```php
class Fragment extends Model 
{
    // ✅ Проверки состояния
    public function hasActiveAudio(): bool
    {
        return $this->tracks()->where('status', 'active')->exists();
    }
    
    public function canBeDeleted(): bool
    {
        return !$this->hasActiveAudio() || $this->status === 'draft';
    }
    
    public function isReadyForPublishing(): bool
    {
        return $this->verses()->exists() && 
               $this->status === 'draft' &&
               $this->hasRequiredFields();
    }
    
    // ✅ Доменные вычисления
    public function getSize(): string
    {
        $lineCount = $this->verses()->count();
        
        if ($lineCount <= 10) return 'short';
        if ($lineCount <= 30) return 'medium';
        return 'large';
    }
}
```

#### 4. **Форматирование и представление**

```php
class Track extends Model 
{
    // ✅ Форматирование собственных данных
    public function getFormattedDuration(): string
    {
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }
    
    public function getPlayUrl(): string
    {
        return asset("uploads/audio/{$this->fragment_id}/{$this->filename}");
    }
}
```

### ✅ Domain Services - выносите для:

#### 1. **Сложных бизнес-процессов**

Пример из вашего проекта - **TimingService**:

```php
class TimingDomainService 
{
    // ❌ Слишком сложно для Rich Model
    public function setVerseTiming(Track $track, Verse $verse, float $endTime): void
    {
        // 1. Множественные проверки
        $this->validateLastVerseRule($track, $verse);
        $this->validateTimeBounds($track, $endTime);
        $this->validateSequentialOrder($track, $line, $endTime);
        
        // 2. Работа с несколькими объектами
        $track->timings()->updateOrCreate(
            ['verse_id' => $verse->id],
            ['end_time' => $endTime]
        );
        
        // 3. Побочные эффекты
        $this->recalculateRelatedTimings($track, $line);
        $this->updateTrackCompletionStatus($track);
        
        // 4. События
        event(new VerseTimingUpdated($track, $verse, $endTime));
    }
    
    private function validateLastVerseRule(Track $track, Verse $verse): void
    {
        if ($line->isLast()) {
            throw new DomainException('Last line timing is fixed to track duration');
        }
    }
    
    private function validateTimeBounds(Track $track, float $endTime): void
    {
        if ($endTime <= 0 || $endTime > $track->duration) {
            throw new DomainException('Invalid timing bounds');
        }
    }
    
    private function validateSequentialOrder(Track $track, Verse $verse, float $endTime): void
    {
        $previousTiming = $track->getLastTimingBefore($line);
        if ($previousTiming && $endTime < $previousTiming->end_time) {
            throw new DomainException('Timing cannot go backwards');
        }
    }
}
```

#### 2. **Создания сложных агрегатов**

Пример из вашего **PoemProcessor**:

```php
class PoemCreationService 
{
    public function __construct(
        private MeilisearchIndexer $indexer,
        private PoemValidator $validator
    ) {}
    
    public function createPoemWithFragments(CreatePoemData $data): Poem
    {
        $this->validator->validate($data);
        
        return DB::transaction(function() use ($data) {
            // 1. Создать основную сущность
            $poem = Poem::create([
                'title' => $data->title,
                'year_written' => $data->yearWritten,
                'status' => 'draft',
                'is_divided' => $data->isDivided,
            ]);
            
            // 2. Связать с авторами
            $this->linkAuthors($poem, $data->authorIds);
            
            // 3. Создать вложенные сущности
            foreach ($data->fragments as $fragmentData) {
                $fragment = $this->createFragment($poem, $fragmentData);
                $this->createVerses($fragment, $fragmentData['text']);
            }
            
            // 4. Внешние эффекты
            $this->indexer->indexPoem($poem);
            event(new PoemCreated($poem));
            
            return $poem->load(['authors', 'fragments.verses']);
        });
    }
    
    private function createVerses(Fragment $fragment, string $text): void
    {
        $verses = Poem::parseTextToVerses($text); // Используем метод модели
        
        foreach ($verses as $index => $lineData) {
            $fragment->verses()->create([
                'position' => $index + 1,
                'text' => $lineData['text'],
                'end_line' => $lineData['end_line'],
            ]);
        }
    }
}
```

#### 3. **Координации между агрегатами**

```php
class AudioProcessingService 
{
    public function __construct(
        private AudioProcessor $processor,
        private FileManager $fileManager,
        private AudioValidator $validator
    ) {}
    
    public function processUploadedAudio(Fragment $fragment, UploadedFile $file, string $title): Track
    {
        // Работаем с несколькими доменными объектами и внешними сервисами
        
        // 1. Валидация (внешний сервис)
        $this->validator->validateAudioFile($file);
        
        // 2. Обработка файла (внешний сервис)
        $duration = $this->processor->getDuration($file);
        $filename = $this->fileManager->generateUniqueFilename($title);
        
        // 3. Сохранение (внешний сервис)
        $path = $this->fileManager->storeAudio($fragment, $file, $filename);
        
        // 4. Создание доменного объекта
        $track = $fragment->tracks()->create([
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'duration' => $duration,
            'title' => $title,
            'sort_order' => $fragment->getNextTrackOrder(), // Метод модели
            'status' => 'draft',
        ]);
        
        // 5. Доменные события
        event(new AudioUploaded($track));
        
        return $track;
    }
}
```

## 📊 Правило 80/20 для Laravel

- **80% логики** → Rich Models
- **20% логики** → Domain Services

## 🛠️ Практические примеры из вашего проекта

### ✅ Переносим в Rich Models

#### Из TimingService → Track Model:
```php
class Track extends Model 
{
    // ✅ Простые проверки остаются в модели
    public function isTimingComplete(): bool
    {
        $versesCount = $this->fragment->verses()->count();
        $timingsCount = $this->timings()->count();
        return $timingsCount >= ($versesCount - 1);
    }
    
    public function canBeActivated(): bool
    {
        return $this->status === 'draft' && $this->isTimingComplete();
    }
    
    public function activate(): bool
    {
        if (!$this->canBeActivated()) {
            return false;
        }
        
        $this->update(['status' => 'active']);
        event(new TrackActivated($this));
        return true;
    }
}
```

#### Из PoemProcessor → Poem Model:
```php
class Poem extends Model 
{
    // ✅ Парсинг текста - доменное знание
    public static function parseTextToVerses(string $text): array
    {
        $verses = [];
        $textVerses = explode("\n", $text);
        
        foreach ($textVerses as $line) {
            $line = trim($line);
            if ($line !== '') {
                $verses[] = [
                    'text' => $line,
                    'end_line' => false // Доменная логика
                ];
            }
        }
        
        return $verses;
    }
    
    // ✅ Простые проверки публикации
    public function canBePublished(): bool
    {
        return $this->fragments()->exists() && 
               $this->fragments()->whereHas('verses')->exists();
    }
    
    public function publish(): bool
    {
        if (!$this->canBePublished()) {
            return false;
        }
        
        $this->update(['status' => 'published']);
        event(new PoemPublished($this));
        return true;
    }
}
```

### ✅ Выносим в Domain Services

#### Сложные процессы создания:
```php
class PoemCreationService 
{
    // ❌ Слишком сложно для модели
    public function createFromFormData(array $formData): Poem
    {
        // Транзакция
        // Создание связанных объектов
        // Валидация
        // Индексация
        // События
    }
}
```

#### Сложная логика таймингов:
```php
class TimingDomainService 
{
    // ❌ Слишком сложно для модели
    public function setVerseTiming(Track $track, Verse $verse, float $endTime): void
    {
        // Множественные валидации
        // Работа с несколькими объектами
        // Пересчеты
        // Каскадные обновления
    }
}
```

## 🚀 Итого: Ваша стратегия

### В моделях оставляйте:
```php
// ✅ Простые проверки
$poem->isPublished()
$track->isActive()
$fragment->hasAudio()

// ✅ Вычисления на своих данных
$poem->calculateReadingTime()
$track->getFormattedDuration()

// ✅ Простые действия
$poem->publish()
$track->activate()

// ✅ Всегда в моделях
Scopes, отношения, события модели
```

### В Domain Services выносите:
```php
// ✅ Сложные бизнес-процессы
TimingDomainService::setVerseTiming()
PoemCreationService::createFromText()
AudioProcessingService::processUpload()

// ✅ Координацию агрегатов
// ✅ Транзакционную логику
// ✅ Работу с внешними сервисами
```

## 🎯 Главное правило

> **Если логика помещается в 10-15 строк и работает в основном с данными самого объекта - оставляйте в Rich Model.**
> 
> **Если логика сложная, работает с несколькими объектами или включает внешние зависимости - выносите в Domain Service.**

Этот подход даст вам лучшее из обоих миров: простоту Rich Models + мощь Domain Services для сложных случаев! 🎯