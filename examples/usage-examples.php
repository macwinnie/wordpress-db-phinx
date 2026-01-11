<?php

/**
 * Complete usage examples for WP DB Phinx Helper
 *
 * This file demonstrates all major use cases.
 * Run with: php examples/usage-examples.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Mock WordPress environment for examples
require_once __DIR__ . '/../tests/bootstrap.php';
$wpdb = $GLOBALS['wpdb'];

use Macwinnie\WpDbPhinxHelper\Examples\Models\{
    AuthUser,
    BasicUser,
    Category,
    Post,
    Product
};

echo "WP DB Phinx Helper - Usage Examples\n";
echo "===================================\n\n";

// Example 1: Basic Model
echo "1. Basic Model (No UUID/Slug)\n";
echo "------------------------------\n";
$user = new BasicUser(
    'John Doe',
    'john@example.com'
);
echo "Created user: {$user->name}\n\n";

// Example 2: UUID Model
echo "2. UUID Model\n";
echo "-------------\n";
$product = new Product(
    'Widget',
    29.99
);
$product->save();
echo "Product will have UUID: {$product->uuid}\n\n";

// Example 3: Slug Model
echo "3. Slug Model\n";
echo "-------------\n";
$post = new Post(
    'My First Post',
    'Content here...'
);
$post->save();
echo "Post shall have slug “my-first-post”: {$post->slug}\n\n";

// Example 4: Both UUID and Slug
echo "4. Model with UUID and Slug\n";
echo "---------------------------\n";
$category = new Category(
    'Electronics',
    'Electronic products'
);
$category->save();
echo "Category has both UUID and slug:\nUUID: {$category->uuid}\nSlug: {$category->slug}\n\n";

// Example 5: Custom non-editable fields
echo "5. Custom Non-Editable Fields\n";
echo "-----------------------------\n";
$authUser = new AuthUser(
    'auth@example.com'
);
echo "Auth user with protected fields – setting `email_verified_at` once won't throw an error, second time should:\n";
$authUser->email_verified_at = strtotime("now - 10minutes");

try {
    $authUser->email_verified_at = strtotime("now");
    echo "This line should never be printed!\n";
} catch (\Exception $e) {
    echo "As expected, the trial to update `email_verified_at` after first set failed!\n\n";
}

echo "All examples completed!\n";
