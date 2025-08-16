<?php
declare(strict_types=1);

// Подключаем конфигурацию
require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

use App\DatabaseHelper;

// Настройка отображения ошибок для разработки
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Получаем список авторов
try {
    $authors = DatabaseHelper::getAllAuthors();
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Добавить простой стих</title>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.default.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2/dist/js/tom-select.complete.min.js" defer></script>
  <script src="poem_utils.js" defer></script>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-lg mx-auto bg-white p-6 rounded shadow" x-data="formData()">
    <h1 class="text-2xl mb-4">Новый простой стих</h1>
    <div class="mb-6 p-2 bg-blue-50 rounded">
      <a href="search.html" class="text-blue-600 hover:underline">Поиск</a>
      <span class="mx-2">|</span>
      <a href="add_poem_fragment.php" class="text-blue-600 hover:underline">Добавить фрагмент</a>
      <span class="mx-2">|</span>
      <a href="poem_list.php" class="text-blue-600 hover:underline">Управление озвучками</a>
    </div>

    <?php if (!empty($_GET['success'])): ?>
      <div id="msg" class="p-4 mb-4 text-green-800 bg-green-200 rounded">
        Стих успешно добавлен!
      </div>
      <script>
        setTimeout(() => {
          const e = document.getElementById("msg");
          if (e) e.style.display = "none";
        }, 2000);
      </script>
    <?php endif; ?>

    <form action="process_poem.php" method="post" class="space-y-4">
      <!-- Название -->
      <div>
        <label class="block mb-1">Название</label>
        <input name="title" type="text" x-model="title" @input="validate()" required
               class="w-full border rounded px-3 py-2" placeholder="Введите название">
        <input type="hidden" name="poem_id" value="0">
      </div>

      <!-- Авторы -->
      <div>
        <label class="block mb-1">Авторы</label>
        <select id="author-select" name="author_ids[]" x-model="authorIds" @change="validate()" multiple
                class="w-full border rounded px-3 py-2">
          <?php foreach ($authors as $a): ?>
            <option value="<?= htmlspecialchars((string)$a['id']) ?>">
              <?= htmlspecialchars($a['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Грейд -->
      <div>
        <label class="block mb-1">Грейд (Школа)</label>
        <select name="grade_level" x-model="grade" @change="validate()" required
                class="w-full border rounded px-3 py-2">
          <option value="primary">Начальная</option>
          <option value="middle">Средняя</option>
          <option value="secondary" selected>Старшая</option>
        </select>
      </div>

      <!-- Текст стихотворения -->
      <div>
        <label class="block mb-1">Текст стихотворения</label>
        <textarea name="poem_text" x-model="poemText" @input="updateTitle(); validate()" rows="8" required
                  class="w-full border rounded px-3 py-2"
                  placeholder="Вставьте весь текст стихотворения, разделяя строфы пустой строкой"></textarea>
        <div class="mt-2 flex space-x-2">
          <button type="button" @click="poemText = splitIntoStanzas(poemText, 4); validate()" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">4</button>
          <button type="button" @click="poemText = splitIntoStanzas(poemText, 5); validate()" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">5</button>
          <button type="button" @click="poemText = splitIntoStanzas(poemText, 6); validate()" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">6</button>
          <button type="button" @click="poemText = splitIntoStanzas(poemText, 8); validate()" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">8</button>
          <button type="button" @click="poemText = splitIntoStanzas(poemText, 10); validate()" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 text-sm">10</button>
        </div>
      </div>

      <!-- Отправка -->
      <button type="submit" :disabled="!canSubmit"
              class="w-full bg-blue-600 text-white rounded py-2 disabled:opacity-50">
        Создать стих
      </button>
    </form>
  </div>

  <script>
    function formData() {
      return {
        title: '',
        authorIds: [],
        poemText: '',
        grade: 'secondary',
        canSubmit: false,
        updateTitle() {
          if (!this.title) {
            this.title = this.poemText.split('\n')[0].replace(/[.,!;:\n\r]+$/g, '');
          }
        },
        validate() {
          this.canSubmit =
            this.title.trim() !== '' &&
            this.poemText.trim() !== '';
        }
      };
    }
    document.addEventListener('DOMContentLoaded', () => {
      new TomSelect('#author-select', {
        plugins: ['remove_button'],
        create: true,
        persist: false,
        onItemAdd:function(){
          this.setTextboxValue('');
        }
      });
    });
  </script>
</body>
</html>