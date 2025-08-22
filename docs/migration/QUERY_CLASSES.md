# Query классы для сложных запросов

## Принципы Query классов

**Когда использовать Query классы:**
- Сложные запросы, которые не помещаются в Model Scopes
- Комбинирование данных из разных источников (БД + MeiliSearch)
- Агрегация и сложные вычисления
- Запросы со множественными JOIN и подзапросами
- Кеширование результатов сложных запросов

**Не использовать для:**
- Простых выборок (используем Scopes)
- CRUD операций (используем Eloquent методы)
- Односложных фильтров (используем where())

## CatalogPoemsQuery

### Назначение
Используется на странице каталога стихотворений для отображения полного списка с фильтрацией, сортировкой и пагинацией.

### Базируется на коде
- `poem_list.php` интерфейс и SQL запросы (строки 200-400)
- `FragmentQuery` класс (classes/FragmentQuery.php)
- Сложные JOIN запросы из `DatabaseHelper->getAllFragmentsWithDetails()`

### Параметры фильтрации
```php
class CatalogFilters
{
    public function __construct(
        public ?string $search = null,
        public ?string $author = null,
        public ?string $grade = null,        // 'primary', 'middle', 'secondary'
        public ?string $size = null,         // 'short', 'medium', 'large'
        public ?bool $hasAudio = null,
        public ?bool $hasTimings = null,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public string $sortBy = 'title',     // 'title', 'author', 'year', 'audio_count'
        public string $sortDir = 'asc',
        public int $perPage = 50
    ) {}
}
```

