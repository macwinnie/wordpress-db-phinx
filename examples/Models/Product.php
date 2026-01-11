<?php

namespace Macwinnie\WpDbPhinxHelper\Examples\Models;

use Macwinnie\WpDbPhinxHelper\GenericModel as Model;

/**
 * Example 2: Model with UUID only
 *
 * @property int    $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class Product extends Model {
    protected static string $__tablename = "yourplugin_products";
    protected static bool $__useUuid = true;
    protected static array $__attributes = [];
}
