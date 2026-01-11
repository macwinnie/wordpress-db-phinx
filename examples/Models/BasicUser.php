<?php

namespace Macwinnie\WpDbPhinxHelper\Examples\Models;

use Macwinnie\WpDbPhinxHelper\GenericModel as Model;

/**
 * Example 1: Basic Model without UUID or Slug
 *
 * @property int    $id
 * @property string $name
 * @property string $email
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class BasicUser extends Model {
    protected static string $__tablename = "yourplugin_users";
    protected static array $__attributes = [];
}
