<?php

namespace Tests\Unit;

use App\Models\Service;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ServiceValidationTest extends TestCase
{
    /** @dataProvider invalidNumericValues */
    public function test_invalid_quantity_and_rate_values_are_rejected(array $input): void
    {
        $rules = [
            'quantity' => Service::$rules['quantity'],
            'rate' => Service::$rules['rate'],
        ];

        $this->assertTrue(Validator::make($input, $rules)->fails());
    }

    public static function invalidNumericValues(): array
    {
        return [
            'negative quantity' => [['quantity' => -1, 'rate' => 100]],
            'fractional quantity' => [['quantity' => 1.5, 'rate' => 100]],
            'non-numeric rate' => [['quantity' => 1, 'rate' => 'abc']],
            'negative rate' => [['quantity' => 1, 'rate' => -10]],
            'too many rate decimals' => [['quantity' => 1, 'rate' => '10.123']],
        ];
    }

    public function test_valid_quantity_and_rate_are_accepted(): void
    {
        $rules = [
            'quantity' => Service::$rules['quantity'],
            'rate' => Service::$rules['rate'],
        ];

        $validator = Validator::make(['quantity' => 3, 'rate' => '1250.50'], $rules);

        $this->assertFalse($validator->fails());
    }
}
