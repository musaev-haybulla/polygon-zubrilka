<?php
declare(strict_types=1);
require 'config.php';

header('Content-Type: application/json');

$poemId = (int)($_GET['poem_id'] ?? 0);

if ($poemId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid poem ID']);
    exit;
}

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare(
        "SELECT id, label, structure_info, sort_order 
         FROM fragments 
         WHERE poem_id = ? 
         ORDER BY sort_order"
    );
    $stmt->execute([$poemId]);
    $fragments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($fragments);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
