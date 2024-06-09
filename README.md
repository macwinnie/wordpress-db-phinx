# Wordpress Plugin Helper for Database migrations

This helper allows you to use [Phinx](https://phinx.org/) for defining Database migrations. The placed config file `phinx.php` will use the Wordpress Configured Database.

For Phinx being able to work, from your plugin directory run on a bash cli:

```sh
cp vendor/macwinnie/wp-db-phinx-helper/files/phinx.php ./
```

Also possible is to expand the `Macwinnie\WpDbPhinxHelper\DBUtilisator` class by your Plugin-Class:

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
