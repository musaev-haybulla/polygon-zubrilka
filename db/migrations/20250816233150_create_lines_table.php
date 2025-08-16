<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLinesTable extends AbstractMigration
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
        $table = $this->table('lines');
        $table->addColumn('fragment_id', 'biginteger', ['null' => false])
              ->addColumn('line_number', 'integer', ['null' => false])
              ->addColumn('text', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('end_line', 'boolean', ['null' => false])
              ->addColumn('created_at', 'datetime', ['null' => false])
              ->addColumn('updated_at', 'datetime', ['null' => false])
              ->addColumn('deleted_at', 'datetime', ['null' => true])
              ->create();
    }
}
