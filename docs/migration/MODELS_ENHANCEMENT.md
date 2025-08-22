# Обогащение Eloquent моделей проекта "Зубрилка"

## Архитектурный принцип

**Eloquent модели = Rich Domain Objects**
- Вместо Repository используем Scopes для переиспользуемых запросов
- Бизнес-логику размещаем прямо в моделях
- Query Builder методы для сложных запросов
- События модели для развязки компонентов

## Model: Author

### Текущее состояние
Базируется на миграции `20250816233147_create_authors_table.php` и использовании в:
- `DatabaseHelper->searchAuthors()` (classes/DatabaseHelper.php:80)
- `DatabaseHelper->getAllAuthors()` (classes/DatabaseHelper.php:102)

### Предлагаемые Scopes

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Author extends Model
{
    use SoftDeletes;
    
    // Поиск по имени (аналог searchAuthors)
    public function scopeSearch($query, string $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->whereRaw('MATCH(first_name, middle_name, last_name) AGAINST(? IN BOOLEAN MODE)', [$searchTerm])
              ->orWhere('last_name', 'like', "%{$searchTerm}%")
              ->orWhere('first_name', 'like', "%{$searchTerm}%");
        });
    }
    
    // Авторы с опубликованными произведениями
    public function scopeWithPublishedPoems($query)
    {
        return $query->whereHas('poems', function ($q) {
            $q->where('status', 'published');
        });
    }
    
    // Авторы по веку
    public function scopeByCentury($query, int $century)
    {
        $startYear = ($century - 1) * 100 + 1;
        $endYear = $century * 100;
        
        return $query->whereBetween('birth_year', [$startYear, $endYear]);
    }
    
    // Популярные авторы (много произведений)
    public function scopePopular($query, int $minPoems = 3)
    {
        return $query->has('poems', '>=', $minPoems);
    }
}
```

### Бизнес-методы

```php
// Получение полного имени
public function getFullNameAttribute(): string
{
    return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
}

// Получение отображаемого имени для селектов (аналог getAllAuthors)
public function getDisplayNameAttribute(): string
{
    $name = $this->full_name;
    if ($this->birth_year || $this->death_year) {
        $years = $this->birth_year . '-' . ($this->death_year ?? 'н.в.');
        $name .= " ({$years})";
    }
    return $name;
}

// Проверка активности автора
public function isActive(): bool
{
    return $this->poems()->where('status', 'published')->exists();
}

// Подсчет произведений
public function getTotalPoemsCount(): int
{
    return $this->poems()->count();
}
```

### Отношения

```php
public function poems()
{
    return $this->belongsToMany(Poem::class, 'poem_authors');
}

public function fragments()
{
    return $this->hasManyThrough(Fragment::class, Poem::class);
}
```

---

## Model: Poem

### Текущее состояние
Базируется на:
- `DatabaseHelper->searchPoemsByTitle()` (classes/DatabaseHelper.php:127)
- `PoemProcessor->getOrCreatePoem()` (classes/PoemProcessor.php:79)
- `process_poem.php` логика создания

### Предлагаемые Scopes

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poem extends Model
{
    use SoftDeletes;
    
    protected $casts = [
        'is_divided' => 'boolean',
    ];
    
    // Опубликованные стихи
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
    
    // Черновики
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
    
    // Поиск по названию (аналог searchPoemsByTitle)
    public function scopeByTitle($query, string $title)
    {
        return $query->where('title', 'like', "%{$title}%");
    }
    
    // Стихи автора
    public function scopeByAuthor($query, $author)
    {
        if (is_string($author)) {
            return $query->whereHas('authors', function ($q) use ($author) {
                $q->search($author);
            });
        }
        
        return $query->whereHas('authors', function ($q) use ($author) {
            $q->where('id', $author);
        });
    }
    
    // По году написания
    public function scopeByYear($query, int $year)
    {
        return $query->where('year_written', $year);
    }
    
    // По периоду
    public function scopeByPeriod($query, int $startYear, int $endYear)
    {
        return $query->whereBetween('year_written', [$startYear, $endYear]);
    }
    
    // Стихи с аудио
    public function scopeWithAudio($query)
    {
        return $query->whereHas('fragments.tracks', function ($q) {
            $q->where('status', 'active');
        });
    }
    
    // Разделенные на фрагменты
    public function scopeDivided($query)
    {
        return $query->where('is_divided', true);
    }
    
    // Простые (не разделенные)
    public function scopeSimple($query)
    {
        return $query->where('is_divided', false);
    }
    
    // По уровню сложности
    public function scopeByGrade($query, string $grade)
    {
        return $query->whereHas('fragments', function ($q) use ($grade) {
            $q->where('grade_level', $grade);
        });
    }
    
    // Популярные (критерий можно настроить)
    public function scopePopular($query)
    {
        return $query->withCount('fragments')
                    ->having('fragments_count', '>', 0)
                    ->orderByDesc('fragments_count');
    }
}
```

