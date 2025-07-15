<?php
/**
 * Страница управления озвучками стихотворений
 */
declare(strict_types=1);

// Подключаем конфигурацию и классы
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/autoload.php';
require __DIR__ . '/config/poem_size_config.php';

// Настройка отображения ошибок для разработки
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

try {
    // Получаем все фрагменты (отдельные стихи) с их данными
    $query = new FragmentQuery();
    $results = $query
        ->withPoems()
        ->withAuthors() 
        ->withLines()
        ->withAudio()
        ->withSortingFields()
        ->get();
    
    // Обрабатываем результаты - каждый фрагмент как отдельный стих
    $poems = [];
    foreach ($results as $row) {
        $audioCount = (int)$row['audio_count'];
        
        // Определяем заголовок стиха
        $poemTitle = $row['label'] ?: $row['poem_title'];
        if ($row['label'] && $row['poem_title']) {
            $poemTitle = $row['poem_title'] . ' - ' . $row['label'];
        }
        
        // Получаем количество строк из базы данных
        $lineCount = (int)($row['line_count'] ?? 0);
        
        // Определяем размер стихотворения
        $poemSize = getPoemSize($lineCount);

        // Собираем структурированный массив озвучек
        $audioIds = $row['audio_ids'] ? explode('|', $row['audio_ids']) : [];
        $audioTitles = $row['audio_titles'] ? explode('|', $row['audio_titles']) : [];
        $audioTypes = $row['audio_types'] ? explode('|', $row['audio_types']) : [];
        
        $audios = [];
        for ($i = 0; $i < $audioCount; $i++) {
            $audios[] = [
                'id' => $audioIds[$i] ?? null,
                'title' => $audioTitles[$i] ?? 'Озвучка',
                'is_ai' => isset($audioTypes[$i]) && $audioTypes[$i] === '1'
            ];
        }
        
        $poems[] = [
            'id' => $row['fragment_id'],
            'title' => $poemTitle,
            'poem_title' => $row['poem_title'],
            'fragment_label' => $row['label'],
            'year_written' => $row['year_written'],
            'authors' => $row['authors'] ?? 'Неизвестный автор',
            'grade_level' => $row['grade_level'],
            'sort_order' => $row['sort_order'],
            'text' => $row['fragment_text'],
            'line_count' => $lineCount,
            'size' => $poemSize,
            'has_audio' => $audioCount > 0,
            'audios' => $audios
        ];
    }
    
} catch (PDOException $e) {
    // В случае ошибки показываем пустой массив
    $poems = [];
    if (APP_ENV === 'development') {
        echo "<!-- Ошибка БД: " . htmlspecialchars($e->getMessage()) . " -->";
    }
}

// Функция для получения класса бейджа возрастной группы
function getGradeClass($grade) {
    switch ($grade) {
        case 'primary': return 'bg-info text-dark';
        case 'middle': return 'bg-secondary';
        case 'secondary': return 'bg-primary';
        default: return 'bg-light text-dark';
    }
}

// Функция для получения названия возрастной группы
function getGradeName($grade) {
    switch ($grade) {
        case 'primary': return 'Младшие';
        case 'middle': return 'Средние';
        case 'secondary': return 'Старшие';
        default: return 'Не указано';
    }
}

// Функция для получения значения фильтра возрастной группы
function getGradeFilterValue($grade) {
    switch ($grade) {
        case 'primary': return 'young';
        case 'middle': return 'middle';
        case 'secondary': return 'senior';
        default: return 'all';
    }
}

