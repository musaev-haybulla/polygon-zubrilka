# План рефакторинга проекта "Зубрилка" для миграции на Laravel

## Обзор текущего состояния

Проект представляет собой процедурное PHP-приложение с элементами ООП, готовое к миграции на Laravel framework. Основные проблемы:
- Смешение бизнес-логики с презентационным слоем
- Прямые SQL запросы в endpoint'ах
- Отсутствие dependency injection
- Дублирование кода валидации и обработки ошибок

## Стратегия миграции

### Этап 1: Подготовка и планирование (1-2 недели)

#### 1.1 Настройка Laravel проекта
- [ ] Создать новый Laravel проект
- [ ] Настроить базу данных и миграции
- [ ] Перенести конфигурацию из текущих config файлов
- [ ] Настроить файловое хранилище для аудио

#### 1.2 Анализ и документирование
- [x] Завершен анализ текущего кода (PROJECT_ANALYSIS.md)
- [x] Документированы API endpoints (API_ENDPOINTS.md)
- [x] Описана схема БД (DATABASE_SCHEMA.md)
- [x] Составлен граф зависимостей (DEPENDENCIES.md)

### Этап 2: Миграция данных и моделей (2-3 недели)

#### 2.1 Создание Laravel миграций
```php
// Использовать существующую схему из db/migrations/
php artisan make:migration create_authors_table
php artisan make:migration create_poems_table
php artisan make:migration create_fragments_table
php artisan make:migration create_lines_table
php artisan make:migration create_poem_authors_table
php artisan make:migration create_tracks_table
php artisan make:migration create_timings_table
```

#### 2.2 Создание Eloquent моделей
```php
// app/Models/
Author.php       - связи с poems через poem_authors
Poem.php         - связи с authors, fragments
Fragment.php     - связи с poem, lines, tracks
Verse.php        - связи с fragment, timings
Track.php        - связи с fragment, timings
Timing.php       - связи с track, line
PoemAuthor.php   - pivot модель
```

#### 2.3 Реализация связей и скоупов
```php
// В моделях добавить:
- hasMany/belongsTo связи
- Мягкое удаление (SoftDeletes)
- Фабрики для тестирования
- Скоупы для фильтрации (published, byGrade, etc.)
```

### Этап 3: Рефакторинг бизнес-логики (3-4 недели)

#### 3.1 Создание UseCase классов (одно действие = один класс)

**Poems UseCases:**
```php
// app/UseCases/Poems/
CreatePoem.php      - создание стихотворения с фрагментами
PublishPoem.php     - публикация с валидацией
DeletePoem.php      - удаление с очисткой аудио
SearchPoems.php     - поиск с фильтрацией
```

**Audio UseCases:**
```php
// app/UseCases/Audio/
UploadAudio.php     - загрузка аудиофайла
TrimAudio.php       - обрезка аудио
DeleteAudio.php     - удаление с файловой системы
RestoreAudio.php    - восстановление оригинала
```

**Timings UseCases:**
```php
// app/UseCases/Timings/
InitializeTiming.php - подготовка данных для разметки
SaveVerseTiming.php  - сохранение тайминга строки
FinalizeTiming.php   - завершение разметки трека
```

#### 3.2 Обогащение моделей Eloquent (вместо Repository)

**Scopes для переиспользуемых запросов:**
```php
// В модели Poem
public function scopePublished($query)
public function scopeByAuthor($query, $author)
public function scopeWithAudio($query)

// В модели Fragment  
public function scopeByGrade($query, $grade)
public function scopeWithTimings($query)
```

**Бизнес-методы в моделях:**
```php
// В модели Poem
public function publish(): void
public function canBePublished(): bool
public function calculateReadingTime(): int

// В модели Track
public function trim(float $start, float $end): void
public function isTimingComplete(): bool
```

