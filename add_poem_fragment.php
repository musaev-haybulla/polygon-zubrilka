<?php
declare(strict_types=1);
require 'config.php';
$pdo = getPdo();

// Получаем список авторов
$authors = $pdo->query(
    "SELECT id, CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name 
     FROM authors 
     ORDER BY full_name"
)->fetchAll();

// Получаем список существующих разделенных стихотворений
$poems = $pdo->query(
    "SELECT id, title 
     FROM poems 
     WHERE is_divided = 1 
     ORDER BY title"
)->fetchAll();

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
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-3xl mx-auto bg-white p-6 rounded shadow" x-data="formData()">
    <h1 class="text-2xl mb-4">Добавить фрагмент стихотворения</h1>
    <div class="mb-6 p-2 bg-blue-50 rounded">
      <a href="add_simple_poem.php" class="text-blue-600 hover:underline">Добавить простой стих →</a>
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
                    <option value="middle" selected>Средняя</option>
                    <option value="secondary">Старшая</option>
                </select>
            </div>

            <div>
                <label class="block mb-1">Порядок вставки</label>
                <select name="sort_order" class="w-full border rounded px-3 py-2" x-html="sortOrderOptions">
                    <option value="0">Сделать первым</option>
                    <?php foreach ($fragments as $fragment): ?>
                        <option value="<?= $fragment['sort_order'] + 1 ?>">
                            После "<?= htmlspecialchars(
                                $fragment['label']
                                ?: preg_replace('/[.,!?;:]+$/u', '', $fragment['first_line']) . '...'
                            ) ?>"
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block mb-1">Текст фрагмента</label>
                <textarea name="poem_text" rows="10" required 
                          class="w-full border rounded px-3 py-2"
                          x-on:input="checkText()"
                          placeholder="Введите текст фрагмента, разделяя строфы пустой строкой"></textarea>
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
        fragments: <?= json_encode($fragments) ?>,
        selectedPoemId: 0,  // Всегда начинаем с 0, так как больше не используем poem_id из URL
        fragmentGrade: 'middle',
        
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
            
            if (selectedOption && selectedOption.dataset.poemId) {
                // Выбрано существующее стихотворение
                this.selectedPoemId = parseInt(selectedOption.dataset.poemId);
                this.newPoemTitle = ''; // Сбрасываем новое название
                this.fetchFragments();
            } else if (this.poemTitle) {
                // Введено новое название
                this.selectedPoemId = 0;
                this.fragments = [];
            } else {
                // Ничего не выбрано
                this.selectedPoemId = 0;
                this.newPoemTitle = '';
                this.fragments = [];
            }
            
            this.validate();
        },
        
        onPoemSelect(event) {
            if (!event || !event.target) return;
            
            const poemId = event.target.value || '';
            this.selectedPoemId = parseInt(poemId) || 0;
            
            if (this.selectedPoemId) {
                // Загружаем фрагменты для выбранного стихотворения
                fetch(`get_fragments.php?poem_id=${this.selectedPoemId}`)
                    .then(response => response.json())
                    .then(data => {
                        this.fragments = data || [];
                        this.validate();
                    })
                    .catch(error => {
                        console.error('Ошибка при загрузке фрагментов:', error);
                        this.fragments = [];
                        this.validate();
                    });
            } else {
                this.fragments = [];
                this.validate();
            }
        },
        
        validate() {
            const poemText = document.querySelector('textarea[name="poem_text"]').value.trim();
            
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
        
        get sortOrderOptions() {
            if (!this.fragments || this.fragments.length === 0) {
                return '<option value="0">Сделать первым</option>';
            }
            
            let options = '<option value="0">Сделать первым</option>';
            this.fragments.forEach((frag, index) => {
                const label = frag.label
                    ? frag.label
                    : frag.first_line.replace(/[.,!?;:]+$/u, '') + '...';
                const value = frag.sort_order + 1;
                options += `<option value="${value}">После "${this.escapeHtml(label)}"</option>`;
            });
            return options;
        },
        
        escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
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