### Реализация
```php
<?php

namespace App\Queries;

use App\Models\Fragment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogPoemsQuery
{
    public function __construct(
        private CatalogFilters $filters
    ) {}
    
    public function paginate(): LengthAwarePaginator
    {
        // Кеширование для часто используемых запросов
        $cacheKey = $this->getCacheKey();
        
        return Cache::remember($cacheKey, 300, function () {
            return $this->buildQuery()->paginate($this->filters->perPage);
        });
    }
    
    public function get(): Collection
    {
        return $this->buildQuery()->get();
    }
    
    private function buildQuery(): Builder
    {
        $query = Fragment::query()
            ->select([
                'fragments.*',
                'poems.title as poem_title',
                'poems.year_written',
                'poems.is_divided',
                DB::raw('GROUP_CONCAT(CONCAT(authors.last_name, " ", COALESCE(authors.first_name, "")) SEPARATOR ", ") as authors_names'),
                DB::raw('COUNT(DISTINCT tracks.id) as audio_count'),
                DB::raw('COUNT(DISTINCT timings.id) as timings_count'),
                DB::raw('COUNT(DISTINCT verses.id) as verses_count'),
                // Поля для сортировки
                DB::raw('CASE 
                    WHEN fragments.grade_level = "primary" THEN 1 
                    WHEN fragments.grade_level = "middle" THEN 2 
                    WHEN fragments.grade_level = "secondary" THEN 3 
                    ELSE 4 END as grade_sort_order'),
                DB::raw('CASE 
                    WHEN COUNT(DISTINCT verses.id) <= 10 THEN "short"
                    WHEN COUNT(DISTINCT verses.id) <= 30 THEN "medium"
                    ELSE "large" END as calculated_size')
            ])
            ->join('poems', 'fragments.poem_id', '=', 'poems.id')
            ->join('poem_authors', 'poems.id', '=', 'poem_authors.poem_id')
            ->join('authors', 'poem_authors.author_id', '=', 'authors.id')
            ->leftJoin('verses', 'fragments.id', '=', 'verses.fragment_id')
            ->leftJoin('tracks', function ($join) {
                $join->on('fragments.id', '=', 'tracks.fragment_id')
                     ->where('tracks.status', '=', 'active');
            })
            ->leftJoin('timings', function ($join) {
                $join->on('tracks.id', '=', 'timings.track_id');
            })
            ->where('fragments.status', 'published')
            ->where('poems.status', 'published')
            ->groupBy([
                'fragments.id', 
                'fragments.poem_id',
                'fragments.label',
                'fragments.grade_level',
                'fragments.sort_order',
                'poems.title',
                'poems.year_written',
                'poems.is_divided'
            ]);
            
        // Применяем фильтры
        $this->applyFilters($query);
        $this->applySorting($query);
        
        return $query;
    }
    
    private function applyFilters(Builder $query): void
    {
        // Поиск по тексту
        if ($this->filters->search) {
            $query->where(function ($q) {
                $searchTerm = $this->filters->search;
                $q->where('poems.title', 'like', "%{$searchTerm}%")
                  ->orWhere('fragments.label', 'like', "%{$searchTerm}%")
                  ->orWhere('authors.last_name', 'like', "%{$searchTerm}%")
                  ->orWhereExists(function ($subQuery) use ($searchTerm) {
                      $subQuery->select(DB::raw(1))
                               ->from('verses')
                               ->whereColumn('verses.fragment_id', 'fragments.id')
                               ->where('verses.text', 'like', "%{$searchTerm}%");
                  });
            });
        }
        
        // Фильтр по автору
        if ($this->filters->author) {
            $query->where(function ($q) {
                $author = $this->filters->author;
                $q->where('authors.last_name', 'like', "%{$author}%")
                  ->orWhere('authors.first_name', 'like', "%{$author}%");
            });
        }
        
        // Фильтр по уровню образования
        if ($this->filters->grade) {
            $query->where('fragments.grade_level', $this->filters->grade);
        }
        
        // Фильтр по размеру
        if ($this->filters->size) {
            $query->having('calculated_size', $this->filters->size);
        }
        
        // Фильтр по наличию аудио
        if ($this->filters->hasAudio !== null) {
            if ($this->filters->hasAudio) {
                $query->having('audio_count', '>', 0);
            } else {
                $query->having('audio_count', '=', 0);
            }
        }
        
        // Фильтр по наличию таймингов
        if ($this->filters->hasTimings !== null) {
            if ($this->filters->hasTimings) {
                $query->having('timings_count', '>', 0);
            } else {
                $query->having('timings_count', '=', 0);
            }
        }
        
        // Фильтр по годам
        if ($this->filters->yearFrom) {
            $query->where('poems.year_written', '>=', $this->filters->yearFrom);
        }
        
        if ($this->filters->yearTo) {
            $query->where('poems.year_written', '<=', $this->filters->yearTo);
        }
    }
    
    private function applySorting(Builder $query): void
    {
        switch ($this->filters->sortBy) {
            case 'title':
                $query->orderBy('poems.title', $this->filters->sortDir);
                break;
                
            case 'author':
                $query->orderBy('authors_names', $this->filters->sortDir);
                break;
                
            case 'year':
                $query->orderBy('poems.year_written', $this->filters->sortDir);
                break;
                
            case 'audio_count':
                $query->orderBy('audio_count', $this->filters->sortDir);
                break;
                
            case 'grade':
                $query->orderBy('grade_sort_order', $this->filters->sortDir);
                break;
                
            default:
                $query->orderBy('poems.title', 'asc');
        }
        
        // Вторичная сортировка для стабильности
        $query->orderBy('fragments.sort_order', 'asc');
    }
    
    private function getCacheKey(): string
    {
        return 'catalog_poems_' . md5(serialize($this->filters));
    }
}
```

---

## SearchPoemsQuery

### Назначение
Комбинированный поиск, который использует MeiliSearch для быстрого полнотекстового поиска и дополняет результаты данными из MySQL.

### Базируется на коде
- `SearchService->performFullSearch()` (classes/SearchService.php:21)
- `search_api.php` логика (строки 25-35)
- `transfer_to_meilisearch.php` структура индекса

### Параметры поиска
```php
class SearchParams
{
    public function __construct(
        public string $query,
        public ?string $type = null,        // 'poems', 'fragments', 'verses', 'authors'
        public ?string $grade = null,
        public ?bool $hasAudio = null,
        public int $limit = 50,
        public int $offset = 0
    ) {}
}
```

