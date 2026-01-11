<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migration for Category (with UUID and Slug)
 */
class CreateCategoryTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('yourplugin_categories', [
            'id' => false,
            'primary_key' => ['id'],
        ]);
        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'signed' => false,
        ])
              ->addColumn('uuid', 'string', ['limit' => 36])
              ->addColumn('slug', 'string', ['limit' => 250])
              ->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('is_active', 'boolean', ['default' => true])
              ->addTimestamps()
              ->addIndex(['uuid'], ['unique' => true])
              ->addIndex(['slug'], ['unique' => true])
              ->create();
    }
}
