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
        "SELECT `f`.`id`, `f`.`label`, `f`.`structure_info`, `f`.`sort_order`, `f`.`grade_level`, `l`.`text` AS `first_line`
         FROM `fragments` AS `f`
         LEFT JOIN `lines` AS `l` ON (`l`.`fragment_id` = `f`.`id` AND `l`.`line_number` = 1)
         WHERE `f`.`poem_id` = ?
         ORDER BY `f`.`sort_order`"
    );
    $stmt->execute([$poemId]);
    $fragments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($fragments);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