### Реализация
```php
<?php

namespace App\Queries;

use App\Services\MeilisearchIndexer;
use App\Models\Poem;
use App\Models\Fragment;
use App\Models\Author;
use Illuminate\Support\Collection;

class SearchPoemsQuery
{
    public function __construct(
        private MeilisearchIndexer $indexer
    ) {}
    
    public function execute(SearchParams $params): array
    {
        // Быстрый поиск через MeiliSearch
        $meilisearchResults = $this->searchMeilisearch($params);
        
        // Дополнительный поиск в БД для точности
        $dbResults = $this->searchDatabase($params);
        
        // Объединение и ранжирование результатов
        return $this->mergeAndRankResults($meilisearchResults, $dbResults, $params);
    }
    
    private function searchMeilisearch(SearchParams $params): array
    {
        $filters = [];
        
        if ($params->grade) {
            $filters[] = "grade_level = '{$params->grade}'";
        }
        
        if ($params->hasAudio !== null) {
            $filters[] = $params->hasAudio ? 'has_audio = true' : 'has_audio = false';
        }
        
        return $this->indexer->search($params->query, [
            'limit' => $params->limit,
            'offset' => $params->offset,
            'filter' => $filters,
            'attributesToRetrieve' => [
                'id', 'type', 'title', 'content', 'author_names', 
                'grade_level', 'has_audio', 'poem_id', 'fragment_id'
            ],
            'attributesToHighlight' => ['title', 'content'],
        ]);
    }
    
    private function searchDatabase(SearchParams $params): Collection
    {
        $results = collect();
        
        // Поиск по названиям поэм
        if (!$params->type || $params->type === 'poems') {
            $poems = Poem::query()
                ->byTitle($params->query)
                ->published()
                ->with(['authors', 'fragments'])
                ->limit(10)
                ->get()
                ->map(fn($poem) => [
                    'type' => 'poem',
                    'id' => $poem->id,
                    'title' => $poem->title,
                    'authors' => $poem->authors->pluck('full_name')->implode(', '),
                    'preview' => $poem->getPreviewVerses(),
                    'year' => $poem->year_written,
                ]);
            
            $results = $results->merge($poems);
        }
        
        // Поиск по авторам
        if (!$params->type || $params->type === 'authors') {
            $authors = Author::query()
                ->search($params->query)
                ->withPublishedPoems()
                ->limit(5)
                ->get()
                ->map(fn($author) => [
                    'type' => 'author',
                    'id' => $author->id,
                    'name' => $author->full_name,
                    'birth_year' => $author->birth_year,
                    'poems_count' => $author->poems()->published()->count(),
                ]);
            
            $results = $results->merge($authors);
        }
        
        // Поиск по содержимому строк (если MeiliSearch не дал результатов)
        if ($results->isEmpty() && (!$params->type || $params->type === 'verses')) {
            $fragments = Fragment::query()
                ->whereHas('verses', function ($q) use ($params) {
                    $q->byContent($params->query);
                })
                ->published()
                ->with(['poem.authors', 'verses'])
                ->limit(10)
                ->get()
                ->map(fn($fragment) => [
                    'type' => 'fragment',
                    'id' => $fragment->id,
                    'poem_title' => $fragment->poem->title,
                    'fragment_label' => $fragment->label,
                    'authors' => $fragment->poem->authors->pluck('full_name')->implode(', '),
                    'matching_verses' => $fragment->verses()
                        ->byContent($params->query)
                        ->pluck('text')
                        ->toArray(),
                ]);
            
            $results = $results->merge($fragments);
        }
        
        return $results;
    }
    
    private function mergeAndRankResults(array $meilisearchResults, Collection $dbResults, SearchParams $params): array
    {
        $merged = collect();
        
        // Добавляем результаты MeiliSearch с высоким приоритетом
        foreach ($meilisearchResults['hits'] ?? [] as $hit) {
            $merged->push([
                'score' => $hit['_rankingScore'] ?? 1.0,
                'source' => 'meilisearch',
                'type' => $hit['type'],
                'data' => $hit,
            ]);
        }
        
        // Добавляем результаты БД с немного меньшим приоритетом
        foreach ($dbResults as $result) {
            // Простой скоринг по совпадению в названии
            $titleMatch = stripos($result['title'] ?? $result['name'] ?? '', $params->query) !== false;
            $score = $titleMatch ? 0.8 : 0.6;
            
            $merged->push([
                'score' => $score,
                'source' => 'database',
                'type' => $result['type'],
                'data' => $result,
            ]);
        }
        
        // Сортировка по релевантности и удаление дубликатов
        return $merged
            ->sortByDesc('score')
            ->unique(function ($item) {
                return $item['type'] . '_' . $item['data']['id'];
            })
            ->take($params->limit)
            ->groupBy('type')
            ->map(fn($items) => $items->pluck('data'))
            ->toArray();
    }
}
```

