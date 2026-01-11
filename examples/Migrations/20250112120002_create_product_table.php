<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migration for Product (with UUID)
 */
class CreateProductTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('yourplugin_products', [
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
              ->addTimestamps()
              ->addIndex(['uuid'], ['unique' => true])
              ->create();
    }
}
