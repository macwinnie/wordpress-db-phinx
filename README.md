# Wordpress Plugin Helper for Database migrations

This helper allows you to use [Phinx](https://phinx.org/) for defining Database migrations. The placed config file `phinx.php` will use the Wordpress Configured Database.

You can install this helper library by using `composer`:

```
composer require macwinnie/wp-db-phinx-helper
```

It is recommended to expand the `Macwinnie\WpDbPhinxHelper\DBUtilisator` class by your Plugin-Class:

```php

class MyPlugin extends DBUtilisator {

    public static function plugin_activation_method () {
        parent::plugin_activation_method(); # ensures Phinx config placed and migrations run on install / activation / update
    }

    public static function plugin_uninstall_method () {
        parent::plugin_uninstall_method(); # ensures Phinx migrations rolled back in Database on Plugin uninstall
    }
}

```

The static function `static::basePath()` can be used to get your Plugin absolute path on the webserver – and will also check if the Phinx config file is present in the base directory of your Plugin.

## Folder structure

Your Database migrations shall reside within `db/migrations`, Database seeds within `db/seeds` as childpath of your WordPress plugin.

Phinx takes over creation of paths as you follow the [official documentation](https://book.cakephp.org/phinx/0/en/commands.html). The `phinx` executable can be found in `vendor/bin/phinx` as you install this helper library via `composer`.
