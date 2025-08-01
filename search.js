document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const searchButton = document.getElementById('search-button');
    const resultsContainer = document.getElementById('results');

    // Специально проверяем "Евгений Онегин"
    searchInput.addEventListener('input', function() {
        const value = this.value.toLowerCase().trim();
        if (value.includes('евгений') || value.includes('онегин')) {
            // Обнаружен ввод "Евгений Онегин"
        }
    });

    // Search when button is clicked
    searchButton.addEventListener('click', performSearch);
    
    // Also search when Enter key is pressed
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });

    function performSearch() {
        const query = searchInput.value.trim();
        
        if (query.length < 2) {
            resultsContainer.innerHTML = '<p class="no-results">Введите не менее 2 символов для поиска</p>';
            return;
        }
        
        // Show loading indicator
        resultsContainer.innerHTML = '<p>Поиск...</p>';
        
        // Стандартный запрос через API для всех поисковых запросов
        fetch('search_api.php?query=' + encodeURIComponent(query))
            .then(response => {
                return response.text();
            })
            .then(text => {
                try {
                    return text ? JSON.parse(text) : [];
                } catch (e) {
                    throw new Error('Неверный формат данных');
                }
            })
            .then(data => {
                displayResults(data);
            })
            .catch(error => {
                resultsContainer.innerHTML = '<p class="no-results">Произошла ошибка при поиске. Пожалуйста, попробуйте еще раз.</p>';
            });
    }

    function displayResults(data) {
        if (data.length === 0) {
            resultsContainer.innerHTML = `
                <div class="p-6 text-center text-gray-600 bg-white rounded-lg shadow">
                    <p class="text-lg">Ничего не найдено</p>
                    <p class="text-sm mt-1">Попробуйте изменить поисковый запрос</p>
                </div>`;
            return;
        }
        
        let html = '';
        
        data.forEach(item => {
            // Функция для отображения строк стихотворения
            const renderLines = (lines, maxLines = 2) => {
                if (!lines || !Array.isArray(lines) || lines.length === 0) return '';
                
                let linesHtml = '<div class="mt-2 space-y-1 italic text-gray-700">';
                lines.slice(0, maxLines).forEach(line => {
                    if (line) linesHtml += `<p class="line">${line}</p>`;
                });
                if (lines.length > maxLines) {
                    linesHtml += '<p class="text-sm text-gray-500">...</p>';
                }
                linesHtml += '</div>';
                return linesHtml;
            };
            
            // Общий шаблон карточки
            const renderCard = (title, meta, content, type) => {
                return `
                <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-100 hover:shadow-md transition-shadow duration-200">
                    <div class="p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">${title}</h3>
                                <div class="text-sm text-gray-500 mt-1">
                                    ${meta}
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${type === 'poem' ? 'bg-blue-100 text-blue-800' : type === 'fragment' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'}">
                                ${type === 'poem' ? 'Стихотворение' : type === 'fragment' ? 'Фрагмент' : 'Строка'}
                            </span>
                        </div>
                        <div class="mt-3">
                            ${content}
                        </div>
                    </div>
                </div>
                `;
            };

            if (item.type === 'poem') {
                const meta = [
                    item.author && `Автор: <span class="font-medium">${item.author}</span>`,
                    item.year_written && `Год: <span class="font-medium">${item.year_written}</span>`
                ].filter(Boolean).join(' • ');
                
                let content = '';
                if (item.fragments) {
                    content = '<div class="mt-2 space-y-4">';
                    item.fragments.forEach(f => {
                        content += '<div class="border-t border-gray-200 pt-2">';
                        if (f.label) {
                            content += `<p class="text-sm font-medium text-gray-800">${f.label}</p>`;
                        }
                        f.lines.forEach(line => {
                            if (line) content += `<p class="line">${line}</p>`;
                        });
                        content += '</div>';
                    });
                    content += '</div>';
                } else {
                    content = renderLines(item.lines);
                }
                
                html += renderCard(
                    item.title || 'Без названия',
                    meta,
                    content,
                    'poem'
                );
                
            } else if (item.type === 'first_line') {
                const meta = [
                    item.author && `Автор: <span class="font-medium">${item.author}</span>`
                ].filter(Boolean).join(' • ');

                html += renderCard(
                    item.poem_title || 'Без названия',
                    meta,
                    renderLines(item.lines, 3),
                    'line'
                );

            } else if (item.type === 'line') {
                const meta = [
                    item.author && `Автор: <span class="font-medium">${item.author}</span>`,
                    item.year && `Год: <span class="font-medium">${item.year}</span>`
                ].filter(Boolean).join(' • ');

                html += renderCard(
                    item.title || 'Без названия',
                    meta,
                    renderLines(item.lines, 3),
                    'line'
                );
                
            } else if (item.type === 'fragment') {
                const meta = [
                    item.author && `Автор: <span class="font-medium">${item.author}</span>`,
                    item.label && `Метка: <span class="font-medium">${item.label}</span>`
                ].filter(Boolean).join(' • ');
                
                html += renderCard(
                    item.poem_title || 'Без названия',
                    meta,
                    renderLines(item.lines, 3),
                    'fragment'
                );
            }
        });
        
        resultsContainer.innerHTML = html;
    }
});
