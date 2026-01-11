<?php

declare(strict_types=1);

namespace Macwinnie\WpDbPhinxHelper\Tests\Unit;

use Macwinnie\WpDbPhinxHelper\Examples\Models\BasicUser;
use Macwinnie\WpDbPhinxHelper\Exceptions\ModelNotFoundException;
use Macwinnie\WpDbPhinxHelper\GenericModel;
use Macwinnie\WpDbPhinxHelper\Tests\Fixtures\FailingSaveModel;
use Macwinnie\WpDbPhinxHelper\Tests\Fixtures\NonUuidModel;
use Macwinnie\WpDbPhinxHelper\Tests\Fixtures\NoUuidSlugModel;
use Macwinnie\WpDbPhinxHelper\Tests\Fixtures\SlugErrorModel;
use Macwinnie\WpDbPhinxHelper\Tests\Fixtures\TestPostModel;
use Macwinnie\WpDbPhinxHelper\Tests\Fixtures\UuidCollisionModel;
use Macwinnie\WpDbPhinxHelper\Tests\TestCase;

/**
 * @covers \Macwinnie\WpDbPhinxHelper\GenericModel
 */
final class GenericModelTest extends TestCase {
    public function testGetAttributesAndEditableAttributes(): void {
        $attrs = TestPostModel::getAttributes(true);

        $this->assertContains('id', $attrs);
        $this->assertContains('uuid', $attrs);
        $this->assertContains('slug', $attrs);
        $this->assertContains('title', $attrs);
        $this->assertContains('created_at', $attrs);
        $this->assertContains('updated_at', $attrs);

        $editables = TestPostModel::getEditableAttributes(true);

        // base non-editable + uuid + slug + content_hash must be excluded
        $this->assertNotContains('id', $editables);
        $this->assertNotContains('created_at', $editables);
        $this->assertNotContains('updated_at', $editables);
        $this->assertNotContains('uuid', $editables);
        $this->assertNotContains('slug', $editables);
        $this->assertNotContains('content_hash', $editables);

        $this->assertContains('title', $editables);
        $this->assertContains('content', $editables);
    }

    public function testMagicGetAndUnknownAttribute(): void {
        $title1 = "Hello";
        $post1 = new TestPostModel(title: $title1);
        $this->assertSame($title1, $post1->title);

        $title2 = "`title` is first editable";
        $post2 = new TestPostModel($title2);
        $this->assertSame($title2, $post2->title);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Attribute `does_not_exist` does not exist on model");

        /** @phpstan-ignore-next-line */
        $post1->does_not_exist;
    }

