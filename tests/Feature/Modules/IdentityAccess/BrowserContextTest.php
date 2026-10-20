<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\IdentityAccess;

use App\Http\Middleware\HandleInertiaRequests;
use App\Shared\Errors\IssueData;
use App\Shared\Errors\NextActionData;
use App\Shared\Realtime\NotificationEnvelopeData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\IdentityAccess\Accounts;
use Tests\TestCase;

final class BrowserContextTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_context_is_empty_private_and_uses_only_an_allowed_presentation_locale(): void
    {
        $this->getJson('/access/context', ['X-Presentation-Locale' => 'en'])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Content-Language', 'en')
            ->assertExactJson(['user' => null, 'broadcastToken' => null, 'authGeneration' => null, 'expiresAt' => null, 'locale' => 'en']);
        $this->getJson('/access/context', ['X-Presentation-Locale' => 'untrusted'])->assertOk()->assertJsonPath('locale', 'fr');
    }

    #[DataProvider('accounts')]
    public function test_context_exposes_only_the_current_account_and_session_route(string $login, string $role): void
    {
        $this->withCredentials();
        Accounts::signIn($this, $login);
        $user = Accounts::user($login);
        $token = session('identity_access.broadcast_token');
        $response = $this->getJson('/access/context?workshopId=foreign&role=manager', ['X-Presentation-Locale' => 'en'])
            ->assertOk()->assertJsonCount(5)->assertJsonCount(6, 'user')
            ->assertJsonPath('user.id', $user['id'])->assertJsonPath('user.workshopId', $user['workshopId'])
            ->assertJsonPath('user.role', $role)->assertJsonPath('broadcastToken', $token)
            ->assertJsonPath('authGeneration', 1)->assertJsonPath('locale', 'en');
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $response->json('expiresAt'));
        $this->assertStringNotContainsString(session()->getId(), (string) $response->getContent());
        $this->post('/logout')->assertRedirect('/');
        $this->getJson('/access/context')->assertOk()->assertJsonPath('user', null)->assertJsonPath('broadcastToken', null);
    }

    /** @return list<array{string,string}> */
    public static function accounts(): array
    {
        return AccessBoundaryTest::accounts();
    }

    public function test_native_login_errors_have_safe_reformattable_keys_and_keep_the_inertia_redirect(): void
    {
        $this->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(request()) ?? '');
        $this->from('/login')->withHeader('X-Presentation-Locale', 'en')
            ->post('/login', ['login' => 'unknown-account', 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors(['login' => 'The account ID or password is incorrect.']);
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/login', ['X-Inertia' => 'true', 'X-Presentation-Locale' => 'en'])
            ->assertOk()->assertJsonPath('props.errors.login', 'The account ID or password is incorrect.')
            ->assertJsonPath('props.validationPresentation.locale', 'en')
            ->assertJsonPath('props.validationPresentation.fieldErrors.login.0.messageKey', 'identity.credentials');
    }

    public function test_required_validation_uses_french_before_validation_and_keeps_the_form_values(): void
    {
        $this->withHeader('X-Inertia-Version', app(HandleInertiaRequests::class)->version(request()) ?? '');
        $this->from('/login')->post('/login', ['login' => 'kept-account'])->assertRedirect('/login')
            ->assertSessionHasErrors(['password' => 'Le champ mot de passe est obligatoire.']);
        $this->get('/login', ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('props.validationPresentation.fieldErrors.password.0.messageKey', 'validation.required');
    }

    public function test_unavailable_json_resource_never_exposes_a_native_exception_or_a_database_diagnostic(): void
    {
        $this->getJson('/resource-that-does-not-exist')->assertNotFound()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('issue.errorCode', 'identity_access.resource_unavailable')
            ->assertJsonPath('issue.outcome', 'notApplied')->assertJsonPath('issue.nextAction.kind', 'reload')
            ->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
    }

    public function test_issue_values_are_immutable_and_serialize_empty_parameters_as_an_object_without_sql(): void
    {
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });
        $data = new IssueData('identity_access.authentication_required', [], (string) Str::uuid7(), 'notApplied', new NextActionData('signIn'));
        $json = json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
        $this->assertInstanceOf(\stdClass::class, $json->parameters);
        $this->assertSame('signIn', $json->nextAction->kind);
        $this->assertSame('issues.identity_access.authentication_required', $json->messageKey);
        $this->assertFalse(property_exists($json, 'fieldErrors'));
        $this->assertSame(0, $queries);
        $this->expectException(\Error::class);
        (new \ReflectionProperty($data, 'parameters'))->setValue($data, ['private' => 'changed']);
    }

    public function test_role_destinations_are_enforced_even_when_requested_directly(): void
    {
        Accounts::signIn($this);
        $this->get('/mes-demandes')->assertOk();
        $this->get('/demandes')->assertForbidden();
        $this->get('/gestion/materiels')->assertForbidden();
    }

    public function test_json_validation_keeps_its_selected_issue_instead_of_becoming_a_generic_incident(): void
    {
        $this->postJson('/login', ['login' => 'synthetic-no-password'], ['X-Presentation-Locale' => 'en'])
            ->assertUnprocessable()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('issue.errorCode', 'validation.invalid_input')
            ->assertJsonPath('issue.outcome', 'notApplied')
            ->assertJsonPath('issue.fieldErrors.password.0.messageKey', 'validation.required')
            ->assertJsonMissingPath('exception');
    }

    public function test_notification_envelope_is_a_selected_immutable_json_contract(): void
    {
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });
        $payload = ['projection' => 'catalog'];
        $value = new NotificationEnvelopeData((string) Str::uuid7(), 1, 'catalog.invalidated', $payload);
        $payload['projection'] = 'changed';
        $json = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(5, $json);
        $this->assertSame(1, $json['contractVersion']);
        $this->assertSame(['projection' => 'catalog'], $json['payload']);
        $this->assertSame(0, $queries);
    }

    public function test_notification_envelope_refuses_a_payload_above_the_transport_budget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NotificationEnvelopeData((string) Str::uuid7(), 1, 'catalog.invalidated', ['projection' => str_repeat('x', 16 * 1024)]);
    }

    public function test_notification_envelope_refuses_a_mutable_object_inside_its_payload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NotificationEnvelopeData((string) Str::uuid7(), 1, 'catalog.invalidated', ['projection' => new \stdClass]);
    }
}