### Бизнес-методы

```php
// Публикация стихотворения (новая логика)
public function publish(): bool
{
    if (!$this->canBePublished()) {
        return false;
    }
    
    $this->update(['status' => 'published']);
    event(new \App\Events\PoemPublished($this));
    
    return true;
}

// Проверка возможности публикации
public function canBePublished(): bool
{
    // Должны быть фрагменты
    if (!$this->fragments()->exists()) {
        return false;
    }
    
    // У каждого фрагмента должны быть строки
    return !$this->fragments()->doesntHave('verses')->exists();
}

// Подсчет времени чтения (примерно 150 слов в минуту)
public function calculateReadingTime(): int
{
    $totalWords = $this->fragments()
        ->with('verses')
        ->get()
        ->sum(function ($fragment) {
            return $fragment->verses->sum(function ($line) {
                return str_word_count($line->text);
            });
        });
    
    return max(1, (int) ceil($totalWords / 150)); // минимум 1 минута
}

// Получение первых строк для превью
public function getPreviewVerses(int $limit = 2): array
{
    return $this->fragments()
        ->orderBy('sort_order')
        ->first()
        ?->verses()
        ->orderBy('position')
        ->limit($limit)
        ->pluck('text')
        ->toArray() ?? [];
}

// Проверка наличия аудио
public function hasAudio(): bool
{
    return $this->fragments()->whereHas('tracks', function ($q) {
        $q->where('status', 'active');
    })->exists();
}

// Получение общей длительности аудио
public function getTotalAudioDuration(): float
{
    return $this->fragments()
        ->with(['tracks' => fn($q) => $q->where('status', 'active')])
        ->get()
        ->sum(function ($fragment) {
            return $fragment->tracks->sum('duration');
        });
}
```

### Отношения

```php
public function authors()
{
    return $this->belongsToMany(Author::class, 'poem_authors');
}

public function fragments()
{
    return $this->hasMany(Fragment::class)->orderBy('sort_order');
}

public function owner()
{
    return $this->belongsTo(User::class, 'owner_id');
}
```

---

## Model: Fragment

### Текущее состояние
Базируется на:
- `DatabaseHelper->getFragmentsByPoemId()` (classes/DatabaseHelper.php:50)
- `DatabaseHelper->searchFragmentsByLabel()` (classes/DatabaseHelper.php:165)
- `FragmentQuery` класс для сложных запросов

### Предлагаемые Scopes

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fragment extends Model
{
    use SoftDeletes;
    
    protected $casts = [
        'sort_order' => 'integer',
    ];
    
    // По уровню образования
    public function scopeByGrade($query, string $grade)
    {
        return $query->where('grade_level', $grade);
    }
    
    // Начальная школа
    public function scopePrimary($query)
    {
        return $query->where('grade_level', 'primary');
    }
    
    // Средняя школа
    public function scopeMiddle($query)
    {
        return $query->where('grade_level', 'middle');
    }
    
    // Старшая школа
    public function scopeSecondary($query)
    {
        return $query->where('grade_level', 'secondary');
    }
    
    // Опубликованные фрагменты
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
    
    // С аудио
    public function scopeWithAudio($query)
    {
        return $query->has('tracks');
    }
    
    // С активным аудио
    public function scopeWithActiveAudio($query)
    {
        return $query->whereHas('tracks', function ($q) {
            $q->where('status', 'active');
        });
    }
    
    // С таймингами
    public function scopeWithTimings($query)
    {
        return $query->whereHas('tracks.timings');
    }
    
    // Поиск по названию фрагмента (аналог searchFragmentsByLabel)
    public function scopeByLabel($query, string $label)
    {
        return $query->where('label', 'like', "%{$label}%");
    }
    
    // По размеру (количеству строк)
    public function scopeBySize($query, string $size)
    {
        return $query->withCount('verses')
            ->when($size === 'short', fn($q) => $q->having('verses_count', '<=', 10))
            ->when($size === 'medium', fn($q) => $q->having('verses_count', '>', 10)->having('verses_count', '<=', 30))
            ->when($size === 'large', fn($q) => $q->having('verses_count', '>', 30));
    }
}
```

### Бизнес-методы

```php
// Получение размера фрагмента
public function getSize(): string
{
    $versesCount = $this->verses()->count();
    
    if ($versesCount <= 10) return 'short';
    if ($versesCount <= 30) return 'medium';
    return 'large';
}

