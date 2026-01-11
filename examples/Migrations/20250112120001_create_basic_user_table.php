<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migration for BasicUser
 */
class CreateBasicUserTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('yourplugin_users', [
            'id' => false,
            'primary_key' => ['id'],
        ]);
        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'signed' => false,
        ])
              ->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('email', 'string', ['limit' => 255])
              ->addTimestamps()
              ->addIndex(['email'], ['unique' => true])
              ->create();
    }
}
