<?php

namespace Lithe\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * This class provides a simple caching mechanism using the filesystem.
 */
class Cache
{
    private static $cacheDir = null;

    private static $serializerMap = [
        'serialize' => ['serialize', 'unserialize'],
        'json' => ['json_encode', 'json_decode'],
        'yaml' => ['yaml_emit', 'yaml_parse'], // Assuming YAML serialization is available
    ];

    /**
     * Set the cache directory.
     *
     * @param string $dir The directory where cache files will be stored.
     */
    public static function dir($dir)
    {
        self::$cacheDir = $dir;
        self::ensureCacheDirExists();
    }

    /**
     * Ensure that the cache directory exists, creating it if necessary.
     *
     * @throws RuntimeException if the cache directory cannot be created.
     */
    private static function ensureCacheDirExists()
    {
        self::$cacheDir = self::$cacheDir ?: dirname(__DIR__, 4) . '/storage/framework/cache';

        if (!is_dir(self::$cacheDir)) {
            if (!mkdir(self::$cacheDir, 0777, true) && !is_dir(self::$cacheDir)) {
                throw new RuntimeException("Failed to create cache directory: " . self::$cacheDir);
            }
        }
    }

    /**
     * Get the path to the cache file for a given key.
     *
     * @param string $key The key for which to retrieve the cache file path.
     * @return string The cache file path.
     */
    private static function getCacheFile($key)
    {
        return self::$cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    /**
     * Add data to the cache.
     *
     * @param string $key The key under which the data will be stored.
     * @param mixed $data The data to be cached.
     * @param int $expiration The expiration time in seconds (default is 3600).
     * @param string $serializer The serializer to use (default is 'serialize').
     * @throws RuntimeException if the cache data cannot be written to file.
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

        if (file_put_contents($cacheFile, json_encode($cacheData), LOCK_EX) === false) {
            throw new RuntimeException("Failed to write cache data to file: $cacheFile");
        }
    }

    /**
     * Serialize data using the specified serializer.
     *
     * @param mixed $data The data to be serialized.
     * @param string $serializer The serializer to use.
     * @return string The serialized data.
     * @throws InvalidArgumentException if an invalid serializer is specified.
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
     * Unserialize data using the specified serializer.
     *
     * @param string $serializedData The serialized data to be unserialized.
     * @param string $serializer The serializer to use.
     * @return mixed The unserialized data.
     * @throws InvalidArgumentException if an invalid serializer is specified.
     */
    private static function unserializeData($serializedData, $serializer)
    {
        if (!isset(self::$serializerMap[$serializer])) {
            throw new InvalidArgumentException("Invalid serializer: $serializer");
        }

        $unserializerFunction = self::$serializerMap[$serializer][1];

        if ($serializer === 'json') {
            return call_user_func($unserializerFunction, $serializedData, true); // true for associative
        }

        return call_user_func($unserializerFunction, $serializedData);
    }

    /**
     * Retrieve data from the cache.
     *
     * @param string $key The key for the cached data.
     * @return mixed|null The cached data, or null if not found or expired.
     */
    public static function get($key)
    {
        $cacheFile = self::getCacheFile($key);

        if (!file_exists($cacheFile)) {
            return null; // Cache not found
        }

        $cacheDataJson = file_get_contents($cacheFile);
        $cacheData = json_decode($cacheDataJson, true);

        // Check if the decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            unlink($cacheFile); // Remove corrupted file
            return null;
        }

        // Check if the necessary keys exist
        if (!isset($cacheData['expiration'], $cacheData['data'], $cacheData['serializer'])) {
            unlink($cacheFile); // Remove corrupted file
            return null;
        }

        // Check if the cache has expired
        if (time() > $cacheData['expiration']) {
            unlink($cacheFile); // Remove expired cache
            return null;
        }

        // Unserialize and return the cached data
        return self::unserializeData($cacheData['data'], $cacheData['serializer']);
    }

    /**
     * Invalidate a cached item.
     *
     * @param string $key The key for the cached item to be invalidated.
     */
    public static function invalidate($key)
    {
        $cacheFile = self::getCacheFile($key);

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * Retrieve data from the cache or compute it using the provided callback.
     *
     * @param string $key The key for the cached data.
     * @param callable $callback The callback to compute the data if not found in cache.
     * @param int $expiration The expiration time in seconds (default is 3600).
     * @param string $serializer The serializer to use (default is 'serialize').
     * @return mixed The cached or computed data.
     */
    public static function remember($key, $callback, $expiration = 3600, $serializer = 'serialize')
    {
        $cachedData = self::get($key);

        if (!$cachedData) {
            $cachedData = $callback();
            self::add($key, $cachedData, $expiration, $serializer);
        }

        return $cachedData;
    }
}
