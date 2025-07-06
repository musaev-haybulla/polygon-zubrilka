<?php
/**
 * Вспомогательный класс для работы с базой данных
 */
declare(strict_types=1);

class DatabaseHelper 
{
    private static ?PDO $pdo = null;
    
    /**
     * Получение подключения к БД
     */
    private static function getPdo(): PDO 
    {
        if (self::$pdo === null) {
            self::$pdo = getPdo();
        }
        return self::$pdo;
    }
    
    /**
     * Получение фрагментов стихотворения по ID
     */
    public static function getFragmentsByPoemId(int $poemId): array 
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare(
            "SELECT `f`.`id`, `f`.`label`, `f`.`structure_info`, `f`.`sort_order`, `f`.`grade_level`, `l`.`text` AS `first_line`
             FROM `fragments` AS `f`
             LEFT JOIN `lines` AS `l` ON (`l`.`fragment_id` = `f`.`id` AND `l`.`line_number` = 1)
             WHERE `f`.`poem_id` = ?
             ORDER BY `f`.`sort_order`"
        );
        
        $stmt->execute([$poemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Поиск авторов по запросу
     */
    public static function searchAuthors(string $query): array 
    {
        if (strlen($query) < 2) {
            return [];
        }
        
        $pdo = self::getPdo();
        $sql = "
          SELECT
            id,
            CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name,
            MATCH(first_name,middle_name,last_name)
              AGAINST(:query IN NATURAL LANGUAGE MODE) AS score_ft
          FROM authors
          WHERE
            MATCH(first_name,middle_name,last_name) AGAINST(:query IN NATURAL LANGUAGE MODE)
            OR first_name   LIKE :like
            OR middle_name  LIKE :like
            OR last_name    LIKE :like
          ORDER BY score_ft DESC, full_name
          LIMIT 10
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':query' => $query,
            ':like'  => "%{$query}%"
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получение всех авторов для выпадающего списка
     */
    public static function getAllAuthors(): array 
    {
        $pdo = self::getPdo();
        return $pdo->query(
            "SELECT id, CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name 
             FROM authors 
             ORDER BY full_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получение всех разделенных стихотворений
     */
    public static function getDividedPoems(): array 
    {
        $pdo = self::getPdo();
        return $pdo->query(
            "SELECT id, title 
             FROM poems 
             WHERE is_divided = 1 
             ORDER BY title"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получение полного списка фрагментов с авторами и озвучками
     */
    public static function getAllFragmentsWithDetails(): array 
    {
        $pdo = self::getPdo();
        $sql = "
            SELECT 
                f.id AS fragment_id,
                f.label AS fragment_label,
                f.grade_level,
                f.sort_order,
                p.id AS poem_id,
                p.title AS poem_title,
                p.year_written,
                GROUP_CONCAT(DISTINCT CONCAT_WS(' ', a.first_name, a.middle_name, a.last_name) SEPARATOR ', ') AS authors,
                GROUP_CONCAT(DISTINCT l.text ORDER BY l.line_number SEPARATOR '<br>') AS fragment_text,
                COUNT(DISTINCT at.id) AS audio_count,
                GROUP_CONCAT(DISTINCT at.title SEPARATOR '|') AS audio_titles,
                GROUP_CONCAT(DISTINCT at.is_ai_generated SEPARATOR '|') AS audio_types
            FROM `fragments` f
            LEFT JOIN `poems` p ON f.poem_id = p.id
            LEFT JOIN `poem_authors` pa ON p.id = pa.poem_id
            LEFT JOIN `authors` a ON pa.author_id = a.id
            LEFT JOIN `lines` l ON f.id = l.fragment_id
            LEFT JOIN `audio_tracks` at ON f.id = at.fragment_id AND at.deleted_at IS NULL
            WHERE f.deleted_at IS NULL 
            AND p.deleted_at IS NULL
            AND (l.deleted_at IS NULL OR l.id IS NULL)
            GROUP BY f.id
            ORDER BY p.title, f.sort_order
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Поиск стихотворений по названию
     */
    public static function searchPoemsByTitle(string $query): array 
    {
        $pdo = self::getPdo();
        $sql = "SELECT p.id, p.title, p.year_written,
               GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS author
               FROM `poems` p
               LEFT JOIN `poem_authors` pa ON p.id = pa.poem_id
               LEFT JOIN `authors` a ON pa.author_id = a.id
               WHERE p.title LIKE :query AND p.deleted_at IS NULL
               GROUP BY p.id
               LIMIT 20";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':query' => "%{$query}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Поиск по первой строке стиха
     */
    public static function searchByFirstLine(string $query): array 
    {
        $pdo = self::getPdo();
        $sql = "SELECT l.id AS line_id, l.text, f.id AS fragment_id, p.id AS poem_id, p.title AS poem_title, p.year_written,
               GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS author
               FROM `lines` l
               JOIN `fragments` f ON l.fragment_id = f.id
               JOIN `poems` p ON f.poem_id = p.id
               LEFT JOIN `poem_authors` pa ON p.id = pa.poem_id
               LEFT JOIN `authors` a ON pa.author_id = a.id
               WHERE l.line_number = 1 
               AND l.text LIKE :query
               AND l.deleted_at IS NULL
               AND f.deleted_at IS NULL
               AND p.deleted_at IS NULL
               GROUP BY p.id
               LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':query' => "%{$query}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Поиск по названию фрагмента
     */
    public static function searchFragmentsByLabel(string $query): array 
    {
        $pdo = self::getPdo();
        $sql = "SELECT f.id, f.label, p.id AS poem_id, p.title AS poem_title,
               GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS author
               FROM `fragments` f 
               JOIN `poems` p ON f.poem_id = p.id
               LEFT JOIN `poem_authors` pa ON p.id = pa.poem_id
               LEFT JOIN `authors` a ON pa.author_id = a.id 
               WHERE f.label LIKE :query AND f.deleted_at IS NULL AND p.deleted_at IS NULL
               GROUP BY f.id
               LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':query' => "%{$query}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Поиск по содержимому строк стихов
     */
    public static function searchByLineContent(string $query): array 
    {
        $pdo = self::getPdo();
        $sql = "SELECT l.id AS line_id, l.text, l.line_number, f.id AS fragment_id, f.label AS fragment_label,
               p.id AS poem_id, p.title AS poem_title, p.year_written,
               GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS author
               FROM `lines` l
               JOIN `fragments` f ON l.fragment_id = f.id
               JOIN `poems` p ON f.poem_id = p.id
               LEFT JOIN `poem_authors` pa ON p.id = pa.poem_id
               LEFT JOIN `authors` a ON pa.author_id = a.id
               WHERE l.text LIKE :query
               AND l.deleted_at IS NULL
               AND f.deleted_at IS NULL
               AND p.deleted_at IS NULL
               GROUP BY p.id
               LIMIT 15";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':query' => "%{$query}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Получение первых N строк фрагмента
     */
    public static function getFirstLines(int $fragmentId, int $limit = 2): array 
    {
        $pdo = self::getPdo();
        $sql = "SELECT text FROM `lines` 
               WHERE fragment_id = ? 
               AND deleted_at IS NULL 
               ORDER BY line_number 
               LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fragmentId, $limit]);
        $lines = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Всегда возвращаем массив из 2 элементов
                return array_pad($lines, 2, '');
    }
}