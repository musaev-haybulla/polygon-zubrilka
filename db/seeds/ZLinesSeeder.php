<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class ZLinesSeeder extends AbstractSeed
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
        $lines = [
            // Фрагмент 1: "Я помню чудное мгновенье" (Пушкин)
            ['fragment_id' => 1, 'line_number' => 1, 'text' => 'Я помню чудное мгновенье:', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 1, 'line_number' => 2, 'text' => 'Передо мной явилась ты,', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 1, 'line_number' => 3, 'text' => 'Как мимолетное виденье,', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 1, 'line_number' => 4, 'text' => 'Как гений чистой красоты.', 'end_line' => true, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 1, 'line_number' => 5, 'text' => 'В томленьях грусти безнадежной,', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 1, 'line_number' => 6, 'text' => 'В тревогах шумной суеты,', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 1, 'line_number' => 7, 'text' => 'Звучал мне долго голос нежный', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 1, 'line_number' => 8, 'text' => 'И снились милые черты.', 'end_line' => true, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            
            // Фрагмент 2: "Парус" (Лермонтов)
            ['fragment_id' => 2, 'line_number' => 1, 'text' => 'Белеет парус одинокой', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 2, 'line_number' => 2, 'text' => 'В тумане моря голубом!..', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 2, 'line_number' => 3, 'text' => 'Что ищет он в стране далекой?', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 2, 'line_number' => 4, 'text' => 'Что кинул он в краю родном?..', 'end_line' => true, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 2, 'line_number' => 5, 'text' => 'Играют волны — ветер свищет,', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 2, 'line_number' => 6, 'text' => 'И мачта гнется и скрипит...', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 2, 'line_number' => 7, 'text' => 'Увы! он счастия не ищет', 'end_line' => false, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['fragment_id' => 2, 'line_number' => 8, 'text' => 'И не от счастия бежит!', 'end_line' => true, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
        ];

        $this->table('lines')->insert($lines)->saveData();
    }
}
