<?php
/**
 * Сервис для работы с MeiliSearch
 */
declare(strict_types=1);

namespace App\Services;

use Exception;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;

class MeiliSearchService
{
    private Client $client;
    private string $indexName;
    
    public function __construct()
    {
        // Если ключ пустой, передаем null (для dev режима)
        $apiKey = MEILISEARCH_KEY === '' ? null : MEILISEARCH_KEY;
        $this->client = new Client(MEILISEARCH_HOST_INTERNAL, $apiKey);
        $this->indexName = MEILISEARCH_INDEX;
    }
    
    /**
     * Получить или создать индекс
     */
    public function getIndex()
    {
        try {
            return $this->client->getIndex($this->indexName);
        } catch (ApiException $e) {
            // Если индекса нет, создаем его
            if ($e->getCode() === 404) {
                $task = $this->client->createIndex($this->indexName);
                // Ждем создания индекса
                $this->waitForTask($task['taskUid']);
                return $this->client->getIndex($this->indexName);
            }
            throw $e;
        }
    }
    
    /**
     * Очистить индекс
     */
    public function clearIndex(): array
    {
        $index = $this->getIndex();
        return $index->deleteAllDocuments();
    }
    
    /**
     * Добавить документы в индекс
     */
    public function addDocuments(array $documents, ?string $primaryKey = 'id'): array
    {
        if (empty($documents)) {
            throw new Exception('Нет документов для добавления');
        }
        
        $index = $this->getIndex();
        return $index->addDocuments($documents, $primaryKey);
    }
    
    /**
     * Добавить документы батчами
     */
    public function addDocumentsBatch(array $documents, int $batchSize = 100): array
    {
        $results = [];
        $batches = array_chunk($documents, $batchSize);
        
        foreach ($batches as $i => $batch) {
            echo "Отправляем батч " . ($i + 1) . " из " . count($batches) . " (" . count($batch) . " документов)...\n";
            
            $result = $this->addDocuments($batch);
            $results[] = $result;
            
            echo "Задача: " . $result['taskUid'] . "\n";
            
            // Небольшая пауза между батчами
            if ($i < count($batches) - 1) {
                sleep(1);
            }
        }
        
        return $results;
    }
    
    /**
     * Настроить поисковые атрибуты
     */
    public function configureSearchAttributes(): array
    {
        $index = $this->getIndex();
        
        // Настраиваем атрибуты для поиска
        $searchableAttributes = [
            'name',
            'title', 
            'author_name',
            'author_name_variants',
            'first_line',
            'full_text',
            'fragment_label'
        ];
        
        // Настраиваем атрибуты для фильтрации
        $filterableAttributes = [
            'type',
            'author_id',
            'grade_level',
            'year_written',
            'is_standalone'
        ];
        
        // Настраиваем атрибуты для сортировки
        $sortableAttributes = [
            'year_written',
            'title',
            'author_name'
        ];
        
        $results = [];
        $results['searchable'] = $index->updateSearchableAttributes($searchableAttributes);
        $results['filterable'] = $index->updateFilterableAttributes($filterableAttributes);
        $results['sortable'] = $index->updateSortableAttributes($sortableAttributes);
        
        return $results;
    }
    
    /**
     * Получить статистику индекса
     */
    public function getIndexStats(): array
    {
        $index = $this->getIndex();
        $stats = $index->getStats();
        
        return [
            'numberOfDocuments' => $stats['numberOfDocuments'],
            'isIndexing' => $stats['isIndexing'],
            'fieldDistribution' => $stats['fieldDistribution'] ?? []
        ];
    }
    
    /**
     * Проверить статус задачи
     */
    public function getTaskStatus(int $taskUid): array
    {
        return $this->client->getTask($taskUid);
    }
    
    /**
     * Дождаться завершения задачи
     */
    public function waitForTask(int $taskUid, int $timeoutSeconds = 60): bool
    {
        $startTime = time();
        
        while (time() - $startTime < $timeoutSeconds) {
            $task = $this->getTaskStatus($taskUid);
            
            if ($task['status'] === 'succeeded') {
                return true;
            }
            
            if ($task['status'] === 'failed') {
                throw new Exception("Задача #{$taskUid} завершилась с ошибкой: " . ($task['error']['message'] ?? 'Неизвестная ошибка'));
            }
            
            sleep(1);
        }
        
        throw new Exception("Задача #{$taskUid} не завершилась за {$timeoutSeconds} секунд");
    }
}