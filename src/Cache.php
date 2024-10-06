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
     * The directory where cached data will be stored.
     *
     * @var string
     */
    private static $cacheDir = null;

    /**
     * A map of supported serializer names to their corresponding PHP functions.
     *
     * @var array
     */
    private static $serializerMap = [
        'serialize' => ['serialize', 'unserialize'],
        'json' => ['json_encode', 'json_decode'],
        'yaml' => ['yaml_emit', 'yaml_parse'], // Assuming YAML serialization is available
    ];

    /**
     * Initializes the cache directory.
     *
     * @param string $dir The directory where cached data will be stored.
     */
    public static function dir($dir)
    {
        self::$cacheDir = $dir;
        self::ensureCacheDirExists();
    }

    /**
     * Ensures the cache directory exists. If it does not, it attempts to create it.
     *
     * @throws RuntimeException If the directory cannot be created.
     */
    private static function ensureCacheDirExists()
    {
        // Set a default directory if none is provided.
        self::$cacheDir = self::$cacheDir ?: dirname(__DIR__, 4) . '/storage/framework/cache';

        // Check if the directory exists; if not, create it.
        if (!is_dir(self::$cacheDir)) {
            if (!mkdir(self::$cacheDir, 0777, true) && !is_dir(self::$cacheDir)) {
                throw new RuntimeException("Failed to create cache directory: " . self::$cacheDir);
            }
        }
    }

    /**
     * Generates the filename for a cached item based on its key.
     *
     * @param string $key The key of the cached item.
     *
     * @return string The filename for the cached item.
     */
    private static function getCacheFile($key)
    {
        // Return the full path of the cache file using an MD5 hash of the key.
        return self::$cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    /**
     * Stores data in the cache with an optional expiration time and serializer.
     *
     * @param string $key The key to identify the cached data.
     * @param mixed $data The data to be cached.
     * @param int $expiration (optional) The number of seconds the data should remain cached. Defaults to 3600 (1 hour).
     * @param string $serializer (optional) The serializer to use for storing the data. Defaults to 'serialize'.
     *
     * @throws RuntimeException If writing the data to the cache file fails.
     * @throws InvalidArgumentException If the provided serializer is not supported.
     */
    public static function add($key, $data, $expiration = 3600, $serializer = 'serialize')
    {
        // Ensure the cache directory exists.
        self::ensureCacheDirExists();

        // Generate the cache file path.
        $cacheFile = self::getCacheFile($key);

        // Prepare the cache data to include expiration, serialized data, and the serializer used.
        $cacheData = [
            'expiration' => time() + $expiration,
            'data' => self::serializeData($data, $serializer),
            'serializer' => $serializer,
        ];

        // Write the cache data to a file using a lock to prevent concurrent writes.
        if (file_put_contents($cacheFile, json_encode($cacheData), LOCK_EX) === false) {
            throw new RuntimeException("Failed to write cache data to file: $cacheFile");
        }
    }

    /**
     * Serializes data using the specified serializer.
     *
     * @param mixed $data The data to be serialized.
     * @param string $serializer The name of the serializer to use.
     *
     * @throws InvalidArgumentException If the provided serializer is not supported.
     *
     * @return string The serialized data.
     */
    private static function serializeData($data, $serializer)
    {
        // Ensure the serializer is supported.
        if (!isset(self::$serializerMap[$serializer])) {
            throw new InvalidArgumentException("Invalid serializer: $serializer");
        }

        // Call the appropriate function to serialize the data.
        $serializerFunction = self::$serializerMap[$serializer][0];
        return call_user_func($serializerFunction, $data);
    }

    /**
     * Deserializes data using the specified serializer.
     *
     * @param string $serializedData The serialized data.
     * @param string $serializer The name of the serializer used for serialization.
     *
     * @throws InvalidArgumentException If the provided serializer is not supported.
     *
     * @return mixed The deserialized data.
     */
    private static function unserializeData($serializedData, $serializer)
    {
        // Ensure the serializer is supported.
        if (!isset(self::$serializerMap[$serializer])) {
            throw new InvalidArgumentException("Invalid serializer: $serializer");
        }

        // Call the appropriate function to deserialize the data.
        $unserializerFunction = self::$serializerMap[$serializer][1];

        // Special case for JSON: ensure the output is an associative array.
        if ($serializer === 'json') {
            return call_user_func($unserializerFunction, $serializedData, true); // true for associative array
        }

        // For 'serialize' and 'yaml', no additional parameters are needed.
        return call_user_func($unserializerFunction, $serializedData);
    }

    /**
     * Retrieves data from the cache for a given key.
     *
     * @param string $key The key to identify the cached data.
     *
     * @return mixed The cached data, or null if not found or expired.
     * @throws InvalidArgumentException If the provided serializer is not supported.
     */
    public static function get($key)
    {
        // Get the cache file path based on the key.
        $cacheFile = self::getCacheFile($key);

        // If the cache file does not exist, return null.
        if (!file_exists($cacheFile)) {
            return null;
        }

        // Read the content of the cache file.
        $content = file_get_contents($cacheFile);

        if (!$content) {
            return false;
        }

        // Decode the JSON content of the cache file.
        $cacheData = json_decode($content, true);

        // If the cache data is invalid, delete the cache file and return null.
        if (!is_array($cacheData)) {
            unlink($cacheFile);
            return null;
        }

        // Check if the cache has expired; if so, delete the cache file and return null.
        if (time() > $cacheData['expiration']) {
            unlink($cacheFile);
            return null;
        }

        // Return the deserialized cached data.
        return self::unserializeData($cacheData['data'], $cacheData['serializer']);
    }

    /**
     * Invalidates a cached item by removing its corresponding file.
     *
     * @param string $key The key used to identify the cached data.
     */
    public static function invalidate($key)
    {
        // Get the cache file path based on the key.
        $cacheFile = self::getCacheFile($key);

        // If the cache file exists, delete it.
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * Retrieves data from the cache or executes a callback to fetch and cache the data if not found.
     *
     * @param string $key The key used to identify the cached data.
     * @param callable $callback The callback function to fetch the data if it is not found in the cache.
     * @param int $expiration (optional) The number of seconds the data should remain cached. Defaults to 3600 (1 hour).
     * @param string $serializer (optional) The serializer to use for storing the data. Defaults to 'json'.
     *
     * @return mixed The cached data, or the data fetched by the callback if not found in the cache.
     */
    public static function remember($key, $callback, $expiration = 3600, $serializer = 'serialize')
    {
        // Try to retrieve the cached data.
        $cachedData = self::get($key);

        // If the cached data is not found or expired:
        if (!$cachedData) {
            // Execute the callback to fetch new data.
            $cachedData = $callback();

            // Store the new data in the cache.
            self::add($key, $cachedData, $expiration, $serializer);
        }

        // Return the cached (or newly fetched) data.
        return $cachedData;
    }
}
