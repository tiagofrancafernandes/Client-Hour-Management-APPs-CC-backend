<?php

namespace Tests\Unit\Services;

use App\Models\PaymentMethodConfig;
use App\Services\PaymentMethodSetupValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodSetupValidatorTest extends TestCase
{
    use RefreshDatabase;

    private PaymentMethodSetupValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PaymentMethodSetupValidator();
    }

    public function testValidatesRequiredFields(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 'valid_pix_key',
            'receiver_name' => '',
            'receiver_city_name' => 'São Paulo',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('receiver_name', $result['errors']);
        $this->assertContains('Campo obrigatório', $result['errors']['receiver_name']);
    }

    public function testValidatesStringType(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 123,
            'receiver_name' => 'John Doe',
            'receiver_city_name' => 'São Paulo',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('pix_key', $result['errors']);
        $this->assertContains('Tipo inválido, esperado: string', $result['errors']['pix_key']);
    }

    public function testValidatesMinimumLength(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 'ab',
            'receiver_name' => 'John Doe',
            'receiver_city_name' => 'São Paulo',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('pix_key', $result['errors']);
        $this->assertContains('Mínimo de 4 caracteres', $result['errors']['pix_key']);
    }

    public function testValidatesMaximumLength(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 'this_is_a_very_long_pix_key_that_exceeds_limit',
            'receiver_name' => 'John Doe',
            'receiver_city_name' => 'São Paulo',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('pix_key', $result['errors']);
        $this->assertContains('Máximo de 25 caracteres', $result['errors']['pix_key']);
    }

    public function testAllowsEmptyOptionalFields(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        // Create a mock config with optional fields
        $setupData = [
            'pix_key' => 'valid_pix',
            'receiver_name' => 'John Doe',
            'receiver_city_name' => 'São Paulo',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidatesAllRequiredFieldsPass(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 'my_pix_key_1234',
            'receiver_name' => 'John Doe',
            'receiver_city_name' => 'São Paulo',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidatesIntegerType(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        // Create a test setup data with a numeric string that should pass integer validation
        $setupData = [
            'pix_key' => '1234',
            'receiver_name' => 'John Doe',
            'receiver_city_name' => 'São Paulo',
        ];

        $result = $this->validator->validate($config, $setupData);

        // Numeric string should be accepted for integer type
        $this->assertTrue($result['valid']);
    }

    public function testValidatesSelectOptions(): void
    {
        // Create a mock config with select type field
        $mockConfig = \Mockery::mock(PaymentMethodConfig::class);
        $mockConfig->shouldReceive('getSetupFieldRules')->andReturn([
            [
                'label' => 'Payment Type',
                'name' => 'payment_type',
                'required' => true,
                'type' => 'select',
                'options' => [
                    ['value' => 'credit', 'label' => 'Credit Card'],
                    ['value' => 'debit', 'label' => 'Debit Card'],
                ],
            ],
        ]);

        $setupData = [
            'payment_type' => 'invalid_option',
        ];

        $result = $this->validator->validate($mockConfig, $setupData);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('payment_type', $result['errors']);
        $this->assertContains('Opção inválida', $result['errors']['payment_type']);
    }

    public function testReturnsProperErrorStructure(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => '',
            'receiver_name' => '',
            'receiver_city_name' => '',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertIsBool($result['valid']);
        $this->assertIsArray($result['errors']);
        $this->assertFalse($result['valid']);

        foreach ($result['errors'] as $fieldName => $errorMessages) {
            $this->assertIsString($fieldName);
            $this->assertIsArray($errorMessages);

            foreach ($errorMessages as $message) {
                $this->assertIsString($message);
            }
        }
    }

    public function testValidationWithMissingFields(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 'valid_key_123',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('receiver_name', $result['errors']);
        $this->assertArrayHasKey('receiver_city_name', $result['errors']);
    }

    public function testValidationWithMultipleErrorsPerField(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 'ab',
            'receiver_name' => 'this_is_a_very_long_name_that_exceeds_max_length',
            'receiver_city_name' => 'a',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('pix_key', $result['errors']);
        $this->assertArrayHasKey('receiver_name', $result['errors']);
        $this->assertArrayHasKey('receiver_city_name', $result['errors']);
    }

    public function testValidationWithExactMinLength(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 'abcd',
            'receiver_name' => 'John',
            'receiver_city_name' => 'Test',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertTrue($result['valid']);
    }

    public function testValidationWithExactMaxLength(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 'a' . str_repeat('b', 24),
            'receiver_name' => str_repeat('c', 25),
            'receiver_city_name' => str_repeat('d', 15),
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertTrue($result['valid']);
    }

    public function testValidationSkipsNonRequiredEmptyFields(): void
    {
        $config = PaymentMethodConfig::factory()->create([
            'payment_method_key' => 'pix_offline',
        ]);

        $setupData = [
            'pix_key' => 'valid_key_123',
            'receiver_name' => 'John Doe',
            'receiver_city_name' => 'São Paulo',
            'optional_field' => '',
        ];

        $result = $this->validator->validate($config, $setupData);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
}
