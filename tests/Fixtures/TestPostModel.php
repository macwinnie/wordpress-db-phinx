<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Fixtures;

use Macwinnie\WpDbPhinxHelper\GenericModel;

/**
 * Simple model used just for testing GenericModel behaviour.
 *
 * @property int         $id
 * @property string      $uuid
 * @property string      $slug
 * @property string|null $title
 * @property string|null $content
 * @property int|null    $created_at
 * @property int|null    $updated_at
 */
final class TestPostModel extends GenericModel {
    protected static string $__tablename = 'test_posts';
    protected static bool $__useUuid = true;
    protected static bool $__useSlug = true;
    protected static array $__attributes = [];
    protected static array $__mandatory = ["title"];

    // Example of custom non-editable field
    protected static array $__noneditable = ['content_hash'];

    // Attribute to tinker with slug
    public bool $skipSlug = false;

    /**
     * Override save to automatically generate slug from title
     */
    public function save(): mixed {
        if (! isset($this->__data['slug']) and ! $this->skipSlug) {
            // content_hash is generated below!
            $this->ensureMandatory(except: ["slug", "content_hash"]);

            /** @var string $title */
            $title = $this->getAttribute('title');
            $this->setValue('slug', static::generateUniqueSlug($title));
        }

        $this->setValue('content_hash', hash('crc32b', is_string($this->content) ? $this->content : ''));

        return parent::save();
    }

    public function update(): mixed {
        $this->setValue('content_hash', hash('crc32b', is_string($this->content) ? $this->content : ''));

        return parent::update();
    }
}
