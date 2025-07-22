<?php
/**
 * Конструктор запросов для фрагментов стихотворений
 */
declare(strict_types=1);

class FragmentQuery 
{
    private PDO $pdo;
    private array $select = [];
    private array $joins = [];
    private array $where = [];
    private array $params = [];
    private array $groupBy = [];
    private array $orderBy = [];
    private ?int $limit = null;
    
    public function __construct() 
    {
        $this->pdo = getPdo();
        $this->reset();
    }
    
    /**
     * Сброс всех условий запроса
     */
    public function reset(): self 
    {
        $this->select = ['f.id AS fragment_id', 'f.label', 'f.grade_level', 'f.sort_order'];
        $this->joins = [];
        $this->where = ['f.deleted_at IS NULL'];
        $this->params = [];
        $this->groupBy = [];
        $this->orderBy = [];
        $this->limit = null;
        
        return $this;
    }
    
    /**
     * Добавить подключение к стихотворениям
     */
    public function withPoems(): self 
    {
        $this->select = array_merge($this->select, [
            'p.id AS poem_id',
            'p.title AS poem_title',
            'p.year_written',
            'p.is_divided'
        ]);
        
        $this->joins[] = 'LEFT JOIN `poems` p ON f.poem_id = p.id';
        $this->where[] = 'p.deleted_at IS NULL';
        
        return $this;
    }
    
    /**
     * Добавить подключение к авторам
     */
    public function withAuthors(): self 
    {
        if (!in_array('p.id AS poem_id', $this->select)) {
            $this->withPoems();
        }
        
        $this->select[] = "GROUP_CONCAT(DISTINCT CONCAT_WS(' ', a.first_name, a.middle_name, a.last_name) SEPARATOR ', ') AS authors";
        
        $this->joins[] = 'LEFT JOIN `poem_authors` pa ON p.id = pa.poem_id';
        $this->joins[] = 'LEFT JOIN `authors` a ON pa.author_id = a.id';
        
        if (!in_array('f.id', $this->groupBy)) {
            $this->groupBy[] = 'f.id';
        }
        
        return $this;
    }
    
    /**
     * Добавить подключение к строкам стиха
     */
    public function withLines(): self 
    {
        $this->select[] = "GROUP_CONCAT(DISTINCT l.text ORDER BY l.line_number SEPARATOR '<br>') AS fragment_text";
        $this->select[] = "COUNT(DISTINCT l.id) AS line_count";
        
        $this->joins[] = 'LEFT JOIN `lines` l ON f.id = l.fragment_id';
        $this->where[] = '(l.deleted_at IS NULL OR l.id IS NULL)';
        
        if (!in_array('f.id', $this->groupBy)) {
            $this->groupBy[] = 'f.id';
        }
        
        return $this;
    }
    
    /**
     * Добавить поля для сортировки по возрасту и размеру
     */
    public function withSortingFields(): self 
    {
        // Приоритет возрастной группы: младшие(1), средние(2), старшие(3)
        $this->select[] = "CASE 
            WHEN f.grade_level = 'primary' THEN 1
            WHEN f.grade_level = 'middle' THEN 2  
            WHEN f.grade_level = 'secondary' THEN 3
            ELSE 4 
        END AS grade_priority";
        
        // Приоритет размера: короткие(1), средние(2), крупные(3)
        $this->select[] = "CASE 
            WHEN COUNT(DISTINCT l.id) <= 8 THEN 1
            WHEN COUNT(DISTINCT l.id) <= 20 THEN 2
            ELSE 3 
        END AS size_priority";
        
        return $this;
    }
    
