# Видение проекта "Зубрилка"

Этот документ описывает высокоуровневые цели и будущие направления развития проекта, которые выходят за рамки конкретных задач в `ROADMAP.md`.

## Курирование контента учителем

### Проблема
На данный момент в базу добавлено около 400 фрагментов стихов из школьной программы. Однако объем этих фрагментов (например, для поэм "Евгений Онегин" или "Бородино") был определен субъективно. Слишком короткий фрагмент может быть бесполезен для ученика, а слишком длинный — вводить в заблуждение и мешать процессу запоминания.

### Решение
Необходимо привлечь квалифицированного учителя литературы для проверки и корректировки существующей базы стихов.

### Предлагаемый флоу
1.  Создается специальная страница, на которой единым списком выводятся все стихи, требующие проверки.
2.  Для каждого стихотворения доступны три опции:
    *   **"Объем верный"**: При нажатии на эту кнопку стих считается проверенным и перемещается в список "отработанных".
    *   **"Объем избыточный"**: Требует оставить комментарий с указанием, где следует закончить фрагмент.
    *   **"Объем недостаточный"**: Требует оставить комментарий с указанием, какие строки необходимо добавить.
3.  Стихи, помеченные как "избыточные" или "недостаточные", вместе с комментариями эксперта поступают в бэклог для дальнейшей доработки.

Этот механизм позволит создать выверенную и педагогически ценную базу учебных материалов, что является ключевой целью проекта.
