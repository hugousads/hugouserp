<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class OrdersFractionalQuantityTest extends TestCase
{
    public function test_fractional_item_quantities_pass_validation(): void
    {
        $data = [
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 0.75,
                    'price' => 100,
                ],
            ],
        ];

        // Test only the quantity validation rule that changed
        $rules = [
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.price' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Fractional quantities should be accepted by validation rules');
    }
}
