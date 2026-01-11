<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Unit;

use Macwinnie\WpDbPhinxHelper\Examples\Models\BasicUser;
use Macwinnie\WpDbPhinxHelper\QueryBuilder;
use Macwinnie\WpDbPhinxHelper\Tests\TestCase;

class QueryBuilderTest extends TestCase {
    public function testItBuildsSimpleSelectQuery(): void {
        $builder = new QueryBuilder(BasicUser::class);

        $sql = $builder->toSql();

        $this->assertSame('SELECT * FROM `wp_yourplugin_users`', $sql);
    }

    public function testItBuildsQueryWithWhereClause(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->where('name', '=', 'John')->toSql();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('`name` = John', $sql);
    }

    public function testItBuildsQueryWithMultipleWhereClauses(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder
            ->where('name', '=', 'John')
            ->where('age', '>', 18)
            ->toSql();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('`name` = John', $sql);
        $this->assertStringContainsString('AND `age` > 18', $sql);
    }

    public function testItBuildsQueryWithOrWhere(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder
            ->where('status', '=', 'active')
            ->orWhere('status', '=', 'pending')
            ->toSql();

        $this->assertStringContainsString('OR', $sql);
    }

    public function testItBuildsQueryWithWhereIn(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->whereIn('id', [1, 2, 3])->toSql();

        $this->assertStringContainsString('IN', $sql);
    }

    public function testItBuildsQueryWithWhereNull(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->whereNull('deleted_at')->toSql();

        $this->assertStringContainsString('`deleted_at` IS NULL', $sql);
    }

    public function testItBuildsQueryWithWhereNotNull(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->whereNotNull('email')->toSql();

        $this->assertStringContainsString('`email` IS NOT NULL', $sql);
    }

    public function testItBuildsQueryWithOrderBy(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->orderBy('name', 'ASC')->toSql();

        $this->assertStringContainsString('ORDER BY `name` ASC', $sql);
    }

    public function testItBuildsQueryWithMultipleOrderBy(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder
            ->orderBy('priority', 'DESC')
            ->orderBy('name', 'ASC')
            ->toSql();

        $this->assertStringContainsString('ORDER BY `priority` DESC, `name` ASC', $sql);
    }

