<?php
declare(strict_types=1);

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../classes/autoload.php';

use App\Services\TimingService;

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    if (!is_array($data)) $data = [];

    // Accept `id` (preferred) and keep `track_id` for backward compatibility
    $trackId = 0;
    if (isset($data['id'])) {
        $trackId = (int)$data['id'];
    } elseif (isset($data['track_id'])) {
        $trackId = (int)$data['track_id'];
    }
    $lineId = isset($data['line_id']) ? (int)$data['line_id'] : 0;
    $endTime = isset($data['end_time']) ? (float)$data['end_time'] : null;

    if ($trackId <= 0 || $lineId <= 0 || $endTime === null) {
        http_response_code(400);
        echo json_encode(['error' => 'id (track), line_id and end_time are required']);
        exit;
    }

    $pdo = getPdo();
    $service = new TimingService();
    $service->upsertLineEnd($pdo, $trackId, $lineId, (float)$endTime);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    // Бизнес-ошибки отдадим как 400, остальное 500
    $code = $e instanceof RuntimeException ? 400 : 500;
    http_response_code($code);
    echo json_encode(['error' => $e->getMessage()]);
}
