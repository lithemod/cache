# Lithe Cache

This module provides a simple caching mechanism using the filesystem.

## Installation

To install the `lithemod/cache` module, you can use Composer. Run the following command in the root directory of your project:

```bash
composer require lithemod/cache
```

## Usage

After installation, you can use the `Cache` class to store and retrieve cached data. Here’s an example of how to set up and use the cache:

### 1. Configuring the Cache Directory

Before using the cache, you must define the directory where the cached data will be stored. You can do this by calling the `dir` method of the `Cache` class:

```php
use Lithe\Support\Cache;

// Define the cache directory
Cache::dir(__DIR__ . '/cache');
```

### 2. Storing Data in the Cache

To store data in the cache, use the `add` method. You can specify a key, the data to be stored, the expiration time, and the serializer to use:

```php
// Add data to the cache
Cache::add('my_data', ['foo' => 'bar'], 3600, 'serialize');
```

### 3. Retrieving Data from the Cache

To retrieve stored data, use the `get` method:

```php
// Retrieve data from the cache
$data = Cache::get('my_data');

if ($data === null) {
    echo "Data not found or expired.";
} else {
    print_r($data);
}
```

### 4. Invalidating Cache Data

If you need to remove data from the cache, use the `invalidate` method:

```php
// Invalidate the cached data
Cache::invalidate('my_data');
```

### 5. Using `remember`

You can also use the `remember` method to cache data or execute a callback to fetch fresh data if not found in the cache:

```php
$data = Cache::remember('my_key', function () {
    // Logic to fetch data if not in cache
    return ['foo' => 'bar'];
}, 3600, 'serialize');

print_r($data);
```

## Available Methods

- `Cache::dir($dir)`: Sets the cache directory.
- `Cache::add($key, $data, $expiration, $serializer)`: Stores data in the cache.
- `Cache::get($key)`: Retrieves data from the cache.
- `Cache::invalidate($key)`: Removes data from the cache.
- `Cache::remember($key, $callback, $expiration, $serializer)`: Retrieves data from the cache or executes a callback to fetch and store fresh data.

## Final Considerations

Make sure the cache directory has appropriate write permissions to avoid access issues. This module is a simple solution for caching and can be used in various PHP applications.