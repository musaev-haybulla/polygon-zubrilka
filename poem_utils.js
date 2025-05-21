/**
 * Разбивает текст стихотворения на строфы заданного размера.
 * @param {string} text - Текст стихотворения.
 * @param {number} linesPerStanza - Количество строк в строфе.
 * @returns {string} Текст с разделением на строфы.
 */
function splitIntoStanzas(text, linesPerStanza) {
  // Разбиваем текст на строки и удаляем пустые строки на концах
  let lines = text.trim().split('\n').filter(line => line.trim() !== '');
  
  if (lines.length === 0) return text;
  
  let result = [];
  let currentStanza = [];
  
  for (let i = 0; i < lines.length; i++) {
    currentStanza.push(lines[i]);
    
    // Если набрали нужное количество строк или это последняя строка
    if (currentStanza.length === linesPerStanza || i === lines.length - 1) {
      result.push(currentStanza.join('\n'));
      currentStanza = [];
    }
  }
  
  // Возвращаем строфы, разделенные пустой строкой
  return result.join('\n\n');
}

/**
 * Устанавливает текст в textarea по имени.
 * @param {string} fieldName - Имя поля textarea.
 * @param {string} text - Текст для установки.
 */
function setTextAreaValue(fieldName, text) {
  const textarea = document.querySelector(`textarea[name="${fieldName}"]`);
  if (textarea) {
    textarea.value = text;
    // Вызываем событие input для обновления модели в Alpine.js
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }
}
