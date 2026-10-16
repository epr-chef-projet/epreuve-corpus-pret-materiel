<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_post_email_verification_notification_is_not_exposed(): void
    {
        $this->post('/email/verification-notification')->assertNotFound();
        Accounts::signIn($this);
        $this->post('/email/verification-notification')->assertNotFound();
    }
}
