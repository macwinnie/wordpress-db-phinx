<?php

namespace Macwinnie\WpDbPhinxHelper;

/**
 * Query Builder for Model class
 *
 * Provides a fluent interface for building and executing database queries.
 *
 * @example
 * Product::query()
 *     ->where('is_active', '=', true)
 *     ->where('price', '>', 10)
 *     ->orderBy('name', 'ASC')
 *     ->limit(10)
 *     ->get();
 */
class QueryBuilder {
    /**
     * Model class name
     * @var class-string<GenericModel>
     */
    private string $modelClass;

    /**
     * WHERE conditions
     * @var array<int, array{column: string, operator: string, value: mixed, boolean: string}>
     */
    private array $wheres = [];

    /**
     * ORDER BY clauses
     * @var array<int, array{column: string, direction: string}>
     */
    private array $orderBy = [];

    /**
     * LIMIT clause
     * @var int|null
     */
    private ?int $limit = null;

    /**
     * OFFSET clause
     * @var int|null
     */
    private ?int $offset = null;

    /**
     * SELECT columns
     * @var array<string>|null
     */
    private ?array $select = null;

    /**
     * GROUP BY columns
     * @var array<string>
     */
    private array $groupBy = [];

    /**
     * HAVING conditions
     * @var array<int, array{column: string, operator: string, value: mixed, boolean: string}>
     */
    private array $having = [];

    /**
     * Constructor
     *
     * @param class-string<GenericModel> $modelClass
     */
    public function __construct(string $modelClass) {
        $this->modelClass = $modelClass;
    }

