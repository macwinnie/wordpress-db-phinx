<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Fixtures;

use Macwinnie\WpDbPhinxHelper\GenericModel;

/**
 * Model used to test the error branch in GenericModel::sanitizeSlug().
 */
final class SlugErrorModel extends GenericModel {
    protected static string $__tablename = 'slug_error';
    protected static bool $__useUuid = false;
    protected static bool $__useSlug = false;
    protected static array $__attributes = [];

    /** @var int */
    public static int $wrapperCalls = 0;

    /**
     * Override the slug preg_replace wrapper to always "fail"
     * (i.e. return null), so sanitizeSlug() throws.
     */
    protected static function pregReplaceForSlug(string $pattern, string $replacement, string $subject): ?string {
        self::$wrapperCalls++;

        return null;
    }

    /**
     * Public helper to invoke the protected sanitizeSlug() for tests.
     */
    public static function callSanitizeSlug(string $string): string {
        return static::sanitizeSlug($string);
    }
}
