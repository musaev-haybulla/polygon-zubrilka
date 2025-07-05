<?php
/**
 * Обработчик создания и сохранения стихотворений
 */
declare(strict_types=1);

class PoemProcessor 
{
    private PDO $pdo;
    
    public function __construct() 
    {
        $this->pdo = getPdo();
    }
    
    /**
     * Обработка POST данных для создания стиха/фрагмента
     */
    public function processForm(array $postData): array 
    {
        try {
            $this->pdo->beginTransaction();
            
            $poemId = $this->getOrCreatePoem($postData);
            $fragmentId = $this->createFragment($poemId, $postData);
            $this->createLines($fragmentId, $postData['poem_text']);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'poem_id' => $poemId,
                'fragment_id' => $fragmentId
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Получить существующий стих или создать новый
     */
    private function getOrCreatePoem(array $data): int 
    {
        if (!empty($data['poem_id'])) {
            return (int)$data['poem_id'];
        }
        
        // Создаем новое стихотворение
        $stmt = $this->pdo->prepare(
            "INSERT INTO `poems` (`title`, `year_written`, `is_divided`, `status`, `owner_id`, `created_at`, `updated_at`) 
             VALUES (?, ?, ?, 'published', ?, NOW(), NOW())"
        );
        
        $stmt->execute([
            $data['title'],
            !empty($data['year_written']) ? (int)$data['year_written'] : null,
            1, // is_divided = true для фрагментов
            $_SESSION['user_id'] ?? 1
        ]);
        
        $poemId = (int)$this->pdo->lastInsertId();
        
        // Связываем с авторами
        if (!empty($data['author_ids'])) {
            $this->linkAuthorsToPoem($poemId, $data['author_ids']);
        }
        
        return $poemId;
    }
    
    /**
     * Создать фрагмент стихотворения
     */
    private function createFragment(int $poemId, array $data): int 
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `fragments` (`poem_id`, `owner_id`, `label`, `structure_info`, `sort_order`, `grade_level`, `status`, `created_at`, `updated_at`) 
             VALUES (?, ?, ?, ?, ?, ?, 'published', NOW(), NOW())"
        );
        
        $stmt->execute([
            $poemId,
            $_SESSION['user_id'] ?? 1,
            $data['label'] ?? null,
            $data['structure_info'] ?? null,
            (int)($data['sort_order'] ?? 1),
            $data['grade_level'] ?? 'secondary'
        ]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Создать строки фрагмента
     */
    private function createLines(int $fragmentId, string $poemText): void 
    {
        $lines = $this->parseLines($poemText);
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO `lines` (`fragment_id`, `line_number`, `text`, `end_line`, `created_at`, `updated_at`) 
             VALUES (?, ?, ?, ?, NOW(), NOW())"
        );
        
        foreach ($lines as $lineNumber => $lineData) {
            $stmt->execute([
                $fragmentId,
                $lineNumber + 1,
                $lineData['text'],
                $lineData['end_line'] ? 1 : 0
            ]);
        }
    }
    
    /**
     * Связать авторов со стихотворением
     */
    private function linkAuthorsToPoem(int $poemId, array $authorIds): void 
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `poem_authors` (`poem_id`, `author_id`) VALUES (?, ?)"
        );
        
        foreach ($authorIds as $authorId) {
            if (is_numeric($authorId)) {
                $stmt->execute([$poemId, (int)$authorId]);
            } else {
                // Создаем нового автора
                $newAuthorId = $this->createAuthor($authorId);
                $stmt->execute([$poemId, $newAuthorId]);
            }
        }
    }
    
    /**
     * Создать нового автора
     */
    private function createAuthor(string $fullName): int 
    {
        $nameParts = explode(' ', trim($fullName));
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        $middleName = $nameParts[2] ?? null;
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO `authors` (`first_name`, `middle_name`, `last_name`, `created_at`, `updated_at`) 
             VALUES (?, ?, ?, NOW(), NOW())"
        );
        
        $stmt->execute([$firstName, $middleName, $lastName]);
        
        return (int)$this->pdo->lastInsertId();
    }
    
    /**
     * Парсинг текста стихотворения в строки
     */
    private function parseLines(string $text): array 
    {
        $lines = [];
        $textLines = explode("\n", $text);
        
        foreach ($textLines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = [
                    'text' => $line,
                    'end_line' => false // Пока что простая логика
                ];
            }
        }
        
        return $lines;
    }
}