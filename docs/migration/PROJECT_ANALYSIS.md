# Анализ проекта "Зубрилка"

## Статистика
- **Всего файлов**: 40
- **Всего функций**: 68
- **Точек входа (API)**: 16 
- **Классов**: 9
- **Конфигурационных файлов**: 6
- **Таблиц БД**: 7 (authors, poems, fragments, verses, poem_authors, tracks, timings)

## Архитектура проекта

Проект "Зубрилка" представляет собой веб-приложение для управления детскими стихотворениями с функциональностью добавления и обработки аудиозаписей.

### Структура классов:
- **Helper классы**: AudioFileHelper, DatabaseHelper, ResponseHelper
- **Service классы**: SearchService, TimingService, MeiliSearchService  
- **Query Builder**: FragmentQuery для построения сложных запросов
- **Processors**: PoemProcessor, AudioSorter для бизнес-логики

## Файлы

### /config/app_config.php
**Назначение**: Конфигурация основных настроек приложения
**Тип**: config
**HTTP метод**: Не применимо
**Зависимости**: Нет

#### Функции:
Файл содержит только константы, функций нет.

#### Глобальные переменные:
- Константы: APP_NAME, APP_VERSION, APP_ENV, SESSION_LIFETIME, SESSION_NAME, DEFAULT_USER_ID, UPLOADS_DIR, LOGS_DIR

---

### /config/database.php
**Назначение**: Конфигурация подключения к базе данных
**Тип**: config
**HTTP метод**: Не применимо
**Зависимости**: Нет

#### Функции:
Файл содержит только константы для подключения к БД.

#### Глобальные переменные:
- Константы: DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET, PDO_OPTIONS

---

### /config/config.php
**Назначение**: Основной конфигурационный файл с инициализацией сессии и PDO
**Тип**: config
**HTTP метод**: Не применимо
**Зависимости**: database.php, search_config.php, app_config.php

#### Функции:
1. **getPdo(): PDO**
   - Входные: Нет
   - Возвращает: PDO соединение с БД
   - Побочные эффекты: Создание подключения к БД
   - SQL: Подключение к MySQL

#### Глобальные переменные:
- $_SESSION - инициализация и настройка сессии
- session_start() - запуск сессии

---

### /config/poem_size_config.php
**Назначение**: Конфигурация для определения размеров стихотворений
**Тип**: config
**HTTP метод**: Не применимо
**Зависимости**: Нет

#### Функции:
1. **getPoemSize(int $lineCount): string**
   - Входные: Количество строк (int)
   - Возвращает: Размер стихотворения ('short', 'medium', 'large')
   - Побочные эффекты: Нет
   - SQL: Нет

2. **getPoemSizeLabel(string $size): string**
   - Входные: Размер стихотворения (string)
   - Возвращает: Метка для отображения (string)
   - Побочные эффекты: Нет
   - SQL: Нет

#### Глобальные переменные:
- Константа POEM_SIZE_CONFIG

---

### /config/audio.php
**Назначение**: Конфигурация для загрузки и обработки аудиофайлов
**Тип**: config
**HTTP метод**: Не применимо
**Зависимости**: Нет

#### Функции:
Файл возвращает массив конфигурации, функций нет.

#### Глобальные переменные:
Нет глобальных переменных.

---

### /config/search_config.php
**Назначение**: Конфигурация для интеграции с MeiliSearch
**Тип**: config
**HTTP метод**: Не применимо
**Зависимости**: Нет

#### Функции:
Файл содержит только константы конфигурации.

#### Глобальные переменные:
- Константы: MEILISEARCH_HOST_INTERNAL, MEILISEARCH_HOST_EXTERNAL, MEILISEARCH_KEY, MEILISEARCH_HOST, MEILISEARCH_INDEX, SEARCH_RESULTS_PER_PAGE, SEARCH_DEBOUNCE_MS

---

### /classes/autoload.php
**Назначение**: Автозагрузчик классов проекта
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: Нет

#### Функции:
1. **spl_autoload_register(function (string $className): void)**
   - Входные: Имя класса (string)
   - Возвращает: void
   - Побочные эффекты: Загрузка файлов классов
   - SQL: Нет

