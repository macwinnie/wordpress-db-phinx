<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Fixtures;

use Macwinnie\WpDbPhinxHelper\GenericModel;

/**
 * Model used to force GenericModel::save() to fail reloading the just-inserted row.
 */
final class FailingSaveModel extends GenericModel {
    // Use the same table and config as NonUuidModel
    protected static string $__tablename = 'non_uuid_posts';
    protected static bool $__useUuid = false;
    protected static bool $__useSlug = false;
    protected static bool $__useTimestamps = false;
    protected static array $__attributes = [];

    /**
     * Always simulate failure to reload the row after insert.
     *
     * @param int $id
     * @return static|null
     */
    public static function getByID(int $id): ?static {
        return null;
    }
}
