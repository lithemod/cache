<?php

namespace Lithe\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * This class provides a high-performance caching mechanism using the filesystem.
 */
class Cache
{
    private static $cacheDir = null;
    private static $serializerMap = [
        'serialize' => ['serialize', 'unserialize'],
        'json' => ['json_encode', 'json_decode'],
    ];
    private const DIR_LEVELS = 2; // Defines the depth of the directory structure
    private const FILE_EXTENSION = '.cache';

    /**
     * Sets the cache directory and ensures it exists.
     *
     * @param string $dir The directory path for storing cache files.
     * @return void
     */
    public static function dir($dir)
    {
        self::$cacheDir = rtrim($dir, DIRECTORY_SEPARATOR);
        self::ensureCacheDirExists();
    }

    /**
     * Ensures the cache directory exists and is writable.
     *
     * @throws RuntimeException if the cache directory cannot be created or is not writable.
     * @return void
     */
    private static function ensureCacheDirExists()
    {
        self::$cacheDir = self::$cacheDir ?: dirname(__DIR__, 4) . '/storage/framework/cache';

        if (!is_dir(self::$cacheDir) && !mkdir(self::$cacheDir, 0777, true) && !is_dir(self::$cacheDir)) {
            throw new RuntimeException("Failed to create cache directory: " . self::$cacheDir);
        }

        if (!is_writable(self::$cacheDir)) {
            throw new RuntimeException("Cache directory is not writable: " . self::$cacheDir);
        }
    }

    /**
     * Generates the path to the cache file based on a hashed key.
     *
     * @param string $key The cache entry key.
     * @return string The full path to the cache file.
     */
    private static function getCacheFile($key)
    {
        $hash = md5($key);
        $path = self::$cacheDir;

        for ($i = 0; $i < self::DIR_LEVELS; $i++) {
            $path .= DIRECTORY_SEPARATOR . substr($hash, $i * 2, 2);
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }

        return $path . DIRECTORY_SEPARATOR . $hash . self::FILE_EXTENSION;
    }

    /**
     * Adds data to the cache with a specified expiration time and serializer.
     *
     * @param string $key The cache key.
     * @param mixed $data The data to cache.
     * @param int $expiration The cache expiration time in seconds.
     * @param string $serializer The serializer to use (default is 'serialize').
     * @throws RuntimeException if cache data cannot be encoded or file cannot be written.
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

        $encodedData = json_encode($cacheData);
        if ($encodedData === false) {
            throw new RuntimeException("Failed to encode cache data for key: $key");
        }

        $file = @fopen($cacheFile, 'c');
        if ($file === false) {
            throw new RuntimeException("Failed to open cache file: $cacheFile");
        }

        try {
            flock($file, LOCK_EX);
            ftruncate($file, 0);
            fwrite($file, $encodedData);
        } finally {
            fclose($file);
        }
    }

    /**
     * Serializes data using the specified serializer.
     *
     * @param mixed $data The data to serialize.
     * @param string $serializer The serializer type ('serialize' or 'json').
     * @throws InvalidArgumentException if the serializer is invalid.
     * @return mixed The serialized data.
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
     * Unserializes data using the specified serializer.
     *
     * @param mixed $serializedData The data to unserialize.
     * @param string $serializer The serializer type ('serialize' or 'json').
     * @throws InvalidArgumentException if the serializer is invalid.
     * @return mixed The unserialized data.
     */
    private static function unserializeData($serializedData, $serializer)
    {
        if (!isset(self::$serializerMap[$serializer])) {
            throw new InvalidArgumentException("Invalid serializer: $serializer");
        }

        $unserializerFunction = self::$serializerMap[$serializer][1];
        return ($serializer === 'json') ? $unserializerFunction($serializedData, true) : $unserializerFunction($serializedData);
    }

    /**
     * Retrieves a cached item by key.
     *
     * @param string $key The cache key.
     * @throws RuntimeException if the cache file cannot be read.
     * @return mixed|null The cached data, or null if not found or expired.
     */
    public static function get($key)
    {
        $cacheFile = self::getCacheFile($key);

        if (!file_exists($cacheFile)) {
            return null;
        }

        if (!is_readable($cacheFile)) {
            throw new RuntimeException("Cache file is not readable: $cacheFile");
        }

        $file = @fopen($cacheFile, 'r');
        if ($file === false) {
            throw new RuntimeException("Failed to open cache file: $cacheFile");
        }

        try {
            flock($file, LOCK_SH);
            $content = stream_get_contents($file);
        } finally {
            fclose($file);
        }

        if ($content === false) {
            throw new RuntimeException("Failed to read cache file: $cacheFile");
        }

        $cacheData = json_decode($content, true);
        if (!is_array($cacheData) || time() > $cacheData['expiration']) {
            unlink($cacheFile);
            return null;
        }

        return self::unserializeData($cacheData['data'], $cacheData['serializer']);
    }

    /**
     * Checks if one or more cache entries exist and are valid.
     *
     * @param string|array $key The cache key or an array of keys to check.
     * @return bool True if all specified cache entries exist and are valid, otherwise false.
     */
    public static function has($key)
    {
        if (is_array($key)) {
            foreach ($key as $k) {
                if (self::get($k) === null) {
                    return false;
                }
            }
            return true;
        }

        return self::get($key) !== null;
    }

    /**
     * Invalidates one or more specific cache entries.
     *
     * @param string|array $key The cache key or an array of keys to invalidate.
     * @return void
     */
    public static function invalidate($key)
    {
        $keys = is_array($key) ? $key : [$key];

        foreach ($keys as $k) {
            $cacheFile = self::getCacheFile($k);
            if (file_exists($cacheFile) && is_writable($cacheFile)) {
                unlink($cacheFile);
            }
        }
    }

    /**
     * Clears all cache entries.
     *
     * @return void
     */
    public static function clear()
    {
        $iterator = new \RecursiveDirectoryIterator(self::$cacheDir, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
    }

    /**
     * Retrieves a cache item or stores the result of a callback if not present.
     *
     * @param string $key The cache key.
     * @param callable $callback The function to generate the cache value.
     * @param int $expiration The expiration time in seconds.
     * @param string $serializer The serializer type ('serialize' or 'json').
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

    /**
     * Gets the current cache directory path.
     *
     * @return string The cache directory path.
     */
    public static function getCacheDirectory(): string
    {
        return self::$cacheDir;
    }
}
