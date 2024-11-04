# Lithe Cache 

**Lithe Cache** is a simple and efficient caching module that utilizes the filesystem for storage. With it, you can store and retrieve data quickly, improving the performance of your application.

## Installation

To install the `lithemod/cache` module, use Composer. Run the following command in the root directory of your project:

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

### 4. Checking for Data Existence in Cache

To check if a cache entry exists and is valid, you can use the `has` method, which now accepts both a single key and an array of keys:

```php
// Check if a single key exists
if (Cache::has('my_data')) {
    echo "Data is in cache.";
}

// Check multiple keys
if (Cache::has(['key1', 'key2'])) {
    echo "All keys are in cache.";
} else {
    echo "One or more keys were not found or are expired.";
}
```

### 5. Invalidating Cache Data

If you need to remove data from the cache, use the `invalidate` method. You can now invalidate a single key or an array of keys:

```php
// Invalidate a single cache key
Cache::invalidate('my_data');

// Invalidate multiple keys
Cache::invalidate(['key1', 'key2', 'key3']);
```

### 6. Using `remember`

The `remember` method allows you to retrieve data from the cache or execute a callback to fetch fresh data if not found in the cache:

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
- **`Cache::has($key)`**: Checks if a single or multiple cache entries exist.
- **`Cache::invalidate($key)`**: Removes specific data from the cache.
- **`Cache::invalidate(array $keys)`**: Removes multiple cache keys.
- **`Cache::clear()`**: Clears all cached data.
- **`Cache::remember($key, $callback, $expiration, $serializer)`**: Retrieves data from the cache or executes a callback to fetch and store new data.

## Final Considerations

- **Permissions**: Ensure that the cache directory has appropriate write permissions to avoid access issues.
- **Serialization Methods**: `Lithe Cache` supports both `serialize` and `json` for serializing data before storing it. Choose the method that fits your application's needs.
- **Directory Structure**: `Lithe Cache` organizes cache files into subdirectories to facilitate searching and improve performance in large directories.

With `Lithe Cache`, you have a lightweight and easy-to-use solution for caching that can be integrated into various PHP applications, providing improved performance and a smoother user experience.