    public function testItBuildsQueryWithLimit(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->limit(10)->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testItBuildsQueryWithOffset(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->limit(10)->offset(20)->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    public function testItBuildsQueryWithSelectColumns(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->select('id', 'name', 'email')->toSql();

        $this->assertStringContainsString('SELECT `id`, `name`, `email`', $sql);
    }

    public function testItBuildsQueryWithGroupBy(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->groupBy('category')->toSql();

        $this->assertStringContainsString('GROUP BY `category`', $sql);
    }

    public function testItExecutesGetQuery(): void {
        // Seed stub DB with 2 BasicUser rows
        $table = $this->wpdb->prefix . 'yourplugin_users';

        $this->wpdb->tableRows[$table] = [
            ['id' => 1, 'uuid' => 'u1', 'slug' => 'john', 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'uuid' => 'u2', 'slug' => 'jane', 'name' => 'Jane', 'email' => 'jane@example.com'],
        ];

        $builder = new QueryBuilder(BasicUser::class);
        $results = $builder->get();

        $this->assertCount(2, $results);
        $this->assertInstanceOf(BasicUser::class, $results[0]);
    }

    public function testItExecutesFirstQuery(): void {
        $table = $this->wpdb->prefix . 'yourplugin_users';
        $this->wpdb->tableRows[$table] = [
            ['id' => 1, 'uuid' => 'u1', 'slug' => 'john', 'name' => 'John', 'email' => 'john@example.com'],
        ];

        $builder = new QueryBuilder(BasicUser::class);
        $result = $builder->first();

        $this->assertInstanceOf(BasicUser::class, $result);
    }

    public function testItReturnsNullWhenFirstFindsNothing(): void {
        $table = $this->wpdb->prefix . 'yourplugin_users';
        $this->wpdb->tableRows[$table] = []; // no rows

        $builder = new QueryBuilder(BasicUser::class);
        $result = $builder->first();

        $this->assertNull($result);
    }

    public function testItExecutesCountQuery(): void {
        $table = $this->wpdb->prefix . 'yourplugin_users';
        $this->wpdb->tableRows[$table] = [
            ['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5],
        ];

        $builder = new QueryBuilder(BasicUser::class);
        $count = $builder->count();

        $this->assertSame(5, $count);
    }

    public function testItBuildsCountQueryWithWhere(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $count = $builder->where('status', '=', 'active')->count();

        $this->assertEquals(10, $count);
    }

    public function testItChecksExistence(): void {
        for ($i = 0; $i < 5; $i++) {
            $u = new BasicUser(name: "user name " . $i, email: "user" . $i . "@domain.tld");
            $u->save();
        }

        $builder = new QueryBuilder(BasicUser::class);
        $exists = $builder->exists();

        $this->assertTrue($exists);
    }

    public function testItCalculatesSum(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sum = $builder->sum('price');

        $this->assertEquals(150.50, $sum);
    }

    public function testItCalculatesAverage(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $avg = $builder->avg('price');

        $this->assertEquals(25.75, $avg);
    }

    public function testItFindsMinimum(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $min = $builder->min('price');

        $this->assertEquals('10', $min);
    }

    public function testItFindsMaximum(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $max = $builder->max('price');

        $this->assertEquals('100', $max);
    }

    public function testItHandlesEmptyResults(): void {
        $table = $this->wpdb->prefix . 'yourplugin_users';
        $this->wpdb->tableRows[$table] = []; // override default 50 rows

        $builder = new QueryBuilder(BasicUser::class);
        $results = $builder->get();

        $this->assertEmpty($results);
    }

    public function testItThrowsExceptionOnDatabaseError(): void {
        $this->expectException(\Exception::class);

        $this->wpdb->last_error = 'Database error occurred';

        $builder = new QueryBuilder(BasicUser::class);
        $builder->get();
    }

    public function testItHandlesEmptyWhereInArray(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->whereIn('id', [])->toSql();

        // Empty IN should become 1=0 (always false)
        $this->assertStringContainsString('1=0', $sql);
    }

    public function testItHandlesEmptyWhereNotInArray(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder->whereNotIn('id', [])->toSql();

        // Empty NOT IN should become 1=1 (always true)
        $this->assertStringContainsString('1=1', $sql);
    }

    public function testItThrowsExceptionForInvalidOrderDirection(): void {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new QueryBuilder(BasicUser::class);
        $builder->orderBy('name', 'INVALID');
    }

    public function testItThrowsExceptionForNegativeLimit(): void {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new QueryBuilder(BasicUser::class);
        $builder->limit(-1);
    }

    public function testItThrowsExceptionForNegativeOffset(): void {
        $this->expectException(\InvalidArgumentException::class);

        $builder = new QueryBuilder(BasicUser::class);
        $builder->offset(-1);
    }

    public function testItPaginatesResults(): void {
        for ($i = 0; $i < 50; $i++) {
            $u = new BasicUser(name: "user name " . $i, email: "user" . $i . "@domain.tld");
            $u->save();
        }

        $builder = new QueryBuilder(BasicUser::class);
        $result = $builder->paginate(2, 15);

        $this->assertEquals(50, $result['total']);
        $this->assertEquals(15, $result['per_page']);
        $this->assertEquals(2, $result['current_page']);
        $this->assertEquals(4, $result['last_page']);
        $this->assertEquals(16, $result['from']);
        $this->assertEquals(30, $result['to']);
        $this->assertCount(15, $result['items']);
    }

    public function testItChunksResults(): void {

        for ($i = 0; $i < 25; $i++) {
            $u = new BasicUser(name: "user name " . $i, email: "user" . $i . "@domain.tld");
            $u->save();
        }

        $builder = new QueryBuilder(BasicUser::class);

        $called = 0;
        $sizes = [];

        $builder->chunk(10, function (array $results, int $page) use (&$called, &$sizes) {
            $called++;
            $sizes[] = count($results);

            return true;
        });

        $this->assertSame(3, $called);
        $this->assertSame([10, 10, 5], $sizes);
    }

    public function testItStopsChunkingWhenCallbackReturnsFalse(): void {
        $callCount = 0;

        for ($i = 0; $i < 25; $i++) {
            $u = new BasicUser(name: "user name " . $i, email: "user" . $i . "@domain.tld");
            $u->save();
        }

        $builder = new QueryBuilder(BasicUser::class);
        $builder->chunk(10, function ($results, $page) use (&$callCount) {
            $callCount++;

            return false; // Stop after first chunk
        });

        $this->assertEquals(1, $callCount);
    }

    public function testItBuildsComplexQuery(): void {
        $builder = new QueryBuilder(BasicUser::class);
        $sql = $builder
            ->select('id', 'name', 'price')
            ->where('status', '=', 'active')
            ->where('price', '>', 10)
            ->orderBy('price', 'DESC')
            ->limit(20)
            ->offset(10)
            ->toSql();

        $this->assertStringContainsString('SELECT `id`, `name`, `price`', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 20', $sql);
        $this->assertStringContainsString('OFFSET 10', $sql);
    }

    public function testItThrowsExceptionOnDatabaseErrorInGet(): void {
        $this->wpdb->last_error = 'Database error occurred';

        $builder = new QueryBuilder(BasicUser::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error occurred');

        $builder->get();
    }

    public function testItThrowsExceptionOnDatabaseErrorInCount(): void {
        $this->wpdb->last_error = 'Count failed';

        $builder = new QueryBuilder(BasicUser::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Count failed');

        $builder->count();
    }

    public function testWhereInThrowsWhenValueIsNotArray(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value for IN must be an array');

        $builder = new QueryBuilder(BasicUser::class);

        // Force the IN-branch but pass a non-array value
        $builder->where('id', 'IN', 'not-an-array')->toSql();
    }

    public function testWhereNotInThrowsWhenValueIsNotArray(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value for NOT IN must be an array');

        $builder = new QueryBuilder(BasicUser::class);

        $builder->where('id', 'NOT IN', 'not-an-array')->toSql();
    }

    public function testGetPlaceholderChoosesCorrectWpdbPlaceholders(): void {
        $builder = new QueryBuilder(BasicUser::class);

        $rm = new \ReflectionMethod($builder, 'getPlaceholder');
        $rm->setAccessible(true);

        $this->assertSame('%d', $rm->invoke($builder, 123));
        $this->assertSame('%f', $rm->invoke($builder, 12.34));
        $this->assertSame('%s', $rm->invoke($builder, 'abc'));
    }

    public function testItBuildsQueryWithHavingClause(): void {
        $builder = new QueryBuilder(BasicUser::class);

        $sql = $builder
            ->groupBy('status')
            ->having('status', '=', 'active')      // first condition
            ->toSql();

        $this->assertStringContainsString('GROUP BY `status`', $sql);
        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('`status` =', $sql);
    }

    public function testItBuildsQueryWithMultipleHavingClausesAndOrBoolean(): void {
        $builder = new QueryBuilder(BasicUser::class);

        $sql = $builder
            ->groupBy('status')
            ->having('status', '=', 'active')                 // first
            ->having('price', '>', 25, 'OR')                  // second with OR
            ->toSql();

        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('`status` =', $sql);
        $this->assertStringContainsString('OR `price` >', $sql);
    }

    public function testOrderByAcceptsLowercaseDirectionAndNormalizesToUppercase(): void {
        $builder = new QueryBuilder(BasicUser::class);

        $sql = $builder->orderBy('name', 'desc')->toSql();

        $this->assertStringContainsString('ORDER BY `name` DESC', $sql);
    }

    public function testSelectWithNoColumnsFallsBackToStar(): void {
        $builder = new QueryBuilder(BasicUser::class);

        $sql = $builder->select()->toSql();

        $this->assertSame('SELECT * FROM `wp_yourplugin_users`', $sql);
    }

    public function testPaginateThrowsWhenPageIsLessThanOne(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be 1 or greater');

        $builder = new QueryBuilder(BasicUser::class);
        $builder->paginate(0, 15);
    }

    public function testPaginateThrowsWhenPerPageIsLessThanOne(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Per page must be 1 or greater');

        $builder = new QueryBuilder(BasicUser::class);
        $builder->paginate(1, 0);
    }

    public function testChunkDoesNotCallCallbackWhenNoResults(): void {
        // Ensure table has zero rows
        $table = $this->wpdb->prefix . 'yourplugin_users';
        $this->wpdb->tableRows[$table] = [];

        $builder = new QueryBuilder(BasicUser::class);

        $called = 0;
        $builder->chunk(10, function () use (&$called) {
            $called++;

            return true;
        });

        $this->assertSame(0, $called);
    }

    public function testSumReturnsZeroWhenAggregateIsNotNumeric(): void {
        $this->wpdb->forcedVarResult = 'nope';

        $builder = new QueryBuilder(BasicUser::class);

        $this->assertSame(0.0, $builder->sum('price'));
    }

    public function testAvgReturnsZeroWhenAggregateIsNotNumeric(): void {
        $this->wpdb->forcedVarResult = 'nope';

        $builder = new QueryBuilder(BasicUser::class);

        $this->assertSame(0.0, $builder->avg('price'));
    }

    public function testLastErrorRaisedOnMaxAggregate(): void {
        $this->expectException(\Exception::class);
        $this->wpdb->last_error = 'Dummy WP DB error';
        $builder = new QueryBuilder(BasicUser::class);
        $max = $builder->max('price');
    }
}
