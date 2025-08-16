<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTracksTable extends AbstractMigration
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
        $table = $this->table('tracks');
        $table->addColumn('fragment_id', 'biginteger', ['null' => false])
              ->addColumn('filename', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('original_filename', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('duration', 'decimal', ['precision' => 8, 'scale' => 3, 'null' => false])
              ->addColumn('is_ai_generated', 'boolean', ['null' => false])
              ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('sort_order', 'integer', ['null' => false])
              ->addColumn('status', 'enum', ['values' => ['draft', 'active'], 'null' => false])
              ->addColumn('created_at', 'timestamp', ['null' => true])
              ->addColumn('updated_at', 'timestamp', ['null' => true])
              ->create();
    }
}
