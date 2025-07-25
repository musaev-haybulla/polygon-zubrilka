
# План развития проекта

Этот документ описывает ближайшие планы по развитию проекта.

## Добавление озвучек

Процесс добавления новой озвучки будет состоять из нескольких шагов, чтобы обеспечить гибкость и качество.

1.  **Загрузка и основная информация.** На первом этапе пользователь указывает основную информацию об озвучке (чей голос, тип — AI/человек, название) и загружает сам аудиофайл.
**Статус** Выполнено

2.  **Выбор порядка сортировки.** Пользователю будет предложен выпадающий список для определения позиции новой озвучки. Варианты будут включать "Добавить первым" и названия уже существующих озвучек, что позволит разместить новую запись сразу после выбранной.
**Статус** Выполнено

3.  **Разделение озвучек в интерфейсе.** В `poem_list.php` необходимо визуально разделить озвучки на `активные` и `черновики`. Для каждой озвучки в статусе `draft` должны быть доступны опции для редактирования метаинформации, обрезки исходного аудиофайла и перехода к этапу разметки.
**Статус** Выполнено

4.  **Редактирование метаинформации озвучки.** ✅ **Выполнено**
    - Реализовано модальное окно для редактирования метаданных (название, тип голоса, порядок сортировки)
    - Добавлена возможность загрузки нового аудиофайла (опционально)
    - Создан класс `AudioSorter` для правильной нормализации порядка сортировки
    - Реализована AJAX-обработка для обновления интерфейса без перезагрузки
    
    **Подзадачи для доработки:**
    - 4a. ✅ Добавить кнопки для продолжения workflow: "Сохранить и обрезать", "Сохранить и разметить"
    - 4b. ✅ Убрать чекбокс "Обрезать аудиофайл" из модалки редактирования (избежать путаницы)
    - 4c. ✅ Реализовать переходы к соответствующим этапам обработки озвучки
**Статус** Выполнено

5.  **Загрузка и привязка озвучек к фрагментам.** ✅ **Выполнено**
    - Реализована AJAX-загрузка аудиофайлов с прогресс-баром
    - Создана система slug-based имён файлов в формате `{title_slug}-{timestamp}.mp3`
    - Файлы сохраняются в структуру `uploads/audio/{fragment_id}/`
    - Реализована валидация файлов (формат MP3, размер до 3MB)
    - Добавлена система предупреждений при перезаписи обработанных файлов
    - Создан класс `AudioFileHelper` для работы с файлами и путями
    
    **Статус** Выполнено

6.  **Опциональная обрезка файла.** ✅ **Выполнено**
    - Реализован waveform-интерфейс с WaveSurfer.js для визуальной обрезки
    - Создана система сохранения оригинала (`original_filename`) и обрезанной версии (`filename`)
    - Реализована возможность отката к оригинальному файлу
    - Добавлена валидация временных меток и обработка ошибок FFmpeg
    - Интегрирован в workflow загрузки аудио (чекбокс "Обрезать аудиофайл")
    
    **Статус** Выполнено

7.  **Интеграция разметки.** На последнем этапе происходит разметка аудио по тексту. После успешного завершения этого шага статус записи в базе данных меняется на `active`.

    **Статус** Планируется
