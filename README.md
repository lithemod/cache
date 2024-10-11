# Lithe Cache 

**Lithe Cache** is a simple and efficient caching module that utilizes the filesystem. With it, you can store and retrieve data quickly and easily, enhancing the performance of your application.

## Installation

To install the `lithemod/cache` module, you can use Composer. Run the following command in the root directory of your project:

```bash
composer require lithemod/cache
```

## Usage

After installation, you can use the `Cache` class to store and retrieve cached data. Follow these steps:

### 1. Configuring the Cache Directory

Before using the cache, define the directory where cached data will be stored. You can do this by calling the `dir` method of the `Cache` class:

```php
use Lithe\Support\Cache;

// Define the cache directory
Cache::dir(__DIR__ . '/cache');
```

### 2. Storing Data in the Cache

To store data, use the `add` method. You can specify a key, the data to be stored, the expiration time, and the serializer to use:

```php
// Add data to the cache
Cache::add('my_data', ['foo' => 'bar'], 3600, 'serialize'); // Using serialize
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

If you need to remove data from the cache, use the `invalidate` method. You can now invalidate a single key or an array of keys!

```php
// Invalidate cached data
Cache::invalidate('my_data'); // For a single key

// Invalidate multiple keys
Cache::invalidate(['key1', 'key2', 'key3']);
```

### 5. Using `remember`

You can also use the `remember` method to cache data or execute a callback to fetch fresh data if not found in the cache:

```php
$data = Cache::remember('my_key', function () {
    // Logic to fetch data if not in cache
    return ['foo' => 'bar'];
}, 3600, 'serialize'); // Using serialize

print_r($data);
```

## Available Methods

- **`Cache::dir($dir)`**: Sets the cache directory.
- **`Cache::add($key, $data, $expiration, $serializer)`**: Stores data in the cache.
- **`Cache::get($key)`**: Retrieves data from the cache.
- **`Cache::invalidate($key)`**: Removes data from the cache.
- **`Cache::invalidate(array $keys)`**: Removes multiple cache keys.
- **`Cache::remember($key, $callback, $expiration, $serializer)`**: Retrieves data from the cache or executes a callback to fetch and store fresh data.

## Final Considerations

Make sure the cache directory has appropriate write permissions to avoid access issues. This module provides a simple solution for caching and can be used in various PHP applications, delivering improved performance and a smoother user experience.