#### 3.3 Query классы для сложных запросов
```php
// app/Queries/
CatalogPoemsQuery.php      - каталог с фильтрами и пагинацией
SearchPoemsQuery.php       - поиск через MeiliSearch + БД
AudioStatisticsQuery.php   - статистика по озвучкам
FragmentDetailsQuery.php   - фрагмент со всеми связями
```

#### 3.4 Инфраструктурные сервисы (ТОЛЬКО для внешних интеграций)
```php
// app/Services/
MeilisearchIndexer.php     - индексация поиска
AudioProcessor.php         - FFmpeg обработка
FileManager.php            - работа с файловой системой
NotificationService.php    - уведомления
```

### Этап 4: Создание API контроллеров (2-3 недели)

#### 4.1 API Controllers
```php
// app/Http/Controllers/Api/
PoemController.php       - CRUD операции с стихами
FragmentController.php   - управление фрагментами  
AudioController.php      - загрузка и обработка аудио
SearchController.php     - поиск по контенту
TimingController.php     - API для разметки
AuthorController.php     - автодополнение авторов
ConfigController.php     - конфигурация для фронта
```

#### 4.2 Маршруты
```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::apiResource('poems', PoemController::class);
    Route::apiResource('poems.fragments', FragmentController::class);
    Route::post('fragments/{fragment}/audio', [AudioController::class, 'upload']);
    Route::get('search', [SearchController::class, 'search']);
    Route::prefix('timings')->group(function () {
        Route::get('{track}/init', [TimingController::class, 'init']);
        Route::post('{track}/verse', [TimingController::class, 'saveVerse']);
        Route::post('{track}/finalize', [TimingController::class, 'finalize']);
    });
});
```

#### 4.3 Form Requests для валидации
```php
// app/Http/Requests/
CreatePoemRequest.php
UploadAudioRequest.php
SaveTimingRequest.php
SearchRequest.php
```

### Этап 5: Создание Web интерфейсов (2-3 недели)

#### 5.1 Web Controllers
```php
// app/Http/Controllers/Web/
DashboardController.php  - главная страница
PoemManagementController.php - управление стихами
AudioManagementController.php - управление аудио
```

#### 5.2 Blade шаблоны
```
// resources/views/
layouts/app.blade.php    - базовый шаблон
dashboard.blade.php      - главная страница
poems/index.blade.php    - список стихов (poem_list.php)
poems/create.blade.php   - создание стиха
audio/upload.blade.php   - загрузка аудио
audio/edit.blade.php     - обрезка аудио  
audio/timing.blade.php   - разметка аудио
```

### Этап 6: Интеграция внешних сервисов (1-2 недели)

#### 6.1 MeiliSearch интеграция
```php
// config/meilisearch.php - конфигурация
// app/Services/MeiliSearchService.php - сервис
// app/Console/Commands/IndexContent.php - индексация
```

#### 6.2 File Storage
```php
// config/filesystems.php
'audio' => [
    'driver' => 'local',
    'root' => storage_path('app/audio'),
    'url' => env('APP_URL').'/storage/audio',
    'visibility' => 'public',
]
```

#### 6.3 FFmpeg интеграция
```php
// app/Services/AudioProcessing/
FFmpegProcessor.php - обработка аудио
AudioValidator.php - валидация файлов
```

### Этап 7: Тестирование и оптимизация (2-3 недели)

#### 7.1 Создание тестов
```php
// tests/Feature/
PoemManagementTest.php
AudioUploadTest.php
SearchFunctionalityTest.php
TimingApiTest.php

// tests/Unit/
PoemServiceTest.php
AudioServiceTest.php
SearchServiceTest.php
```

#### 7.2 Оптимизация производительности
- Добавить кеширование для поиска
- Оптимизировать запросы к БД (eager loading)
- Настроить индексы БД
- Добавить очереди для тяжелых операций

### Этап 8: Деплой и миграция данных (1-2 недели)

#### 8.1 Подготовка к деплою
- Настройка окружений (staging, production)
- Конфигурация сервера
- Настройка CI/CD