// Получение первых строк
public function getFirstVerses(int $limit = 2): Collection
{
    return $this->verses()
        ->orderBy('position')
        ->limit($limit)
        ->get();
}

// Проверка наличия активного аудио
public function hasActiveAudio(): bool
{
    return $this->tracks()->where('status', 'active')->exists();
}

// Получение активного аудио
public function getActiveTrack(): ?Track
{
    return $this->tracks()->where('status', 'active')->first();
}

// Подсчет слов в фрагменте
public function getWordCount(): int
{
    return $this->verses->sum(function ($line) {
        return str_word_count($line->text);
    });
}

// Проверка полноты таймингов
public function hasCompleteTimings(): bool
{
    $activeTracks = $this->tracks()->where('status', 'active')->get();
    
    return $activeTracks->every(function ($track) {
        return $track->isTimingComplete();
    });
}
```

### Отношения

```php
public function poem()
{
    return $this->belongsTo(Poem::class);
}

public function verses()
{
    return $this->hasMany(Verse::class)->orderBy('position');
}

public function tracks()
{
    return $this->hasMany(Track::class)->orderBy('sort_order');
}

public function owner()
{
    return $this->belongsTo(User::class, 'owner_id');
}
```

---

## Model: Verse

### Текущее состояние
Базируется на:
- `DatabaseHelper->searchByVerseContent()` (classes/DatabaseHelper.php:185)
- `DatabaseHelper->searchByFirstVerse()` (classes/DatabaseHelper.php:145)

### Предлагаемые Scopes

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Verse extends Model
{
    use SoftDeletes;
    
    protected $casts = [
        'position' => 'integer',
        'end_line' => 'boolean',
    ];
    
    // Поиск по тексту (аналог searchByVerseContent)
    public function scopeByContent($query, string $content)
    {
        return $query->where('text', 'like', "%{$content}%");
    }
    
    // Первые строки фрагментов (аналог searchByFirstVerse)
    public function scopeFirstVerses($query)
    {
        return $query->where('position', 1);
    }
    
    // Строки-окончания строф
    public function scopeEndVerses($query)
    {
        return $query->where('end_line', true);
    }
    
    // По номеру строки
    public function scopeByNumber($query, int $lineNumber)
    {
        return $query->where('position', $lineNumber);
    }
}
```

### Бизнес-методы

```php
// Проверка, является ли первой строкой
public function isFirst(): bool
{
    return $this->position === 1;
}

// Проверка, является ли последней в фрагменте
public function isLast(): bool
{
    return $this->fragment
        ->verses()
        ->max('position') === $this->position;
}

// Получение следующей строки
public function getNext(): ?Verse
{
    return $this->fragment
        ->verses()
        ->where('position', '>', $this->position)
        ->orderBy('position')
        ->first();
}

// Получение предыдущей строки
public function getPrevious(): ?Verse
{
    return $this->fragment
        ->verses()
        ->where('position', '<', $this->position)
        ->orderByDesc('position')
        ->first();
}

// Подсчет слов в строке
public function getWordCount(): int
{
    return str_word_count($this->text);
}
```

### Отношения

```php
public function fragment()
{
    return $this->belongsTo(Fragment::class);
}

public function timings()
{
    return $this->hasMany(Timing::class);
}
```

---

## Model: Track

### Текущее состояние
Базируется на миграции `20250816233152_create_tracks_table.php` и использовании в:
- `add_audio_step1.php` операции создания
- `TimingService` для работы с таймингами

### Предлагаемые Scopes

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    protected $casts = [
        'duration' => 'decimal:3',
        'is_ai_generated' => 'boolean',
        'sort_order' => 'integer',
    ];
    
    // Активные треки
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    // Черновики
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
    
    // ИИ-сгенерированные
    public function scopeAiGenerated($query)
    {
        return $query->where('is_ai_generated', true);
    }
    
    // Человеческая озвучка
    public function scopeHumanGenerated($query)
    {
        return $query->where('is_ai_generated', false);
    }
    
    // С полными таймингами
    public function scopeWithCompleteTimings($query)
    {
        return $query->whereHas('fragment.verses', function ($lineQuery) {
            $lineQuery->whereDoesntHave('timings', function ($timingQuery) {
                $timingQuery->where('track_id', $query->getModel()->id ?? 0);
            });
        }, '=', 0);
    }
    
    // По длительности
    public function scopeByDuration($query, float $minDuration = null, float $maxDuration = null)
    {
        return $query->when($minDuration, fn($q) => $q->where('duration', '>=', $minDuration))
                    ->when($maxDuration, fn($q) => $q->where('duration', '<=', $maxDuration));
    }
}
```

### Бизнес-методы

```php
// Активация трека (аналог finalizeTrack)
public function activate(): bool
{
    if (!$this->isTimingComplete()) {
        return false;
    }
    
    $this->update(['status' => 'active']);
    event(new \App\Events\TrackActivated($this));
    
    return true;
}