---

## AudioStatisticsQuery

### Назначение
Получение статистики по аудиозаписям для административных панелей и отчетов.

### Базируется на коде
Новая функциональность, аналогов в текущем коде нет.

### Реализация
```php
<?php

namespace App\Queries;

use App\Models\Track;
use App\Models\Fragment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AudioStatisticsQuery
{
    public function __construct(
        private ?Carbon $dateFrom = null,
        private ?Carbon $dateTo = null
    ) {
        $this->dateFrom = $dateFrom ?? now()->subMonth();
        $this->dateTo = $dateTo ?? now();
    }
    
    public function getOverallStats(): array
    {
        $baseQuery = Track::query()
            ->when($this->dateFrom, fn($q) => $q->where('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('created_at', '<=', $this->dateTo));
            
        return [
            'total_tracks' => $baseQuery->count(),
            'active_tracks' => $baseQuery->clone()->active()->count(),
            'draft_tracks' => $baseQuery->clone()->draft()->count(),
            'ai_generated' => $baseQuery->clone()->aiGenerated()->count(),
            'human_generated' => $baseQuery->clone()->humanGenerated()->count(),
            'total_duration' => $baseQuery->sum('duration'),
            'average_duration' => $baseQuery->avg('duration'),
            'fragments_with_audio' => Fragment::whereHas('tracks')->count(),
            'fragments_without_audio' => Fragment::doesntHave('tracks')->count(),
            'completed_timings' => $baseQuery->clone()->withCompleteTimings()->count(),
        ];
    }
    
    public function getByGradeLevel(): array
    {
        return Track::query()
            ->select([
                'fragments.grade_level',
                DB::raw('COUNT(*) as tracks_count'),
                DB::raw('SUM(tracks.duration) as total_duration'),
                DB::raw('AVG(tracks.duration) as avg_duration'),
                DB::raw('SUM(CASE WHEN tracks.is_ai_generated = 1 THEN 1 ELSE 0 END) as ai_count'),
                DB::raw('SUM(CASE WHEN tracks.status = "active" THEN 1 ELSE 0 END) as active_count')
            ])
            ->join('fragments', 'tracks.fragment_id', '=', 'fragments.id')
            ->when($this->dateFrom, fn($q) => $q->where('tracks.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('tracks.created_at', '<=', $this->dateTo))
            ->groupBy('fragments.grade_level')
            ->orderBy('fragments.grade_level')
            ->get()
            ->keyBy('grade_level')
            ->toArray();
    }
    
    public function getTimingCompletionStats(): array
    {
        return Fragment::query()
            ->select([
                'grade_level',
                DB::raw('COUNT(*) as total_fragments'),
                DB::raw('SUM(CASE WHEN timing_stats.complete_timings > 0 THEN 1 ELSE 0 END) as with_complete_timings'),
                DB::raw('AVG(timing_stats.completion_percentage) as avg_completion_percentage')
            ])
            ->leftJoinSub(
                Track::query()
                    ->select([
                        'fragment_id',
                        DB::raw('COUNT(CASE WHEN timing_complete = 1 THEN 1 END) as complete_timings'),
                        DB::raw('AVG(CASE 
                            WHEN verses_count = 0 THEN 0
                            WHEN timings_count >= (verses_count - 1) THEN 100
                            ELSE (timings_count * 100.0 / GREATEST(verses_count - 1, 1))
                        END) as completion_percentage')
                    ])
                    ->leftJoinSub(
                        DB::table('fragments')
                            ->select([
                                'id as fragment_id',
                                DB::raw('COUNT(verses.id) as verses_count')
                            ])
                            ->leftJoin('verses', 'fragments.id', '=', 'verses.fragment_id')
                            ->groupBy('fragments.id'),
                        'line_stats',
                        'tracks.fragment_id',
                        '=',
                        'line_stats.fragment_id'
                    )
                    ->leftJoinSub(
                        DB::table('timings')
                            ->select([
                                'track_id',
                                DB::raw('COUNT(*) as timings_count')
                            ])
                            ->groupBy('track_id'),
                        'timing_counts',
                        'tracks.id',
                        '=',
                        'timing_counts.track_id'
                    )
                    ->groupBy('tracks.fragment_id'),
                'timing_stats',
                'fragments.id',
                '=',
                'timing_stats.fragment_id'
            )
            ->groupBy('grade_level')
            ->get()
            ->toArray();
    }
    
    public function getDailyUploadStats(): array
    {
        return Track::query()
            ->select([
                DB::raw('DATE(created_at) as upload_date'),
                DB::raw('COUNT(*) as uploads_count'),
                DB::raw('SUM(duration) as total_duration'),
                DB::raw('SUM(CASE WHEN is_ai_generated = 1 THEN 1 ELSE 0 END) as ai_uploads'),
                DB::raw('SUM(CASE WHEN is_ai_generated = 0 THEN 1 ELSE 0 END) as human_uploads')
            ])
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('upload_date')
            ->get()
            ->toArray();
    }
}
```

