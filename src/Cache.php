<?php

namespace Lithe\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * This class provides a simple caching mechanism using the filesystem.
 */
class Cache
{
    /**
     * @var string|null Cache directory path
     */
    private static $cacheDir = null;

    /**
     * @var array Map of supported serialization formats and their functions
     */
    private static $serializerMap = [
        'serialize' => ['serialize', 'unserialize'],
        'json' => ['json_encode', 'json_decode'],
        'yaml' => ['yaml_emit', 'yaml_parse'], // Assuming YAML serialization is available
    ];

    /**
     * Set the cache directory.
     *
     * @param string $dir The directory path to use for cache files.
     * @return void
     */
    public static function dir($dir)
    {
        self::$cacheDir = $dir;
        self::ensureCacheDirExists();
    }

    /**
     * Ensure the cache directory exists and is writable.
     *
     * @throws RuntimeException If the cache directory cannot be created or is not writable.
     * @return void
     */
    private static function ensureCacheDirExists()
    {
        self::$cacheDir = self::$cacheDir ?: dirname(__DIR__, 4) . '/storage/framework/cache';

        if (!is_dir(self::$cacheDir)) {
            if (!mkdir(self::$cacheDir, 0777, true) && !is_dir(self::$cacheDir)) {
                throw new RuntimeException("Failed to create cache directory: " . self::$cacheDir);
            }
        }

        // Check if the directory is writable
        if (!is_writable(self::$cacheDir)) {
            throw new RuntimeException("Cache directory is not writable: " . self::$cacheDir);
        }
    }

    /**
     * Get the cache file path for the given key.
     *
     * @param string $key The cache key.
     * @return string The path to the cache file.
     */
    private static function getCacheFile($key)
    {
        return self::$cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    /**
     * Add a new cache entry.
     *
     * @param string $key The cache key.
     * @param mixed $data The data to store.
     * @param int $expiration The cache expiration time in seconds.
     * @param string $serializer The serialization format to use.
     * @throws RuntimeException If writing to the cache file fails.
     * @return void
     */
    public static function add($key, $data, $expiration = 3600, $serializer = 'serialize')
    {
        self::ensureCacheDirExists();

        $cacheFile = self::getCacheFile($key);

        $cacheData = [
            'expiration' => time() + $expiration,
            'data' => self::serializeData($data, $serializer),
            'serializer' => $serializer,
        ];

        // Use error suppression with '@' and check the result.
        $bytesWritten = @file_put_contents($cacheFile, json_encode($cacheData), LOCK_EX);
        if ($bytesWritten === false) {
            throw new RuntimeException("Failed to write cache data to file: $cacheFile");
        }

        // Double-check file existence and readability after writing
        if (!file_exists($cacheFile) || !is_readable($cacheFile)) {
            throw new RuntimeException("Cache file is not accessible after writing: $cacheFile");
        }
    }

    /**
     * Serialize data using the specified format.
     *
     * @param mixed $data The data to serialize.
     * @param string $serializer The serialization format to use.
     * @throws InvalidArgumentException If an invalid serializer is provided.
     * @return string The serialized data.
     */
    private static function serializeData($data, $serializer)
    {
        if (!isset(self::$serializerMap[$serializer])) {
            throw new InvalidArgumentException("Invalid serializer: $serializer");
        }

        $serializerFunction = self::$serializerMap[$serializer][0];
        return call_user_func($serializerFunction, $data);
    }

    /**
     * Unserialize data using the specified format.
     *
     * @param string $serializedData The serialized data.
     * @param string $serializer The serialization format to use.
     * @throws InvalidArgumentException If an invalid serializer is provided.
     * @return mixed The unserialized data.
     */
    private static function unserializeData($serializedData, $serializer)
    {
        if (!isset(self::$serializerMap[$serializer])) {
            throw new InvalidArgumentException("Invalid serializer: $serializer");
        }

        $unserializerFunction = self::$serializerMap[$serializer][1];

        if ($serializer === 'json') {
            return call_user_func($unserializerFunction, $serializedData, true);
        }

        return call_user_func($unserializerFunction, $serializedData);
    }

    /**
     * Retrieve a cache entry by key.
     *
     * @param string $key The cache key.
     * @throws RuntimeException If the cache file is not readable or cannot be accessed.
     * @return mixed|null The cached data, or null if the entry is expired or does not exist.
     */
    public static function get($key)
    {
        self::ensureCacheDirExists();

        $cacheFile = self::getCacheFile($key);

        if (!file_exists($cacheFile)) {
            return null;
        }

        // Ensure file is readable before trying to get contents
        if (!is_readable($cacheFile)) {
            throw new RuntimeException("Cache file is not readable: $cacheFile");
        }

        $content = @file_get_contents($cacheFile);

        if ($content === false) {
            throw new RuntimeException("Failed to read cache file: $cacheFile");
        }

        $cacheData = json_decode($content, true);

        if (!is_array($cacheData)) {
            unlink($cacheFile);
            return null;
        }

        if (time() > $cacheData['expiration']) {
            unlink($cacheFile);
            return null;
        }

        return self::unserializeData($cacheData['data'], $cacheData['serializer']);
    }

    /**
     * Invalidate a cache entry by key.
     *
     * @param string $key The cache key.
     * @throws RuntimeException If the cache file is not writable or cannot be deleted.
     * @return void
     */
    public static function invalidate($key)
    {
        self::ensureCacheDirExists();
        
        $cacheFile = self::getCacheFile($key);

        if (file_exists($cacheFile)) {
            // Verify that the file is writable before trying to delete
            if (!is_writable($cacheFile)) {
                throw new RuntimeException("Cache file is not writable: $cacheFile");
            }

            if (!unlink($cacheFile)) {
                throw new RuntimeException("Failed to delete cache file: $cacheFile");
            }
        }
    }

    /**
     * Retrieve a cache entry or store the result of a callback if not found.
     *
     * @param string $key The cache key.
     * @param callable $callback A callback to generate the cache value if it doesn't exist.
     * @param int $expiration The cache expiration time in seconds.
     * @param string $serializer The serialization format to use.
     * @return mixed The cached data or the result of the callback.
     */
    public static function remember($key, $callback, $expiration = 3600, $serializer = 'serialize')
    {
        $cachedData = self::get($key);

        if ($cachedData === null) {
            $cachedData = $callback();
            self::add($key, $cachedData, $expiration, $serializer);
        }

        return $cachedData;
    }
}
