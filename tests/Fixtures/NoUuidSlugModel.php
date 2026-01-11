<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Fixtures;

use Macwinnie\WpDbPhinxHelper\GenericModel;

/**
 * Model with UUID and Slug disabled, used to test exception paths.
 */
final class NoUuidSlugModel extends GenericModel {
    protected static string $__tablename = 'no_uuid_slug';
    protected static bool $__useUuid = false;
    protected static bool $__useSlug = false;
    protected static array $__attributes = [];

    /**
     * Expose generateUniqueUuid() for testing.
     */
    public function callGenerateUniqueUuid(): string {
        return $this->generateUniqueUuid();
    }

    /**
     * Expose generateUniqueSlug() for testing.
     *
     * @param string $base
     * @return string
     */
    public function callGenerateUniqueSlug(string $base): string {
        return $this->generateUniqueSlug($base);
    }

    /**
     * Override retrieval of attributes
     */
    protected static function retrieveModelAttributes($force = false) {
        static::$__attributes = [
            "id" => "bigint(20) unsigned",
        ];
    }
}
