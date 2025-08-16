<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateFragmentsTable extends AbstractMigration
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
        $table = $this->table('fragments');
        $table->addColumn('poem_id', 'biginteger', ['null' => false])
              ->addColumn('owner_id', 'biginteger', ['null' => false])
              ->addColumn('label', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('structure_info', 'string', ['limit' => 255, 'null' => true])
              ->addColumn('sort_order', 'integer', ['null' => false])
              ->addColumn('grade_level', 'enum', ['values' => ['primary', 'middle', 'secondary'], 'null' => false])
              ->addColumn('status', 'enum', ['values' => ['draft', 'published', 'unpublished'], 'null' => false])
              ->addColumn('created_at', 'datetime', ['null' => false])
              ->addColumn('updated_at', 'datetime', ['null' => false])
              ->addColumn('deleted_at', 'datetime', ['null' => true])
              ->create();
    }
}
