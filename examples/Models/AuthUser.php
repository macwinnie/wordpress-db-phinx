<?php

namespace Macwinnie\WpDbPhinxHelper\Examples\Models;

use Macwinnie\WpDbPhinxHelper\GenericModel as Model;

/**
 * Example 5: Model with additional non-editable fields
 *
 * @property int    $id
 * @property string $uuid
 * @property string $email
 * @property string $password_hash
 * @property int $email_verified_at
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class AuthUser extends Model {
    protected static string $__tablename = "yourplugin_auth_users";
    protected static bool $__useUuid = true;
    protected static array $__attributes = [];

    /**
     * Add password_hash and email_verified_at to non-editable fields
     */
    protected static array $__noneditable = [
        'email_verified_at',
    ];
}
