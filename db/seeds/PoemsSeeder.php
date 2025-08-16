<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class PoemsSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run(): void
    {
        // Создаем поэмы
        $poems = [
            [
                'owner_id' => 1,
                'title' => 'Я помню чудное мгновенье',
                'year_written' => 1825,
                'status' => 'published',
                'is_divided' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'owner_id' => 1,
                'title' => 'Парус',
                'year_written' => 1832,
                'status' => 'published', 
                'is_divided' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        ];

        $poemsTable = $this->table('poems');
        $poemsTable->insert($poems)->saveData();

        // Связываем поэмы с авторами
        $poemAuthors = [
            ['poem_id' => 1, 'author_id' => 1], // Пушкин
            ['poem_id' => 2, 'author_id' => 2], // Лермонтов
        ];

        $this->table('poem_authors')->insert($poemAuthors)->saveData();

        // Создаем фрагменты (по схеме db.dbml)
        $fragments = [
            [
                'poem_id' => 1,
                'owner_id' => 1,
                'label' => 'Фрагмент 1',
                'structure_info' => 'Полный текст стихотворения',
                'sort_order' => 1,
                'grade_level' => 'secondary',
                'status' => 'published',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'poem_id' => 2,
                'owner_id' => 1,
                'label' => 'Фрагмент 1',
                'structure_info' => 'Полный текст стихотворения',
                'sort_order' => 1,
                'grade_level' => 'middle',
                'status' => 'published',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        ];

        $this->table('fragments')->insert($fragments)->saveData();
    }
}
