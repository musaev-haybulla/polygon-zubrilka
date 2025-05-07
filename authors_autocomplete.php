<?php
// authors_autocomplete.php
declare(strict_types=1);
require 'config.php';

header('Content-Type: application/json; charset=UTF-8');

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$pdo = getPdo();

// Используем MATCH...AGAINST для релевантности + LIKE для подстановки
$sql = "
  SELECT
    id,
    CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name,
    MATCH(first_name,middle_name,last_name)
      AGAINST(:query IN NATURAL LANGUAGE MODE) AS score_ft
  FROM authors
  WHERE
    MATCH(first_name,middle_name,last_name) AGAINST(:query IN NATURAL LANGUAGE MODE)
    OR first_name   LIKE :like
    OR middle_name  LIKE :like
    OR last_name    LIKE :like
  ORDER BY score_ft DESC, full_name
  LIMIT 10
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':query' => $q,
    ':like'  => "%{$q}%"
]);

echo json_encode($stmt->fetchAll());