2. **loadAllClasses(): void**
   - Входные: Нет
   - Возвращает: void
   - Побочные эффекты: Подключение файлов классов
   - SQL: Нет

#### Глобальные переменные:
Нет глобальных переменных.

---

### /search_api.php
**Назначение**: Поисковый API для стихотворений
**Тип**: endpoint
**HTTP метод**: GET
**Зависимости**: config/config.php, vendor/autoload.php, App\ResponseHelper, App\SearchService

#### Функции:
Файл содержит только основную логику обработки API, пользовательских функций нет.

#### Глобальные переменные:
- $_GET['query'], $_GET['q'] - поисковые запросы
- $_SERVER['REQUEST_METHOD'] - метод запроса
- SQL операции: Через SearchService

---

### /config_api.php
**Назначение**: API для получения конфигурации клиентской стороны
**Тип**: endpoint  
**HTTP метод**: GET
**Зависимости**: config/config.php

#### Функции:
Файл содержит только основную логику API, пользовательских функций нет.

#### Глобальные переменные:
- Константы: MEILISEARCH_HOST_EXTERNAL, MEILISEARCH_KEY, MEILISEARCH_INDEX, SEARCH_RESULTS_PER_PAGE, SEARCH_DEBOUNCE_MS

---

### /authors_autocomplete.php
**Назначение**: Автодополнение для авторов
**Тип**: endpoint
**HTTP метод**: GET
**Зависимости**: config/config.php, vendor/autoload.php, App\DatabaseHelper, App\ResponseHelper

#### Функции:
Файл содержит только основную логику API, пользовательских функций нет.

#### Глобальные переменные:
- $_GET['q'] - поисковый запрос
- SQL операции: Через DatabaseHelper::searchAuthors()

---

### /api/timings/init.php
**Назначение**: Инициализация данных для временной разметки аудио
**Тип**: endpoint
**HTTP метод**: GET
**Зависимости**: ../../config/config.php, ../../vendor/autoload.php, App\Services\TimingService

#### Функции:
Файл содержит только основную логику API, пользовательских функций нет.

#### Глобальные переменные:
- $_GET['id'], $_GET['track_id'] - ID трека
- $_SERVER['REQUEST_METHOD'] - метод запроса
- SQL операции: Через TimingService::getInitData()

---

### /api/timings/line.php
**Назначение**: Сохранение времени окончания строки при разметке
**Тип**: endpoint
**HTTP метод**: POST
**Зависимости**: ../../config/config.php, ../../vendor/autoload.php, App\Services\TimingService

#### Функции:
Файл содержит только основную логику API, пользовательских функций нет.

#### Глобальные переменные:
- $_SERVER['REQUEST_METHOD'] - метод запроса
- php://input - POST body с JSON данными
- SQL операции: Через TimingService::upsertVerseEnd()

---

### /api/timings/finalize.php
**Назначение**: Финализация разметки аудио трека
**Тип**: endpoint
**HTTP метод**: POST
**Зависимости**: ../../config/config.php, ../../vendor/autoload.php, App\Services\TimingService

#### Функции:
Файл содержит только основную логику API, пользовательских функций нет.

#### Глобальные переменные:
- $_SERVER['REQUEST_METHOD'] - метод запроса
- php://input - POST body с JSON данными  
- SQL операции: Через TimingService::finalizeTrack()

---

### /process_poem.php
**Назначение**: Обработка форм добавления стихотворений и фрагментов
**Тип**: endpoint
**HTTP метод**: POST
**Зависимости**: config/config.php

#### Функции:
Файл содержит только основную логику обработки формы, пользовательских функций нет.

#### Глобальные переменные:
- $_POST - данные формы
- $_SESSION - ID пользователя
- SQL операции: INSERT INTO poems, INSERT INTO fragments, INSERT INTO verses, INSERT INTO poem_authors, DELETE FROM verses

---

### /add_audio_step1.php
**Назначение**: Обработчик загрузки аудиофайлов (первый шаг)
**Тип**: endpoint
**HTTP метод**: GET, POST
**Зависимости**: config/config.php, vendor/autoload.php, App\AudioFileHelper, App\AudioSorter

