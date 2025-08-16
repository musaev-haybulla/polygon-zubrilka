<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTimingsTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('timings');
        $table->addColumn('track_id', 'biginteger', ['null' => false])
              ->addColumn('line_id', 'biginteger', ['null' => false])
              ->addColumn('end_time', 'decimal', ['precision' => 8, 'scale' => 3, 'null' => false])
              ->addColumn('created_at', 'timestamp', ['null' => true])
              ->addColumn('updated_at', 'timestamp', ['null' => true])
              ->create();
    }
}
