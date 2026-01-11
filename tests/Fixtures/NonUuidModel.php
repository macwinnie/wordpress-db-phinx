<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Fixtures;

use Macwinnie\WpDbPhinxHelper\GenericModel;

/**
 * Model with UUID disabled to test GenericModel::save() fallback behaviour.
 *
 * @property int         $id
 * @property string|null $title
 */
final class NonUuidModel extends GenericModel {
    protected static string $__tablename = 'non_uuid_posts';
    protected static bool $__useUuid = false;
    protected static bool $__useSlug = false;
    protected static bool $__useTimestamps = false;
    protected static array $__attributes = [];

    /**
     * Override retrieval of attributes
     */
    protected static function retrieveModelAttributes($force = false) {
        static::$__attributes = [
            "id" => "bigint(20) unsigned",
            "title" => "varchar(255)",
        ];
    }
}
