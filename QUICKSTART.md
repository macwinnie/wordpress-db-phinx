# Quick Start Guide

This guide helps you quickly implement the refactored WP DB Phinx Helper with optional UUID and Slug support.

## Table of Contents

- [Installation](#installation)
- [Basic Setup](#basic-setup)
- [Feature Flags](#feature-flags)
- [Model Implementation](#model-implementation)
- [Database Migrations](#database-migrations)
- [Usage Examples](#usage-examples)
- [Testing Your Implementation](#testing-your-implementation)

## Installation

### 1. Install via Composer

```bash
composer require macwinnie/wp-db-phinx-helper
```

### 2. Set Up Your Plugin Structure

```
your-plugin/
├── composer.json
├── your-plugin.php
├── src/
│   ├── Models/
│   │   └── YourModel.php
│   └── DBSetup.php
└── db/
    ├── migrations/
    └── seeds/
```

## Basic Setup

### 1. Create Your DBUtilisator Implementation

Create `src/DBSetup.php`:

```php
<?php

namespace YourPlugin;

use Macwinnie\WpDbPhinxHelper\DBUtilisator;

class DBSetup extends DBUtilisator {
    protected static function get_plugin_dir(): string {
        return YOUR_PLUGIN_DIR; // Your plugin constant
    }
}
```

### 2. Hook Into WordPress

In your main plugin file `your-plugin.php`:

```php
<?php
/**
 * Plugin Name: Your Plugin
 * Description: Your plugin description
 * Version: 1.0.0
 */

// Define plugin directory constant
define('YOUR_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Load Composer autoloader
require_once YOUR_PLUGIN_DIR . 'vendor/autoload.php';

use YourPlugin\DBSetup;

// Register activation hook
register_activation_hook(__FILE__, [DBSetup::class, 'plugin_activation_method']);

// Register uninstall hook
register_uninstall_hook(__FILE__, [DBSetup::class, 'plugin_uninstall_method']);
```

## Feature Flags

The refactored Model class supports optional features via class properties:

### Available Features

```php
protected static bool $useUuid = false;  // Enable UUID field
protected static bool $useSlug = false;  // Enable slug field
```

### Feature Comparison

| Feature | When to Use | Methods Available |
|---------|-------------|-------------------|
| **No UUID/Slug** | Simple tables | `getByID()`, `getAll()` |
| **UUID Only** | External API integration, distributed systems | `getByID()`, `getByUUID()`, `getAll()` |
| **Slug Only** | Public-facing URLs, SEO-friendly paths | `getByID()`, `getBySlug()`, `getAll()` |
| **Both** | Complex systems needing both API IDs and URLs | `getByID()`, `getByUUID()`, `getBySlug()`, `getAll()` |

## Model Implementation

### Scenario 1: Basic Model (No UUID/Slug)

**Use Case**: Internal data, simple relationships

```php
<?php

namespace YourPlugin\Models;

use Macwinnie\WpDbPhinxHelper\Model;

/**
 * @property int    $id
 * @property string $name
 * @property string $email
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class User extends Model {
    protected static string $tablename = "yourplugin_users";
    protected static array $attributes = [];
}
```

**Migration**:

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateUserTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('yourplugin_users', [
            'id' => false,
            'primary_key' => ['id'],
        ]);
        
        $table->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
              ->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('email', 'string', ['limit' => 255])
              ->addTimestamps()
              ->addIndex(['email'], ['unique' => true])
              ->create();
    }
}
```

### Scenario 2: UUID Model

**Use Case**: External API integration, microservices, distributed systems

```php
<?php

namespace YourPlugin\Models;

use Macwinnie\WpDbPhinxHelper\Model;

/**
 * @property int    $id
 * @property string $uuid
 * @property string $name
 * @property float  $price
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class Product extends Model {
    protected static string $tablename = "yourplugin_products";
    protected static bool $useUuid = true;
    protected static array $attributes = [];
}
```

**Migration**:

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateProductTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('yourplugin_products', [
            'id' => false,
            'primary_key' => ['id'],
        ]);
        
        $table->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
              ->addColumn('uuid', 'string', ['limit' => 36])
              ->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
              ->addTimestamps()
              ->addIndex(['uuid'], ['unique' => true])
              ->create();
    }
}
```

### Scenario 3: Slug Model

**Use Case**: Blog posts, pages, public content with SEO-friendly URLs

```php
<?php

namespace YourPlugin\Models;

use Macwinnie\WpDbPhinxHelper\Model;

/**
 * @property int    $id
 * @property string $slug
 * @property string $title
 * @property string $content
 * @property bool   $published
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class Article extends Model {
    protected static string $tablename = "yourplugin_articles";
    protected static bool $useSlug = true;
    protected static array $attributes = [];
    
    /**
     * Auto-generate slug from title
     */
    public function save(): mixed {
        if (!isset($this->data['slug']) && isset($this->data['title'])) {
            $this->setValue('slug', $this->generateUniqueSlug($this->data['title']));
        }
        
        return parent::save();
    }
}
```

**Migration**:

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateArticleTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('yourplugin_articles', [
            'id' => false,
            'primary_key' => ['id'],
        ]);
        
        $table->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
              ->addColumn('slug', 'string', ['limit' => 250])
              ->addColumn('title', 'string', ['limit' => 255])
              ->addColumn('content', 'text')
              ->addColumn('published', 'boolean', ['default' => false])
              ->addTimestamps()
              ->addIndex(['slug'], ['unique' => true])
              ->create();
    }
}
```

### Scenario 4: Full Model (UUID + Slug)

**Use Case**: Complex systems requiring both API identifiers and public URLs

```php
<?php

namespace YourPlugin\Models;

use Macwinnie\WpDbPhinxHelper\Model;

/**
 * @property int    $id
 * @property string $uuid
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property bool   $is_active
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
final class Category extends Model {
    protected static string $tablename = "yourplugin_categories";
    protected static bool $useUuid = true;
    protected static bool $useSlug = true;
    protected static array $attributes = [];
    
    /**
     * Auto-generate slug from name
     */
    public function save(): mixed {
        if (!isset($this->data['slug']) && isset($this->data['name'])) {
            $this->setValue('slug', $this->generateUniqueSlug($this->data['name']));
        }
        
        return parent::save();
    }
}
```

**Migration**:

```php
<?php

use Phinx\Migration\AbstractMigration;

final class CreateCategoryTable extends AbstractMigration {
    public function change(): void {
        $table = $this->table('yourplugin_categories', [
            'id' => false,
            'primary_key' => ['id'],
        ]);
        
        $table->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
              ->addColumn('uuid', 'string', ['limit' => 36])
              ->addColumn('slug', 'string', ['limit' => 250])
              ->addColumn('name', 'string', ['limit' => 255])
              ->addColumn('description', 'text', ['null' => true])
              ->addColumn('is_active', 'boolean', ['default' => true])
              ->addTimestamps()
              ->addIndex(['uuid'], ['unique' => true])
              ->addIndex(['slug'], ['unique' => true])
              ->create();
    }
}
```

## Usage Examples

### Basic CRUD Operations

```php
<?php

use YourPlugin\Models\User;

// CREATE
$user = new User(
    'name' => 'John Doe',
    'email' => 'john@example.com'
);
$user->save();

// READ
$foundUser = User::getByID(1);
echo $foundUser->name; // "John Doe"

// UPDATE
$foundUser->name = 'Jane Doe';
$foundUser->save();

// DELETE
$foundUser->delete();

// GET ALL
$allUsers = User::getAll();
foreach ($allUsers as $user) {
    echo $user->name;
}
```

### UUID Operations

```php
<?php

use YourPlugin\Models\Product;

// Create with auto-generated UUID
$product = new Product(
    'name' => 'Widget',
    'price' => 29.99
);
$product->save();

// UUID is automatically generated
$uuid = $product->uuid;
echo $uuid; // "123e4567-e89b-12d3-a456-426614174000"

// Retrieve by UUID
$foundProduct = Product::getByUUID($uuid);

// Use UUID for API responses
$apiResponse = [
    'id' => $foundProduct->uuid,
    'name' => $foundProduct->name,
    'price' => $foundProduct->price
];
```

### Slug Operations

```php
<?php

use YourPlugin\Models\Article;

// Create with auto-generated slug
$article = new Article(
    'title' => 'My First Article',
    'content' => 'This is the content...',
    'published' => true
);
$article->save();

// Slug is automatically generated from title
echo $article->slug; // "my-first-article"

// Retrieve by slug for SEO-friendly URLs
$slug = $_GET['article'] ?? 'my-first-article';
$foundArticle = Article::getBySlug($slug);

// Use in WordPress permalinks
$permalink = home_url("/articles/{$foundArticle->slug}");
```

### Combined UUID and Slug

```php
<?php

use YourPlugin\Models\Category;

// Create with both UUID and slug
$category = new Category(
    'name' => 'Electronics & Gadgets',
    'description' => 'Electronic products and gadgets',
    'is_active' => true
);
$category->save();

// Both are auto-generated
echo $category->uuid; // "123e4567-e89b-12d3-a456-426614174000"
echo $category->slug; // "electronics-gadgets"

// API endpoint using UUID
// GET /api/categories/123e4567-e89b-12d3-a456-426614174000
$apiCategory = Category::getByUUID($_GET['uuid']);

// Public URL using slug
// GET /shop/electronics-gadgets
$publicCategory = Category::getBySlug($_GET['category']);

// Both return the same record
assert($apiCategory->id === $publicCategory->id);
```

### Error Handling

```php
<?php

use YourPlugin\Models\Product;

// Attempting UUID operation on non-UUID model
try {
    $user = User::getByUUID('some-uuid');
} catch (\Exception $e) {
    // Exception: "UUID is not enabled for this model..."
    error_log($e->getMessage());
}

// Attempting slug operation on non-slug model
try {
    $product = Product::getBySlug('some-slug');
} catch (\Exception $e) {
    // Exception: "Slug is not enabled for this model..."
    error_log($e->getMessage());
}
```

## Testing Your Implementation

### 1. Unit Test Example

```php
<?php

namespace YourPlugin\Tests;

use PHPUnit\Framework\TestCase;
use YourPlugin\Models\Category;
use Mockery;

class CategoryTest extends TestCase {
    protected $wpdb;

    protected function setUp(): void {
        parent::setUp();
        
        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_slug_from_name() {
        $this->wpdb->shouldReceive('insert')->once()->andReturn(1);
        $this->wpdb->shouldReceive('get_row')->andReturn(null);
        $this->wpdb->shouldReceive('get_row')->andReturn([
            'id' => 1,
            'uuid' => 'test-uuid',
            'slug' => 'electronics',
            'name' => 'Electronics'
        ]);
        
        $category = new Category('name' => 'Electronics');
        $category->save();
        
        $this->assertEquals('electronics', $category->slug);
    }
}
```

### 2. Run Your Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run static analysis
composer analyse
```

## Common Patterns

### 1. Custom Slug Generation

```php
class Article extends Model {
    protected static bool $useSlug = true;
    
    public function save(): mixed {
        // Custom slug logic
        if (!isset($this->data['slug'])) {
            $baseSlug = $this->data['category'] . '-' . $this->data['title'];
            $this->setValue('slug', $this->generateUniqueSlug($baseSlug));
        }
        
        return parent::save();
    }
}
```

### 2. Additional Non-Editable Fields

```php
class User extends Model {
    protected static bool $useUuid = true;
    
    // Add custom non-editable fields
    protected static array $noneditable = [
        'email_verified_at',
        'last_login_at',
    ];
}
```

### 3. Validation Before Save

```php
class Product extends Model {
    public function save(): mixed {
        // Validate before save
        if (empty($this->data['name'])) {
            throw new \Exception('Product name is required');
        }
        
        if ($this->data['price'] < 0) {
            throw new \Exception('Price must be positive');
        }
        
        return parent::save();
    }
}
```