// Функция для получения класса бейджа размера стихотворения
function getPoemSizeClass($size) {
    switch ($size) {
        case 'short': return 'bg-success text-white';
        case 'medium': return 'bg-warning text-dark';
        case 'large': return 'bg-danger text-white';
        default: return 'bg-light text-dark';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Управление озвучками стихотворений</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    body {
      background-color: #f5f5f5;
    }
    .flat-card {
      background: white;
      border: none;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-bottom: 16px;
      display: flex;
      padding: 0;
    }
    .poem-content {
      padding: 20px;
      flex-grow: 1;
    }
    .voiceover-panel {
      flex-shrink: 0;
      width: 350px;
      padding: 20px;
    }
    .voiceover-panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }
    .voiceover-item {
      border-radius: 6px;
      padding: 10px 0;
      margin-bottom: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .voiceover-item strong {
        font-weight: normal;
        font-size: 0.9rem;
    }
    .filter-section {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      padding: 20px;
      margin-bottom: 24px;
    }
    .poem-text {
      color: #495057;
    }
    .icon-btn {
      background: none;
      border: none;
      color: #6c757d;
      padding: 4px 8px;
      border-radius: 50%;
      transition: background-color 0.2s, color 0.2s;
    }
    .icon-btn:hover {
      background-color: #e9ecef;
      color: #212529;
    }
    .icon-btn.add-btn {
      background-color: #e9ecef;
    }
    .icon-btn.add-btn:hover {
      background-color: #d1d5db;
    }
    .badge-gender-female { background-color: #fce4ec; color: #880e4f; }
    .badge-gender-male { background-color: #e3f2fd; color: #0d47a1; }
    .badge-voice-live { background-color: #e8f5e9; color: #1b5e20; }
    .badge-voice-ai { background-color: #f3e5f5; color: #4a148c; }
  </style>
</head>
<body>
<div class="container py-4" x-data="filterData()">
  <h2 class="fw-bold mb-1">Управление озвучками стихотворений</h2>
  <p class="text-muted mb-4">Управляйте и организуйте озвучки стихотворений</p>

  <!-- Фильтры -->
  <div class="filter-section">
    <form class="row g-3 align-items-center">
      <div class="col-md-3">
        <label class="form-label">Возрастная группа</label>
        <select class="form-select" x-model="selectedAgeGroup">
          <option value="all">Все возрасты</option>
          <option value="young">Младшие</option>
          <option value="middle">Средние</option>
          <option value="senior">Старшие</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Размер</label>
        <select class="form-select" x-model="selectedSize">
          <option value="all">Все размеры</option>
          <option value="large">Крупные</option>
          <option value="medium">Средние</option>
          <option value="short">Короткие</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Без озвучки</label>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" x-model="showOnlyWithoutVoiceovers">
          <label class="form-check-label">Только без озвучки</label>
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label">&nbsp;</label>
        <div>
          <button type="button" class="btn btn-outline-secondary btn-sm" @click="resetFilters()">
            Сбросить фильтры
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- Список стихотворений -->
  <div class="row g-0">
    <?php foreach ($poems as $poem): ?>
    <div class="col-12" 
         data-has-voiceovers="<?= $poem['has_audio'] ? 'true' : 'false' ?>" 
         data-age-group="<?= htmlspecialchars(getGradeFilterValue($poem['grade_level'])) ?>" 
         data-size="<?= htmlspecialchars($poem['size']) ?>" 
         x-show="isVisible($el)">
      <div class="flat-card" x-data='poemCard(<?= json_encode($poem, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
        <div class="poem-content">
          <h5 class="mb-1"><?= htmlspecialchars($poem['title']) ?></h5>
          <p class="text-muted mb-2"><?= htmlspecialchars($poem['authors']) ?></p>
          <div class="mb-3">
            <span class="badge <?= getGradeClass($poem['grade_level']) ?>">
              <?= getGradeName($poem['grade_level']) ?>
            </span>
            <span class="badge <?= getPoemSizeClass($poem['size']) ?>"><?= htmlspecialchars(getPoemSizeLabel($poem['size'])) ?></span>
          </div>
          <div class="poem-text fst-italic">
            <?php 
            // Показываем текст фрагмента (стиха)
            if ($poem['text']): 
              $lines = explode('<br>', $poem['text']);
              $firstLines = array_slice($lines, 0, 2);
              $remainingLines = array_slice($lines, 2);
            ?>
            <p><?= implode('<br>', $firstLines) ?></p>
            <?php if (!empty($remainingLines)): ?>
            <div x-show="expanded" x-collapse.duration.500ms>
              <p><?= implode('<br>', $remainingLines) ?></p>
            </div>
            <button @click="expanded = !expanded" class="btn btn-link p-0" x-text="expanded ? 'Свернуть' : 'Развернуть полностью'"></button>
            <?php endif; ?>
            <?php else: ?>
            <p class="text-muted">Текст не найден</p>
            <?php endif; ?>
          </div>
        </div>
        <div class="voiceover-panel">
          <div class="voiceover-panel-header">
            <h6 class="mb-0 text-muted">Озвучки (<span x-text="audios.length"></span>)</h6>
            <button class="icon-btn add-btn" 
                    data-bs-toggle="modal" 
                    data-bs-target="#addAudioModal" 
                    :data-bs-fragment-id="poem.id">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
          
          <template x-if="audios.length > 0">
            <div>
              <template x-for="audio in audios" :key="audio.id">
                <div class="voiceover-item">
                  <div>
                    <strong class="d-block" x-text="audio.title"></strong>
                    <div>
                      <span class="badge rounded-pill me-1 badge-gender-male">Муж.</span>
                      <span class="badge rounded-pill" :class="audio.is_ai ? 'badge-voice-ai' : 'badge-voice-live'" x-text="audio.is_ai ? 'ИИ' : 'Живой голос'"></span>
                    </div>
                  </div>
                  <div>
                    <button class="icon-btn"><i class="bi bi-pencil"></i></button>
                    <button class="icon-btn" @click="deleteAudio(audio.id)"><i class="bi bi-trash"></i></button>
                  </div>
                </div>
              </template>
            </div>
          </template>

          <template x-if="audios.length === 0">
            <div class="text-muted text-center pt-3">Нет озвучек</div>
          </template>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Модальное окно для добавления озвучки -->
<div class="modal fade" id="addAudioModal" tabindex="-1" aria-labelledby="addAudioModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addAudioModalLabel">Добавить новую озвучку</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addAudioForm" action="add_audio_step1.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" id="fragmentId" name="fragmentId">
          <div class="mb-3">
            <label for="audioTitle" class="form-label">Название озвучки</label>
            <input type="text" class="form-control" id="audioTitle" name="audioTitle" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Тип голоса</label>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="voiceType" id="liveVoice" value="0" checked>
              <label class="form-check-label" for="liveVoice">Живой голос</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="voiceType" id="aiVoice" value="1">
              <label class="form-check-label" for="aiVoice">ИИ-генерированный</label>
            </div>
          </div>
          <div class="mb-3">
            <label for="audioFile" class="form-label">Аудиофайл</label>
            <input class="form-control" type="file" id="audioFile" name="audioFile" accept="audio/*" required>
          </div>
          <div class="mb-3 form-check bg-light p-3 rounded">
            <input type="checkbox" class="form-check-input" id="trimAudio" name="trimAudio" value="1">
            <label class="form-check-label" for="trimAudio"><strong>Обрезать аудиофайл?</strong></label>
            <small class="form-text text-muted d-block">Позволит на следующем шаге удалить тишину или лишние фрагменты в начале и конце озвучки.</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" form="addAudioForm" class="btn btn-primary">Далее</button>
      </div>
    </div>
  </div>
</div>

<script>
function poemCard(poemData) {
  return {
    poem: poemData,
    audios: poemData.audios,
    expanded: false,
    deleteAudio(audioId) {
      if (!audioId) {
        console.error('Не найден ID озвучки');
        return;
      }

      if (confirm('Вы уверены, что хотите удалить эту озвучку?')) {
        fetch('delete_audio.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: audioId })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const index = this.audios.findIndex(a => a.id == audioId);
            if (index > -1) {
              this.audios.splice(index, 1);
            }
            this.$el.closest('.col-12').dataset.hasVoiceovers = this.audios.length > 0 ? 'true' : 'false';
          } else {
            alert('Ошибка при удалении: ' + data.error);
          }
        })
        .catch(error => {
          console.error('Ошибка сети:', error);
          alert('Произошла ошибка сети. Пожалуйста, попробуйте еще раз.');
        });
      }
    }
  }
}

function filterData() {
  return {
    showOnlyWithoutVoiceovers: false,
    selectedAgeGroup: 'all',
    selectedSize: 'all',

    isVisible(element) {
      const hasVoiceovers = element.dataset.hasVoiceovers === 'true';
      const ageGroup = element.dataset.ageGroup;
      const size = element.dataset.size;

      if (this.showOnlyWithoutVoiceovers && hasVoiceovers) return false;
      if (this.selectedAgeGroup !== 'all' && ageGroup !== this.selectedAgeGroup) return false;
      if (this.selectedSize !== 'all' && size !== this.selectedSize) return false;

      return true;
    },

    resetFilters() {
      this.showOnlyWithoutVoiceovers = false;
      this.selectedAgeGroup = 'all';
      this.selectedSize = 'all';
    }
  }
}

const addAudioModal = document.getElementById('addAudioModal');
if (addAudioModal) {
    addAudioModal.addEventListener('show.bs.modal', event => {
      const button = event.relatedTarget;
      const fragmentId = button.getAttribute('data-bs-fragment-id');
      const modalInput = addAudioModal.querySelector('#fragmentId');
      modalInput.value = fragmentId;
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
