<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_forgot_password_is_not_exposed(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_post_forgot_password_is_not_exposed(): void
    {
        $this->post('/forgot-password')->assertNotFound();
        Accounts::signIn($this);
        $this->post('/forgot-password')->assertNotFound();
    }

    public function test_get_reset_password_opaque_is_not_exposed(): void
    {
        $this->get('/reset-password/opaque')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/reset-password/opaque')->assertNotFound();
    }

    public function test_post_reset_password_is_not_exposed(): void
    {
        $this->post('/reset-password')->assertNotFound();
        Accounts::signIn($this);
        $this->post('/reset-password')->assertNotFound();
    }
}
