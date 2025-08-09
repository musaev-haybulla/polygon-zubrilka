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
        $audioStatuses = $row['audio_statuses'] ? explode('|', $row['audio_statuses']) : [];
        $audioSortOrders = $row['audio_sort_orders'] ? explode('|', $row['audio_sort_orders']) : [];
        
        $audios = [];
        for ($i = 0; $i < $audioCount; $i++) {
            $audios[] = [
                'id' => $audioIds[$i] ?? null,
                'title' => $audioTitles[$i] ?? 'Озвучка',
                'is_ai' => isset($audioTypes[$i]) && $audioTypes[$i] === '1',
                'status' => $audioStatuses[$i] ?? 'draft',
                'sort_order' => (int)($audioSortOrders[$i] ?? $i + 1)
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
    
    /* Стили для статусов озвучек */
    .audio-draft-header {
      font-size: 0.85rem;
      color: #6c757d;
      margin: 12px 0 8px 0;
    }
    
    /* Анимация загрузки */
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
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
              <!-- Активные озвучки -->
              <template x-for="audio in activeAudios" :key="audio.id">
                <div class="voiceover-item">
                  <div>
                    <strong class="d-block" x-text="audio.title"></strong>
                    <div>
                      <span class="badge rounded-pill" :class="audio.is_ai ? 'badge-voice-ai' : 'badge-voice-live'" x-text="audio.is_ai ? 'ИИ' : 'Живой голос'"></span>
                    </div>
                  </div>
                  <div>
                    <button class="icon-btn" title="Редактировать"><i class="bi bi-pencil"></i></button>
                    <button class="icon-btn" @click="deleteAudio(audio.id)" title="Удалить"><i class="bi bi-trash"></i></button>
                  </div>
                </div>
              </template>
              
              <!-- Заголовок черновиков -->
              <template x-if="draftAudios.length > 0">
                <div class="audio-draft-header">Черновики</div>
              </template>
              
              <!-- Черновики -->
              <template x-for="audio in draftAudios" :key="audio.id">
                <div class="voiceover-item">
                  <div>
                    <strong class="d-block" x-text="audio.title"></strong>
                    <div>
                      <span class="badge rounded-pill" :class="audio.is_ai ? 'badge-voice-ai' : 'badge-voice-live'" x-text="audio.is_ai ? 'ИИ' : 'Живой голос'"></span>
                    </div>
                  </div>
                  <div>
                    <button class="icon-btn" @click="editAudio(audio)" title="Редактировать метаинформацию"><i class="bi bi-pencil"></i></button>
                    <a :href="'add_audio_step2.php?id=' + audio.id" class="icon-btn" title="Обрезать аудиофайл"><i class="bi bi-scissors"></i></a>
                    <button class="icon-btn" title="Перейти к разметке"><i class="bi bi-play-circle"></i></button>
                    <button class="icon-btn" @click="deleteAudio(audio.id)" title="Удалить"><i class="bi bi-trash"></i></button>
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
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addAudioModalLabel">Добавить новую озвучку</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addAudioForm" method="POST" enctype="multipart/form-data" onsubmit="return false;">
          <input type="hidden" id="fragmentId" name="fragmentId">
          <input type="hidden" id="audioId" name="audioId">
          <input type="hidden" id="editMode" name="editMode" value="0">
          <input type="hidden" id="confirmed" name="confirmed" value="0">
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
            <label for="audioFile" class="form-label" id="audioFileLabel">Аудиофайл</label>
            <input class="form-control" type="file" id="audioFile" name="audioFile" accept="audio/*" required>
            <div class="form-text" id="audioFileHelp" style="display: none;">
              Оставьте пустым, чтобы сохранить текущий файл. Загрузка нового файла сбросит обрезку и разметку.
            </div>
          </div>
          <div class="mb-3">
            <label for="sortOrder" class="form-label">Порядок в списке озвучек</label>
            <select name="sortOrder" id="sortOrder" class="form-select" required>
              <option value="1">Добавить первым</option>
              <!-- Опции будут добавлены динамически через JavaScript -->
            </select>
            <div class="form-text">
              Выберите, где в списке озвучек должна располагаться новая запись.
            </div>
          </div>
          <div class="mb-3 form-check p-3 rounded">
            <input type="checkbox" class="form-check-input" id="trimAudio" name="trimAudio" value="1">
            <label class="form-check-label" for="trimAudio"><strong>Обрезать аудиофайл?</strong></label>
            <small class="form-text text-muted d-block">Позволит на следующем шаге удалить тишину или лишние фрагменты в начале и конце озвучки.</small>
          </div>
          
          <!-- Прогресс-бар загрузки -->
          <div class="mb-3" id="uploadProgress" style="display: none;">
            <label class="form-label">Загрузка файла</label>
            <div class="progress">
              <div class="progress-bar progress-bar-striped progress-bar-animated" 
                   role="progressbar" 
                   id="uploadProgressBar"
                   style="width: 0%">0%</div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <div id="addModeButtons">
          <button type="submit" form="addAudioForm" class="btn btn-primary" id="addAudioSubmitBtn">Далее</button>
        </div>
        <div id="editModeButtons" style="display: none;">
          <button type="submit" form="addAudioForm" class="btn btn-success" data-action="save">Сохранить</button>
          <button type="submit" form="addAudioForm" class="btn btn-warning" data-action="save-and-trim">Сохранить и обрезать</button>
          <button type="submit" form="addAudioForm" class="btn btn-info" data-action="save-and-markup">Сохранить и разметить</button>
        </div>
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
    
    get draftAudios() {
      return this.audios.filter(audio => audio.status === 'draft');
    },
    
    get activeAudios() {
      return this.audios.filter(audio => audio.status === 'active');
    },
    
    editAudio(audio) {
      // Открываем модалку в режиме редактирования
      const modal = new bootstrap.Modal(document.getElementById('addAudioModal'));
      
      // Переключаем в режим редактирования
      const form = document.getElementById('addAudioForm');
      const modalTitle = document.getElementById('addAudioModalLabel');
      const submitButton = document.querySelector('#addAudioModal button[type="submit"]');
      
      // Обновляем заголовки и переключаем кнопки
      modalTitle.textContent = 'Редактировать озвучку';
      document.getElementById('addModeButtons').style.display = 'none';
      document.getElementById('editModeButtons').style.display = 'block';
      
      // Заполняем форму данными
      document.getElementById('fragmentId').value = this.poem.id;
      document.getElementById('audioId').value = audio.id;
      document.getElementById('editMode').value = '1';
      document.getElementById('audioTitle').value = audio.title;
      
      // Устанавливаем тип голоса
      const voiceType = audio.is_ai ? '1' : '0';
      document.querySelector(`input[name="voiceType"][value="${voiceType}"]`).checked = true;
      
      
      // Делаем файл опциональным и скрываем чекбокс обрезки
      const audioFileInput = document.getElementById('audioFile');
      const audioFileLabel = document.getElementById('audioFileLabel');
      const audioFileHelp = document.getElementById('audioFileHelp');
      const trimAudioContainer = document.getElementById('trimAudio').closest('.mb-3');
      
      audioFileInput.required = false;
      audioFileLabel.textContent = 'Новый аудиофайл (опционально)';
      audioFileHelp.style.display = 'block';
      trimAudioContainer.style.display = 'none';
      
      // Показываем поле сортировки и заполняем его текущими озвучками
      const sortOrderContainer = document.getElementById('sortOrder').closest('.mb-3');
      sortOrderContainer.style.display = 'block';
      
      // Заполняем опции сортировки для редактирования
      const sortOrderSelect = document.getElementById('sortOrder');
      while (sortOrderSelect.children.length > 1) {
        sortOrderSelect.removeChild(sortOrderSelect.lastChild);
      }
      
      // Добавляем опции для других озвучек
      this.audios
        .filter(audioItem => audioItem.id != audio.id) // Исключаем текущую озвучку
        .sort((a, b) => a.sort_order - b.sort_order) // Сортируем по sort_order
        .forEach((audioItem) => {
          const option = document.createElement('option');
          option.value = audioItem.sort_order + 1;
          // Если это позиция, где сейчас стоит редактируемая озвучка
          const isCurrent = audioItem.sort_order + 1 === audio.sort_order;
          option.textContent = `После "${audioItem.title}"${isCurrent ? ' (текущая позиция)' : ''}`;
          sortOrderSelect.appendChild(option);
        });
      
      // Обновляем текст первой опции если это текущая позиция
      if (audio.sort_order === 1) {
        sortOrderSelect.options[0].textContent = 'Добавить первым (текущая позиция)';
        sortOrderSelect.value = '1';
      } else {
        sortOrderSelect.options[0].textContent = 'Добавить первым';
        // Ищем озвучку, после которой стоит текущая
        const prevAudio = this.audios.find(a => a.sort_order === audio.sort_order - 1);
        if (prevAudio) {
          sortOrderSelect.value = audio.sort_order; // Это будет соответствовать опции "После prevAudio"
        } else {
          sortOrderSelect.value = '1'; // Fallback
        }
      }
      
      modal.show();
    },
    
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

// Упрощенный AJAX обработчик с централизованной логикой и отладкой
document.getElementById('addAudioForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const form = e.target;
  const formData = new FormData(form);
  // Если не выбрана обрезка — просим сервер выполнить детекцию пауз синхронно
  const trimAudioChecked = document.getElementById('trimAudio').checked;
  if (!trimAudioChecked) {
    formData.append('detect_pauses', '1');
  }
  const progressContainer = document.getElementById('uploadProgress');
  const progressBar = document.getElementById('uploadProgressBar');
  const submitBtn = document.getElementById('addAudioSubmitBtn');
  const originalText = submitBtn.innerHTML;
  
  // Показываем прогресс
  progressContainer.style.display = 'block';
  submitBtn.style.display = 'none';
  
  // Функция сброса состояния загрузки
  function resetUploadState() {
    setTimeout(() => {
      progressContainer.style.display = 'none';
      submitBtn.style.display = 'inline-block';
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
      progressBar.style.width = '0%';
      progressBar.textContent = '0%';
      progressBar.classList.remove('bg-success', 'bg-danger', 'bg-info');
      progressBar.classList.add('progress-bar-striped', 'progress-bar-animated');
    }, 2000);
  }
  
  function handleError(message) {
    progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
    progressBar.classList.add('bg-danger');
    progressBar.textContent = 'Ошибка загрузки';
    alert(message);
    resetUploadState();
  }
  
  // Простая обработка успешного ответа
  function handleSuccess(audioId, detected, detectError) {
    progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
    
    // Проверяем нужно ли перейти к обрезке
    const trimAudio = document.getElementById('trimAudio').checked;
    
    if (trimAudio && audioId > 0) {
      progressBar.classList.add('bg-info');
      progressBar.textContent = 'Переход к обрезке...';
      
      setTimeout(() => {
        window.location.href = `add_audio_step2.php?id=${audioId}`;
      }, 500);
    } else {
      progressBar.classList.add('bg-success');
      if (detected === false) {
        progressBar.textContent = 'Загрузка завершена (детекция не выполнена)';
        if (detectError) {
          alert('Детекция пауз не выполнена: ' + detectError);
        }
      } else if (detected === true) {
        progressBar.textContent = 'Детекция пауз завершена!';
      } else {
        progressBar.textContent = 'Загрузка завершена!';
      }
      
      setTimeout(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('addAudioModal'));
        modal.hide();
        window.location.reload();
      }, 1000);
    }
  }
  
  // Функция отправки запроса (можем переиспользовать для повторной отправки)
  function sendRequest(requestFormData, isRetry = false) {
    const xhr = new XMLHttpRequest();
    
    // Обработчик прогресса загрузки
    xhr.upload.addEventListener('progress', function(e) {
      if (e.lengthComputable) {
        const percentComplete = Math.round((e.loaded / e.total) * 100);
        progressBar.style.width = percentComplete + '%';
        // Когда загрузка достигла 100%, если включена синхронная детекция, показываем indeterminate фазу
        if (percentComplete >= 100 && !trimAudioChecked && formData.get('detect_pauses') === '1') {
          // Держим полосу на 100%, оставляем анимацию и меняем текст
          progressBar.textContent = 'Определяем паузы…';
          const labelEl = document.querySelector('#uploadProgress label.form-label');
          if (labelEl) labelEl.textContent = 'Обработка файла';
        } else {
          progressBar.textContent = percentComplete + '%';
        }
      }
    });
    
    // Обработчик завершения
    xhr.onload = function() {
      if (xhr.status === 200) {
        try {
          // Логируем сырой ответ для отладки
          console.log('Raw server response:', xhr.responseText);
          const response = JSON.parse(xhr.responseText);
          
          if (response.warning) {
            const confirmed = confirm(response.warning);
            
            if (confirmed) {
              document.getElementById('confirmed').value = '1';
              progressBar.style.width = '0%';
              progressBar.textContent = '0%';
              
              const retryFormData = new FormData(form);
              sendRequest(retryFormData, true);
            } else {
              resetUploadState();
            }
            return;
          }
          
          // Успешная загрузка
          if (response.success) {
            const audioId = response.audio_id;
            handleSuccess(audioId, response.detected, response.detect_error);
          } else {
            const errorMessage = response.error || 'Неожиданный ответ сервера';
            handleError(errorMessage);
          }
          
        } catch (e) {
          console.error('JSON parse error:', e);
          console.error('Response was:', xhr.responseText);
          
          // Попробуем извлечь полезную информацию из ответа
          let errorMessage = 'Ошибка парсинга ответа сервера';
          if (xhr.responseText) {
            // Если ответ содержит HTML с ошибкой PHP, попробуем найти полезную информацию
            if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
              errorMessage = 'Ошибка сервера. Проверьте логи для подробностей.';
            } else if (xhr.responseText.length < 200) {
              // Если ответ короткий, возможно это полезная информация
              errorMessage = 'Ошибка сервера: ' + xhr.responseText.substring(0, 100);
            }
          }
          
          handleError(errorMessage);
        }
        
      } else {
        handleError('Ошибка HTTP: ' + xhr.status);
      }
    };
    
    // Обработчик ошибки сети
    xhr.onerror = () => {
      console.error('Network error occurred');
      handleError('Произошла ошибка сети при загрузке файла');
    };
    
    // Отправляем запрос
    xhr.open('POST', 'add_audio_step1.php');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.send(requestFormData);
  }
  
  // Отправляем первоначальный запрос
  sendRequest(formData)
});


