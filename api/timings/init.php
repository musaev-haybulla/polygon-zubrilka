<?php
declare(strict_types=1);

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../vendor/autoload.php';

use App\Services\TimingService;
use App\Services\TimingService;

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
    }

    // Accept `id` (preferred) and keep `track_id` for backward compatibility
    $trackId = 0;
    if (isset($_GET['id'])) {
        $trackId = (int)$_GET['id'];
    } elseif (isset($_GET['track_id'])) {
        $trackId = (int)$_GET['track_id'];
    }
    if ($trackId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'id is required']);
        exit;
    }

    $pdo = getPdo();
    $service = new TimingService();
    $data = $service->getInitData($pdo, $trackId);

    // Доменное правило: последняя строка не хранится в timings; фронт должен использовать totalDuration
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
