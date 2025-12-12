<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\RentalContractStatus;
use PHPUnit\Framework\TestCase;

class RentalContractStatusTest extends TestCase
{
    public function test_active_can_transition_to_expired(): void
    {
        $status = RentalContractStatus::ACTIVE;

        $this->assertTrue($status->canTransitionTo(RentalContractStatus::EXPIRED));
        $this->assertContains(RentalContractStatus::EXPIRED, $status->allowedTransitions());
    }

    public function test_suspended_can_transition_to_expired(): void
    {
        $status = RentalContractStatus::SUSPENDED;

        $this->assertTrue($status->canTransitionTo(RentalContractStatus::EXPIRED));
        $this->assertContains(RentalContractStatus::EXPIRED, $status->allowedTransitions());
    }

    public function test_expired_is_final(): void
    {
        $status = RentalContractStatus::EXPIRED;

        $this->assertTrue($status->isFinal());
        $this->assertEmpty($status->allowedTransitions());
    }

    public function test_terminated_is_final(): void
    {
        $status = RentalContractStatus::TERMINATED;

        $this->assertTrue($status->isFinal());
        $this->assertEmpty($status->allowedTransitions());
    }

    public function test_draft_cannot_transition_to_expired(): void
    {
        $status = RentalContractStatus::DRAFT;

        $this->assertFalse($status->canTransitionTo(RentalContractStatus::EXPIRED));
    }
}
