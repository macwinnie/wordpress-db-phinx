<?php

namespace Macwinnie\WpDbPhinxHelper;

use JsonSerializable;
use Macwinnie\WpDbPhinxHelper\Exceptions\FeatureNotEnabledException;

use Macwinnie\WpDbPhinxHelper\Exceptions\InvalidAttributeException;
use Macwinnie\WpDbPhinxHelper\Exceptions\ModelNotFoundException;
use Ramsey\Uuid\Uuid;

/**
 * Abstract Model class to generalize DB interaction per table.
 *
 * You'll need to (re-)define those variables in each child class:
 *
 *   protected static string $tablename = "actual_table_name";
 *   protected static array $__attributes = [];
 *
 * Optional features (set to true in child class to enable):
 *   protected static bool $__useUuid = true;
 *   protected static bool $__useSlug = true;
 *
 * On using a Slug, you'll have to define a fallback attribute
 * e.g. on the constructor or the `save()` method to generate a
 * slug from. Could be a human readable name like `title` e.g.:
 *   // ...
 *   protected static array $__mandatory = ["title"];
 *   // ...
 *   public function save(): mixed {
 *       if (! isset($this->__data['slug'])) {
 *           $this->ensureMandatory(except: ['slug']);
 *           $this->setValue('slug', static::generateUniqueSlug($this->getAttribute('title')));
 *       }
 *       // ...
 *       return parent::save();
 *   }
 *   // ...
 *
 * Optional protected attributes (only editable / addable once)
 * can be configured by adding them in child class:
 *   protected static array $__noneditable = ["another_protected"];
 *
 * @phpstan-consistent-constructor
 *
 * @property int                $id
 * @property string             $uuid (if $useUuid is true)
 * @property string             $slug (if $useSlug is true)
 * @property \DateTimeImmutable $created_at (if $useTimestamps is true)
 * @property \DateTimeImmutable $updated_at (if $useTimestamps is true)
 */
abstract class GenericModel implements JsonSerializable {
    /**
     * Mandatory: Set table name via Implementing Class!
     * @var string
     */
    protected static string $__tablename;

    /**
     * ATTENTION: empty array `$attributes = []` needs to be re-defined in each
     * child so it will be dynamically filled by (there) local static
     * `retrieveModelAttributes` method and not all the same, first called one ...
     *
     * @var array<string, mixed>
     */
    protected static array $__attributes = [];

    /**
     * Custom non-editable fields for implementation classes.
     * Non-Editables are always considered mandatory!
     *
     * @var array<string>
     */
    protected static array $__noneditable = [];

    /**
     * fields (additional to noneditable ones) which are considered mandatory
     * on save and update
     * @var array<string>
     */
    protected static array $__mandatory = [];

    /** @var array<string> */
    protected static array $__autoset_attributes = ["id", "uuid", "created_at", "updated_at"];

    /**
     * Enable UUID field support
     *
     * @var bool
     */
    protected static bool $__useUuid = false;

    /**
     * Enable slug field support
     *
     * @var bool
     */
    protected static bool $__useSlug = false;

    /**
     * Disable timestamps fields support (`created_at`, `updated_at`)
     *
     * @var bool
     */
    protected static bool $__useTimestamps = true;
    protected static string $__dateTimeFormat = 'Y-m-d H:i:s';

    /**
     * @var array<string,mixed>
     */
    protected array $__data = [];

    /**
     * @var array<string>
     */
    protected array $__changed_attributes = [];
    protected bool $__initialized = false;

    /**
     * Add static cache across all model instances
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $__attributesCache = [];

    /**
     * Base non-editable fields (always present)
     * Non-Editables are always considered mandatory!
     *
     * @var array<string>
     */
    private static array $__baseNoneditable = [
        "id",
        # "created_at", # only relevant if `static::$__useTimestamps == true`
        # "updated_at", # only relevant if `static::$__useTimestamps == true`
        # "uuid", # only relevant if `static::$__useUuid == true`
        # "slug", # only relevant if `static::$__useSlug == true`
    ];

    /* ************* Instance ************* */

    /**
     * Get non-editable attributes including optional fields
     *
     * @return array<string>
     */
    protected static function getNoneditableAttributes(): array {
        $noneditable = self::$__baseNoneditable;

        if (static::$__useUuid) {
            $noneditable[] = 'uuid';
        }

        if (static::$__useSlug) {
            $noneditable[] = 'slug';
        }

        if (static::$__useTimestamps) {
            $noneditable[] = 'created_at';
            $noneditable[] = 'updated_at';
        }

        return array_unique(array_merge($noneditable, static::$__noneditable));
    }

