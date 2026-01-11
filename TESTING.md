# Testing Guide for WP DB Phinx Helper

This guide provides comprehensive information on testing the WP DB Phinx Helper library.

## Table of Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Running Tests](#running-tests)
- [Test Coverage](#test-coverage)
- [Writing Tests](#writing-tests)
- [Continuous Integration](#continuous-integration)

## Overview

The test suite consists of:

- **Unit Tests**: Test individual methods and components in isolation
- **Integration Tests**: Test the interaction between components and workflows
- **100% Code Coverage Goal**: All code paths are tested

### Test Structure

```
tests/
├── bootstrap.php             # Test bootstrap and setup
├── Unit/
│   ├── ModelTest.php         # Model class unit tests
│   └── DBUtilisatorTest.php  # DBUtilisator class unit tests
└── Integration/
    ├── ModelIntegrationTest.php
    └── DBUtilisatorIntegrationTest.php
```

## Prerequisites

### Required Dependencies

```json
{
  "require-dev": {
    "phpunit/phpunit": "^10.5",
    "mockery/mockery": "^1.6",
    "mikey179/vfsstream": "^1.6"
  }
}
```

### System Requirements

- PHP 8.1 or higher
- Composer
- Xdebug (for code coverage)

## Installation

### 1. Install Dependencies

```bash
composer install --dev
```

### 2. Configure Xdebug (for coverage)

Add to your `php.ini`:

```ini
zend_extension=xdebug.so
xdebug.mode=coverage
```

Or run with environment variable:

```bash
XDEBUG_MODE=coverage
```

## Running Tests

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test Suite

```bash
# Unit tests only
vendor/bin/phpunit --testsuite "Unit Tests"

# Integration tests only
vendor/bin/phpunit --testsuite "Integration Tests"
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/ModelTest.php
```

### Run Specific Test Method

```bash
vendor/bin/phpunit --filter test_method_name
```

### Run with Coverage

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage/html
```

Then open `coverage/html/index.html` in your browser.

### Run with Text Coverage

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text
```

## Test Coverage

### Current Coverage Goals

- **Line Coverage**: 100%
- **Branch Coverage**: 100%
- **Method Coverage**: 100%

### Viewing Coverage Reports

#### HTML Report

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage/html
open coverage/html/index.html
```

#### Clover XML Report (for CI)

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover coverage/clover.xml
```

#### Coverage Summary

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text
```

### Coverage Thresholds

The test suite enforces minimum coverage thresholds. Builds will fail if coverage drops below:

- Lines: 95%
- Functions: 95%
- Classes: 95%

## Writing Tests

### Test Naming Conventions

```php
/** @test */
public function it_describes_what_the_test_does() {
    // Test implementation
}
```

### Model Tests Example

```php
<?php

namespace Macwinnie\WpDbPhinxHelper\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;

class MyModelTest extends TestCase {
    protected $wpdb;

    protected function setUp(): void {
        parent::setUp();
        
        // Mock WordPress $wpdb
        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_a_record() {
        $this->wpdb->shouldReceive('insert')
            ->once()
            ->andReturn(1);
        
        // Your test logic here
    }
}
```

### Mocking WordPress Functions

WordPress functions are mocked in `tests/bootstrap.php`:

```php
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}
```

### Testing Optional Features (UUID/Slug)

```php
/** @test */
public function it_throws_exception_when_uuid_not_enabled() {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage("UUID is not enabled");
    
    BasicModel::getByUUID('test-uuid');
}

/** @test */
public function it_generates_uuid_when_enabled() {
    $model = new UuidModel('name' => 'Test');
    $model->save();
    
    $uuid = $model->getAttribute('uuid');
    $this->assertNotNull($uuid);
    $this->assertMatchesRegularExpression(
        '/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}/',
        $uuid
    );
}
```

### Testing Database Operations

```php
/** @test */
public function it_saves_a_new_record() {
    // Arrange
    $this->wpdb->shouldReceive('insert')
        ->once()
        ->with(
            'wp_test_table',
            Mockery::on(function($data) {
                return $data['name'] === 'Test';
            })
        )
        ->andReturn(1);
    
    $this->wpdb->insert_id = 1;
    
    // Act
    $model = new TestModel('name' => 'Test');
    $result = $model->save();
    
    // Assert
    $this->assertEquals(1, $result);
}
```

### Testing Error Conditions

```php
/** @test */
public function it_throws_exception_on_invalid_operation() {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage("Expected error message");
    
    // Code that should throw exception
}
```

## Test Best Practices

### 1. AAA Pattern

Structure tests using Arrange-Act-Assert:

```php
/** @test */
public function it_does_something() {
    // Arrange: Set up test data and mocks
    $model = new TestModel('name' => 'Test');
    
    // Act: Execute the code being tested
    $result = $model->save();
    
    // Assert: Verify the outcome
    $this->assertEquals(1, $result);
}
```

### 2. One Assertion Per Test

Each test should verify one behavior:

```php
/** @test */
public function it_creates_uuid() {
    $model = new UuidModel();
    $model->save();
    
    $this->assertNotNull($model->getAttribute('uuid'));
}

/** @test */
public function it_creates_unique_uuid() {
    $model1 = new UuidModel();
    $model1->save();
    
    $model2 = new UuidModel();
    $model2->save();
    
    $this->assertNotEquals(
        $model1->getAttribute('uuid'),
        $model2->getAttribute('uuid')
    );
}
```

### 3. Test Edge Cases

Always test boundary conditions:

```php
/** @test */
public function it_handles_empty_string_slug() {
    $slug = TestModel::sanitizeSlug('');
    $this->assertEquals('', $slug);
}

/** @test */
public function it_truncates_long_slug() {
    $longString = str_repeat('a', 300);
    $slug = TestModel::sanitizeSlug($longString);
    
    $this->assertEquals(250, strlen($slug));
}
```

### 4. Clean Up After Tests

```php
protected function tearDown(): void {
    Mockery::close(); // Clean up Mockery mocks
    parent::tearDown();
}
```

## Continuous Integration

### GitHub Actions Example

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: ['8.1', '8.2', '8.3']
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: mbstring, xml, ctype, json
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run tests
        run: vendor/bin/phpunit --coverage-clover coverage.xml
      
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml
```

### GitLab CI Example

Create `.gitlab-ci.yml`:

```yaml
test:
  image: php:8.1
  
  before_script:
    - apt-get update
    - apt-get install -y git unzip
    - curl -sS https://getcomposer.org/installer | php
    - php composer.phar install
  
  script:
    - php vendor/bin/phpunit --coverage-text --colors=never
  
  coverage: '/^\s*Lines:\s*\d+\.\d+\%/'
```

## Troubleshooting

### Common Issues

#### 1. "Class 'wpdb' not found"

Make sure `$GLOBALS['wpdb']` is set in your test setup:

```php
protected function setUp(): void {
    $this->wpdb = Mockery::mock('wpdb');
    $GLOBALS['wpdb'] = $this->wpdb;
}
```

#### 2. "Mockery expectations not met"

Ensure you call `Mockery::close()` in tearDown:

```php
protected function tearDown(): void {
    Mockery::close();
    parent::tearDown();
}
```

#### 3. Code Coverage Not Working

Install and enable Xdebug:

```bash
pecl install xdebug
```

Add to php.ini:

```ini
zend_extension=xdebug.so
xdebug.mode=coverage
```

#### 4. Tests Running Slowly

Run tests in parallel (PHPUnit 10+):

```bash
vendor/bin/phpunit --parallel
```

## Performance Testing

### Benchmarking Tests

```php
/** @test */
public function it_performs_within_acceptable_time() {
    $start = microtime(true);
    
    // Run operation 1000 times
    for ($i = 0; $i < 1000; $i++) {
        $model = new TestModel();
    }
    
    $duration = microtime(true) - $start;
    
    $this->assertLessThan(1.0, $duration, 'Operation took too long');
}
```

## Code Quality Tools

### PHPStan (Static Analysis)

```bash
composer require --dev phpstan/phpstan
vendor/bin/phpstan analyse src tests --level=8
```

### PHP CS Fixer (Code Style)

```bash
composer require --dev friendsofphp/php-cs-fixer
vendor/bin/php-cs-fixer fix
```

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Mockery Documentation](http://docs.mockery.io/)
- [VfsStream Documentation](https://github.com/bovigo/vfsStream)
- [WordPress Plugin Testing](https://make.wordpress.org/cli/handbook/plugin-unit-tests/)

## Contributing

When contributing, ensure:

1. All tests pass: `vendor/bin/phpunit`
2. Code coverage remains at 100%
3. New features include tests
4. Tests follow naming conventions
5. Tests are well-documented

## License

Tests are part of the WP DB Phinx Helper package and share the same license.
