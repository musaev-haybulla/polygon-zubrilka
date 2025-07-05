<?php
/**
 * Сервис для обработки поисковых запросов
 */
declare(strict_types=1);

class SearchService 
{
    private array $usedPoemIds = [];
    private array $results = [];
    
    /**
     * Выполнить полный поиск по всем категориям
     */
    public function performFullSearch(string $query): array 
    {
        $this->usedPoemIds = [];
        $this->results = [];
        
        // 1. Поиск по названиям стихотворений
        $this->searchPoemTitles($query);
        
        // 2. Поиск по первым строкам
        $this->searchFirstLines($query);
        
        // 3. Поиск по названиям фрагментов
        $this->searchFragmentLabels($query);
        
        // 4. Поиск по содержимому строк
        $this->searchLineContent($query);
        
        return $this->results;
    }
    
    /**
     * Поиск по названиям стихотворений
     */
    private function searchPoemTitles(string $query): void 
    {
        try {
            $poemResults = DatabaseHelper::searchPoemsByTitle($query);
            
            foreach ($poemResults as $poem) {
                $this->usedPoemIds[] = $poem['id'];
                
                // Получаем первый фрагмент стихотворения для preview
                $lines = $this->getPreviewForPoem($poem['id']);
                
                $this->results[] = [
                    'type' => 'poem',
                    'id' => $poem['id'],
                    'title' => $poem['title'],
                    'year_written' => $poem['year_written'],
                    'author' => $poem['author'] ? $poem['author'] : 'Не указан',
                    'lines' => $lines
                ];
            }
        } catch (Exception $e) {
            // Игнорируем ошибки поиска по названиям
        }
    }
    
    /**
     * Получить preview для стихотворения
     */
    private function getPreviewForPoem(int $poemId): array 
    {
        try {
            // Получаем первый фрагмент стихотворения
            $fragments = DatabaseHelper::getFragmentsByPoemId($poemId);
            if (empty($fragments)) {
                return ['', ''];
            }
            
            // Берем первый фрагмент и получаем его строки
            $firstFragment = $fragments[0];
            return DatabaseHelper::getFirstLines($firstFragment['id']);
            
        } catch (Exception $e) {
            return ['', ''];
        }
    }
    
    /**
     * Поиск по первым строкам стихов
     */
    private function searchFirstLines(string $query): void 
    {
        try {
            $firstLineResults = DatabaseHelper::searchByFirstLine($query);
            
            foreach ($firstLineResults as $result) {
                if (!in_array($result['poem_id'], $this->usedPoemIds)) {
                    $this->usedPoemIds[] = $result['poem_id'];
                    
                    $lines = DatabaseHelper::getFirstLines($result['fragment_id']);
                    
                    $this->results[] = [
                        'type' => 'first_line',
                        'poem_id' => $result['poem_id'],
                        'poem_title' => $result['poem_title'],
                        'text' => $result['text'],
                        'year_written' => $result['year_written'],
                        'author' => $result['author'] ? $result['author'] : 'Не указан',
                        'lines' => $lines
                    ];
                }
            }
        } catch (Exception $e) {
            // Игнорируем ошибки поиска по первым строкам
        }
    }
    
    /**
     * Поиск по названиям фрагментов
     */
    private function searchFragmentLabels(string $query): void 
    {
        try {
            $fragmentResults = DatabaseHelper::searchFragmentsByLabel($query);
            
            foreach ($fragmentResults as $fragment) {
                if (!in_array($fragment['poem_id'], $this->usedPoemIds)) {
                    $this->usedPoemIds[] = $fragment['poem_id'];
                    
                    $lines = DatabaseHelper::getFirstLines($fragment['id']);
                    
                    $this->results[] = [
                        'type' => 'fragment',
                        'id' => $fragment['id'],
                        'poem_id' => $fragment['poem_id'],
                        'poem_title' => $fragment['poem_title'],
                        'label' => $fragment['label'],
                        'author' => $fragment['author'] ? $fragment['author'] : 'Не указан',
                        'lines' => $lines
                    ];
                }
            }
        } catch (Exception $e) {
            // Игнорируем ошибки поиска по фрагментам
        }
    }
    
    /**
     * Поиск по содержимому строк
     */
    private function searchLineContent(string $query): void 
    {
        try {
            $lineResults = DatabaseHelper::searchByLineContent($query);
            
            foreach ($lineResults as $result) {
                if (!in_array($result['poem_id'], $this->usedPoemIds)) {
                    $this->usedPoemIds[] = $result['poem_id'];
                    
                    $lines = DatabaseHelper::getFirstLines($result['fragment_id']);
                    
                    $this->results[] = [
                        'id' => $result['poem_id'],
                        'title' => $result['poem_title'] . ($result['fragment_label'] ? ' - ' . $result['fragment_label'] : ''),
                        'author' => $result['author'] ?? 'Неизвестный автор',
                        'year' => $result['year_written'],
                        'lines' => $lines
                    ];
                }
            }
        } catch (Exception $e) {
            // Игнорируем ошибки поиска по содержимому
        }
    }
}