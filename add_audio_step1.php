<?php
/**
 * Шаг 1: Загрузка аудиофайла с выбором порядка сортировки
 */
declare(strict_types=1);

// Подключаем конфигурацию и классы
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/autoload.php';

// Настройка отображения ошибок для разработки
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: poem_list.php');
    exit;
}

// Валидация данных
$fragmentId = (int)($_POST['fragmentId'] ?? 0);
$audioTitle = trim($_POST['audioTitle'] ?? '');
$voiceType = (int)($_POST['voiceType'] ?? 0);
$trimAudio = !empty($_POST['trimAudio']);

if ($fragmentId <= 0 || empty($audioTitle)) {
    header('Location: poem_list.php?error=invalid_data');
    exit;
}

// Проверяем наличие файла
if (!isset($_FILES['audioFile']) || $_FILES['audioFile']['error'] !== UPLOAD_ERR_OK) {
    header('Location: poem_list.php?error=file_upload');
    exit;
}

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Получаем информацию о фрагменте
    $stmt = $pdo->prepare("
        SELECT f.id, f.label, p.title as poem_title 
        FROM fragments f 
        JOIN poems p ON f.poem_id = p.id 
        WHERE f.id = :fragment_id AND f.deleted_at IS NULL
    ");
    $stmt->execute(['fragment_id' => $fragmentId]);
    $fragment = $stmt->fetch();
    
    if (!$fragment) {
        header('Location: poem_list.php?error=fragment_not_found');
        exit;
    }

    // Получаем существующие озвучки для выпадающего списка порядка
    $stmt = $pdo->prepare("
        SELECT id, title, sort_order 
        FROM audio_tracks 
        WHERE fragment_id = :fragment_id AND deleted_at IS NULL 
        ORDER BY sort_order ASC
    ");
    $stmt->execute(['fragment_id' => $fragmentId]);
    $existingAudios = $stmt->fetchAll();

    // Определяем заголовок фрагмента
    $fragmentTitle = $fragment['label'] ?: $fragment['poem_title'];
    if ($fragment['label'] && $fragment['poem_title']) {
        $fragmentTitle = $fragment['poem_title'] . ' - ' . $fragment['label'];
    }

} catch (PDOException $e) {
    error_log("Database error in add_audio_step1.php: " . $e->getMessage());
    header('Location: poem_list.php?error=database');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить озвучку - Шаг 2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Добавить новую озвучку - Шаг 2</h5>
                        <small class="text-muted">Выберите порядок сортировки для: <?= htmlspecialchars($fragmentTitle) ?></small>
                    </div>
                    <div class="card-body">
                        <form action="add_audio_step2.php" method="POST" enctype="multipart/form-data">
                            <!-- Скрытые поля с данными из предыдущего шага -->
                            <input type="hidden" name="fragmentId" value="<?= $fragmentId ?>">
                            <input type="hidden" name="audioTitle" value="<?= htmlspecialchars($audioTitle) ?>">
                            <input type="hidden" name="voiceType" value="<?= $voiceType ?>">
                            <input type="hidden" name="trimAudio" value="<?= $trimAudio ? '1' : '0' ?>">
                            
                            <!-- Передаем загруженный файл -->
                            <?php
                            // Временно сохраняем файл
                            $uploadDir = __DIR__ . '/uploads/temp/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                            
                            $tempFileName = uniqid('audio_temp_') . '.' . pathinfo($_FILES['audioFile']['name'], PATHINFO_EXTENSION);
                            $tempFilePath = $uploadDir . $tempFileName;
                            
                            if (!move_uploaded_file($_FILES['audioFile']['tmp_name'], $tempFilePath)) {
                                throw new Exception('Не удалось сохранить временный файл');
                            }
                            ?>
                            <input type="hidden" name="tempFileName" value="<?= htmlspecialchars($tempFileName) ?>">
                            <input type="hidden" name="originalFileName" value="<?= htmlspecialchars($_FILES['audioFile']['name']) ?>">

                            <div class="mb-4">
                                <h6>Информация об озвучке:</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Название:</strong> <?= htmlspecialchars($audioTitle) ?></li>
                                    <li><strong>Тип голоса:</strong> <?= $voiceType ? 'ИИ-генерированный' : 'Живой голос' ?></li>
                                    <li><strong>Файл:</strong> <?= htmlspecialchars($_FILES['audioFile']['name']) ?></li>
                                    <li><strong>Обрезка:</strong> <?= $trimAudio ? 'Да' : 'Нет' ?></li>
                                </ul>
                            </div>

                            <div class="mb-3">
                                <label for="sortOrder" class="form-label">Порядок в списке озвучек</label>
                                <select name="sortOrder" id="sortOrder" class="form-select" required>
                                    <option value="1">Добавить первым</option>
                                    <?php foreach ($existingAudios as $audio): ?>
                                        <option value="<?= $audio['sort_order'] + 1 ?>">
                                            После "<?= htmlspecialchars($audio['title']) ?>"
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    Выберите, где в списке озвучек должна располагаться новая запись.
                                </div>
                            </div>

                            <?php if (!empty($existingAudios)): ?>
                            <div class="mb-3">
                                <h6>Текущие озвучки:</h6>
                                <ol class="list-group list-group-numbered">
                                    <?php foreach ($existingAudios as $audio): ?>
                                        <li class="list-group-item"><?= htmlspecialchars($audio['title']) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                            <?php endif; ?>

                            <div class="d-flex gap-2">
                                <a href="poem_list.php" class="btn btn-secondary">Отмена</a>
                                <button type="submit" class="btn btn-primary">
                                    <?= $trimAudio ? 'Далее к обрезке' : 'Сохранить озвучку' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>