---

## FragmentDetailsQuery

### Назначение
Получение полной информации о фрагменте со всеми связанными данными для детальных страниц.

### Базируется на коде
- `FragmentQuery` класс (classes/FragmentQuery.php)
- Сложные JOIN запросы из `getAllFragmentsWithDetails()`

### Реализация
```php
<?php

namespace App\Queries;

use App\Models\Fragment;
use Illuminate\Support\Facades\DB;

class FragmentDetailsQuery
{
    public function __construct(
        private int $fragmentId
    ) {}
    
    public function get(): ?array
    {
        $fragment = Fragment::query()
            ->select([
                'fragments.*',
                'poems.title as poem_title',
                'poems.year_written',
                'poems.is_divided',
                'poems.status as poem_status',
                DB::raw('GROUP_CONCAT(CONCAT(authors.last_name, " ", COALESCE(authors.first_name, "")) ORDER BY authors.last_name SEPARATOR ", ") as authors_names'),
                DB::raw('COUNT(DISTINCT verses.id) as verses_count'),
                DB::raw('COUNT(DISTINCT active_tracks.id) as active_audio_count'),
                DB::raw('COUNT(DISTINCT draft_tracks.id) as draft_audio_count'),
                DB::raw('SUM(DISTINCT active_tracks.duration) as total_audio_duration')
            ])
            ->join('poems', 'fragments.poem_id', '=', 'poems.id')
            ->join('poem_authors', 'poems.id', '=', 'poem_authors.poem_id')
            ->join('authors', 'poem_authors.author_id', '=', 'authors.id')
            ->leftJoin('verses', 'fragments.id', '=', 'verses.fragment_id')
            ->leftJoin('tracks as active_tracks', function ($join) {
                $join->on('fragments.id', '=', 'active_tracks.fragment_id')
                     ->where('active_tracks.status', '=', 'active');
            })
            ->leftJoin('tracks as draft_tracks', function ($join) {
                $join->on('fragments.id', '=', 'draft_tracks.fragment_id')
                     ->where('draft_tracks.status', '=', 'draft');
            })
            ->where('fragments.id', $this->fragmentId)
            ->groupBy([
                'fragments.id',
                'fragments.poem_id', 
                'fragments.label',
                'fragments.grade_level',
                'fragments.status',
                'poems.title',
                'poems.year_written',
                'poems.is_divided',
                'poems.status'
            ])
            ->first();
            
        if (!$fragment) {
            return null;
        }
        
        // Загружаем связанные данные
        $verses = $this->getVerses();
        $tracks = $this->getTracks();
        $relatedFragments = $this->getRelatedFragments($fragment->poem_id);
        
        return [
            'fragment' => $fragment->toArray(),
            'verses' => $verses,
            'tracks' => $tracks,
            'related_fragments' => $relatedFragments,
            'statistics' => $this->getStatistics($fragment),
        ];
    }
    
    private function getVerses(): array
    {
        return DB::table('verses')
            ->select(['id', 'position', 'text', 'end_line'])
            ->where('fragment_id', $this->fragmentId)
            ->orderBy('position')
            ->get()
            ->toArray();
    }
    
    private function getTracks(): array
    {
        return Track::query()
            ->select([
                'tracks.*',
                DB::raw('COUNT(timings.id) as timings_count'),
                DB::raw('(SELECT COUNT(*) FROM verses WHERE verses.fragment_id = tracks.fragment_id) - 1 as required_timings'),
                DB::raw('CASE WHEN COUNT(timings.id) >= ((SELECT COUNT(*) FROM verses WHERE verses.fragment_id = tracks.fragment_id) - 1) THEN 1 ELSE 0 END as timing_complete')
            ])
            ->leftJoin('timings', 'tracks.id', '=', 'timings.track_id')
            ->where('tracks.fragment_id', $this->fragmentId)
            ->groupBy('tracks.id')
            ->orderBy('tracks.sort_order')
            ->get()
            ->map(function ($track) {
                return [
                    ...$track->toArray(),
                    'file_url' => $track->getPlayUrl(),
                    'formatted_duration' => $track->getFormattedDuration(),
                ];
            })
            ->toArray();
    }
    
    private function getRelatedFragments(int $poemId): array
    {
        return Fragment::query()
            ->select(['id', 'label', 'sort_order', 'grade_level'])
            ->where('poem_id', $poemId)
            ->where('id', '!=', $this->fragmentId)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }
    
    private function getStatistics(object $fragment): array
    {
        return [
            'word_count' => $this->getWordCount(),
            'estimated_reading_time' => $this->getEstimatedReadingTime(),
            'audio_coverage' => $fragment->active_audio_count > 0 ? 100 : 0,
            'timing_coverage' => $this->getTimingCoverage(),
            'size_category' => $this->getSizeCategory($fragment->verses_count),
        ];
    }
    
    private function getWordCount(): int
    {
        return DB::table('verses')
            ->where('fragment_id', $this->fragmentId)
            ->get()
            ->sum(function ($line) {
                return str_word_count($line->text);
            });
    }
    
    private function getEstimatedReadingTime(): int
    {
        $wordCount = $this->getWordCount();
        return max(1, (int) ceil($wordCount / 150)); // 150 слов в минуту
    }
    
    private function getTimingCoverage(): float
    {
        $result = DB::table('tracks')
            ->select([
                DB::raw('COUNT(DISTINCT timings.id) as total_timings'),
                DB::raw('(SELECT COUNT(*) FROM verses WHERE verses.fragment_id = tracks.fragment_id) - 1 as required_timings')
            ])
            ->leftJoin('timings', 'tracks.id', '=', 'timings.track_id')
            ->where('tracks.fragment_id', $this->fragmentId)
            ->where('tracks.status', 'active')
            ->first();
            
        if (!$result || $result->required_timings == 0) {
            return 0.0;
        }
        
        return min(100, ($result->total_timings / $result->required_timings) * 100);
    }
    
    private function getSizeCategory(int $versesCount): string
    {
        if ($versesCount <= 10) return 'short';
        if ($versesCount <= 30) return 'medium';
        return 'large';
    }
}
```