// Проверка полноты таймингов
public function isTimingComplete(): bool
{
    $versesCount = $this->fragment->verses()->count();
    $timingsCount = $this->timings()->count();
    
    // Последняя строка не требует тайминга (доменное правило из кода)
    return $timingsCount >= ($versesCount - 1);
}

// Обрезка аудио (аналог операций из add_audio_step2.php)
public function trim(float $startTime, float $endTime): void
{
    // Логика будет в AudioProcessor сервисе
    $newDuration = $endTime - $startTime;
    $this->update(['duration' => $newDuration]);
    
    event(new \App\Events\AudioTrimmed($this, $startTime, $endTime));
}

// Получение пути к файлу
public function getFilePath(): string
{
    return "uploads/audio/{$this->fragment_id}/{$this->filename}";
}

// Получение URL для проигрывания
public function getPlayUrl(): string
{
    return asset($this->getFilePath());
}

// Форматированная длительность
public function getFormattedDuration(): string
{
    $minutes = floor($this->duration / 60);
    $seconds = $this->duration % 60;
    
    return sprintf('%d:%02d', $minutes, $seconds);
}
```

### Отношения

```php
public function fragment()
{
    return $this->belongsTo(Fragment::class);
}

public function timings()
{
    return $this->hasMany(Timing::class);
}
```

---

## Model: Timing

### Текущее состояние
Базируется на `TimingService` операциях и миграции `20250816233153_create_timings_table.php`

### Предлагаемые Scopes

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timing extends Model
{
    protected $casts = [
        'end_time' => 'decimal:3',
    ];
    
    // По треку
    public function scopeForTrack($query, int $trackId)
    {
        return $query->where('track_id', $trackId);
    }
    
    // По времени
    public function scopeByTime($query, float $minTime = null, float $maxTime = null)
    {
        return $query->when($minTime, fn($q) => $q->where('end_time', '>=', $minTime))
                    ->when($maxTime, fn($q) => $q->where('end_time', '<=', $maxTime));
    }
    
    // Упорядоченные по времени
    public function scopeOrderedByTime($query)
    {
        return $query->orderBy('end_time');
    }
}
```

### Бизнес-методы

```php
// Получение начального времени (из предыдущего тайминга)
public function getStartTime(): float
{
    $previousTiming = $this->track->timings()
        ->where('end_time', '<', $this->end_time)
        ->orderByDesc('end_time')
        ->first();
    
    return $previousTiming ? $previousTiming->end_time : 0.0;
}

// Получение длительности строки
public function getVerseDuration(): float
{
    return $this->end_time - $this->getStartTime();
}

// Форматированное время
public function getFormattedEndTime(): string
{
    $minutes = floor($this->end_time / 60);
    $seconds = $this->end_time % 60;
    
    return sprintf('%d:%06.3f', $minutes, $seconds);
}
```

### Отношения

```php
public function track()
{
    return $this->belongsTo(Track::class);
}

public function line()
{
    return $this->belongsTo(Verse::class);
}
```

## Миграция существующих запросов

### DatabaseHelper -> Model Scopes

```php
// Было:
DatabaseHelper::searchAuthors($query)

// Стало:
Author::search($query)->get()

// Было:
DatabaseHelper::searchPoemsByTitle($query)

// Стало:
Poem::byTitle($query)->published()->get()

// Было:
DatabaseHelper::getFragmentsByPoemId($poemId)

// Стало:
Fragment::where('poem_id', $poemId)->with(['verses', 'tracks'])->get()
```

### События моделей для развязки

```php
// В моделях добавить:
protected static function booted()
{
    static::created(function ($model) {
        event(new ModelCreated($model));
    });
    
    static::updated(function ($model) {
        if ($model->wasChanged('status')) {
            event(new ModelStatusChanged($model));
        }
    });
}
```

## Преимущества Rich Models подхода

1. **Нет лишних абстракций** - Repository не нужен с Eloquent
2. **Scopes переиспользуются** - можно комбинировать в разных местах
3. **Бизнес-логика в доменных объектах** - ООП принципы
4. **Читаемость кода** - `Poem::published()->byAuthor('Пушкин')->get()`
5. **Laravel-way** - используем фреймворк как задумано
6. **Тестируемость** - легко тестировать модели и их методы