#### 8.2 Миграция данных
```php
// database/migrations/data/
migrate_existing_data.php - скрипт переноса
```

## Детальный план по компонентам

### Фаза 1: Сервисы (группировка существующих функций)

#### CreatePoem UseCase
**Источники для объединения:**
- `PoemProcessor->processForm()` → `execute()`
- `process_poem.php` логика → рефакторинг в UseCase
- Валидация → в PoemValidator
- Индексация → в MeilisearchIndexer

**Новая реализация:**
```php
class CreatePoem
{
    public function execute(CreatePoemData $data): Poem
    {
        // Валидация через инжектируемый валидатор
        // Создание поэмы и фрагментов
        // Индексация для поиска
        // Отправка события PoemCreated
    }
}
```

#### UploadAudio UseCase
**Источники для объединения:**
- `AudioFileHelper` методы → AudioProcessor + FileManager
- `add_audio_step1.php` логика → `execute()`
- Валидация файлов → AudioValidator
- FFmpeg операции → AudioProcessor

**Новая реализация:**
```php
class UploadAudio
{
    public function execute(Fragment $fragment, UploadAudioData $data): Track
    {
        // Валидация через AudioValidator
        // Обработка через AudioProcessor
        // Сохранение через FileManager
        // Создание Track модели
        // Событие AudioUploaded
    }
}
```

#### SearchPoems UseCase
**Источники для объединения:**
- `SearchService->performFullSearch()` → `execute()`
- `DatabaseHelper` поисковые методы → в Scopes моделей
- `search_api.php` логика → в контроллер
- MeiliSearch интеграция → в MeilisearchIndexer

**Новая реализация:**
```php
class SearchPoems
{
    public function execute(SearchFilters $filters): array
    {
        // Поиск через MeilisearchIndexer
        // Дополнительная фильтрация через Eloquent Scopes
        // Агрегация результатов
        // Возврат структурированных данных
    }
}
```

#### Timings UseCases
**Источники для объединения:**
- `TimingService` методы → разделить на 3 UseCase
- `api/timings/*` логика → в соответствующие UseCase
- Валидация → TimingValidator
- Бизнес-правила → в модель Track

**InitializeTiming, SaveVerseTiming, FinalizeTiming** - каждый со своей ответственностью

### Фаза 2: Слои архитектуры

#### Controllers (Тонкие, только вызов UseCase)
```
Api/
├── PoemController.php      - CRUD операции через UseCases
├── FragmentController.php  - управление фрагментами
├── AudioController.php     - загрузка/обработка аудио
├── SearchController.php    - поиск через SearchPoems UseCase
├── TimingController.php    - работа с таймингами
└── ConfigController.php    - конфигурация для фронта

Web/
├── DashboardController.php - главная страница
├── PoemController.php      - веб-интерфейс управления
├── AudioController.php     - веб-интерфейс аудио
└── TimingController.php    - интерфейс разметки
```

#### UseCases (Бизнес-логика)
```
Poems/
├── CreatePoem.php         - создание стихотворения
├── PublishPoem.php        - публикация
├── DeletePoem.php         - удаление
├── SearchPoems.php        - поиск с фильтрами
└── UpdatePoem.php         - редактирование

Audio/
├── UploadAudio.php        - загрузка файла
├── TrimAudio.php          - обрезка
├── DeleteAudio.php        - удаление
└── RestoreAudio.php       - восстановление

Timings/
├── InitializeTiming.php   - инициализация
├── SaveVerseTiming.php    - сохранение тайминга
└── FinalizeTiming.php     - финализация

Authors/
├── CreateAuthor.php       - создание автора
└── SearchAuthors.php      - поиск для автодополнения
```

#### Services (ТОЛЬКО инфраструктура)
```
External/
├── MeilisearchIndexer.php - индексация поиска
├── AudioProcessor.php     - FFmpeg операции
├── FileManager.php        - файловая система
└── NotificationService.php - уведомления

Validators/
├── PoemValidator.php      - валидация стихов
├── AudioValidator.php     - валидация аудио
└── TimingValidator.php    - валидация таймингов
```

