<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_email_verify_is_not_exposed(): void
    {
        $this->get('/email/verify')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/email/verify')->assertNotFound();
    }

    public function test_get_email_verify_opaque_opaque_is_not_exposed(): void
    {
        $this->get('/email/verify/opaque/opaque')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/email/verify/opaque/opaque')->assertNotFound();
    }
}
