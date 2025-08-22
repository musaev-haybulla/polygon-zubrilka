# Структура базы данных проекта "Зубрилка"

## Обзор

База данных состоит из 7 основных таблиц, организованных для хранения стихотворений, авторов, аудиозаписей и временных меток.

## Схема таблиц

### Таблица: `authors`
**Назначение**: Хранение информации об авторах стихотворений

| Поле | Тип | Ограничения | Описание |
|------|-----|-------------|----------|
| `id` | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Уникальный ID автора |
| `first_name` | VARCHAR(100) | NULL | Имя автора |
| `middle_name` | VARCHAR(100) | NULL | Отчество автора |
| `last_name` | VARCHAR(100) | NULL | Фамилия автора |
| `birth_year` | INT | NULL | Год рождения |
| `death_year` | INT | NULL | Год смерти |
| `biography` | TEXT | NULL | Биография автора |
| `created_at` | DATETIME | NOT NULL | Дата создания записи |
| `updated_at` | DATETIME | NOT NULL | Дата последнего обновления |
| `deleted_at` | DATETIME | NULL | Дата удаления (soft delete) |

---

### Таблица: `poems`
**Назначение**: Хранение информации о стихотворениях/поэмах

| Поле | Тип | Ограничения | Описание |
|------|-----|-------------|----------|
| `id` | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Уникальный ID стихотворения |
| `owner_id` | BIGINT | NULL | ID владельца записи |
| `title` | VARCHAR(255) | NOT NULL | Название стихотворения |
| `year_written` | INT | NULL | Год написания |
| `status` | ENUM | NOT NULL | Статус: 'draft', 'published', 'unpublished' |
| `is_divided` | BOOLEAN | NOT NULL | Разделено ли на фрагменты |
| `created_at` | DATETIME | NOT NULL | Дата создания записи |
| `updated_at` | DATETIME | NOT NULL | Дата последнего обновления |
| `deleted_at` | DATETIME | NULL | Дата удаления (soft delete) |

---

### Таблица: `fragments`
**Назначение**: Хранение фрагментов стихотворений (для больших произведений)

| Поле | Тип | Ограничения | Описание |
|------|-----|-------------|----------|
| `id` | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Уникальный ID фрагмента |
| `poem_id` | BIGINT | NOT NULL, FK | Ссылка на стихотворение |
| `owner_id` | BIGINT | NOT NULL | ID владельца записи |
| `label` | VARCHAR(255) | NULL | Название фрагмента |
| `structure_info` | VARCHAR(255) | NULL | Информация о структуре |
| `sort_order` | INT | NOT NULL | Порядок сортировки |
| `grade_level` | ENUM | NOT NULL | Уровень: 'primary', 'middle', 'secondary' |
| `status` | ENUM | NOT NULL | Статус: 'draft', 'published', 'unpublished' |
| `created_at` | DATETIME | NOT NULL | Дата создания записи |
| `updated_at` | DATETIME | NOT NULL | Дата последнего обновления |
| `deleted_at` | DATETIME | NULL | Дата удаления (soft delete) |

---

### Таблица: `verses`
**Назначение**: Хранение отдельных строк стихотворений

| Поле | Тип | Ограничения | Описание |
|------|-----|-------------|----------|
| `id` | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Уникальный ID строки |
| `fragment_id` | BIGINT | NOT NULL, FK | Ссылка на фрагмент |
| `position` | INT | NOT NULL | Номер строки в фрагменте |
| `text` | VARCHAR(255) | NOT NULL | Текст строки |
| `end_line` | BOOLEAN | NOT NULL | Является ли строка концом строфы |
| `created_at` | DATETIME | NOT NULL | Дата создания записи |
| `updated_at` | DATETIME | NOT NULL | Дата последнего обновления |
| `deleted_at` | DATETIME | NULL | Дата удаления (soft delete) |

---

### Таблица: `poem_authors`
**Назначение**: Связующая таблица между стихотворениями и авторами (many-to-many)

| Поле | Тип | Ограничения | Описание |
|------|-----|-------------|----------|
| `poem_id` | BIGINT | NOT NULL, FK, PRIMARY KEY | Ссылка на стихотворение |
| `author_id` | BIGINT | NOT NULL, FK, PRIMARY KEY | Ссылка на автора |

**Составной первичный ключ**: (`poem_id`, `author_id`)

---