    /**
     * Add a WHERE condition
     *
     * @param string $column Column name
     * @param string $operator Comparison operator (=, !=, >, <, >=, <=, LIKE, IN, NOT IN, IS NULL, IS NOT NULL)
     * @param mixed $value Value to compare (not needed for IS NULL/IS NOT NULL)
     * @param string $boolean Boolean operator (AND, OR)
     * @return self
     */
    public function where(string $column, string $operator, mixed $value = null, string $boolean = 'AND'): self {
        // Handle IS NULL and IS NOT NULL (no value needed)
        if (in_array(strtoupper($operator), ['IS NULL', 'IS NOT NULL'])) {
            $this->wheres[] = [
                'column' => $column,
                'operator' => $operator,
                'value' => null,
                'boolean' => $boolean,
            ];

            return $this;
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add an OR WHERE condition
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return self
     */
    public function orWhere(string $column, string $operator, mixed $value): self {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Add a WHERE IN condition
     *
     * @param string $column Column name
     * @param array<mixed> $values Array of values
     * @param string $boolean Boolean operator
     * @return self
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self {
        return $this->where($column, 'IN', $values, $boolean);
    }

    /**
     * Add a WHERE NOT IN condition
     *
     * @param string $column Column name
     * @param array<mixed> $values Array of values
     * @return self
     */
    public function whereNotIn(string $column, array $values): self {
        return $this->where($column, 'NOT IN', $values);
    }

    /**
     * Add a WHERE NULL condition
     *
     * @param string $column Column name
     * @return self
     */
    public function whereNull(string $column): self {
        return $this->where($column, 'IS NULL');
    }

    /**
     * Add a WHERE NOT NULL condition
     *
     * @param string $column Column name
     * @return self
     */
    public function whereNotNull(string $column): self {
        return $this->where($column, 'IS NOT NULL');
    }

    /**
     * Add an ORDER BY clause
     *
     * @param string $column Column name
     * @param string $direction Sort direction (ASC, DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $direction = strtoupper($direction);

        if (! in_array($direction, ['ASC', 'DESC'])) {
            throw new \InvalidArgumentException("Direction must be ASC or DESC, got: {$direction}");
        }

        $this->orderBy[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Set LIMIT
     *
     * @param int $limit Number of records to return
     * @return self
     */
    public function limit(int $limit): self {
        if ($limit < 0) {
            throw new \InvalidArgumentException("Limit must be non-negative, got: {$limit}");
        }

        $this->limit = $limit;

        return $this;
    }

    /**
     * Set OFFSET
     *
     * @param int $offset Number of records to skip
     * @return self
     */
    public function offset(int $offset): self {
        if ($offset < 0) {
            throw new \InvalidArgumentException("Offset must be non-negative, got: {$offset}");
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * Set SELECT columns
     *
     * @param string ...$columns Columns to select
     * @return self
     */
    public function select(string ...$columns): self {
        $this->select = $columns;

        return $this;
    }

    /**
     * Add GROUP BY columns
     *
     * @param string ...$columns Columns to group by
     * @return self
     */
    public function groupBy(string ...$columns): self {
        $this->groupBy = array_merge($this->groupBy, $columns);

        return $this;
    }

    /**
     * Add a HAVING condition
     *
     * @param string $column Column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @param string $boolean Boolean operator (AND, OR)
     * @return self
     */
    public function having(string $column, string $operator, mixed $value, string $boolean = 'AND'): self {
        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Execute query and get all results
     *
     * @return array<GenericModel>
     */
    public function get(): array {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $sql = $this->buildSelectQuery();

        /** @var array<array<string, mixed>>|null $rows */
        $rows = $wpdb->get_results($sql, ARRAY_A);

        if ($wpdb->last_error) {
            throw new \Exception($wpdb->last_error);
        }

        if (! $rows) {
            return [];
        }

        return $this->hydrateModels($rows);
    }

    /**
     * Execute query and get first result
     *
     * @return GenericModel|null
     */
    public function first(): ?GenericModel {
        $originalLimit = $this->limit;
        $this->limit = 1;

        $results = $this->get();

        $this->limit = $originalLimit;

        return $results[0] ?? null;
    }

    /**
     * Get count of matching records
     *
     * @return int
     */
    public function count(): int {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $sql = $this->buildCountQuery();

        /** @var string|null $result */
        $result = $wpdb->get_var($sql);

        if ($wpdb->last_error) {
            throw new \Exception($wpdb->last_error);
        }

        return (int) ($result ?? 0);
    }

    /**
     * Check if any records exist
     *
     * @return bool
     */
    public function exists(): bool {
        return $this->count() > 0;
    }

    /**
     * Get the sum of a column
     *
     * @param string $column Column to sum
     * @return float
     */
    public function sum(string $column): float {
        $value = $this->aggregate('SUM', $column);

        if (! is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * Get the average of a column
     *
     * @param string $column Column to average
     * @return float
     */
    public function avg(string $column): float {
        $value = $this->aggregate('AVG', $column);

        if (! is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * Get the minimum value of a column
     *
     * @param string $column Column to get min value
     * @return mixed
     */
    public function min(string $column): mixed {
        return $this->aggregate('MIN', $column);
    }

    /**
     * Get the maximum value of a column
     *
     * @param string $column Column to get max value
     * @return mixed
     */
    public function max(string $column): mixed {
        return $this->aggregate('MAX', $column);
    }

    /**
     * Execute an aggregate function
     *
     * @param string $function Aggregate function (SUM, AVG, MIN, MAX, COUNT)
     * @param string $column Column name
     * @return string|int|float|null
     */
    protected function aggregate(string $function, string $column): mixed {
        /** @var \wpdb $wpdb */
        global $wpdb;

        $function = strtoupper($function);
        $tableName = $this->modelClass::getTableName();

        $sql = "SELECT {$function}(`{$column}`) FROM `{$tableName}`";
        $sql .= $this->buildWhereClause();
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildHavingClause();

        /** @var string|int|float|null $result */
        $result = $wpdb->get_var($sql);

        if ($wpdb->last_error) {
            throw new \Exception($wpdb->last_error);
        }

        return $result;
    }

    /**
     * Build SELECT query
     *
     * @return string
     */
    protected function buildSelectQuery(): string {
        $tableName = $this->modelClass::getTableName();

        // SELECT clause
        if ($this->select !== null && ! empty($this->select)) {
            $columns = array_map(function ($col) {
                return "`{$col}`";
            }, $this->select);
            $selectClause = implode(', ', $columns);
        } else {
            $selectClause = '*';
        }

        $sql = "SELECT {$selectClause} FROM `{$tableName}`";

        // WHERE clause
        $sql .= $this->buildWhereClause();

        // GROUP BY clause
        $sql .= $this->buildGroupByClause();

        // HAVING clause
        $sql .= $this->buildHavingClause();

        // ORDER BY clause
        $sql .= $this->buildOrderByClause();

        // LIMIT clause
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        // OFFSET clause
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * Build COUNT query
     *
     * @return string
     */
    protected function buildCountQuery(): string {
        $tableName = $this->modelClass::getTableName();

        $sql = "SELECT COUNT(*) FROM `{$tableName}`";
        $sql .= $this->buildWhereClause();
        $sql .= $this->buildGroupByClause();
        $sql .= $this->buildHavingClause();

        return $sql;
    }

    /**
     * Build WHERE clause
     *
     * @return string
     */
    protected function buildWhereClause(): string {
        if (empty($this->wheres)) {
            return '';
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $conditions = [];

        foreach ($this->wheres as $index => $where) {
            $column = $where['column'];
            $operator = strtoupper($where['operator']);
            $value = $where['value'];
            $boolean = $where['boolean'];

            // Build condition based on operator
            if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                $condition = "`{$column}` {$operator}";
            } elseif ($operator === 'IN' || $operator === 'NOT IN') {
                if (! is_array($value)) {
                    throw new \InvalidArgumentException("Value for {$operator} must be an array");
                }

                if (empty($value)) {
                    // Handle empty array - always false for IN, always true for NOT IN
                    $condition = $operator === 'IN' ? '1=0' : '1=1';
                } else {
                    $placeholders = [];

                    foreach ($value as $v) {
                        $placeholders[] = $this->getPlaceholder($v);
                    }
                    $inPlaceholders = implode(', ', $placeholders);

                    /** @var string $prepared */
                    $prepared = $wpdb->prepare("`{$column}` {$operator} ({$inPlaceholders})", ...$value);
                    $condition = $prepared;
                }
            } else {
                // Standard comparison operators
                $placeholder = $this->getPlaceholder($value);

                /** @var string $prepared */
                $prepared = $wpdb->prepare("`{$column}` {$operator} {$placeholder}", $value);
                $condition = $prepared;
            }

            // Add boolean operator (AND/OR) except for first condition
            if ($index === 0) {
                $conditions[] = $condition;
            } else {
                $conditions[] = "{$boolean} {$condition}";
            }
        }

        return ' WHERE ' . implode(' ', $conditions);
    }

    /**
     * Build ORDER BY clause
     *
     * @return string
     */
    protected function buildOrderByClause(): string {
        if (empty($this->orderBy)) {
            return '';
        }

        $orders = array_map(function ($order) {
            return "`{$order['column']}` {$order['direction']}";
        }, $this->orderBy);

        return ' ORDER BY ' . implode(', ', $orders);
    }

    /**
     * Build GROUP BY clause
     *
     * @return string
     */
    protected function buildGroupByClause(): string {
        if (empty($this->groupBy)) {
            return '';
        }

        $columns = array_map(function ($col) {
            return "`{$col}`";
        }, $this->groupBy);

        return ' GROUP BY ' . implode(', ', $columns);
    }

    /**
     * Build HAVING clause
     *
     * @return string
     */
    protected function buildHavingClause(): string {
        if (empty($this->having)) {
            return '';
        }

        /** @var \wpdb $wpdb */
        global $wpdb;

        $conditions = [];

        foreach ($this->having as $index => $having) {
            $column = $having['column'];
            $operator = $having['operator'];
            $value = $having['value'];
            $boolean = $having['boolean'];

            $placeholder = $this->getPlaceholder($value);

            /** @var string $prepared */
            $prepared = $wpdb->prepare("`{$column}` {$operator} {$placeholder}", $value);
            $condition = $prepared;

            if ($index === 0) {
                $conditions[] = $condition;
            } else {
                $conditions[] = "{$boolean} {$condition}";
            }
        }

        return ' HAVING ' . implode(' ', $conditions);
    }

    /**
     * Get wpdb placeholder for value type
     *
     * @param mixed $value
     * @return string
     */
    protected function getPlaceholder(mixed $value): string {
        if (is_int($value)) {
            return '%d';
        } elseif (is_float($value)) {
            return '%f';
        }

        return '%s';

    }

    /**
     * Hydrate database rows into Model instances
     *
     * @param array<array<string, mixed>> $rows
     * @return array<GenericModel>
     */
    protected function hydrateModels(array $rows): array {
        $models = [];

        foreach ($rows as $row) {
            $model = new ($this->modelClass)(...$row);

            // Use reflection to call protected prepareCommonAttributes
            $reflection = new \ReflectionClass($model);
            $method = $reflection->getMethod('prepareCommonAttributes');
            $method->setAccessible(true);
            $method->invoke($model, $row);

            // Set initialized flag
            $property = $reflection->getProperty('__initialized');
            $property->setAccessible(true);
            $property->setValue($model, true);

            $models[] = $model;
        }

        return $models;
    }

    /**
     * Get the SQL query string (for debugging)
     *
     * @return string
     */
    public function toSql(): string {
        return $this->buildSelectQuery();
    }

    /**
     * Chunk results for processing large datasets
     *
     * @param int $chunkSize Number of records per chunk
     * @param callable $callback Function to process each chunk
     * @return void
     */
    public function chunk(int $chunkSize, callable $callback): void {
        $page = 1;

        do {
            $offset = ($page - 1) * $chunkSize;

            $results = (clone $this)
                ->limit($chunkSize)
                ->offset($offset)
                ->get();

            if (empty($results)) {
                break;
            }

            // Call callback with chunk
            if ($callback($results, $page) === false) {
                break;
            }

            $page++;
        } while (count($results) === $chunkSize);
    }

    /**
     * Get paginated results
     *
     * @param int $page Current page (1-indexed)
     * @param int $perPage Items per page
     * @return array{items: array<GenericModel>, total: int, per_page: int, current_page: int, last_page: int, from: int, to: int}
     */
    public function paginate(int $page = 1, int $perPage = 15): array {
        if ($page < 1) {
            throw new \InvalidArgumentException("Page must be 1 or greater, got: {$page}");
        }

        if ($perPage < 1) {
            throw new \InvalidArgumentException("Per page must be 1 or greater, got: {$perPage}");
        }

        // Get total count
        $total = $this->count();

        // Calculate pagination
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        // Get items for current page
        $items = (clone $this)
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $from = $total > 0 ? $offset + 1 : 0;
        $to = min($offset + $perPage, $total);

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to,
        ];
    }
}
