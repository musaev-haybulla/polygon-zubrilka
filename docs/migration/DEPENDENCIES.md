# Граф зависимостей проекта "Зубрилка"

## Обзор архитектуры

Проект имеет четкую иерархию зависимостей с конфигурационными файлами в основе, вспомогательными классами в середине и API endpoints на верхнем уровне.

## Основные уровни зависимостей

### Уровень 1: Конфигурация (Foundation)
**Файлы без внешних зависимостей:**
- `config/database.php`
- `config/app_config.php` 
- `config/search_config.php`
- `config/audio.php`
- `config/poem_size_config.php`

### Уровень 2: Базовая конфигурация
**Зависит только от уровня 1:**
- `config/config.php` ← `database.php`, `search_config.php`, `app_config.php`

### Уровень 3: Инфраструктурные классы
**Независимые helper классы:**
- `classes/ResponseHelper.php` (без зависимостей)
- `classes/autoload.php` (без зависимостей)

### Уровень 4: Core классы
**Зависят от config.php:**
- `classes/AudioFileHelper.php` ← `config.php` (косвенно)
- `classes/DatabaseHelper.php` ← `getPdo()` из `config.php`
- `classes/AudioSorter.php` ← `getPdo()` из `config.php`
- `classes/FragmentQuery.php` ← `getPdo()` из `config.php`
- `classes/PoemProcessor.php` ← `getPdo()` из `config.php`

### Уровень 5: Сервисные классы
**Зависят от core классов:**
- `classes/SearchService.php` ← `DatabaseHelper`
- `classes/Services/TimingService.php` ← PDO (прямо)
- `classes/Services/MeiliSearchService.php` ← Meilisearch\Client

### Уровень 6: API Endpoints и UI
**Используют все нижние уровни:**

## Детальный граф зависимостей

```
config/database.php (0 зависимостей)
config/app_config.php (0 зависимостей)  
config/search_config.php (0 зависимостей)
config/audio.php (0 зависимостей)
config/poem_size_config.php (0 зависимостей)
    ↓
config/config.php (3 зависимости)
    ↓
┌─────────────────────────────────────────────────┐
│                Core Classes                     │
├─────────────────────────────────────────────────┤
│ classes/ResponseHelper.php (0 зависимостей)     │
│ classes/autoload.php (0 зависимостей)          │
│ classes/AudioFileHelper.php (1 зависимость)    │
│ classes/DatabaseHelper.php (1 зависимость)     │
│ classes/AudioSorter.php (1 зависимость)        │
│ classes/FragmentQuery.php (1 зависимость)      │
│ classes/PoemProcessor.php (1 зависимость)      │
└─────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────┐
│               Service Classes                   │
├─────────────────────────────────────────────────┤
│ classes/SearchService.php (1 зависимость)      │
│ classes/Services/TimingService.php (0 завис.)  │
│ classes/Services/MeiliSearchService.php (1 зав.)│
└─────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────┐
│            API Endpoints & UI                   │
├─────────────────────────────────────────────────┤
│ search_api.php (4 зависимости)                 │
│ config_api.php (1 зависимость)                 │
│ authors_autocomplete.php (4 зависимости)       │
│ api/timings/*.php (3 зависимости каждый)       │
│ process_poem.php (1 зависимость)               │
│ add_audio_step1.php (5 зависимостей)           │
│ add_audio_step2.php (3 зависимости)            │
│ delete_audio.php (3 зависимости)               │
│ restore_original_audio.php (3 зависимости)     │
│ get_fragments.php (4 зависимости)              │
│ poem_list.php (5 зависимостей)                 │
│ add_simple_poem.php (3 зависимости)            │
│ add_poem_fragment.php (3 зависимости)          │
│ transfer_to_meilisearch.php (3 зависимости)    │
└─────────────────────────────────────────────────┘
```

## Анализ зависимостей по файлам

### Файлы с наибольшим количеством зависимостей:

1. **add_audio_step1.php (5 зависимостей)**
   - `config/config.php`
   - `vendor/autoload.php`
   - `App\AudioFileHelper`
   - `App\AudioSorter`
   - Прямые SQL операции

2. **poem_list.php (5 зависимостей)**
   - `config/config.php`
   - `vendor/autoload.php`
   - `App\FragmentQuery`
   - `config/poem_size_config.php`
   - Сложные SQL запросы

3. **search_api.php (4 зависимости)**
   - `config/config.php`
   - `vendor/autoload.php`
   - `App\ResponseHelper`
   - `App\SearchService`

### Критические узлы зависимостей:

1. **config/config.php** - используется в 90% файлов
2. **vendor/autoload.php** - используется во всех современных endpoint'ах
3. **App\ResponseHelper** - используется во всех API endpoint'ах
4. **App\DatabaseHelper** - центральный класс для работы с БД

## Цепочки зависимостей

### Самая длинная цепочка:
```
config/database.php → 
config/config.php → 
classes/DatabaseHelper.php → 
classes/SearchService.php → 
search_api.php
(5 уровней)
```

### Типичная цепочка для API:
```
config файлы → 
config.php → 
core classes → 
service classes → 
API endpoint
```

## Проблемные зависимости

### 1. Циклические зависимости
**Не обнаружено** - архитектура имеет четкую иерархию

### 2. Слишком много прямых зависимостей
- `add_audio_step1.php` - напрямую работает с SQL
- `process_poem.php` - содержит бизнес-логику и SQL
- `poem_list.php` - смешивает презентацию с запросами к БД

### 3. Отсутствие инверсии зависимостей
- Классы напрямую создают экземпляры PDO
- Нет dependency injection
- Жестко закодированные пути к внешним инструментам (FFmpeg)

### 4. Глобальные зависимости
- `$_SESSION` используется во многих файлах
- `getPdo()` как глобальная функция
- Конфигурационные константы разбросаны

## Внешние зависимости

### Composer пакеты:
- `meilisearch/meilisearch-php` - для полнотекстового поиска
- Стандартные PSR пакеты (через autoload)

### Системные зависимости:
- **MySQL** - основная БД
- **FFmpeg/FFprobe** - обработка аудио
- **MeiliSearch сервер** - поисковый движок

### Файловая система:
- `uploads/audio/{fragment_id}/` - хранение аудиофайлов
- Логи в `/logs/` директории

## Рекомендации по рефакторингу зависимостей

### 1. Ввести Dependency Injection Container
```php
// Вместо прямого создания
$pdo = getPdo();

// Использовать контейнер
$pdo = $container->get(PDOInterface::class);
```

### 2. Создать сервисные интерфейсы
```php
interface DatabaseServiceInterface
interface AudioProcessorInterface
interface SearchServiceInterface
```

### 3. Убрать прямые SQL из endpoint'ов
- Вынести в репозитории
- Использовать сервисные классы
- Создать Use Case классы

### 4. Централизовать конфигурацию
- Единый конфигурационный класс
- Environment-based настройки
- Валидация конфигурации

### 5. Создать фасады для внешних зависимостей
```php
interface AudioProcessorInterface
class FFmpegAudioProcessor implements AudioProcessorInterface
class CloudAudioProcessor implements AudioProcessorInterface
```

## Метрики зависимостей

- **Средняя глубина зависимостей**: 3-4 уровня
- **Максимальная глубина**: 5 уровней
- **Файлов с 0 зависимостей**: 5 (конфигурационные)
- **Файлов с 1 зависимостью**: 8 (core классы)
- **Файлов с 3+ зависимостей**: 14 (endpoint'ы)
- **Самый связанный файл**: `config/config.php` (используется в 90% файлов)