#### Функции:
1. **returnError($errorCode, $message = null)**
   - Входные: Код ошибки (mixed), сообщение (string|null)
   - Возвращает: void (завершает выполнение)
   - Побочные эффекты: Отправка JSON ответа или редирект
   - SQL: Нет

#### Глобальные переменные:
- $_POST - данные формы загрузки
- $_FILES - загруженные файлы
- $_SERVER - определение типа запроса
- SQL операции: SELECT, INSERT INTO tracks, UPDATE tracks, DELETE FROM timings

---

### /add_audio_step2.php
**Назначение**: Интерфейс для обрезки аудиофайлов (второй шаг)
**Тип**: endpoint
**HTTP метод**: GET, POST
**Зависимости**: config/config.php, vendor/autoload.php, App\AudioFileHelper

#### Функции:
Файл содержит HTML интерфейс и обработку POST запросов, пользовательских функций нет.

#### Глобальные переменные:
- $_GET - ID аудиозаписи
- $_POST - данные для обрезки
- $_SERVER - метод запроса
- SQL операции: SELECT tracks, UPDATE tracks

---

### /add_audio_step3.php
**Назначение**: Интерфейс для разметки аудиофайлов (третий шаг)
**Тип**: endpoint
**HTTP метод**: GET
**Зависимости**: Нет прямых зависимостей (SPA интерфейс)

#### Функции:
Файл содержит только HTML и JavaScript, PHP функций нет.

#### Глобальные переменные:
Нет PHP переменных (клиентский код).

---

### /add_poem_fragment.php
**Назначение**: Интерфейс для добавления фрагментов поэм
**Тип**: endpoint
**HTTP метод**: GET
**Зависимости**: config/config.php, vendor/autoload.php, App\DatabaseHelper

#### Функции:
Файл содержит HTML интерфейс, пользовательских PHP функций нет.

#### Глобальные переменные:
- $_GET - параметр success
- SQL операции: Через DatabaseHelper::getAllAuthors(), DatabaseHelper::getDividedPoems()

---

### /add_simple_poem.php
**Назначение**: Интерфейс для добавления простых стихотворений
**Тип**: endpoint
**HTTP метод**: GET
**Зависимости**: config/config.php, vendor/autoload.php, App\DatabaseHelper

#### Функции:
Файл содержит HTML интерфейс, пользовательских PHP функций нет.

#### Глобальные переменные:
- $_GET - параметр success
- SQL операции: Через DatabaseHelper::getAllAuthors()

---

### /classes/AudioFileHelper.php
**Назначение**: Помощник для работы с аудиофайлами
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: Нет

#### Функции:
1. **generateSlug(string $title): string**
   - Входные: Название озвучки (string)
   - Возвращает: URL-совместимый slug (string)
   - Побочные эффекты: Нет
   - SQL: Нет

2. **generateFilename(string $title): string**
   - Входные: Название озвучки (string)
   - Возвращает: Имя файла с timestamp (string)
   - Побочные эффекты: Нет
   - SQL: Нет

3. **getAudioPath(int $fragmentId, string $filename): string**
   - Входные: ID фрагмента (int), имя файла (string)
   - Возвращает: Относительный путь к файлу (string)
   - Побочные эффекты: Нет
   - SQL: Нет

4. **getAbsoluteAudioPath(int $fragmentId, string $filename, string $baseDir = __DIR__): string**
   - Входные: ID фрагмента (int), имя файла (string), базовая директория (string)
   - Возвращает: Абсолютный путь к файлу (string)
   - Побочные эффекты: Нет
   - SQL: Нет

5. **ensureFragmentDirectory(int $fragmentId, string $baseDir = __DIR__): string**
   - Входные: ID фрагмента (int), базовая директория (string)
   - Возвращает: Путь к созданной директории (string)
   - Побочные эффекты: Создание директории
   - SQL: Нет

6. **getFFmpegPath(): string**
   - Входные: Нет
   - Возвращает: Путь к FFmpeg (string)
   - Побочные эффекты: Нет
   - SQL: Нет

7. **getFFprobePath(): string**
   - Входные: Нет
   - Возвращает: Путь к FFprobe (string)
   - Побочные эффекты: Нет
   - SQL: Нет