## Использование Query классов в контроллерах

### API Controller пример
```php
<?php

namespace App\Http\Controllers\Api;

use App\Queries\CatalogPoemsQuery;
use App\Queries\SearchPoemsQuery;

class PoemController extends Controller
{
    public function index(Request $request)
    {
        $filters = new CatalogFilters(
            search: $request->search,
            author: $request->author,
            grade: $request->grade,
            // ... остальные параметры
        );
        
        $query = new CatalogPoemsQuery($filters);
        $results = $query->paginate();
        
        return response()->json($results);
    }
    
    public function search(Request $request)
    {
        $params = new SearchParams(
            query: $request->query('q'),
            type: $request->type,
            // ... остальные параметры
        );
        
        $query = new SearchPoemsQuery(app(MeilisearchIndexer::class));
        $results = $query->execute($params);
        
        return response()->json($results);
    }
}
```

## Преимущества Query классов

1. **Инкапсуляция сложности** - сложные запросы изолированы в отдельных классах
2. **Переиспользование** - один Query класс можно использовать в разных местах
3. **Тестируемость** - легко unit-тестировать изолированно
4. **Читаемость** - название класса объясняет назначение запроса
5. **Производительность** - возможность кеширования результатов
6. **Гибкость** - параметризация через конструктор или методы
7. **Разделение ответственности** - Query классы не знают о HTTP или презентации