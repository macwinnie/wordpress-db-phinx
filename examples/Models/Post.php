<?php

namespace Macwinnie\WpDbPhinxHelper\Examples\Models;

use Macwinnie\WpDbPhinxHelper\GenericModel as Model;

/**
 * Example 3: Model with Slug only
 *
 * @property int    $id
 * @property string $slug
 * @property string $title
 * @property string $content
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class Post extends Model {
    protected static string $__tablename = "yourplugin_posts";
    protected static bool $__useSlug = true;
    protected static array $__attributes = [];
    protected static array $__mandatory = ["title"];

    /**
     * Override save to automatically generate slug from title
     */
    public function save(): mixed {
        // Generate slug from title if not set
        if (! isset($this->__data['slug'])) {
            $this->ensureMandatory(except: ["slug"]);

            /** @var string $title */
            $title = $this->getAttribute('title');
            $this->setValue('slug', static::generateUniqueSlug($title));
        }

        return parent::save();
    }
}
