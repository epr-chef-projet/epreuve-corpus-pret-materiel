<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_two_factor_challenge_is_not_exposed(): void
    {
        $this->get('/two-factor-challenge')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/two-factor-challenge')->assertNotFound();
    }

    public function test_post_two_factor_challenge_is_not_exposed(): void
    {
        $this->post('/two-factor-challenge')->assertNotFound();
        Accounts::signIn($this);
        $this->post('/two-factor-challenge')->assertNotFound();
    }

    public function test_post_user_two_factor_authentication_is_not_exposed(): void
    {
        $this->post('/user/two-factor-authentication')->assertNotFound();
        Accounts::signIn($this);
        $this->post('/user/two-factor-authentication')->assertNotFound();
    }
}
