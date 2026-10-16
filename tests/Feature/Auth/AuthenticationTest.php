<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\IdentityAccess\Access\UseCases\ManageSession\SessionRepository;
use App\Modules\IdentityAccess\Identity\UseCases\Authenticate\IdentityRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_replaces_the_pre_authentication_session_identifier(): void
    {
        $this->withSession(['guest-marker' => 'synthetic']);
        session()->save();
        $previous = session()->getId();
        $this->withCookie(config('session.cookie'), $previous);
        Accounts::signIn($this);
        $this->assertNotSame($previous, session()->getId());
        $this->assertSame('', session()->getHandler()->read($previous));
        $this->get('/dashboard')->assertOk();
    }

    public function test_a_stale_session_role_is_revoked_against_the_current_sql_identity(): void
    {
        Accounts::signIn($this);
        $previous = session()->getId();
        session()->put('identity_access.role', 'staff');
        session()->save();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNull(app(IdentityRepository::class)->resolve(Accounts::user()['id'], app(SessionRepository::class)->key($previous)));
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_precreated_users_authenticate_by_normalized_login(): void
    {
        $this->post('/login', ['login' => ' ATELIER-1-BORROWER-1 ', 'password' => Accounts::receipt()['password']])->assertRedirect('/dashboard');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->assertAuthenticated();
        $this->get('/dashboard')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_wrong_password_and_unknown_login_have_the_same_public_error(): void
    {
        $responses = [];
        foreach (['atelier-1-borrower-1', 'unknown-account'] as $login) {
            $response = $this->post('/login', ['login' => $login, 'password' => 'invalid']);
            $response->assertSessionHasErrors('login');
            $responses[] = session('errors')->first('login');
            $this->assertGuest();
        }
        $this->assertSame($responses[0], $responses[1]);
    }

    public function test_logout_revokes_the_session_and_renews_csrf(): void
    {
        Accounts::signIn($this);
        $oldId = session()->getId();
        $oldCsrf = session()->token();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldCsrf, session()->token());
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['login' => 'rate-limit-probe', 'password' => 'invalid'])->assertSessionHasErrors('login');
        }
        $this->post('/login', ['login' => ' RATE-LIMIT-PROBE ', 'password' => 'invalid'])->assertTooManyRequests();
    }

    public function test_remember_and_client_supplied_authority_are_rejected(): void
    {
        foreach (['remember' => true, 'role' => 'manager', 'workshopId' => Accounts::user('atelier-2-manager')['workshopId']] as $key => $value) {
            $this->post('/login', ['login' => 'atelier-1-borrower-1', 'password' => Accounts::receipt()['password'], $key => $value])->assertSessionHasErrors('login');
            $this->assertGuest();
        }
    }

    public function test_session_lifetime_is_absolute_despite_activity(): void
    {
        Accounts::signIn($this);
        $this->travel(119)->minutes();
        $this->get('/dashboard')->assertOk();
        $this->travel(1)->minutes();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