const addAudioModal = document.getElementById('addAudioModal');
if (addAudioModal) {
    addAudioModal.addEventListener('show.bs.modal', event => {
      const button = event.relatedTarget;
      
      // Проверяем, если модалка уже настроена для редактирования
      if (document.getElementById('editMode').value === '1') {
        return; // Не сбрасываем, модалка уже настроена
      }
      
      // Режим добавления - настраиваем форму
      const modalTitle = document.getElementById('addAudioModalLabel');
      const submitButton = document.querySelector('#addAudioModal button[type="submit"]');
      const audioFileInput = document.getElementById('audioFile');
      const audioFileLabel = document.getElementById('audioFileLabel');
      const audioFileHelp = document.getElementById('audioFileHelp');
      
      modalTitle.textContent = 'Добавить новую озвучку';
      document.getElementById('addModeButtons').style.display = 'block';
      document.getElementById('editModeButtons').style.display = 'none';
      audioFileInput.required = true;
      audioFileLabel.textContent = 'Аудиофайл';
      audioFileHelp.style.display = 'none';
      
      // Показываем чекбокс обрезки для режима добавления
      const trimAudioContainer = document.getElementById('trimAudio').closest('.mb-3');
      trimAudioContainer.style.display = 'block';
      
      // Показываем поле сортировки
      document.getElementById('sortOrder').closest('.mb-3').style.display = 'block';
      
      // Сбрасываем скрытые поля
      document.getElementById('audioId').value = '';
      document.getElementById('editMode').value = '0';
      
      const fragmentId = button.getAttribute('data-bs-fragment-id');
      const modalInput = addAudioModal.querySelector('#fragmentId');
      modalInput.value = fragmentId;
      
      // Находим данные стихотворения по fragmentId
      const poemCard = button.closest('[x-data]');
      if (poemCard && poemCard._x_dataStack) {
        const poemData = poemCard._x_dataStack[0];
        const sortOrderSelect = addAudioModal.querySelector('#sortOrder');
        
        // Очищаем существующие опции (кроме "Добавить первым")
        while (sortOrderSelect.children.length > 1) {
          sortOrderSelect.removeChild(sortOrderSelect.lastChild);
        }
        
        // Добавляем опции для каждой существующей озвучки
        if (poemData.audios && poemData.audios.length > 0) {
          poemData.audios.forEach((audio, index) => {
            const option = document.createElement('option');
            option.value = audio.sort_order + 1; // Используем реальный sort_order + 1
            option.textContent = `После "${audio.title}"`;
            sortOrderSelect.appendChild(option);
          });
        }
      }
    });
    
    // Сбрасываем режим редактирования при закрытии модалки
    addAudioModal.addEventListener('hidden.bs.modal', event => {
      document.getElementById('editMode').value = '0';
      document.getElementById('confirmed').value = '0';
      document.getElementById('addAudioForm').reset();
      
      // Показываем все скрытые поля обратно
      document.getElementById('audioTitle').closest('.mb-3').style.display = 'block';
      document.querySelector('input[name="voiceType"]').closest('.mb-3').style.display = 'block';
      document.getElementById('sortOrder').closest('.mb-3').style.display = 'block';
      document.getElementById('trimAudio').closest('.mb-3').style.display = 'block';
      
      // Скрываем прогресс-бар и возвращаем кнопки
      document.getElementById('uploadProgress').style.display = 'none';
      const submitBtn = document.getElementById('addAudioSubmitBtn');
      const editButtons = document.getElementById('editModeButtons');
      submitBtn.style.display = 'inline-block';
      submitBtn.disabled = false;
      editButtons.style.display = 'block';
      
      // Сбрасываем прогресс-бар
      const progressBar = document.getElementById('uploadProgressBar');
      progressBar.style.width = '0%';
      progressBar.textContent = '0%';
      progressBar.classList.remove('bg-success', 'bg-danger');
      progressBar.classList.add('progress-bar-striped', 'progress-bar-animated');
      
      // Возвращаем оригинальный текст кнопки
      document.querySelector('#addModeButtons button').textContent = 'Далее';
    });
    
    // Обработчики для кнопок редактирования
    document.getElementById('editModeButtons').addEventListener('click', function(e) {
      if (e.target.type === 'submit') {
        e.preventDefault();
        const action = e.target.dataset.action;
        const form = document.getElementById('addAudioForm');
        const formData = new FormData(form);
        const audioId = formData.get('audioId');
        
        // Устанавливаем нужное действие
        formData.set('editAction', action);
        
        // Показываем прогресс-бар и скрываем кнопки редактирования
        const progressContainer = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('uploadProgressBar');
        const editButtons = document.getElementById('editModeButtons');
        
        progressContainer.style.display = 'block';
        editButtons.style.display = 'none';
        
        // Создаем XMLHttpRequest для отслеживания прогресса
        const xhr = new XMLHttpRequest();
        
        // Обработчик прогресса загрузки (если есть файл)
        xhr.upload.addEventListener('progress', function(e) {
          if (e.lengthComputable) {
            const percentComplete = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percentComplete + '%';
            progressBar.textContent = percentComplete + '%';
          }
        });
        
        // Обработчик завершения
        xhr.onload = function() {
          if (xhr.status === 200) {
            // Успешное сохранение
            progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
            progressBar.classList.add('bg-success');
            progressBar.textContent = 'Сохранение завершено!';
            
            setTimeout(() => {
              if (action === 'save') {
                // Обычное сохранение - обновляем интерфейс и закрываем модалку
                const cards = document.querySelectorAll('[x-data]');
                cards.forEach(card => {
                  if (card._x_dataStack) {
                    const poemData = card._x_dataStack[0];
                    if (poemData && poemData.audios) {
                      const audioIndex = poemData.audios.findIndex(a => a.id == audioId);
                      if (audioIndex !== -1) {
                        poemData.audios[audioIndex].title = formData.get('audioTitle');
                        poemData.audios[audioIndex].is_ai = formData.get('voiceType') === '1';
                        poemData.audios[audioIndex].sort_order = parseInt(formData.get('sortOrder'));
                        poemData.audios.sort((a, b) => a.sort_order - b.sort_order);
                      }
                    }
                  }
                });
                const modal = bootstrap.Modal.getInstance(document.getElementById('addAudioModal'));
                modal.hide();
              } else if (action === 'save-and-trim') {
                // Переход на страницу обрезки
                window.location.href = `add_audio_step2.php?id=${audioId}`;
              } else if (action === 'save-and-markup') {
                // Переход на страницу разметки
                window.location.href = `add_audio_step3.php?id=${audioId}`;
              }
            }, 1000);
          } else {
            // Ошибка сохранения
            progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
            progressBar.classList.add('bg-danger');
            progressBar.textContent = 'Ошибка сохранения';
            alert('Ошибка при сохранении изменений');
            resetEditState();
          }
        };
        
        // Обработчик ошибки сети
        xhr.onerror = function() {
          progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
          progressBar.classList.add('bg-danger');
          progressBar.textContent = 'Ошибка сети';
          alert('Произошла ошибка сети');
          resetEditState();
        };
        
        // Функция сброса состояния редактирования
        function resetEditState() {
          setTimeout(() => {
            progressContainer.style.display = 'none';
            editButtons.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            progressBar.classList.remove('bg-success', 'bg-danger');
            progressBar.classList.add('progress-bar-striped', 'progress-bar-animated');
          }, 2000);
        }
        
        // Отправляем запрос
        xhr.open('POST', 'add_audio_step1.php');
        xhr.send(formData);
      }
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
