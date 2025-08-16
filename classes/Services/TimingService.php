<?php
declare(strict_types=1);

namespace App\Services;
use PDO;
use RuntimeException;

/**
 * TimingService
 * Доменные правила:
 * - В timings храним только end_time для строк, КРОМЕ последней.
 * - Последняя строка не редактируется и не сохраняется; её конец всегда равен длительности трека (tracks.duration).
 */
class TimingService
{
    public function __construct()
    {
    }

    /**
     * Вернуть данные для инициализации разметки таймингов.
     * @return array{audioUrl:string,totalDuration:float,lines:array<int,array{id:int,text:string,line_number:int}>,timings:array<int,float>}
     */
    public function getInitData(PDO $pdo, int $trackId): array
    {
        // Track + fragment
        $stmt = $pdo->prepare('SELECT id, fragment_id, filename, duration, title FROM tracks WHERE id = ?');
        $stmt->execute([$trackId]);
        $track = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$track) {
            throw new RuntimeException('Track not found');
        }

        $fragmentId = (int)$track['fragment_id'];
        $duration = (float)$track['duration'];

        // Lines of fragment
        $stmt = $pdo->prepare('SELECT id, text, line_number FROM `lines` WHERE fragment_id = ? ORDER BY line_number');
        $stmt->execute([$fragmentId]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$lines) {
            throw new RuntimeException('No lines for fragment');
        }

        // Existing timings for this track (исходно без доменного добивания последней строки)
        $stmt = $pdo->prepare('SELECT line_id, end_time FROM timings WHERE track_id = ?');
        $stmt->execute([$trackId]);
        $timingsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $timings = [];
        foreach ($timingsRaw as $row) {
            $timings[(int)$row['line_id']] = (float)$row['end_time'];
        }

        // Domain rule: последняя строка всегда имеет end = duration (не хранится в БД)
        $lastLineId = $this->getLastLineId($pdo, $fragmentId);
        if ($lastLineId) {
            $timings[$lastLineId] = $duration;
        }


        // Build audio URL: uploads/audio/{fragmentId}/{filename}
        $audioUrl = $this->buildAudioUrl((int)$track['fragment_id'], (string)$track['filename']);

        // Нормализуем типы в lines
        $normLines = array_map(function ($l) {
            return [
                'id' => (int)$l['id'],
                'text' => (string)$l['text'],
                'line_number' => (int)$l['line_number'],
            ];
        }, $lines);