    /**
     * Добавить подключение к аудиодорожкам
     */
    public function withAudio(): self 
    {
        $this->select = array_merge($this->select, [
            'COUNT(DISTINCT at.id) AS audio_count',
            "GROUP_CONCAT(DISTINCT at.id ORDER BY at.sort_order SEPARATOR '|') AS audio_ids",
            "GROUP_CONCAT(DISTINCT at.title ORDER BY at.sort_order SEPARATOR '|') AS audio_titles",
            "GROUP_CONCAT(DISTINCT at.is_ai_generated ORDER BY at.sort_order SEPARATOR '|') AS audio_types",
            "GROUP_CONCAT(DISTINCT at.status ORDER BY at.sort_order SEPARATOR '|') AS audio_statuses",
            "GROUP_CONCAT(DISTINCT at.sort_order ORDER BY at.sort_order SEPARATOR '|') AS audio_sort_orders"
        ]);
        
        $this->joins[] = 'LEFT JOIN `audio_tracks` at ON f.id = at.fragment_id';
        
        if (!in_array('f.id', $this->groupBy)) {
            $this->groupBy[] = 'f.id';
        }
        
        return $this;
    }
    
    /**
     * Фильтр по ID фрагмента
     */
    public function whereFragmentId(int $fragmentId): self 
    {
        $this->where[] = 'f.id = :fragment_id';
        $this->params[':fragment_id'] = $fragmentId;
        return $this;
    }
    
    /**
     * Фильтр по ID стихотворения
     */
    public function wherePoemId(int $poemId): self 
    {
        $this->where[] = 'f.poem_id = :poem_id';
        $this->params[':poem_id'] = $poemId;
        return $this;
    }
    
    /**
     * Фильтр по уровню образования
     */
    public function whereGradeLevel(string $gradeLevel): self 
    {
        $this->where[] = 'f.grade_level = :grade_level';
        $this->params[':grade_level'] = $gradeLevel;
        return $this;
    }
    
    /**
     * Фильтр по наличию озвучки
     */
    public function whereHasAudio(bool $hasAudio = true): self 
    {
        if (!$this->hasAudioJoin()) {
            $this->withAudio();
        }
        
        if ($hasAudio) {
            $this->where[] = 'COUNT(DISTINCT at.id) > 0';
        } else {
            $this->where[] = 'COUNT(DISTINCT at.id) = 0';
        }
        
        return $this;
    }
    
    /**
     * Установить сортировку
     */
    public function orderBy(string $field, string $direction = 'ASC'): self 
    {
        $this->orderBy[] = "{$field} {$direction}";
        return $this;
    }
    
    /**
     * Установить лимит результатов
     */
    public function limit(int $limit): self 
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Проверка наличия JOIN с аудио
     */
    private function hasAudioJoin(): bool 
    {
        foreach ($this->joins as $join) {
            if (strpos($join, 'audio_tracks') !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Построить и выполнить запрос
     */
    public function get(): array 
    {
        $sql = 'SELECT ' . implode(', ', $this->select) . ' FROM `fragments` f';
        
        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        } else {
            // Сортировка по умолчанию: сначала по возрастной группе, затем по размеру
            $hasGradePriority = false;
            $hasSizePriority = false;
            
            foreach ($this->select as $field) {
                if (strpos($field, 'grade_priority') !== false) $hasGradePriority = true;
                if (strpos($field, 'size_priority') !== false) $hasSizePriority = true;
            }
            
            if ($hasGradePriority && $hasSizePriority) {
                $sql .= ' ORDER BY grade_priority ASC, size_priority ASC, p.title ASC, f.sort_order ASC';
            } elseif (in_array('p.title AS poem_title', $this->select)) {
                $sql .= ' ORDER BY p.title, f.sort_order';
            } else {
                $sql .= ' ORDER BY f.sort_order';
            }
        }
        
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить только первый результат
     */
    public function first(): ?array 
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }
    
    /**
     * Получить количество результатов
     */
    public function count(): int 
    {
        $originalSelect = $this->select;
        $this->select = ['COUNT(DISTINCT f.id) as count'];
        
        $result = $this->get();
        
        $this->select = $originalSelect;
        
        return (int)($result[0]['count'] ?? 0);
    }
}