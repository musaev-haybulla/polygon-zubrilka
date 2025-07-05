<?php
declare(strict_types=1);

// Подключаем конфигурацию и классы
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/autoload.php';

// Настройка отображения ошибок для разработки
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

try {
    // Получаем список авторов
    $authors = DatabaseHelper::getAllAuthors();

    // Получаем список существующих разделенных стихотворений
    $poems = DatabaseHelper::getDividedPoems();
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Инициализируем пустой массив фрагментов
$fragments = [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить фрагмент стихотворения</title>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.default.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2/dist/js/tom-select.complete.min.js"></script>
  <script src="poem_utils.js" defer></script>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-3xl mx-auto bg-white p-6 rounded shadow" x-data="formData()">
    <h1 class="text-2xl mb-4">Добавить фрагмент стихотворения</h1>
    <div class="mb-6 p-2 bg-blue-50 rounded">
      <a href="search.html" class="text-blue-600 hover:underline">Поиск</a>
      <span class="mx-2">|</span>
      <a href="add_simple_poem.php" class="text-blue-600 hover:underline">Добавить простой стих</a>
    </div>

    <?php if (!empty($_GET['success'])): ?>
        <div id="msg" class="p-4 mb-4 text-green-800 bg-green-200 rounded">
            Фрагмент успешно добавлен!
        </div>
        <script>
            setTimeout(() => {
                const e = document.getElementById("msg");
                if (e) e.style.display = "none";
            }, 3000);
        </script>
    <?php endif; ?>

    <form action="process_poem.php" method="post" class="space-y-4">
        <!-- Выбор или создание стихотворения -->
        <div class="space-y-4 p-4 border rounded">
            <h2 class="text-lg font-semibold">Стихотворение</h2>
            
            <div>
                <label class="block mb-1">Название стихотворения</label>
                <select id="poem-select" x-model="poemTitle" @change="handlePoemSelect()" class="w-full" autocomplete="off">
                    <option value="">Введите или выберите название</option>
                    <?php foreach ($poems as $poem): ?>
                        <option value="<?= htmlspecialchars($poem['title']) ?>" data-poem-id="<?= (int)$poem['id'] ?>">
                            <?= htmlspecialchars($poem['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="poem_id" x-model="selectedPoemId">
            </div>

            <div x-show="!selectedPoemId" class="space-y-4 mt-4 p-4 bg-gray-50 rounded">
                <input type="hidden" name="title" x-model="poemTitle">
                <div>
                    <label class="block mb-1">Авторы</label>
                    <select id="author-select" name="author_ids[]" x-model="authorIds" @change="validate()" multiple class="w-full border rounded px-3 py-2">
                        <?php foreach ($authors as $a): ?>
                            <option value="<?= htmlspecialchars((string)$a['id']) ?>">
                                <?= htmlspecialchars($a['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="is_divided" value="1">
            </div>
        </div>

        <!-- Поля фрагмента -->
        <div class="space-y-4 p-4 border rounded">
            <h2 class="text-lg font-semibold">Фрагмент</h2>
            
            <div>
                <label class="block mb-1">Название фрагмента (необязательно)</label>
                <input type="text" name="label" class="w-full border rounded px-3 py-2" 
                       placeholder="Например: Глава 1, Часть 2">
            </div>

            <div>
                <label class="block mb-1">Структурная информация (необязательно)</label>
                <input type="text" name="structure_info" class="w-full border rounded px-3 py-2" 
                       placeholder="Например: Экспозиция, Кульминация">
            </div>

            <div>
                <label class="block mb-1">Грейд (Школа)</label>
                <select name="grade_level" x-model="fragmentGrade" @change="validate()" class="w-full border rounded px-3 py-2">
                    <option value="primary">Начальная</option>
                    <option value="middle">Средняя</option>
                    <option value="secondary" selected>Старшая</option>
                </select>
            </div>

            <div>
                <label class="block mb-1">Порядок вставки</label>
                <select name="sort_order" x-model="selectedSortOrder" class="w-full border rounded px-3 py-2">
                    <option value="1">Сделать первым</option>
                    <template x-for="fragment in fragments" :key="fragment.id">
                        <option :value="parseInt(fragment.sort_order) + 1" x-text="'После ' + getFragmentLabel(fragment)"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="block mb-1">Текст фрагмента</label>
                <textarea name="poem_text" rows="8" required
                          class="w-full border rounded px-3 py-2" 
                          x-on:input="checkText()"
                          placeholder="Введите текст фрагмента, разделяя строфы пустой строкой"></textarea>
                          <div class="mt-2 flex space-x-2">
                            <button type="button" onclick="setTextAreaValue('poem_text', splitIntoStanzas(document.querySelector('textarea[name=\'poem_text\']').value, 4))" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">4</button>
                            <button type="button" onclick="setTextAreaValue('poem_text', splitIntoStanzas(document.querySelector('textarea[name=\'poem_text\']').value, 5))" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">5</button>
                            <button type="button" onclick="setTextAreaValue('poem_text', splitIntoStanzas(document.querySelector('textarea[name=\'poem_text\']').value, 6))" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">6</button>
                            <button type="button" onclick="setTextAreaValue('poem_text', splitIntoStanzas(document.querySelector('textarea[name=\'poem_text\']').value, 8))" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">8</button>
                            <button type="button" onclick="setTextAreaValue('poem_text', splitIntoStanzas(document.querySelector('textarea[name=\'poem_text\']').value, 10))" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">10</button>
                          </div>
            </div>
        </div>

        <!-- poem_id передается через скрытое поле выше -->
        
        <div class="mt-4 p-2 bg-gray-100 rounded text-sm text-gray-600" x-text="debugInfo"></div>
        <button type="submit" :disabled="!canSubmit"
                class="w-full bg-blue-600 text-white rounded py-2 px-4 hover:bg-blue-700 disabled:opacity-50 mt-4">
            Сохранить фрагмент
        </button>
    </form>
</div>

<script>
// Регистрируем компонент в Alpine.js
document.addEventListener('alpine:init', () => {
    Alpine.data('formData', () => ({
        poemTitle: '',
        authorIds: [],
        canSubmit: false,
        debugInfo: '',
        fragments: [],
        selectedPoemId: 0,
        selectedSortOrder: 1, // Значение по умолчанию для порядка вставки
        fragmentGrade: 'secondary',
        
        async init() {
            // Ждем загрузки DOM и скриптов
            await new Promise(resolve => {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', resolve);
                } else {
                    resolve();
                }
            });

            // Проверяем, что TomSelect загружен
            if (typeof TomSelect === 'undefined') {
                console.error('TomSelect не загружен');
                return;
            }

            // Инициализация TomSelect для авторов
            this.authorSelect = new TomSelect('#author-select', {
                plugins: ['remove_button'],
                create: true,
                persist: false,
                onItemAdd: () => {
                    this.authorSelect.setTextboxValue('');
                    this.validate();
                },
                onItemRemove: () => this.validate()
            });

            // Инициализация TomSelect для выбора стихотворения
            this.poemSelect = new TomSelect('#poem-select', {
                create: true, // Разрешаем создание новых элементов
                createOnBlur: true, // Создаем элемент при потере фокуса
                persist: false, // Не сохранять созданные элементы
                sortField: {
                    field: 'text',
                    direction: 'asc'
                },
                render: {
                    option: function(data, escape) {
                        // Показываем только существующие элементы
                        if (data.value) {
                            return '<div>' + escape(data.text) + '</div>';
                        }
                        return '';
                    },
                    item: function(item, escape) {
                        // Позволяем вводить произвольный текст
                        return '<div>' + escape(item.text) + '</div>';
                    }
                },
                // Обработчики событий
                onChange: (value) => {
                    this.poemTitle = value;
                    this.handlePoemSelect();
                },
                onItemAdd: (value) => {
                    this.poemTitle = value;
                    this.handlePoemSelect();
                }
            });
            
            this.validate();
        },
        
        handlePoemSelect() {
            const selectElement = document.querySelector('#poem-select');
            const selectedOption = selectElement.options[selectElement.selectedIndex];

            this.fragments = []; // Всегда сбрасываем фрагменты при изменении

            if (selectedOption && selectedOption.dataset.poemId) {
                this.selectedPoemId = parseInt(selectedOption.dataset.poemId, 10);

                fetch(`get_fragments.php?poem_id=${this.selectedPoemId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.success && Array.isArray(data.data)) {
                            this.fragments = data.data;
                        } else {
                            this.fragments = [];
                        }
                        this.updateFragmentOrder();
                        this.validate();
                    })
                    .catch(error => {
                        console.error('Ошибка при загрузке фрагментов:', error);
                        this.fragments = [];
                        this.updateFragmentOrder();
                        this.validate();
                    });
            } else {
                // Обработка нового стихотворения или сброса выбора
                this.selectedPoemId = 0;
                this.updateFragmentOrder();
                this.validate();
            }
        },
        
        escapeHtml(str) {
            if (typeof str !== 'string') return '';
            return str
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        },

        // Получает отображаемое название для фрагмента
        getFragmentLabel(fragment) {
            if (fragment.label) {
                return '"' + this.escapeHtml(fragment.label) + '"';
            }
            if (fragment.first_line) {
                return '"' + this.escapeHtml(fragment.first_line.replace(/[.,!?;:]+$/u, '') + '...') + '"';
            }
            return '"Фрагмент ' + (fragment.sort_order + 1) + '"';
        },
        
        // Обновляет порядок вставки по умолчанию
        updateFragmentOrder() {
            this.$nextTick(() => {
                if (this.fragments && this.fragments.length > 0) {
                    // По умолчанию — вставить после последнего фрагмента
                    const maxSortOrder = Math.max(...this.fragments.map(f => parseInt(f.sort_order)));
                    this.selectedSortOrder = maxSortOrder + 1;
                } else {
                    // Если фрагментов нет, по умолчанию — сделать первым
                    this.selectedSortOrder = 1;
                }
            });
        },
        
        validate() {
            const poemText = document.querySelector('textarea[name="poem_text"]')?.value.trim() || '';
            
            if (this.selectedPoemId > 0) {
                // Выбрано существующее стихотворение
                this.canSubmit = poemText !== '';
            } else {
                // Вводится новое стихотворение
                this.canSubmit = this.poemTitle.trim() !== '' && poemText !== '';
            }
            
            // Обновляем отладочную информацию
            this.debugInfo = `poemTitle: "${this.poemTitle}", selectedPoemId: ${this.selectedPoemId}, poemText: ${poemText ? 'заполнен' : 'пуст'}, canSubmit: ${this.canSubmit}`;
            
            // Принудительно обновляем состояние кнопки
            const button = document.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = !this.canSubmit;
            }
        },
        
        // Вызываем валидацию при изменении текста
        checkText() {
            this.validate();
        },
        
        async fetchFragments() {
            if (!this.selectedPoemId) {
                this.fragments = [];
                return;
            }
            
            try {
                const response = await fetch(`get_fragments.php?poem_id=${this.selectedPoemId}`);
                if (!response.ok) throw new Error('Ошибка загрузки фрагментов');
                this.fragments = await response.json();
            } catch (error) {
                console.error('Ошибка:', error);
                alert('Не удалось загрузить фрагменты');
                this.fragments = [];
            }
        }
    }));
});
</script>
</body>
</html>
