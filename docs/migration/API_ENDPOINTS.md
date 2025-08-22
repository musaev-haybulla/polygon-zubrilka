# API Endpoints проекта "Зубрилка"

## Поиск и конфигурация

| Endpoint | Метод | Параметры | Описание |
|----------|-------|-----------|----------|
| `/search_api.php` | GET | `query` или `q` (string, мин. 2 символа) | Полнотекстовый поиск по стихотворениям |
| `/config_api.php` | GET | - | Получение конфигурации MeiliSearch для клиента |
| `/authors_autocomplete.php` | GET | `q` (string, мин. 2 символа) | Автодополнение авторов |

## Управление контентом

| Endpoint | Метод | Параметры | Описание |
|----------|-------|-----------|----------|
| `/process_poem.php` | POST | Данные формы добавления стихотворения | Обработка создания стихотворений и фрагментов |
| `/get_fragments.php` | GET | `poem_id` (int) | Получение фрагментов конкретного стихотворения |

## Загрузка и обработка аудио

| Endpoint | Метод | Параметры | Описание |
|----------|-------|-----------|----------|
| `/add_audio_step1.php` | GET/POST | Данные загрузки файла | Загрузка аудиофайлов (шаг 1) |
| `/add_audio_step2.php` | GET/POST | `id` - ID аудиозаписи, данные обрезки | Интерфейс обрезки аудио (шаг 2) |
| `/add_audio_step3.php` | GET | `id` - ID аудиозаписи | SPA интерфейс разметки аудио (шаг 3) |
| `/delete_audio.php` | POST | JSON: `{id: int}` | Удаление аудиозаписи |
| `/restore_original_audio.php` | POST | JSON: `{id: int}` | Восстановление оригинальной версии аудио |

## Временная разметка аудио (Timings API)

| Endpoint | Метод | Параметры | Описание |
|----------|-------|-----------|----------|
| `/api/timings/init.php` | GET | `id` или `track_id` (int) | Инициализация данных для разметки |
| `/api/timings/verse.php` | POST | JSON: `{id: int, verse_id: int, end_time: float}` | Сохранение времени окончания строки |
| `/api/timings/finalize.php` | POST | JSON: `{id: int}` | Финализация разметки трека |

## Интерфейсы

| Endpoint | Метод | Параметры | Описание |
|----------|-------|-----------|----------|
| `/poem_list.php` | GET | Фильтры поиска | Интерфейс управления озвучками |
| `/add_simple_poem.php` | GET | `success` (опционально) | Форма добавления простого стихотворения |
| `/add_poem_fragment.php` | GET | `success` (опционально) | Форма добавления фрагмента поэмы |

## Служебные скрипты

| Endpoint | Метод | Параметры | Описание |
|----------|-------|-----------|----------|
| `/transfer_to_meilisearch.php` | CLI | - | Перенос данных из MySQL в MeiliSearch |

## Структура ответов

### Успешные ответы
```json
{
  "success": true,
  "data": {
    // данные ответа
  }
}
```

### Ошибки
```json
{
  "error": "Сообщение об ошибке",
  "code": 400
}
```

### Специфичные форматы

#### Search API (`/search_api.php`)
```json
{
  "poems": [
    {
      "id": 1,
      "title": "Название стихотворения",
      "authors": ["Автор"],
      "preview": ["Первая строка", "Вторая строка"]
    }
  ],
  "fragments": [
    {
      "id": 1,
      "label": "Название фрагмента",
      "poem_title": "Название поэмы",
      "preview": ["Строки фрагмента"]
    }
  ],
  "lines": [
    {
      "fragment_id": 1,
      "position": 1,
      "content": "Текст строки",
      "poem_title": "Название поэмы"
    }
  ]
}
```

#### Timings Init API (`/api/timings/init.php`)
```json
{
  "ok": true,
  "data": {
    "track": {
      "id": 1,
      "filename": "audio.mp3",
      "duration": 120.5
    },
    "lines": [
      {
        "id": 1,
        "position": 1,
        "content": "Текст строки"
      }
    ],
    "timings": [
      {
        "verse_id": 1,
        "end_time": 5.2
      }
    ]
  }
}
```

#### Config API (`/config_api.php`)
```json
{
  "meilisearch": {
    "host": "http://localhost:7700",
    "key": "master_key",
    "index": "poems"
  },
  "search": {
    "results_per_page": 50,
    "debounce_ms": 300
  }
}
```

## Особенности API

### CORS Headers
Все API endpoints устанавливают CORS заголовки для кроссдоменных запросов:
- `Access-Control-Allow-Origin: *`
- `Access-Control-Allow-Methods: GET, POST, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type`

### Content-Type
JSON API endpoints возвращают `Content-Type: application/json; charset=utf-8`

### Обработка OPTIONS
API endpoints обрабатывают preflight OPTIONS запросы с кодом 200

### Валидация
- Поисковые запросы требуют минимум 2 символа
- ID параметры валидируются как положительные целые числа
- JSON данные валидируются на корректность формата

### Безопасность
- Использование prepared statements для SQL запросов
- Валидация входных данных
- Обработка исключений с возвратом безопасных сообщений об ошибках