    /**
     * instance constructor
     *
     * @param array<string, mixed> $args all attributes given to the object
     */
    public function __construct(...$args) {
        $editables = static::getEditableAttributes();

        foreach ($args as $key => $value) {
            if (is_string($key)) {
                $this->setValue((string) $key, $value);
            } else {
                $this->setValue($editables[$key], $value);
            }
        }
        $this->__changed_attributes = [];
    }

    /**
     * magic set method – is called when trying to set object attributes
     *
     * @param string $name  attribute name
     * @param mixed  $value value to be set
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void {
        $editables = static::getEditableAttributes();

        if (
            in_array($name, $editables) or (
                ! array_key_exists($name, $this->__data) or
                $this->__data[$name] == null
            )
        ) {
            $this->setValue($name, $value);
        } else {
            throw new \Exception(sprintf("Attribute `%s` may not be changed.", $name));
        }
    }

    /**
     * update a value and store in change variable if really changed
     *
     * @param string $key   attribute name to change
     * @param mixed  $value value to set for attribute
     *
     * @return void
     */
    public function setValue($key, $value) {
        if (
            $this->__initialized and
            (
                ! array_key_exists($key, $this->__data) or
                $this->__data[$key] != $value
            )
        ) {
            $this->__changed_attributes[] = $key;
        }
        $this->__data[$key] = $value;
    }

    /**
     * magic get method – is called when accessing object attributes
     *
     * @param  string $name name of attribute to access
     * @return mixed        value of object attribute
     */
    public function __get($name) {
        return $this->getAttribute($name);
    }

    /**
     * return data as array e.g. for JSON encoding
     *
     * @return array<mixed> dictionary with instance / row representation
     */
    public function jsonSerialize(): mixed {
        return $this->__data;
    }

    /**
     * get an attribute from current instance / row
     *
     * @param  string $name attribute to retrieve
     *
     * @return mixed        value of attribute
     */
    public function getAttribute(string $name): mixed {
        static::retrieveModelAttributes();

        if (! array_key_exists($name, static::$__attributes)) {
            throw new InvalidAttributeException(
                sprintf(
                    "Attribute `%s` does not exist on model %s. Available attributes: %s",
                    $name,
                    static::class,
                    implode(', ', array_keys(static::$__attributes))
                )
            );
        }

        return array_key_exists($name, $this->__data) ? $this->__data[$name] : null;
    }

    /**
     * do preparation steps for non-editable fields and timestamps
     *
     * @param  array<string, mixed> $dbRow DB row to work with
     *
     * @return void
     */
    protected function prepareCommonAttributes(array $dbRow): void {
        $noneditable = static::getNoneditableAttributes();

        /** @var array<string> $edited */
        $edited = [];

        foreach ($noneditable as $key) {
            if (isset($dbRow[$key]) and $dbRow[$key] != null) {
                $this->__data[$key] = $dbRow[$key];
                $edited[] = $key;
            }
        }

        if (array_key_exists('created_at', $this->__data) and in_array('created_at', $edited)) {
            /** @var string $createdAt */
            $createdAt = $this->__data['created_at'];
            $this->__data["created_at"] = strtotime($createdAt);
        }

        if (array_key_exists('updated_at', $this->__data) and in_array('updated_at', $edited)) {
            /** @var string $updatedAt */
            $updatedAt = $this->__data['updated_at'];
            $this->__data["updated_at"] = strtotime($updatedAt);
        }
    }

    /**
     * Generate a unique UUID for this model
     *
     * @return string
     * @throws \Exception if UUID is not enabled for this model
     */
    protected function generateUniqueUuid(): string {
        if (! static::$__useUuid) {
            throw new \Exception("UUID is not enabled for this model. Set 'protected static bool \$useUuid = true;' in the model class.");
        }

        $uuid = Uuid::uuid4()->toString();

        while (static::getByUUID($uuid) != null) {
            $uuid = Uuid::uuid4()->toString();
        }

        return $uuid;
    }

