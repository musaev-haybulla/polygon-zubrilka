<?php
/**
 * Класс для управления порядком сортировки аудиодорожек
 */
declare(strict_types=1);

class AudioSorter
{
    private PDO $pdo;
    
    public function __construct()
    {
        $this->pdo = getPdo();
    }
    
    /**
     * Переставляет озвучку на новую позицию с правильной нормализацией
     * @param int $audioId ID озвучки для перестановки
     * @param int $fragmentId ID фрагмента
     * @param int $newPosition Новая позиция (1-based)
     * @return bool Успешность операции
     */
    public function moveAudio(int $audioId, int $fragmentId, int $newPosition): bool
    {
        try {
            $this->pdo->beginTransaction();
            
            // Получаем все озвучки фрагмента, исключая перемещаемую
            $stmt = $this->pdo->prepare("
                SELECT id, sort_order 
                FROM tracks 
                WHERE fragment_id = :fragment_id 
                AND id != :audio_id 
                ORDER BY sort_order ASC
            ");
            $stmt->execute([
                'fragment_id' => $fragmentId,
                'audio_id' => $audioId
            ]);
            $otherAudios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Создаем новый порядок: вставляем перемещаемую озвучку в нужную позицию
            $newOrder = [];
            $inserted = false;
            
            // Если позиция 1 или меньше - вставляем в начало
            if ($newPosition <= 1) {
                $newOrder[] = ['id' => $audioId, 'sort_order' => 1];
                $inserted = true;
                $nextPosition = 2;
            } else {
                $nextPosition = 1;
            }
            
            // Обрабатываем остальные озвучки
            foreach ($otherAudios as $audio) {
                // Если достигли нужной позиции и еще не вставили - вставляем
                if (!$inserted && $nextPosition == $newPosition) {
                    $newOrder[] = ['id' => $audioId, 'sort_order' => $nextPosition];
                    $nextPosition++;
                    $inserted = true;
                }
                
                // Добавляем текущую озвучку
                $newOrder[] = ['id' => $audio['id'], 'sort_order' => $nextPosition];
                $nextPosition++;
            }
            
            // Если еще не вставили (позиция больше количества элементов) - добавляем в конец
            if (!$inserted) {
                $newOrder[] = ['id' => $audioId, 'sort_order' => $nextPosition];
            }
            
            // Обновляем sort_order для всех озвучек
            foreach ($newOrder as $item) {
                $stmt = $this->pdo->prepare("
                    UPDATE tracks 
                    SET sort_order = :sort_order, updated_at = NOW() 
                    WHERE id = :audio_id
                ");
                $stmt->execute([
                    'sort_order' => $item['sort_order'],
                    'audio_id' => $item['id']
                ]);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("AudioSorter error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Добавляет новую озвучку в указанную позицию
     * @param int $fragmentId ID фрагмента
     * @param int $position Позиция для вставки (1-based)
     * @return int Нормализованная позиция для INSERT
     */
    public function getInsertPosition(int $fragmentId, int $position): int
    {
        try {
            // Получаем количество существующих озвучек
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM tracks 
                WHERE fragment_id = :fragment_id
            ");
            $stmt->execute(['fragment_id' => $fragmentId]);
            $count = (int)$stmt->fetchColumn();
            
            // Если позиция больше количества + 1, ставим в конец
            if ($position > $count + 1) {
                $position = $count + 1;
            }
            
            // Если позиция меньше 1, ставим в начало
            if ($position < 1) {
                $position = 1;
            }
            
            // Сдвигаем существующие озвучки
            if ($position <= $count) {
                $stmt = $this->pdo->prepare("
                    UPDATE tracks 
                    SET sort_order = sort_order + 1, updated_at = NOW() 
                    WHERE fragment_id = :fragment_id 
                    AND sort_order >= :position
                ");
                $stmt->execute([
                    'fragment_id' => $fragmentId,
                    'position' => $position
                ]);
            }
            
            return $position;
            
        } catch (Exception $e) {
            error_log("AudioSorter getInsertPosition error: " . $e->getMessage());
            return 1; // Возвращаем безопасную позицию
        }
    }
    
    /**
     * Нормализует порядок сортировки для всех озвучек фрагмента
     * @param int $fragmentId ID фрагмента
     * @return bool Успешность операции
     */
    public function normalizeOrder(int $fragmentId): bool
    {
        try {
            $this->pdo->beginTransaction();
            
            // Получаем все озвучки в текущем порядке
            $stmt = $this->pdo->prepare("
                SELECT id 
                FROM tracks 
                WHERE fragment_id = :fragment_id 
                ORDER BY sort_order ASC, id ASC
            ");
            $stmt->execute(['fragment_id' => $fragmentId]);
            $audios = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Переиндексируем с 1
            foreach ($audios as $index => $audioId) {
                $stmt = $this->pdo->prepare("
                    UPDATE tracks 
                    SET sort_order = :sort_order, updated_at = NOW() 
                    WHERE id = :audio_id
                ");
                $stmt->execute([
                    'sort_order' => $index + 1,
                    'audio_id' => $audioId
                ]);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("AudioSorter normalizeOrder error: " . $e->getMessage());
            return false;
        }
    }
}