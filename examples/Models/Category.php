<?php

namespace Macwinnie\WpDbPhinxHelper\Examples\Models;

use Macwinnie\WpDbPhinxHelper\GenericModel as Model;

/**
 * Example 4: Model with both UUID and Slug
 *
 * @property int    $id
 * @property string $uuid
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property bool   $is_active
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class Category extends Model {
    protected static string $__tablename = "yourplugin_categories";
    protected static bool $__useUuid = true;
    protected static bool $__useSlug = true;
    protected static array $__attributes = [];
    protected static array $__mandatory = ["name"];

    /**
     * Override save to automatically generate slug from name
     */
    public function save(): mixed {
        if (! isset($this->__data['slug'])) {
            $this->ensureMandatory(except: ["slug"]);

            /** @var string $name */
            $name = $this->getAttribute('name');
            $this->setValue('slug', static::generateUniqueSlug($name));
        }

        return parent::save();
    }
}
