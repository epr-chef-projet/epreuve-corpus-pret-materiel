<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_user_confirm_password_is_not_exposed(): void
    {
        $this->get('/user/confirm-password')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/user/confirm-password')->assertNotFound();
    }

    public function test_post_user_confirm_password_is_not_exposed(): void
    {
        $this->post('/user/confirm-password')->assertNotFound();
        Accounts::signIn($this);
        $this->post('/user/confirm-password')->assertNotFound();
    }

    public function test_get_user_confirmed_password_status_is_not_exposed(): void
    {
        $this->get('/user/confirmed-password-status')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/user/confirmed-password-status')->assertNotFound();
    }
}
