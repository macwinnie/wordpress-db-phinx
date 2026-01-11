<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migration for AuthUser
 */
class CreateAuthUserTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('yourplugin_auth_users', [
            'id' => false,
            'primary_key' => ['id'],
        ]);
        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'signed' => false,
        ])
              ->addColumn('uuid', 'string', ['limit' => 36])
              ->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('email', 'string', ['limit' => 255])
              ->addColumn('password_hash', 'string')
              ->addColumn('email_verified_at', 'string')
              ->addTimestamps()
              ->addIndex(['uuid'], ['unique' => true])
              ->addIndex(['email'], ['unique' => true])
              ->create();
    }
}
