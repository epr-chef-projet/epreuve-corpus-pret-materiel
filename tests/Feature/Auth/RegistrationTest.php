<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_register_is_not_exposed(): void
    {
        $this->get('/register')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/register')->assertNotFound();
    }

    public function test_post_register_is_not_exposed(): void
    {
        $this->post('/register')->assertNotFound();
        Accounts::signIn($this);
        $this->post('/register')->assertNotFound();
    }
}
