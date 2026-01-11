<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Fixtures;

use Macwinnie\WpDbPhinxHelper\GenericModel;

/**
 * Model used to test the UUID collision retry loop in GenericModel::generateUniqueUuid().
 */
final class UuidCollisionModel extends GenericModel {
    protected static string $__tablename = 'uuid_collision_test';
    protected static bool $__useUuid = true;
    protected static bool $__useSlug = false;
    protected static array $__attributes = [];

    /** @var int */
    public static int $getByUuidCalls = 0;

    /**
     * Public wrapper to access the protected generateUniqueUuid() for testing.
     */
    public function callGenerateUniqueUuid(): string {
        return $this->generateUniqueUuid();
    }

    /**
     * Override getByUUID to simulate a collision on first call
     * and "no collision" on subsequent calls.
     *
     * @param string $uuid
     * @return static|null
     */
    public static function getByUUID(string $uuid): ?static {
        self::$getByUuidCalls++;

        // First call: simulate that the UUID already exists (collision)
        if (self::$getByUuidCalls === 1) {
            return new static(uuid: $uuid);
        }

        // Second and later calls: no collision
        return null;
    }
}