8. **deleteAudioFile(int $fragmentId, string $filename, bool $logOperation = true): bool**
   - Входные: ID фрагмента (int), имя файла (string), логирование (bool)
   - Возвращает: Результат операции (bool)
   - Побочные эффекты: Удаление файла, логирование
   - SQL: Нет

9. **deleteAudioFiles(PDO $pdo, int $audioId): bool**
   - Входные: PDO соединение, ID аудиозаписи (int)
   - Возвращает: Результат операции (bool)
   - Побочные эффекты: Удаление файлов и записей из БД
   - SQL: SELECT, DELETE FROM timings

#### Глобальные переменные:
Нет глобальных переменных.

---

### /classes/AudioSorter.php
**Назначение**: Управление порядком сортировки аудиодорожек
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: Функция getPdo()

#### Функции:
1. **__construct()**
   - Входные: Нет
   - Возвращает: void
   - Побочные эффекты: Инициализация PDO соединения
   - SQL: Через getPdo()

2. **moveAudio(int $audioId, int $fragmentId, int $newPosition): bool**
   - Входные: ID аудио (int), ID фрагмента (int), новая позиция (int)
   - Возвращает: Успешность операции (bool)
   - Побочные эффекты: Изменение порядка в БД
   - SQL: SELECT, UPDATE tracks

3. **getInsertPosition(int $fragmentId, int $position): int**
   - Входные: ID фрагмента (int), желаемая позиция (int)
   - Возвращает: Нормализованная позиция (int)
   - Побочные эффекты: Сдвиг существующих записей
   - SQL: SELECT COUNT, UPDATE tracks

4. **normalizeOrder(int $fragmentId): bool**
   - Входные: ID фрагмента (int)
   - Возвращает: Успешность операции (bool)
   - Побочные эффекты: Переиндексация порядка
   - SQL: SELECT, UPDATE tracks

#### Глобальные переменные:
Нет глобальных переменных.

---

### /classes/DatabaseHelper.php
**Назначение**: Вспомогательный класс для работы с базой данных
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: Функция getPdo()

#### Функции:
1. **getFragmentsByPoemId(int $poemId): array**
   - Входные: ID поэмы (int)
   - Возвращает: Массив фрагментов (array)
   - Побочные эффекты: Нет
   - SQL: SELECT с JOIN fragments и verses

2. **searchAuthors(string $query): array**
   - Входные: Поисковый запрос (string)
   - Возвращает: Массив авторов (array)
   - Побочные эффекты: Нет
   - SQL: SELECT с MATCH AGAINST и LIKE

3. **getAllAuthors(): array**
   - Входные: Нет
   - Возвращает: Массив всех авторов (array)
   - Побочные эффекты: Нет
   - SQL: SELECT с CONCAT

4. **getDividedPoems(): array**
   - Входные: Нет
   - Возвращает: Массив разделенных поэм (array)
   - Побочные эффекты: Нет
   - SQL: SELECT WHERE is_divided = 1

5. **getAllFragmentsWithDetails(): array**
   - Входные: Нет
   - Возвращает: Массив фрагментов с деталями (array)
   - Побочные эффекты: Нет
   - SQL: Сложный SELECT с множественными JOIN

6. **searchPoemsByTitle(string $query): array**
   - Входные: Поисковый запрос (string)
   - Возвращает: Массив поэм (array)
   - Побочные эффекты: Нет
   - SQL: SELECT с LIKE и GROUP BY

7. **searchByFirstVerse(string $query): array**
   - Входные: Поисковый запрос (string)
   - Возвращает: Массив результатов (array)
   - Побочные эффекты: Нет
   - SQL: SELECT с JOIN и WHERE position = 1

8. **searchFragmentsByLabel(string $query): array**
   - Входные: Поисковый запрос (string)
   - Возвращает: Массив фрагментов (array)
   - Побочные эффекты: Нет
   - SQL: SELECT с JOIN и LIKE

9. **searchByVerseContent(string $query): array**
   - Входные: Поисковый запрос (string)
   - Возвращает: Массив результатов (array)
   - Побочные эффекты: Нет
   - SQL: SELECT с JOIN и LIKE