    public function testMagicSetRespectsNoneditableFieldsAfterInitialization(): void {
        $post = new TestPostModel(title: 'Initial title');

        $post->id = 5;

        $ref = new \ReflectionClass($post);
        $prop = $ref->getProperty('__initialized');
        $prop->setAccessible(true);
        $prop->setValue($post, true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Attribute `id` may not be changed.');
        $post->id = 10;
    }

    public function testSaveInsertsRowAndGeneratesUuidAndSlug(): void {
        $post = new TestPostModel(title: 'My First Post', content: 'Lorem ipsum');
        $tableName = $post->getTableName();

        $result = $post->save();

        $this->assertSame(1, $result);
        $this->assertGreaterThan(0, $post->id);
        $this->assertNotSame('', $post->uuid);
        $this->assertSame('my-first-post', $post->slug);

        $this->assertArrayHasKey($tableName, $this->wpdb->tableRows);

        $row = $this->wpdb->tableRows[$tableName][0];

        $this->assertSame($post->id, $row['id']);
        $this->assertSame($post->uuid, $row['uuid']);
    }

    public function testSlugIsUnique(): void {
        $post1 = new TestPostModel(title: 'My Duplicate Post', content: 'Lorem ipsum dolor sit amet ...');
        $post1->save();

        $post2 = new TestPostModel(title: 'My Duplicate Post', content: 'Weit hinten hinter den Wortbergen ...');
        $post2->save();

        $this->assertSame('my-duplicate-post', $post1->slug);
        $this->assertSame('my-duplicate-post-1', $post2->slug);
    }

    public function testUpdateChangesOnlyDirtyAttributesAndUpdatesTimestamp(): void {
        // pre-populate one row
        $table = $this->wpdb->prefix . 'test_posts';
        $this->wpdb->insert(
            $table,
            [
                'uuid' => 'foo',
                'slug' => 'my-post',
                'title' => 'Original Title',
                'content' => 'Content',
                'created_at' => '2024-01-01 10:00:00',
                'updated_at' => '2024-01-01 10:00:00',
            ]
        );

        // load via UUID using GenericModel API
        $loaded = TestPostModel::getByUUID('foo');
        $this->assertInstanceOf(TestPostModel::class, $loaded);

        // mark as initialized as if it came from DB
        $ref = new \ReflectionClass($loaded);
        $prop = $ref->getProperty('__initialized');
        $prop->setAccessible(true);
        $prop->setValue($loaded, true);

        // change only title
        $loaded->title = 'Changed Title';
        $result = $loaded->update();

        $this->assertSame(1, $result);

        // ensure updated_at changed
        $this->assertNotSame(
            strtotime('2024-01-01 10:00:00'),
            $loaded->updated_at
        );

        // verify row in fake DB
        $row = $this->wpdb->tableRows[$table][0];
        $this->assertSame('Changed Title', $row['title']);
    }

    public function testGetAllFetchesAll(): void {
        $table = $this->wpdb->prefix . 'test_posts';

        // two rows
        $this->wpdb->insert($table, ['uuid' => 'u1', 'slug' => 'p1', 'title' => 'A']);
        $this->wpdb->insert($table, ['uuid' => 'u2', 'slug' => 'p2', 'title' => 'B']);

        $all = TestPostModel::getAll();

        $this->assertCount(2, $all);
        $this->assertSame('A', $all[0]->title);
        $this->assertSame('B', $all[1]->title);
    }

    public function testDeleteRemovesRow(): void {
        $table = $this->wpdb->prefix . 'test_posts';

        $this->wpdb->insert($table, ['uuid' => 'u1', 'slug' => 'p1', 'title' => 'A']);
        $this->wpdb->insert($table, ['uuid' => 'u2', 'slug' => 'p2', 'title' => 'B']);

        $post = TestPostModel::getByID(1);
        $this->assertInstanceOf(TestPostModel::class, $post);

        $deleted = $post->delete();
        $this->assertSame(1, $deleted);
        $this->assertCount(1, $this->wpdb->tableRows[$table]);
    }

    public function testGetTableNameUsesWpPrefix(): void {
        $this->assertSame(
            'wp_test_posts',
            TestPostModel::getTableName()
        );
    }

    public function testGetAttributeReturnsNullForNotSet(): void {
        $post = new TestPostModel(title: 'Hello');

        $this->assertNull($post->getAttribute('content')); // not set yet
    }

    public function testSanitizeSlugAndGenerateUniqueSlug(): void {
        $table = $this->wpdb->prefix . 'test_posts';

        $existing = new TestPostModel(title: 'My Title');
        $existing->save();

        $post = new TestPostModel(title: 'My Title');
        $post->save();

        // second post must have slug 'my-title-1'
        $this->assertSame('my-title-1', $post->slug);
    }

    public function testJsonSerializeReturnsUnderlyingData(): void {
        $post = new TestPostModel(title: 'Hello', content: 'World');

        $json = json_encode($post, JSON_THROW_ON_ERROR);

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('content', $data);

        $this->assertSame('Hello', $data['title']);
        $this->assertSame('World', $data['content']);
    }

    public function testGenerateUniqueUuidRetriesOnCollision(): void {
        // Reset call counter
        UuidCollisionModel::$getByUuidCalls = 0;

        $model = new UuidCollisionModel();

        $uuid = $model->callGenerateUniqueUuid();

        $this->assertNotSame('', $uuid);

        // Ensure getByUUID was called at least twice:
        // first time returns a dummy instance (collision),
        // second time returns null (no collision).
        $this->assertGreaterThanOrEqual(2, UuidCollisionModel::$getByUuidCalls);
    }

    public function testGenerateUniqueUuidThrowsWhenUuidDisabled(): void {
        $model = new NoUuidSlugModel();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('UUID is not enabled for this model. Set');
        $model->callGenerateUniqueUuid();
    }

    public function testGenerateUniqueSlugThrowsWhenSlugDisabled(): void {
        $model = new NoUuidSlugModel();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Slug is not enabled for this model');
        $model->callGenerateUniqueSlug('dummy');
    }

    public function testGetByUUIDThrowsWhenUuidDisabled(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('UUID is not enabled for model');

        NoUuidSlugModel::getByUUID('foo');
    }

    public function testGetBySlugThrowsWhenSlugDisabled(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Slug is not enabled for this model');

        NoUuidSlugModel::getBySlug('foo');
    }

    public function testUpdateThrowsWhenIdNotSet(): void {
        $post = new TestPostModel(title: 'No ID yet');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ID not set – please use `save` method!');

        $post->update();
    }

    public function testDeleteThrowsWhenIdNotSet(): void {
        $post = new TestPostModel(title: 'Unsaved');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ID not set – seems to not be saved and so not deletable.');

        $post->delete();
    }

    public function testGetByIdThrowsWhenWpdbHasLastError(): void {
        $this->wpdb->last_error = 'simulated error';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('simulated error');

        TestPostModel::getByID(123);
    }

    public function testGetAllThrowsWhenWpdbHasLastError(): void {
        $this->wpdb->last_error = 'get_all_error';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('get_all_error');

        TestPostModel::getAll();
    }

    public function testSanitizeSlugThrowsWhenPregReplaceWrapperReturnsNull(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Slug could not be sanitized');

        SlugErrorModel::callSanitizeSlug('any-input');
    }

    public function testSaveUsesInsertIdWhenUuidDisabled(): void {
        $model = new NonUuidModel(title: 'No uuid');
        $tableName = $model->getTableName();

        $result = $model->save();

        $this->assertSame(1, $result);
        $this->assertSame(1, $model->id);
        $this->assertArrayHasKey($tableName, $this->wpdb->tableRows);
        $this->assertCount(1, $this->wpdb->tableRows[$tableName]);
        $this->assertSame('No uuid', $this->wpdb->tableRows[$tableName][0]['title']);
    }

    public function testSaveCallsUpdateWhenIdIsSet(): void {
        $tableName = $this->wpdb->prefix . 'non_uuid_posts';

        // Seed one row directly into the fake DB
        $this->wpdb->insert($tableName, ['title' => 'Original']);

        $existingId = $this->wpdb->insert_id;

        // Load the existing row as a model instance
        $loaded = NonUuidModel::getByID($existingId);
        $this->assertNotNull($loaded);

        // Change a field so update has something to do
        $loaded->title = 'Changed title';

        // save() must take the "id already set" -> update() branch
        $result = $loaded->save();

        $this->assertSame(1, $result);
        $this->assertSame('Changed title', $this->wpdb->tableRows[$tableName][0]['title']);
    }

    public function testSaveThrowsWhenReloadingInsertedRowFails(): void {
        $model = new FailingSaveModel(title: 'Will fail');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('save operation not successful.');

        $model->save();
    }

    public function testUpdateThrowsWhenReloadingRowFails(): void {
        // Create a model with an ID so update() doesn't throw the "ID not set" error
        $model = new FailingSaveModel(
            id: 1,
            title: 'Will fail on update'
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('update operation not successful.');

        $model->update();
    }

    public function testCanSetLastErrorWithoutResetInsideTest(): void {
        $this->assertSame('', $this->wpdb->last_error);
        $this->wpdb->last_error = 'simulated error in test';

        $this->assertSame('simulated error in test', $this->wpdb->last_error);
    }

    /**
     * @depends testCanSetLastErrorWithoutResetInsideTest
     */
    public function testLastErrorIsClearedBySetUpBetweenTests(): void {
        // If correctly reset after last_error thrown, this should be back to empty
        $this->assertSame('', $this->wpdb->last_error);
    }

    public function testGetByIdThrowsModelNotFoundExceptionWhenNoRowFound(): void {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model Macwinnie\WpDbPhinxHelper\Tests\Fixtures\TestPostModel with ID 999 not found');

        TestPostModel::getByID(999);
    }

    public function testRetrieveModelAttributesHydratesAttributesFromWpdbResults(): void {

        $user = new BasicUser(name: 'Max Muster', email: 'user@domain.tld');

        $this->assertSame(
            array_keys($this->wpdb->mockedSchemas[$user->getTableName()]),
            $user->getAttributes()
        );
    }

    public function testModelMethodQueryCreatesQueryBuilder(): void {
        $user = new BasicUser(name: "John Doe", email: "john@doe.tld");
        $sql = $user->query()->where('name', '=', 'John')->toSql();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('`name` = John', $sql);
    }

    public function testPregReplaceForSlugReturnsNullOnRegexError(): void {

        $rc = new \ReflectionClass(GenericModel::class);
        $rm = $rc->getMethod('pregReplaceForSlug');
        $rm->setAccessible(true);

        $result = @$rm->invoke(
            null,
            '/[a-z/',   // preg_replace() returns null due to invalid pattern
            '-',
            'test'
        );

        $this->assertNull($result);
    }

    public function testEnsureMandatoryThrowsWhenMandatoryFieldsMissing(): void {
        $modelClass = TestPostModel::class;
        $post = new TestPostModel(content: 'Lorem ipsum');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "{$modelClass}: missing mandatory attributes [id, uuid, slug, title, created_at, updated_at, content_hash]"
        );

        $post->ensureMandatory(skip_autoset: false);
    }

    public function testSaveThrowsSlugMissingUnlessSkipSlugEnabled(): void {
        $modelClass = TestPostModel::class;
        $post = new TestPostModel(content: 'Lorem ipsum');

        // Make it pass the first validation (set required fields / autoset fields as needed)
        // Adjust these to whatever your model expects.
        $post->id = 1;
        $post->uuid = 'u1';
        $post->title = 'Hello';
        $post->created_at = strtotime('now');
        $post->updated_at = strtotime('now');
        $post->setValue('content_hash', 'abc');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("{$modelClass}: missing mandatory attributes [slug]");

        $post->skipSlug = true;
        $post->save();
    }

    public function testModelMandatoryAttributesSetCorrect(): void {
        $modelClass = TestPostModel::class;

        // Read protected static properties via reflection
        $mandatory = $this->getProtected($modelClass, '__mandatory');
        $autoset = $this->getProtected($modelClass, '__autoset_attributes');

        // Create model with minimal data
        $post = new TestPostModel(title: 'Lorem', content: 'Lorem ipsum');

        // Assert initial mandatory & autoset attributes
        $this->assertSame(['title'], $mandatory->getValue($post));
        $this->assertSame(
            ['id', 'uuid', 'created_at', 'updated_at'],
            $autoset->getValue($post)
        );

        // No failure on only checking for "title" mandatory field
        $post->ensureMandatory(only: ["title"]);

        // But failure on full checking without auto generated
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "{$modelClass}: missing mandatory attributes [slug, content_hash]"
        );

        $post->ensureMandatory(skip_autoset: true);
    }
}
