<?php

declare(strict_types=1);

namespace App\Services;

use App\Audio\AudioPauseDetector;
use PDO;

class PauseDetectionService
{
    public function __construct(private ?AudioPauseDetector $detector = null)
    {
        $this->detector = $detector ?? new AudioPauseDetector();
    }

    /**
     * Выполнить детекцию пауз и сохранить только splits в tracks.pause_detection
     * Возвращает true при успешной записи (в том числе при пустом массиве, когда пауз нет), false — при исключении в процессе.
     */
    public function detectAndSaveSplits(PDO $pdo, int $trackId, int $fragmentId, string $audioPath, ?float $durationSec = null): bool
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            $numLines = $this->computeNumLines($pdo, $fragmentId);
            $effectiveNumLines = ($numLines > 1) ? $numLines : null;

            error_log(sprintf(
                'PauseDetectionService: track#%d fragment#%d num_lines=%s file=%s duration=%s',
                $trackId,
                $fragmentId,
                var_export($effectiveNumLines, true),
                $audioPath,
                $durationSec !== null ? number_format($durationSec, 3) : 'n/a'
            ));

            $result = $this->detector->detectPauses($audioPath, $effectiveNumLines);
            $splits = $result['splits'] ?? [];

            // fallback: если просили N-1 пауз, а получили пусто, попробуем без ограничения
            if (empty($splits) && $effectiveNumLines !== null) {
                error_log(sprintf(
                    'PauseDetectionService: empty splits with num_lines=%d, retry without limit',
                    $effectiveNumLines
                ));
                $resultRaw = $this->detector->detectPauses($audioPath, null);
                if (isset($resultRaw['splits']) && is_array($resultRaw['splits'])) {
                    $splits = $resultRaw['splits'];
                }
            }

            $stmt = $pdo->prepare('UPDATE tracks SET pause_detection = :json WHERE id = :id');
            $stmt->execute([
                'json' => json_encode($splits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $trackId,
            ]);

            return true;
        } catch (\Throwable $t) {
            error_log('PauseDetectionService failed: ' . $t->getMessage());
            return false;
        }
    }

    /**
     * Возвращает желаемое число пауз как (COUNT(lines) - 1), но не меньше 1.
     */
    private function computeNumLines(PDO $pdo, int $fragmentId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `lines` WHERE fragment_id = :fid');
        $stmt->execute(['fid' => $fragmentId]);
        $linesCount = (int) $stmt->fetchColumn();
        return max($linesCount - 1, 1);
    }
}
