<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_settings_profile_is_not_exposed(): void
    {
        $this->get('/settings/profile')->assertNotFound();
        Accounts::signIn($this);
        $this->get('/settings/profile')->assertNotFound();
    }

    public function test_patch_settings_profile_is_not_exposed(): void
    {
        $this->patch('/settings/profile')->assertNotFound();
        Accounts::signIn($this);
        $this->patch('/settings/profile')->assertNotFound();
    }

    public function test_delete_settings_profile_is_not_exposed(): void
    {
        $this->delete('/settings/profile')->assertNotFound();
        Accounts::signIn($this);
        $this->delete('/settings/profile')->assertNotFound();
    }
}
