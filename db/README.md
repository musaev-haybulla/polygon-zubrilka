# Database Migrations

Этот проект использует [Phinx](https://phinx.org/) для управления миграциями базы данных.

## Быстрый старт

### Основные команды

```bash
# Посмотреть статус миграций
./phinx.sh status

# Запустить все новые миграции
./phinx.sh migrate

# Откатить последнюю миграцию
./phinx.sh rollback

# Создать новую миграцию
./phinx.sh create CreatePoemsTable

# Создать сидер для тестовых данных
./phinx.sh seed:create AuthorsSeeder

# Запустить все сидеры
./phinx.sh seed:run
```

## Структура

- `migrations/` - файлы миграций
- `seeds/` - файлы для заполнения тестовыми данными

## Совместимость с Laravel

Phinx использует похожий на Laravel синтаксис, что упростит будущую миграцию:

### Phinx (текущий)
```php
$table = $this->table('users');
$table->addColumn('name', 'string', ['limit' => 100])
      ->addColumn('email', 'string')
      ->addTimestamps()
      ->create();
```

### Laravel (будущий)
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('email');
    $table->timestamps();
});
```

## Схема базы данных

### Таблицы проекта:

1. **authors** - Авторы стихотворений
2. **poems** - Поэмы и стихотворения  
3. **fragments** - Фрагменты крупных произведений
4. **lines** - Строки стихов
5. **poem_authors** - Связь поэм с авторами (many-to-many)
6. **tracks** - Аудио записи фрагментов
7. **timings** - Тайминги строк в аудио записях

### Порядок выполнения миграций:

Миграции выполняются в хронологическом порядке по timestamp:
1. `CreateAuthorsTable` - базовая таблица авторов
2. `CreatePoemsTable` - поэмы
3. `CreateFragmentsTable` - фрагменты (ссылается на poems)
4. `CreateLinesTable` - строки (ссылается на fragments)  
5. `CreatePoemAuthorsTable` - связь поэм и авторов
6. `CreateTracksTable` - аудио записи (ссылается на fragments)
7. `CreateTimingsTable` - тайминги (ссылается на tracks и lines)

### Пример миграции

```php
<?php
use Phinx\Migration\AbstractMigration;

final class CreatePoemsTable extends AbstractMigration
{
    public function change(): void
    {
        $poems = $this->table('poems');
        $poems
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('year_written', 'integer', ['null' => true])
            ->addColumn('is_divided', 'boolean', ['default' => false])
            ->addTimestamps()
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->addIndex(['title'])
            ->create();
    }
}
```