#### Queries (Сложные запросы)
```
├── CatalogPoemsQuery.php     - каталог с фильтрацией
├── SearchPoemsQuery.php      - комбинированный поиск
├── AudioStatisticsQuery.php  - статистика
└── FragmentDetailsQuery.php  - детали фрагмента
```

### Фаза 3: Миграция данных

#### Стратегия переноса:
1. **Дамп текущей БД** с сохранением данных
2. **Создание Laravel миграций** на основе существующей схемы
3. **Скрипт переноса данных** с валидацией
4. **Верификация целостности** после переноса

#### Скрипт миграции:
```php
// database/migrations/data/2025_01_01_000000_migrate_existing_data.php
class MigrateExistingData extends Migration
{
    public function up()
    {
        // Перенос авторов
        $this->migrateAuthors();
        
        // Перенос стихотворений
        $this->migratePoems();
        
        // Перенос фрагментов
        $this->migrateFragments();
        
        // Перенос строк
        $this->migrateVerses();
        
        // Перенос аудио треков
        $this->migrateTracks();
        
        // Перенос таймингов
        $this->migrateTimings();
        
        // Верификация связей
        $this->validateMigration();
    }
}
```

## Критерии готовности каждого этапа

### Этап 1 - Готов когда:
- ✅ Laravel проект настроен и запускается
- ✅ База данных создана и миграции применены
- ✅ Конфигурация перенесена и работает
- ✅ Файловое хранилище настроено

### Этап 2 - Готов когда:
- ✅ Все модели созданы с правильными связями
- ✅ Мягкое удаление работает
- ✅ Фабрики созданы для тестирования
- ✅ Seeders работают корректно

### Этап 3 - Готов когда:
- ✅ Все сервисы созданы и протестированы
- ✅ Репозитории реализованы
- ✅ Use Cases покрывают основную функциональность
- ✅ Dependency Injection настроен

### Этап 4 - Готов когда:
- ✅ Все API endpoints работают
- ✅ Валидация запросов реализована
- ✅ JSON ответы соответствуют спецификации
- ✅ API тесты проходят

### Этап 5 - Готов когда:
- ✅ Web интерфейс функционален
- ✅ Формы работают корректно
- ✅ Asset'ы подключены (CSS, JS)
- ✅ Навигация работает

### Этап 6 - Готов когда:
- ✅ MeiliSearch интеграция работает
- ✅ Файловое хранилище функционирует
- ✅ FFmpeg обработка настроена
- ✅ Внешние API доступны

### Этап 7 - Готов когда:
- ✅ Feature тесты покрывают основную функциональность
- ✅ Unit тесты написаны для сервисов
- ✅ Performance профилирование выполнено
- ✅ Кеширование настроено

### Этап 8 - Готов когда:
- ✅ Данные успешно мигрированы
- ✅ Staging окружение развернуто
- ✅ Production готов к деплою
- ✅ Rollback план готов

## Риски и митигация

### Технические риски:
1. **Потеря данных при миграции**
   - Митигация: Создание бэкапов перед каждым этапом
   
2. **Несовместимость API после рефакторинга**
   - Митигация: Поддержка старого API параллельно с новым
   
3. **Производительность ухудшится**
   - Митигация: Профилирование на каждом этапе

### Временные риски:
1. **Недооценка сложности миграции**
   - Митигация: Добавить 20% буфер к каждому этапу
   
2. **Зависимости между командами**
   - Митигация: Четкие контракты между компонентами

## Постмиграционные улучшения

### После завершения основной миграции:
1. **Добавить аутентификацию и авторизацию**
2. **Реализовать API версионирование** 
3. **Добавить rate limiting**
4. **Настроить мониторинг и алерты**
5. **Добавить админ панель**
6. **Реализовать экспорт/импорт данных**
7. **Добавить уведомления**
8. **Настроить полнотекстовый поиск**