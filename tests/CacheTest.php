<?php

namespace Tests\Support;

use Lithe\Support\Cache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        // Set a temporary directory for the cache during tests
        Cache::dir(sys_get_temp_dir() . '/test_cache');
    }

    protected function tearDown(): void
    {
        // Clear cache files after tests
        array_map('unlink', glob(sys_get_temp_dir() . '/test_cache/*.cache'));
    }

    public function testCacheDirectoryCreation()
    {
        $this->assertDirectoryExists(Cache::getCacheDirectory(), 'The cache directory was not created correctly.');
        $this->assertIsWritable(Cache::getCacheDirectory(), 'The cache directory is not writable.');
    }

    public function testAddAndGetCacheEntry()
    {
        $key = 'test_key';
        $data = 'Test data';

        Cache::add($key, $data, 3600);
        $retrievedData = Cache::get($key);

        $this->assertSame($data, $retrievedData, 'The retrieved cache value does not match the original value.');
    }

    public function testCacheExpiration()
    {
        $key = 'expire_test';
        $data = 'This data will expire';

        Cache::add($key, $data, 1); // Expires in 1 second
        sleep(2); // Wait for expiration
        $retrievedData = Cache::get($key);

        $this->assertNull($retrievedData, 'The cache was not invalidated after the expiration time.');
    }

    public function testInvalidateCacheEntry()
    {
        $key = 'invalidate_test';
        $data = 'This data will be invalidated';

        Cache::add($key, $data, 3600);
        Cache::invalidate($key);
        $retrievedData = Cache::get($key);

        $this->assertNull($retrievedData, 'The cache was not invalidated correctly.');
    }

    public function testAddCacheWithInvalidSerializer()
    {
        $this->expectException(\InvalidArgumentException::class);

        $key = 'invalid_serializer';
        $data = 'Invalid data';
        $serializer = 'invalid_serializer';

        Cache::add($key, $data, 3600, $serializer);
    }

    public function testRememberMethodStoresCallbackResult()
    {
        $key = 'remember_test';
        $data = 'Data from callback';

        $result = Cache::remember($key, fn() => $data, 3600);

        $this->assertSame($data, $result, 'The callback result was not stored correctly by the remember method.');
        $retrievedData = Cache::get($key);
        $this->assertSame($data, $retrievedData, 'The value was not retrieved correctly after being stored by the remember method.');
    }

    public function testRememberMethodRetrievesExistingCache()
    {
        $key = 'remember_existing';
        $data = 'Existing data';

        Cache::add($key, $data, 3600);

        $result = Cache::remember($key, fn() => 'Callback data', 3600);

        $this->assertSame($data, $result, 'The remember method did not return the existing cached value.');
    }
}
