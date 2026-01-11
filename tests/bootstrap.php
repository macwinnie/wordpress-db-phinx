<?php

declare(strict_types=1);

// 1. Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// 2. WordPress-like constants for DB etc (already provided by you)
require_once __DIR__ . '/../stubs/constants-stub.php';

// 3. Fake wpdb implementation for unit & integration tests
//    This is deliberately simple but hits all methods used by your code.
if (! class_exists('wpdb')) {
    class wpdb {
        /** DB config (using your constants) */
        public string $dbhost = DB_HOST;
        public string $dbname = DB_NAME;
        public string $dbuser = DB_USER;
        public string $dbpassword = DB_PASSWORD;
        public string $charset = DB_CHARSET;
        public string $collate = DB_COLLATE;
        public string $prefix = 'wp_';
        public string $last_error = '';
        public int $insert_id = 0;
        public mixed $forcedVarResult = null;

        /** @var array<string, array<string, string>> */
        public array $mockedSchemas = [];

        /**
         * In-memory “tables”
         * @var array<string, array<int, array<string, mixed>>>
         */
        public array $tableRows = [];

        /**
         * Column schemas
         * @var array<string, list<stdClass>>
         */
        public array $tableSchemas = [];

        /**
         * @var array<string, list<int|string>>
         */
        public array $cols = [];

        public function __construct(?string $schemaJsonFile = null) {
            $schemaJsonFile ??= __DIR__ . '/Fixtures/information_schema.columns.json';

            $this->loadTableSchemasFromJson($schemaJsonFile);
        }

        public function resetTables(): void {
            foreach ($this->tableRows as $tableName => $rows) {
                $this->tableRows[$tableName] = [];
            }
        }

        /**
         * load default table schemas from JSON definition file for tests
         * @param  string $file path to file
         * @return void
         */
        private function loadTableSchemasFromJson(string $file): void {
            if (! is_file($file)) {
                throw new \RuntimeException("Schema JSON file not found: {$file}");
            }

            try {
                $json_contents = file_get_contents($file);
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    "JSON file '{$file}' not readable. Original error: {$e->getMessage()}",
                    0,
                    $e
                );
            }

            if ($json_contents === false) {
                throw new \RuntimeException(
                    "JSON file '{$file}' does not return any contents."
                );
            }

            $decoded = json_decode(
                $json_contents,
                true,               // decode as arrays, we’ll convert to stdClass
                512,
                JSON_THROW_ON_ERROR
            );

            if (! is_array($decoded)) {
                throw new \RuntimeException("Invalid schema JSON structure in: {$file}");
            }

            /** @var array<string, list<stdClass>> $schemas */
            $schemas = [];

            foreach ($decoded as $table => $columns) {
                if (! is_string($table) || ! is_array($columns)) {
                    continue;
                }

                $table = $this->prefix . $table;

                $this->tableSchemas[$table] = [];
                $this->mockedSchemas[$table] = [];

                foreach ($columns as $col) {
                    if (
                        ! is_array($col) ||
                        ! isset($col['column_name'], $col['column_type']) ||
                        ! is_string($col['column_name']) ||
                        ! is_string($col['column_type'])
                    ) {
                        continue;
                    }

                    $this->tableSchemas[$table][] = (object) [
                        'column_name' => $col['column_name'],
                        'column_type' => $col['column_type'],
                    ];
                    $this->mockedSchemas[$table][$col['column_name']] = $col['column_type'];
                }
            }
        }

        /**
         * Very dumb sprintf-based prepare emulating $wpdb->prepare
         *
         * @param  string                     $query
         * @param  mixed                      ...$args
         * @return string
         */
        public function prepare(string $query, ...$args): string {
            if (empty($args)) {
                return $query;
            }

            $values = array_map(
                static function (mixed $v): bool|float|int|string|null {
                    if (is_bool($v) || is_float($v) || is_int($v) || is_string($v) || $v === null) {
                        return $v;
                    }

                    // For anything weird, just drop to null – good enough for tests
                    return null;
                },
                $args
            );

            /** @var array<bool|float|int|string|null> $values */
            return vsprintf($query, $values);
        }

        /**
         * @param  string      $sql
         * @param  int|string  $output
         * @return array<int, mixed>|null
         */
        public function get_results(string $sql, $output = ARRAY_A) {
            // GenericModel: information_schema.columns
            if (stripos($sql, 'information_schema.columns') !== false) {
                if (preg_match("/table_name\s*=\s*'([^']+)'/i", $sql, $m)) {
                    $table = $m[1];

                    // tableSchemas is list<stdClass>, exactly what GenericModel expects
                    return $this->tableSchemas[$table] ?? [];
                }

                return [];
            }

            // Normal table SELECTs (used by QueryBuilder::get)
            $table = $this->extractTable($sql);

            if ($table === null) {
                return [];
            }

            $rows = $this->tableRows[$table] ?? [];

            // LIMIT / OFFSET for QueryBuilder get(), chunk(), paginate()
            $limit = null;
            $offset = 0;

            if (preg_match('/LIMIT\s+(\d+)/i', $sql, $m)) {
                $limit = (int) $m[1];
            }

            if (preg_match('/OFFSET\s+(\d+)/i', $sql, $m)) {
                $offset = (int) $m[1];
            }

            if ($offset > 0 || $limit !== null) {
                $rows = array_slice($rows, $offset, $limit ?? null);
            }

            // For normal tables we always use arrays, as stored in $tableRows
            return $rows;
        }

        /**
         * For SELECT * FROM `table` WHERE ...
         *
         * @param  string $sql
         * @param  string $output
         * @return array<string, mixed>|null
         */
        public function get_row(string $sql, string $output = ARRAY_A) {
            $table = $this->extractTable($sql);

            if ($table === null) {
                return null;
            }

            if (stripos($sql, 'information_schema.columns') !== false) {
                /** @var array<int, array<string, mixed>> $results */
                $results = $this->get_results($sql, $output);

                return $results[0] ?? null;
            }

            $rows = $this->tableRows[$table] ?? [];

            $conditions = $this->extractSimpleConditions($sql);

            if (! empty($conditions)) {
                $rows = array_values(array_filter(
                    $rows,
                    static function (array $row) use ($conditions): bool {
                        foreach ($conditions as $key => $value) {
                            if (! array_key_exists($key, $row)) {
                                return false;
                            }

                            if ($row[$key] != $value) { // loose compare, no casting
                                return false;
                            }
                        }

                        return true;
                    }
                ));
            }

            if (! isset($rows[0])) {
                return null;
            }

            return $rows[0];
        }

        /**
         * For SELECT `id` FROM `%s`
         *
         * @param  string $sql
         * @return list<int|string>
         */
        public function get_col(string $sql): array {
            if (preg_match('/SELECT\s+`([^`]+)`\s+FROM\s+`([^`]+)`/i', $sql, $m)) {
                $column = $m[1];
                $table = $m[2];

                $rows = $this->tableRows[$table] ?? [];

                $colValues = array_column($rows, $column); // list<mixed>

                return array_map(
                    static function (mixed $v): int|string {
                        if (is_int($v) || is_string($v)) {
                            return $v;
                        }

                        if (is_bool($v)) {
                            return $v ? 1 : 0;
                        }

                        if (is_float($v)) {
                            return (int) $v;
                        }

                        // null or anything else
                        return '';
                    },
                    $colValues
                );
            }

            return [];
        }

        /**
         * Simulate insert – store row in memory
         *
         * @param string               $table
         * @param array<string, mixed> $data
         *
         * @return int|false
         */
        public function insert(string $table, array $data) {
            if (! isset($this->tableRows[$table])) {
                /** @var array<int, array<string, mixed>> $empty */
                $empty = [];
                $this->tableRows[$table] = $empty;
            }

            if (! isset($data["created_at"])) {
                $data["created_at"] = strtotime('now');
                ;
            }

            if (! isset($data["updated_at"])) {
                $data["updated_at"] = strtotime('now');
                ;
            }

            // auto-assign an ID if not present
            if (! isset($data['id'])) {
                $data['id'] = count($this->tableRows[$table]) + 1;
            } elseif (! is_int($data['id'])) {
                $id = $data['id'];

                /** @var bool|float|int|resource|string|null $id */
                $data['id'] = (int) $id;
            }

            /** @var int $id */
            $id = $data['id'];
            $this->insert_id = $id;

            /** @var array<string, mixed> $row */
            $row = $data;

            $this->tableRows[$table][] = $row;

            return 1;
        }

        /**
         * Simulate update
         *
         * @param string                $table
         * @param array<string, mixed>  $data
         * @param array<string, mixed>  $where
         *
         * @return int|false
         */
        public function update(string $table, array $data, array $where) {
            if (! isset($this->tableRows[$table])) {
                return 0;
            }

            $updated = 0;

            if (! isset($data["updated_at"])) {
                $data["updated_at"] = strtotime('now');
                ;
            }

            foreach ($this->tableRows[$table] as &$row) {
                $match = true;

                foreach ($where as $key => $value) {
                    if (! array_key_exists($key, $row)) {
                        $match = false;

                        break;
                    }

                    if ($row[$key] != $value) { // loose, no casting
                        $match = false;

                        break;
                    }
                }

                if ($match) {
                    foreach ($data as $k => $v) {
                        $row[$k] = $v;
                    }
                    $updated++;
                }
            }

            return $updated;
        }

        /**
         * Simulate delete
         *
         * @param string                         $table
         * @param array<string, mixed>      $where
         *
         * @return int|false
         */
        public function delete(string $table, array $where) {
            if (! isset($this->tableRows[$table])) {
                return 0;
            }

            $deleted = 0;

            foreach ($this->tableRows[$table] as $idx => $row) {
                $match = true;

                foreach ($where as $key => $value) {
                    if (! array_key_exists($key, $row)) {
                        $match = false;

                        break;
                    }

                    if ($row[$key] != $value) { // loose compare, no cast
                        $match = false;

                        break;
                    }
                }

                if ($match) {
                    unset($this->tableRows[$table][$idx]);
                    $deleted++;
                }
            }

            // reindex array
            $this->tableRows[$table] = array_values($this->tableRows[$table]);

            return $deleted;
        }

        private function extractTable(string $sql): ?string {
            if (preg_match('/FROM\s+`([^`]+)`/i', $sql, $m)) {
                return $m[1];
            }

            return null;
        }

        /**
         * Only handles simple WHERE id/uuid/slug conditions for GenericModel.
         *
         * @return array<string, string>
         */
        private function extractSimpleConditions(string $sql): array {
            $conditions = [];

            if (preg_match('/WHERE\s+id\s*=\s*(\d+)/i', $sql, $m)) {
                $conditions['id'] = $m[1];
            }

            if (preg_match("/WHERE\s+uuid\s*=\s*([^ \n\r\t;]+)/i", $sql, $m)) {
                $conditions['uuid'] = trim($m[1], " '\"");
            }

            if (preg_match("/WHERE\s+slug\s*=\s*([^ \n\r\t;]+)/i", $sql, $m)) {
                $conditions['slug'] = trim($m[1], " '\"");
            }

            return $conditions;
        }

        /**
         * @param  string $sql
         * @return mixed
         */
        public function get_var(string $sql) {
            // QueryBuilder aggregates for wp_yourplugin_users

            if ($this->forcedVarResult !== null) {
                return $this->forcedVarResult;
            }

            // COUNT for BasicUser
            if (preg_match('/SELECT\s+COUNT\(\*\)\s+FROM\s+`wp_yourplugin_users`/i', $sql)) {
                // special case: where status = 'active' → tests expect 10
                if (stripos($sql, '`status`') !== false) {
                    return 10;
                }

                // otherwise use the real number of in-memory rows
                $table = 'wp_yourplugin_users';

                return isset($this->tableRows[$table]) ? count($this->tableRows[$table]) : 0;
            }

            // SUM/AVG/MIN/MAX used in tests
            if (preg_match('/SELECT\s+SUM\(`price`\)\s+FROM\s+`wp_yourplugin_users`/i', $sql)) {
                return 150.50;
            }

            if (preg_match('/SELECT\s+AVG\(`price`\)\s+FROM\s+`wp_yourplugin_users`/i', $sql)) {
                return 25.75;
            }

            if (preg_match('/SELECT\s+MIN\(`price`\)\s+FROM\s+`wp_yourplugin_users`/i', $sql)) {
                return '10';
            }

            if (preg_match('/SELECT\s+MAX\(`price`\)\s+FROM\s+`wp_yourplugin_users`/i', $sql)) {
                return '100';
            }

            // Generic COUNT(*) for other tables (GenericModel)
            if (preg_match('/SELECT\s+COUNT\(\*\)\s+FROM\s+`([^`]+)`/i', $sql, $m)) {
                $table = $m[1];

                return isset($this->tableRows[$table]) ? count($this->tableRows[$table]) : 0;
            }

            return null;
        }
    }
}

// put fake wpdb into global scope as WordPress would
$GLOBALS['wpdb'] = new wpdb();
