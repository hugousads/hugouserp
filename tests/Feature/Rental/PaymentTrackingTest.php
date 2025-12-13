<?php

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Models\Branch;
use App\Models\Property;
use App\Models\RentalContract;
use App\Models\RentalInvoice;
use App\Models\RentalPayment;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected RentalService $service;
    protected Branch $branch;
    protected User $user;
    protected RentalContract $contract;
    protected RentalInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RentalService::class);

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
        ]);

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $property = Property::create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Property',
            'address' => '123 Test St',
        ]);

        $unit = RentalUnit::create([
            'property_id' => $property->id,
            'code' => 'UNIT-001',
            'status' => 'occupied',
        ]);

        $tenant = Tenant::create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Tenant',
            'phone' => '1234567890',
        ]);

        $this->contract = RentalContract::create([
            'branch_id' => $this->branch->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->subMonths(1),
            'end_date' => now()->addMonths(11),
            'rent' => 5000,
            'status' => 'active',
        ]);

        $this->invoice = RentalInvoice::create([
            'contract_id' => $this->contract->id,
            'period' => now()->format('Y-m'),
            'amount' => 5000,
            'status' => 'unpaid',
            'paid_total' => 0,
            'due_date' => now()->addDays(5),
        ]);

        // Authenticate as user
        $this->actingAs($this->user);
    }

    public function test_collect_payment_creates_rental_payment_record(): void
    {
        $paymentAmount = 2500;
        $paymentMethod = 'bank_transfer';
        $reference = 'REF-12345';

        $result = $this->service->collectPayment(
            $this->invoice->id,
            $paymentAmount,
            $paymentMethod,
            $reference
        );

        // Verify payment record was created
        $payment = RentalPayment::where('invoice_id', $this->invoice->id)->first();

        $this->assertNotNull($payment);
        $this->assertEquals($paymentAmount, $payment->amount);
        $this->assertEquals($paymentMethod, $payment->method);
        $this->assertEquals($reference, $payment->reference);
        $this->assertEquals($this->contract->id, $payment->contract_id);
        $this->assertEquals($this->user->id, $payment->created_by);

        // Verify invoice was updated
        $this->assertEquals($paymentAmount, $result->paid_total);
        $this->assertEquals('unpaid', $result->status); // Still unpaid as only partial payment
    }

    public function test_collect_payment_marks_invoice_as_paid_when_fully_paid(): void
    {
        $paymentAmount = 5000;

        $result = $this->service->collectPayment(
            $this->invoice->id,
            $paymentAmount,
            'cash'
        );

        // Verify invoice status changed to paid
        $this->assertEquals('paid', $result->status);
        $this->assertEquals($paymentAmount, $result->paid_total);
    }

    public function test_multiple_payments_are_tracked_separately(): void
    {
        // First payment
        $this->service->collectPayment($this->invoice->id, 2000, 'cash', 'PAYMENT-1');

        // Second payment
        $this->service->collectPayment($this->invoice->id, 1500, 'bank_transfer', 'PAYMENT-2');

        // Third payment
        $this->service->collectPayment($this->invoice->id, 1500, 'cash', 'PAYMENT-3');

        // Verify all three payment records exist
        $payments = RentalPayment::where('invoice_id', $this->invoice->id)->get();

        $this->assertCount(3, $payments);
        $this->assertEquals(2000, $payments[0]->amount);
        $this->assertEquals(1500, $payments[1]->amount);
        $this->assertEquals(1500, $payments[2]->amount);

        // Verify invoice total
        $invoice = RentalInvoice::find($this->invoice->id);
        $this->assertEquals(5000, $invoice->paid_total);
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_payment_defaults_to_cash_method(): void
    {
        $result = $this->service->collectPayment($this->invoice->id, 1000);

        $payment = RentalPayment::where('invoice_id', $this->invoice->id)->first();

        $this->assertEquals('cash', $payment->method);
        $this->assertNull($payment->reference);
    }
}
