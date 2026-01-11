<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migration for Post (with Slug)
 */
class CreatePostTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('yourplugin_posts', [
            'id' => false,
            'primary_key' => ['id'],
        ]);
        $table->addColumn('id', 'biginteger', [
            'identity' => true,
            'signed' => false,
        ])
              ->addColumn('slug', 'string', ['limit' => 250])
              ->addColumn('title', 'string', ['limit' => 255])
              ->addColumn('content', 'text')
              ->addTimestamps()
              ->addIndex(['slug'], ['unique' => true])
              ->create();
    }
}
