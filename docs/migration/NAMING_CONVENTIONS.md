# 🏷️ Соглашения по именованию

Принятые решения по именованию сущностей в Laravel проекте.

## 🎯 Основные принципы

1. **Избегаем двойных названий** - никаких PoemLine, TrackAudio
2. **Поэтическая терминология** - используем специфические термины где уместно
3. **Краткость важнее описательности** - Verse лучше PoemLine
4. **Без конфликтов с SQL** - избегаем зарезервированных слов

## 📚 Основные сущности

### Поэтический контент
```php
Poem      // Стихотворение/поэма (любого размера)
Fragment  // Фрагмент/часть поэмы (остается как есть)
Verse     // Строка стихотворения (было Line)
```

### Аудио и тайминги
```php
Track     // Аудиозапись фрагмента
Timing    // Тайминг строки в треке
```

### Авторы и связи
```php
Author      // Автор
PoemAuthor  // Связь автор-поэма (исключение: без этого никак)
```

## 🔄 Изменения от текущей схемы

| Было | Стало | Причина |
|------|-------|---------|
| `lines` | `verses` | Конфликт с SQL keyword LINE |
| `Line` | `Verse` | Поэтическая терминология |
| `line_number` | `position` | Единообразие с другими таблицами |

## 📁 Структура таблиц

```
poems           // Метаданные произведений
├── fragments   // Части произведений
│   └── verses  // Строки (было lines)
├── tracks      // Аудиозаписи
└── timings     // Тайминги строк

authors         // Авторы
poem_authors    // Связи (единственное двойное название)
```

## 🎭 Доменная терминология

### В контексте поэзии:
- **Verse** - строка стихотворения
- **Fragment** - логическая часть произведения
- **Poem** - любое стихотворное произведение

### В контексте системы:
- **Track** - аудиофайл с озвучкой
- **Timing** - временная метка строки
- **Status** - состояние (draft/published/active)

## 🚫 Что НЕ используем

```php
// ❌ Двойные названия
PoemLine, TrackAudio, FragmentPart

// ❌ Сокращения
Ln, Frag, Aud

// ❌ Технические термины для поэзии
Row, Record, Item

// ❌ SQL конфликты
Line, Order, Group
```

## ✅ Примеры правильного именования

### Модели
```php
class Poem extends Model {}
class Fragment extends Model {}
class Verse extends Model {}       // было Line
class Track extends Model {}
class Timing extends Model {}
class Author extends Model {}
```

### Методы моделей
```php
// ✅ Четкие, специфичные имена
$verse->isLast()
$track->isTimingComplete()
$poem->canBePublished()
$fragment->hasActiveAudio()
```

### UseCase классы
```php
CreatePoem
CreateFragment
CreateVerse              // было CreateLine
UploadAudio
SetVerseTiming          // было SetLineTiming
PublishPoem
```

## 🎯 Практическое применение

### В миграциях:
```php
Schema::create('verses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fragment_id')->constrained();
    $table->integer('position');  // Единообразная позиция
    $table->string('text');
    $table->boolean('end_line')->default(false);
    $table->timestamps();
    
    $table->index(['fragment_id', 'position']);
});
```

### В отношениях:
```php
class Fragment extends Model {
    public function verses() {
        return $this->hasMany(Verse::class)->orderBy('position');
    }
}
```

### В UseCase:
```php
class SetVerseTiming {
    public function execute(Track $track, Verse $verse, float $endTime): void
    {
        // логика установки тайминга строки
    }
}
```

---
**Дата принятия**: Январь 2025  
**Статус**: Принято к реализации