10. **getFirstVerses(int $fragmentId, int $limit = 2): array**
    - Входные: ID фрагмента (int), лимит строк (int)
    - Возвращает: Массив первых строк (array)
    - Побочные эффекты: Нет
    - SQL: SELECT с ORDER BY и LIMIT

#### Глобальные переменные:
Нет глобальных переменных.

---

### /classes/FragmentQuery.php
**Назначение**: Конструктор запросов для фрагментов стихотворений
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: Функция getPdo()

#### Функции:
1-16. **[Query Builder методы]**
   - Входные: Различные в зависимости от метода
   - Возвращает: self для цепочки вызовов или результаты запроса
   - Побочные эффекты: Построение и выполнение SQL запросов
   - SQL: Динамические SELECT запросы с JOIN, WHERE, ORDER BY, LIMIT

#### Глобальные переменные:
Нет глобальных переменных.

---

### /classes/PoemProcessor.php
**Назначение**: Обработчик создания и сохранения стихотворений
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: Функция getPdo()

#### Функции:
1. **processForm(array $postData): array**
   - Входные: Данные формы (array)
   - Возвращает: Результат операции (array)
   - Побочные эффекты: Создание записей в БД
   - SQL: Транзакция с INSERT операциями

2-8. **[Вспомогательные методы]**
   - Входные: Различные в зависимости от метода
   - Возвращает: ID созданных записей или void
   - Побочные эффекты: INSERT операции в БД
   - SQL: INSERT INTO poems, fragments, verses, authors, poem_authors

#### Глобальные переменные:
- $_SESSION - ID пользователя

---

### /classes/ResponseHelper.php
**Назначение**: Вспомогательный класс для работы с JSON ответами
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: Нет

#### Функции:
1-8. **[Response методы]**
   - Входные: Данные и HTTP коды
   - Возвращает: void (завершает выполнение)
   - Побочные эффекты: Отправка HTTP ответов
   - SQL: Нет

#### Глобальные переменные:
Нет глобальных переменных.

---

### /classes/SearchService.php
**Назначение**: Сервис для обработки поисковых запросов
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: App\DatabaseHelper

#### Функции:
1. **performFullSearch(string $query): array**
   - Входные: Поисковый запрос (string)
   - Возвращает: Результаты поиска (array)
   - Побочные эффекты: Выполнение множественных поисковых запросов
   - SQL: Через методы DatabaseHelper

2-6. **[Поисковые методы]**
   - Входные: Поисковые запросы
   - Возвращает: void (модифицирует внутренний массив) или массивы результатов
   - Побочные эффекты: SQL запросы через DatabaseHelper
   - SQL: Различные SELECT запросы для поиска

#### Глобальные переменные:
Нет глобальных переменных.

---

### /classes/Services/TimingService.php
**Назначение**: Сервис для управления таймингами аудиоразметки
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: PDO

#### Функции:
1. **getInitData(PDO $pdo, int $trackId): array**
   - Входные: PDO соединение, ID трека (int)
   - Возвращает: Данные для инициализации разметки (array)
   - Побочные эффекты: Запросы к БД
   - SQL: SELECT tracks, SELECT verses, SELECT timings

2. **upsertVerseEnd(PDO $pdo, int $trackId, int $lineId, float $endTime): void**
   - Входные: PDO соединение, ID трека (int), ID строки (int), время окончания (float)
   - Возвращает: void
   - Побочные эффекты: Обновление или вставка тайминга
   - SQL: INSERT ON DUPLICATE KEY UPDATE timings

3. **finalizeTrack(PDO $pdo, int $trackId): void**
   - Входные: PDO соединение, ID трека (int)
   - Возвращает: void
   - Побочные эффекты: Финализация трека, проверка корректности таймингов
   - SQL: SELECT verses, SELECT timings, UPDATE tracks

#### Глобальные переменные:
Нет глобальных переменных.

---

### /classes/Services/MeiliSearchService.php
**Назначение**: Сервис для работы с MeiliSearch
**Тип**: library
**HTTP метод**: Не применимо
**Зависимости**: Meilisearch\Client

