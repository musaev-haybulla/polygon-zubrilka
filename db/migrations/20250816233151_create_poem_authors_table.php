<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePoemAuthorsTable extends AbstractMigration
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
        $table = $this->table('poem_authors', ['id' => false, 'primary_key' => ['poem_id', 'author_id']]);
        $table->addColumn('poem_id', 'biginteger', ['null' => false])
              ->addColumn('author_id', 'biginteger', ['null' => false])
              ->create();
    }
}
