<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceLine> */
class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => 'Base subscription charge',
            'quantity' => 1,
            'unit_price' => '29.00',
            'amount' => '29.00',
            'type' => 'base',
            'metadata' => null,
        ];
    }
}