### Таблица: `tracks`
**Назначение**: Хранение аудиозаписей для фрагментов

| Поле | Тип | Ограничения | Описание |
|------|-----|-------------|----------|
| `id` | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Уникальный ID трека |
| `fragment_id` | BIGINT | NOT NULL, FK | Ссылка на фрагмент |
| `filename` | VARCHAR(255) | NOT NULL | Имя файла |
| `original_filename` | VARCHAR(255) | NULL | Оригинальное имя файла |
| `duration` | DECIMAL(8,3) | NOT NULL | Длительность в секундах |
| `is_ai_generated` | BOOLEAN | NOT NULL | Сгенерировано ли ИИ |
| `title` | VARCHAR(255) | NOT NULL | Название озвучки |
| `sort_order` | INT | NOT NULL | Порядок сортировки |
| `status` | ENUM | NOT NULL | Статус: 'draft', 'active' |
| `created_at` | TIMESTAMP | NULL | Дата создания записи |
| `updated_at` | TIMESTAMP | NULL | Дата последнего обновления |

---

### Таблица: `timings`
**Назначение**: Хранение временных меток для синхронизации аудио со строками

| Поле | Тип | Ограничения | Описание |
|------|-----|-------------|----------|
| `id` | BIGINT | PRIMARY KEY, AUTO_INCREMENT | Уникальный ID тайминга |
| `track_id` | BIGINT | NOT NULL, FK | Ссылка на аудиотрек |
| `verse_id` | BIGINT | NOT NULL, FK | Ссылка на строку |
| `end_time` | DECIMAL(8,3) | NOT NULL | Время окончания строки в секундах |
| `created_at` | TIMESTAMP | NULL | Дата создания записи |
| `updated_at` | TIMESTAMP | NULL | Дата последнего обновления |

## Связи между таблицами

```
authors ←→ poem_authors ←→ poems
                             ↓
                         fragments
                             ↓
                          verses ←→ timings
                             ↓      ↑
                          tracks ←--┘
```

### Детальные связи:

1. **Authors ↔ Poems** (Many-to-Many через poem_authors)
   - Один автор может написать множество стихотворений
   - Одно стихотворение может иметь нескольких авторов

2. **Poems → Fragments** (One-to-Many)
   - Одно стихотворение может состоять из множества фрагментов
   - Каждый фрагмент принадлежит одному стихотворению

3. **Fragments → Verses** (One-to-Many)
   - Один фрагмент содержит множество строк
   - Каждая строка принадлежит одному фрагменту

4. **Fragments → Tracks** (One-to-Many)
   - Для одного фрагмента может быть несколько аудиозаписей
   - Каждая аудиозапись принадлежит одному фрагменту

5. **Tracks ↔ Verses через Timings** (Many-to-Many)
   - Один трек может иметь тайминги для множества строк
   - Одна строка может иметь тайминги в разных треках

## Индексы и ограничения

### Рекомендуемые индексы:
- `poems(title)` - для поиска по названию
- `fragments(poem_id, sort_order)` - для получения фрагментов поэмы в порядке
- `verses(fragment_id, position)` - для получения строк фрагмента в порядке
- `tracks(fragment_id, sort_order)` - для получения треков фрагмента в порядке
- `timings(track_id, verse_id)` - для синхронизации аудио со строками
- `authors(last_name, first_name)` - для поиска авторов

### Внешние ключи:
- `fragments.poem_id` → `poems.id`
- `verses.fragment_id` → `fragments.id`
- `tracks.fragment_id` → `fragments.id`
- `timings.track_id` → `tracks.id`
- `timings.verse_id` → `verses.id`
- `poem_authors.poem_id` → `poems.id`
- `poem_authors.author_id` → `authors.id`

## Особенности реализации

### Soft Delete
Таблицы `authors`, `poems`, `fragments`, `verses` поддерживают мягкое удаление через поле `deleted_at`.

### Versioning
Все таблицы имеют поля `created_at` и `updated_at` для отслеживания изменений.

### Иерархическая структура
Данные организованы иерархически: Автор → Стихотворение → Фрагмент → Строки + Аудиотреки + Тайминги.

### Гибкость контента
- Стихотворения могут быть как цельными, так и разделенными на фрагменты
- Поддержка нескольких авторов для одного произведения
- Множественные аудиозаписи для одного фрагмента с разными исполнителями