    /**
     * Generate a unique slug for this model
     *
     * @param string $baseSlug Base string to create slug from
     * @return string
     * @throws \Exception if slug is not enabled for this model
     */
    protected function generateUniqueSlug(string $baseSlug): string {
        if (! static::$__useSlug) {
            throw new \Exception("Slug is not enabled for this model. Set 'protected static bool \$useSlug = true;' in the model class.");
        }

        $slug = static::sanitizeSlug($baseSlug);
        $originalSlug = $slug;
        $counter = 1;

        while (static::getBySlug($slug) != null) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Wrapper around preg_replace to allow testing and centralized error handling.
     *
     * @param string $pattern
     * @param string $replacement
     * @param string $subject
     * @return string|null
     */
    protected static function pregReplaceForSlug(string $pattern, string $replacement, string $subject): ?string {
        /** @var string|null $result */
        $result = \preg_replace($pattern, $replacement, $subject);

        if (! is_string($result)) {
            return null;
        }

        return $result;
    }

    /**
     * Sanitize a string to create a valid slug
     *
     * @param string $string String to sanitize
     * @return string
     */
    protected static function sanitizeSlug(string $string): string {
        $slug = strtolower(trim($string));
        $slug = static::pregReplaceForSlug('/[^a-z0-9-]+/', '-', $slug);

        if ($slug != null) {
            $slug = static::pregReplaceForSlug('/-+/', '-', $slug);
        }

        if ($slug === null) {
            throw new \Exception("Slug could not be sanitized", 1);
        }
        $slug = trim($slug, '-');

        return substr($slug, 0, 250);
    }

    /**
     * fetch all mandatory attribute names which actually are valid attributes
     *
     * @param  bool          $force        force fresh fetch of attributes
     * @param  bool          $skip_autoset skip auto set (by DB backend) attributes
     * @return array<string>               mandatory fields of the implementation class
     */
    public static function getMandatories(bool $force = false, bool $skip_autoset = false) {
        $attrs = static::getAttributes($force);
        $noneditable = static::getNoneditableAttributes();
        $mandatory = array_intersect($attrs, array_merge(static::$__mandatory, $noneditable));

        if ($skip_autoset) {
            $mandatory = array_diff($mandatory, static::$__autoset_attributes);
        }

        return array_values($mandatory);
    }

    /**
     * check for all mandatory attributes being set – otherwise fail
     *
     * @param  array<string>  $except which attributes to skip in check
     * @param  array<string>  $only   list of attributes to check for – need to be mandatory
     *                                by nature and only reflected if `except` empty!
     * @param  bool           $skip_autoset on save method (or equivalent), `id`, `created_at`
     *                                      and other DB-backend-generated values may still be
     *                                      missing – skip them
     * @return void
     */
    public function ensureMandatory($except = [], $only = [], $skip_autoset = true): void {
        $noneditable = static::getNoneditableAttributes();
        $mandatories = static::getMandatories(skip_autoset: $skip_autoset);

        if (! empty($except)) {
            $mandatories = array_diff($mandatories, $except);
        } elseif (! empty($only)) {
            $mandatories = array_intersect($mandatories, $only);
        }
        $exceptionAttributes = [];

        foreach ($mandatories as $key) {
            if ($this->getAttribute($key) == null) {
                $exceptionAttributes[] = $key;
            }
        }

        if (! empty($exceptionAttributes)) {
            throw new \RuntimeException(sprintf(
                '%s: missing mandatory attributes [%s]',
                static::class,
                implode(', ', $exceptionAttributes)
            ));
        }
    }

    /**
     * insert new row / instance into database or update if existing (ID already set)
     *
     * @return mixed
     */
    public function save(): mixed {
        if (isset($this->__data["id"])) {
            return $this->update();
        }

        // Generate UUID if enabled
        if (static::$__useUuid && ! isset($this->__data['uuid'])) {
            $this->setValue('uuid', $this->generateUniqueUuid());
        }

        // `id`, `created_at` and first value of `updated_at` are mostly generated by DB backend!
        $this->ensureMandatory();

        /** @var \wpdb $wpdb */
        global $wpdb;
        $data = [];
        static::retrieveModelAttributes();

        foreach (array_keys(static::$__attributes) as $a) {
            if (array_key_exists($a, $this->__data)) {
                $data[$a] = $this->__data[$a];
            }
        }

        $r = $wpdb->insert(static::getTableName(), $data);
        $this->__changed_attributes = [];

        if (static::$__useUuid) {
            /** @var string $uuid */
            $uuid = $this->getAttribute('uuid');
            $h = static::getByUUID($uuid);
        } else {
            // Fall back to fetching by last insert ID
            /** @var int $lastId */
            $lastId = (int) $wpdb->insert_id;
            $h = static::getByID($lastId);
        }

        if ($h == null) {
            throw new \Exception("save operation not successful.");
        }

        $this->__data["id"] = $h->__data["id"];

        if (in_array("created_at", static::getAttributes())) {
            $this->__data["created_at"] = $h->__data["created_at"];
        }

        return $r;
    }

    /**
     * update an instance / row in database
     *
     * returns the number of rows updated, or false on error
     * source: [wpdb::update](https://developer.wordpress.org/reference/classes/wpdb/update/)
     *
     * @return int|false
     */
    public function update() {
        if (! isset($this->__data["id"])) {
            throw new \Exception("ID not set – please use `save` method!");
        }

        /** @var \wpdb $wpdb */
        global $wpdb;
        $data = [];
        static::retrieveModelAttributes();

        $this->ensureMandatory(skip_autoset: false);

        foreach ($this->__changed_attributes as $a) {
            if (array_key_exists($a, static::$__attributes)) {
                $data[$a] = $this->__data[$a];
            }
        }

        $r = $wpdb->update(static::getTableName(), $data, ["id" => $this->__data["id"], ]);

        if (static::$__useUuid) {
            /** @var string $uuid */
            $uuid = $this->getAttribute('uuid');
            $h = static::getByUUID($uuid);
        } else {
            /** @var int $id */
            $id = $this->__data["id"];
            $h = static::getByID($id);
        }

        if ($h == null) {
            throw new \Exception("update operation not successful.");
        }

        if (in_array("updated_at", static::getAttributes())) {
            $this->__data["updated_at"] = $h->__data["updated_at"];
        }

        $this->__changed_attributes = [];

        return $r;
    }

    /**
     * delete an instance / row from database
     *
     * Returns the number of rows deleted, or false on error
     * source: [wpdb::delete](https://developer.wordpress.org/reference/classes/wpdb/delete/)
     *
     * @return int|false
     */
    public function delete() {
        if (! isset($this->__data["id"])) {
            throw new \Exception("ID not set – seems to not be saved and so not deletable.");
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        return $wpdb->delete(static::getTableName(), ["id" => $this->__data["id"]]);
    }

    /* ************* Global ************* */

    /**
     * get table name for current Model
     *
     * @return string
     */
    public static function getTableName() {
        /** @var \wpdb $wpdb */
        global $wpdb;

        return sprintf('%s%s', $wpdb->prefix, static::$__tablename);
    }

    /**
     * fetch all objects of current model from DB and return their instances
     *
     * @return array<static>
     */
    public static function getAll(): array {
        /** @var \wpdb $wpdb */
        global $wpdb;
        $sql = static::sqlInsertTableName('SELECT * FROM `%s`;');
        $rows = $wpdb->get_results($sql, ARRAY_A);

        /** @codeCoverageIgnore */
        if ($wpdb->last_error) {
            throw new \Exception($wpdb->last_error);
        }

        $objects = [];

        if ($rows != null) {

            foreach ($rows as $dbRow) {
                if (is_array($dbRow)) {
                    /** @var array<string, mixed> $dbRow */
                    $objects[] = self::getObjectFromSQLResult($dbRow, static::class);
                }
            }
        }

        /** @var array<static> $objects */
        return $objects;
    }

    /**
     * get single representation by UUID
     *
     * @param  string $uuid UUID to identify what row to fetch
     *
     * @return static       representation of the fetched row as Model instance
     * @throws \Exception   if UUID is not enabled for this model
     */
    public static function getByUUID(string $uuid): ?static {
        if (! static::$__useUuid) {
            throw new FeatureNotEnabledException(
                sprintf(
                    "UUID is not enabled for model %s. Set 'protected static bool \$useUuid = true;'",
                    static::class
                )
            );
        }

        $sql = static::prepareSql('SELECT * FROM `%s` WHERE uuid = %%s;', $uuid);

        return static::getSingleBySQL($sql);
    }

    /**
     * get single representation by slug
     *
     * @param  string $slug Slug to identify what row to fetch
     *
     * @return static       representation of the fetched row as Model instance
     * @throws \Exception if slug is not enabled for this model
     */
    public static function getBySlug(string $slug): ?static {
        if (! static::$__useSlug) {
            throw new \Exception("Slug is not enabled for this model. Set 'protected static bool \$useSlug = true;' in the model class.");
        }

        $sql = static::prepareSql('SELECT * FROM `%s` WHERE slug = %%s;', $slug);

        return static::getSingleBySQL($sql);
    }

    /**
     * get single representation by ID
     *
     * @param  int  $id  ID to identify what row to fetch
     *
     * @return static        representation of the fetched row as Model instance
     */
    public static function getByID(int $id): ?static {
        $sql = static::prepareSql('SELECT * FROM `%s` WHERE id = %%d;', $id);
        $model = static::getSingleBySQL($sql);

        if ($model === null) {
            throw new ModelNotFoundException(
                sprintf("Model %s with ID %d not found", static::class, $id)
            );
        }

        return $model;
    }

    /**
     * helper to fetch one object of current model by SQL query
     *
     * @param  string $sql SQL Query to fetch a single row
     *
     * @return ?static      representation of the fetched row as Model instance
     */
    protected static function getSingleBySQL($sql): ?static {
        /** @var \wpdb $wpdb */
        global $wpdb;

        /** @var array<string, mixed> $dbRow */
        $dbRow = $wpdb->get_row($sql, ARRAY_A);

        /** @codeCoverageIgnore */
        if ($wpdb->last_error) {
            throw new \Exception($wpdb->last_error);
        }

        return self::getObjectFromSQLResult($dbRow, static::class);
    }

    /**
     * get static object from fetched DB row
     *
     * @param  array<string, mixed>  $dbRow single result of Database query
     *
     * @return ?static               representation of the fetched row as Model instance
     */
    private static function getObjectFromSQLResult($dbRow, string $modelClass): ?static {
        if ($dbRow != null) {
            /** @var static */
            $obj = new $modelClass(...$dbRow);
            $obj->prepareCommonAttributes($dbRow);
            $obj->__initialized = true;

            return $obj;
        }

        return null;
    }

    /**
     * insert table name into SQL query
     *
     * @param  string $sql SQL query the table name is the only format symbol – additional format symbols need to have an additional % prefix, so `%%s` e.g!
     *
     * @return string      SQL query with inserted table name
     */
    protected static function sqlInsertTableName($sql): string {
        return sprintf($sql, static::getTableName());
    }

    /**
     * prepare SQL with data values
     *
     * @param  string $sql  SQL query to prepare
     * @param  mixed  $args values to be inserted into the SQL
     *
     * @return string       filled SQL query
     */
    protected static function prepareSql($sql, ...$args) {
        /** @var \wpdb $wpdb */
        global $wpdb;

        /** @var literal-string $unfilled_sql */
        $unfilled_sql = static::sqlInsertTableName($sql);

        /** @var string $sql */
        $sql = $wpdb->prepare($unfilled_sql, ...$args);

        return $sql;
    }

    /**
     * get all possible attributes for the current Model from database as a list
     *
     * @param  bool $force `true` to fetch from Database although already fetched
     *
     * @return array<string>
     */
    public static function getAttributes($force = false) {
        static::retrieveModelAttributes($force);

        return array_keys(static::$__attributes);
    }

    /**
     * fetch all attributes from database
     *
     * @param  bool $force `true` to fetch from Database although already fetched
     *
     * @return void
     */
    protected static function retrieveModelAttributes($force = false) {

        $cacheKey = static::class;

        if (! $force && isset(self::$__attributesCache[$cacheKey])) {
            static::$__attributes = self::$__attributesCache[$cacheKey];

            return;
        }

        if ($force or empty(static::$__attributes)) {
            /** @var \wpdb $wpdb */
            global $wpdb;
            static::$__attributes = [];
            $sql = static::prepareSql(
                "select column_name, column_type from information_schema.columns where table_schema = '%%s' and table_name = '%s' order by ordinal_position;",
                DB_NAME
            );

            /** @var list<object{column_name: string, column_type: string}>|null $result */
            $result = $wpdb->get_results($sql);

            if (is_iterable($result)) {
                foreach ($result as $row) {
                    static::$__attributes[$row->column_name] = $row->column_type;
                }
            }

            // Cache the results
            self::$__attributesCache[$cacheKey] = static::$__attributes;
        }
    }

    /**
     * fetch a list of attributes which may be editable after creation from external
     *
     * @param  bool $force `true` to fetch from Database although already fetched
     *
     * @return array<string>
     */
    public static function getEditableAttributes($force = false) {
        $attrs = static::getAttributes($force);
        $noneditable = static::getNoneditableAttributes();
        $diff = array_values(array_diff($attrs, $noneditable));

        return $diff;
    }

    /**
     * Start a query builder instance
     *
     * @return QueryBuilder
     */
    public static function query(): QueryBuilder {
        return new QueryBuilder(static::class);
    }
}