        return [
            'audioUrl' => $audioUrl,
            'totalDuration' => $duration,
            'lines' => $normLines,
            'timings' => $timings,
        ];
    }

    /**
     * UPSERT end_time для строки, кроме последней (запрещено по доменному правилу).
     */
    public function upsertLineEnd(PDO $pdo, int $trackId, int $lineId, float $endTime): void
    {
        // Определить последнюю строку для трека
        [$fragmentId, $lastLineId, $totalDuration] = $this->getFragmentAndLastLine($pdo, $trackId);
        if ($lineId === $lastLineId) {
            throw new RuntimeException('Last line end_time is fixed to total duration and cannot be updated.');
        }

        if ($endTime <= 0 || $endTime > $totalDuration) {
            throw new RuntimeException('Invalid end_time bounds.');
        }

        // Мягкая проверка неубывания относительно предыдущей строки (если есть)
        $prevEnd = $this->getPreviousEndTime($pdo, $trackId, $fragmentId, $lineId);
        if ($prevEnd !== null && $endTime < $prevEnd) {
            throw new RuntimeException('end_time cannot be less than previous line end_time.');
        }

        // UPSERT timings
        $sql = 'INSERT INTO timings (track_id, line_id, end_time) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE end_time = VALUES(end_time)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$trackId, $lineId, $endTime]);
    }

    /**
     * Строгая финализация: проверка полноты и корректности таймингов.
     * Правила:
     * - Для всех строк, кроме последней, должен быть записан end_time в timings.
     * - Времена неубывающие и каждый end_time > start_time.
     * - Пересечений нет: end_time[i] <= start_time[i+1] (эквивалентно монотонному росту end_time).
     * - Последняя строка не хранится — её конец равен duration трека и должен быть > последнего end_time.
     */
    public function finalizeTrack(PDO $pdo, int $trackId): void
    {
        [$fragmentId, $lastLineId, $duration] = $this->getFragmentAndLastLine($pdo, $trackId);

        // Получить все линии фрагмента по порядку
        $stmt = $pdo->prepare('SELECT id, line_number FROM `lines` WHERE fragment_id = ? ORDER BY line_number ASC');
        $stmt->execute([$fragmentId]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$lines || count($lines) < 1) {
            throw new RuntimeException('No lines to finalize');
        }

        // Собрать end_time для всех, кроме последней
        $nonLast = array_slice($lines, 0, -1);
        $prevEnd = 0.0;
        foreach ($nonLast as $idx => $line) {
            $lineId = (int)$line['id'];
            $q = $pdo->prepare('SELECT end_time FROM timings WHERE track_id = ? AND line_id = ?');
            $q->execute([$trackId, $lineId]);
            $end = $q->fetchColumn();
            if ($end === false) {
                throw new RuntimeException(sprintf('Missing timing for line_id=%d', $lineId));
            }
            $end = (float)$end;
            if ($end <= $prevEnd) {
                throw new RuntimeException('Timings must be strictly increasing');
            }
            if ($end <= 0.0 || $end >= $duration) {
                throw new RuntimeException('Timing end_time out of bounds');
            }
            $prevEnd = $end;
        }

        if ($prevEnd <= 0.0) {
            throw new RuntimeException('Empty timings');
        }
        if ($prevEnd >= $duration) {
            throw new RuntimeException('Last timing must be less than track duration');
        }

        // Переводим трек в статус active
        $upd = $pdo->prepare("UPDATE tracks SET status = 'active', updated_at = NOW() WHERE id = ?");
        $upd->execute([$trackId]);
    }

    private function getFragmentAndLastLine(PDO $pdo, int $trackId): array
    {
        $stmt = $pdo->prepare('SELECT fragment_id, duration FROM tracks WHERE id = ?');
        $stmt->execute([$trackId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Track not found');
        }
        $fragmentId = (int)$row['fragment_id'];
        $duration = (float)$row['duration'];

        $stmt = $pdo->prepare('SELECT id FROM `lines` WHERE fragment_id = ? ORDER BY line_number DESC LIMIT 1');
        $stmt->execute([$fragmentId]);
        $lastLineId = (int)$stmt->fetchColumn();
        if (!$lastLineId) {
            throw new RuntimeException('No lines for fragment');
        }

        return [$fragmentId, $lastLineId, $duration];
    }

    private function getPreviousEndTime(PDO $pdo, int $trackId, int $fragmentId, int $lineId): ?float
    {
        // Находим номер текущей строки
        $stmt = $pdo->prepare('SELECT line_number FROM `lines` WHERE id = ?');
        $stmt->execute([$lineId]);
        $num = $stmt->fetchColumn();
        if ($num === false) return null;
        $num = (int)$num;
        if ($num <= 1) {
            // Первая строка: предыдущего конца нет, старт всегда 0.0
            return 0.0;
        }

        // Найти id предыдущей строки
        $stmt = $pdo->prepare('SELECT id FROM `lines` WHERE fragment_id = ? AND line_number = ?');
        $stmt->execute([$fragmentId, $num - 1]);
        $prevId = $stmt->fetchColumn();
        if ($prevId === false) return null;
        $prevId = (int)$prevId;

        // Взять timing предыдущей
        $stmt = $pdo->prepare('SELECT end_time FROM timings WHERE track_id = ? AND line_id = ?');
        $stmt->execute([$trackId, $prevId]);
        $prevEnd = $stmt->fetchColumn();
        return $prevEnd !== false ? (float)$prevEnd : null;
    }

    private function buildAudioUrl(int $fragmentId, string $filename): string
    {
        // uploads/audio/{fragmentId}/{filename}
        return 'uploads/audio/' . $fragmentId . '/' . $filename;
    }

    private function getLastLineId(PDO $pdo, int $fragmentId): ?int
    {
        $stmt = $pdo->prepare('SELECT id FROM `lines` WHERE fragment_id = ? ORDER BY line_number DESC LIMIT 1');
        $stmt->execute([$fragmentId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }
}
