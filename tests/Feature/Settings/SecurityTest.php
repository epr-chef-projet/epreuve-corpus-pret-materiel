<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_settings_security_is_not_exposed(): void
    {
        $this->get('/settings/security')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/settings/security')->assertNotFound();
    }

    public function test_put_settings_password_is_not_exposed(): void
    {
        $this->put('/settings/password')->assertNotFound();
        Accounts::signIn($this);
        $this->put('/settings/password')->assertNotFound();
    }

    public function test_get__well_known_passkey_endpoints_is_not_exposed(): void
    {
        $this->get('/.well-known/passkey-endpoints')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/.well-known/passkey-endpoints')->assertNotFound();
    }

    public function test_get_passkeys_login_options_is_not_exposed(): void
    {
        $this->get('/passkeys/login/options')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/passkeys/login/options')->assertNotFound();
    }

    public function test_post_passkeys_login_is_not_exposed(): void
    {
        $this->post('/passkeys/login')->assertNotFound();
        Accounts::signIn($this);
        $this->post('/passkeys/login')->assertNotFound();
    }

    public function test_post_user_passkeys_is_not_exposed(): void
    {
        $this->post('/user/passkeys')->assertNotFound();
        Accounts::signIn($this);
        $this->post('/user/passkeys')->assertNotFound();
    }
}
