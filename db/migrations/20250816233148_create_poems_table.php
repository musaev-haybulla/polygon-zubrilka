<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePoemsTable extends AbstractMigration
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
        $table = $this->table('poems');
        $table->addColumn('owner_id', 'biginteger', ['null' => true])
              ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('year_written', 'integer', ['null' => true])
              ->addColumn('status', 'enum', ['values' => ['draft', 'published', 'unpublished'], 'null' => false])
              ->addColumn('is_divided', 'boolean', ['null' => false])
              ->addColumn('created_at', 'datetime', ['null' => false])
              ->addColumn('updated_at', 'datetime', ['null' => false])
              ->addColumn('deleted_at', 'datetime', ['null' => true])
              ->create();
    }
}
