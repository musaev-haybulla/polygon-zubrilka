<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class AuthorsSeeder extends AbstractSeed
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
        $authors = [
            [
                'first_name' => 'Александр',
                'middle_name' => 'Сергеевич',
                'last_name' => 'Пушкин',
                'birth_year' => 1799,
                'death_year' => 1837,
                'biography' => 'Великий русский поэт, драматург и прозаик, заложивший основы русского реалистического направления',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'first_name' => 'Михаил',
                'middle_name' => 'Юрьевич',
                'last_name' => 'Лермонтов',
                'birth_year' => 1814,
                'death_year' => 1841,
                'biography' => 'Русский поэт, прозаик, драматург, художник',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->table('authors')->insert($authors)->saveData();
    }
}