#### Функции:
1-9. **[MeiliSearch методы]**
   - Входные: Различные в зависимости от операции
   - Возвращает: Результаты операций или статусы
   - Побочные эффекты: Операции с MeiliSearch индексом
   - SQL: Нет

#### Глобальные переменные:
Нет глобальных переменных.

---

### /delete_audio.php
**Назначение**: Удаление аудиозаписей
**Тип**: endpoint
**HTTP метод**: POST (JSON)
**Зависимости**: config/config.php, vendor/autoload.php, App\AudioFileHelper

#### Функции:
Файл содержит только основную логику обработки, пользовательских функций нет.

#### Глобальные переменные:
- php://input - JSON данные запроса
- SQL операции: SELECT tracks, DELETE FROM timings, DELETE FROM tracks

---

### /get_fragments.php
**Назначение**: API для получения фрагментов стихотворения
**Тип**: endpoint
**HTTP метод**: GET
**Зависимости**: config/config.php, vendor/autoload.php, App\ResponseHelper, App\DatabaseHelper

#### Функции:
Файл содержит только основную логику обработки, пользовательских функций нет.

#### Глобальные переменные:
- $_GET - параметр poem_id
- SQL операции: Через DatabaseHelper::getFragmentsByPoemId()

---

### /poem_list.php
**Назначение**: Интерфейс управления озвучками стихотворений
**Тип**: endpoint
**HTTP метод**: GET
**Зависимости**: config/config.php, vendor/autoload.php, App\FragmentQuery, config/poem_size_config.php

#### Функции:
1. **getGradeClass($grade): string**
   - Входные: Грейд (mixed)
   - Возвращает: CSS класс (string)
   - Побочные эффекты: Нет
   - SQL: Нет

2. **getGradeName($grade): string**
   - Входные: Грейд (mixed)
   - Возвращает: Название грейда (string)
   - Побочные эффекты: Нет
   - SQL: Нет

3. **getGradeFilterValue($grade): string**
   - Входные: Грейд (mixed)
   - Возвращает: Значение для фильтра (string)
   - Побочные эффекты: Нет
   - SQL: Нет

4. **getPoemSizeClass($size): string**
   - Входные: Размер (mixed)
   - Возвращает: CSS класс (string)
   - Побочные эффекты: Нет
   - SQL: Нет

#### Глобальные переменные:
- SQL операции: Через FragmentQuery с множественными JOIN

---

### /restore_original_audio.php
**Назначение**: Восстановление оригинального аудиофайла
**Тип**: endpoint
**HTTP метод**: POST (JSON)
**Зависимости**: config/config.php, vendor/autoload.php, App\AudioFileHelper

#### Функции:
Файл содержит только основную логику обработки, пользовательских функций нет.

#### Глобальные переменные:
- php://input - JSON данные запроса
- SQL операции: SELECT tracks, UPDATE tracks

---

### /transfer_to_meilisearch.php
**Назначение**: Скрипт переноса данных из MySQL в MeiliSearch
**Тип**: endpoint (CLI скрипт)
**HTTP метод**: Не применимо (консольный скрипт)
**Зависимости**: config/config.php, vendor/autoload.php, App\Services\MeiliSearchService

#### Функции:
1. **normalizeFirstVerse($text): string**
   - Входные: Текст (mixed)
   - Возвращает: Нормализованный текст (string)
   - Побочные эффекты: Нет
   - SQL: Нет

2. **generateNameVariants($firstName, $middleName, $lastName): array**
   - Входные: Имя (mixed), отчество (mixed), фамилия (mixed)
   - Возвращает: Массив вариантов имени (array)
   - Побочные эффекты: Нет
   - SQL: Нет

#### Глобальные переменные:
- SQL операции: SELECT authors, SELECT poems с JOIN, SELECT verses

---

## Технологический стек

**Backend:**
- PHP 8+ с strict types
- MySQL для основного хранения данных
- MeiliSearch для полнотекстового поиска
- FFmpeg для обработки аудио

**Frontend:**
- Bootstrap для UI
- Alpine.js для интерактивности
- WaveSurfer.js для работы с аудио
- Vanilla JavaScript

**Инфраструктура:**
- Docker (compose файлы в проекте)
- Phinx для миграций БД
- Composer для управления зависимостями