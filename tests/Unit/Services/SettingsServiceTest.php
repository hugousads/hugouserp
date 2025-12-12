<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\SystemSetting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SettingsService();
    }

    /**
     * Test encrypted array settings round-trip correctly.
     */
    public function test_encrypted_array_round_trip(): void
    {
        $key = 'test.encrypted.array';
        $arrayValue = [
            'option1' => 'value1',
            'option2' => 'value2',
            'nested' => [
                'key' => 'nested_value',
            ],
        ];

        // Set encrypted array
        $this->service->set($key, $arrayValue, [
            'is_encrypted' => true,
            'type' => 'array',
            'group' => 'test',
        ]);

        // Retrieve and verify structure is maintained
        $retrieved = $this->service->getDecrypted($key);

        $this->assertIsArray($retrieved);
        $this->assertEquals($arrayValue, $retrieved);
        $this->assertEquals('value1', $retrieved['option1']);
        $this->assertEquals('value2', $retrieved['option2']);
        $this->assertIsArray($retrieved['nested']);
        $this->assertEquals('nested_value', $retrieved['nested']['key']);
    }

    /**
     * Test encrypted string values still work correctly.
     */
    public function test_encrypted_string_round_trip(): void
    {
        $key = 'test.encrypted.string';
        $stringValue = 'sensitive_api_key_12345';

        // Set encrypted string
        $this->service->set($key, $stringValue, [
            'is_encrypted' => true,
            'type' => 'string',
            'group' => 'test',
        ]);

        // Retrieve and verify it's still a string
        $retrieved = $this->service->getDecrypted($key);

        $this->assertIsString($retrieved);
        $this->assertEquals($stringValue, $retrieved);
    }

    /**
     * Test non-encrypted values are returned correctly.
     */
    public function test_non_encrypted_value_retrieval(): void
    {
        $key = 'test.plain.value';
        $value = 'plain_text_value';

        // Set non-encrypted value
        $this->service->set($key, $value, [
            'is_encrypted' => false,
            'type' => 'string',
            'group' => 'test',
        ]);

        // Retrieve and verify
        $retrieved = $this->service->get($key);

        $this->assertEquals($value, $retrieved);
    }

    /**
     * Test default value is returned when key doesn't exist.
     */
    public function test_default_value_when_key_missing(): void
    {
        $default = ['default' => 'array'];
        $retrieved = $this->service->getDecrypted('non.existent.key', $default);

        $this->assertEquals($default, $retrieved);
    }

    /**
     * Test non-encrypted array settings round-trip correctly without data loss.
     */
    public function test_non_encrypted_array_round_trip(): void
    {
        $key = 'test.plain.array';
        $arrayValue = [
            'option1' => 'value1',
            'option2' => 'value2',
            'option3' => 'value3',
        ];

        // Set non-encrypted array
        $this->service->set($key, $arrayValue, [
            'is_encrypted' => false,
            'type' => 'array',
            'group' => 'test',
        ]);

        // Retrieve and verify full array is preserved
        $retrieved = $this->service->getDecrypted($key);

        $this->assertIsArray($retrieved);
        $this->assertCount(3, $retrieved);
        $this->assertEquals($arrayValue, $retrieved);
        $this->assertEquals('value1', $retrieved['option1']);
        $this->assertEquals('value2', $retrieved['option2']);
        $this->assertEquals('value3', $retrieved['option3']);
    }
}
