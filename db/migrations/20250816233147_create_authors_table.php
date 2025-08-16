<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuthorsTable extends AbstractMigration
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
        $table = $this->table('authors');
        $table->addColumn('first_name', 'string', ['limit' => 100, 'null' => true])
              ->addColumn('middle_name', 'string', ['limit' => 100, 'null' => true])
              ->addColumn('last_name', 'string', ['limit' => 100, 'null' => true])
              ->addColumn('birth_year', 'integer', ['null' => true])
              ->addColumn('death_year', 'integer', ['null' => true])
              ->addColumn('biography', 'text', ['null' => true])
              ->addColumn('created_at', 'datetime', ['null' => false])
              ->addColumn('updated_at', 'datetime', ['null' => false])
              ->addColumn('deleted_at', 'datetime', ['null' => true])
              ->create();
    }
}
