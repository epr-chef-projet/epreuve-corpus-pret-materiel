<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Access\Http;

use App\Shared\Errors\FieldErrorData;
use App\Shared\Errors\IssueData;
use App\Shared\Errors\NextActionData;
use App\Shared\Errors\ValidationPresentationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Adapts native HTTP failures and preserves already selected JSON issues. */
final class AccessIssueMapper
{
    public function validation(ValidationException $error, Request $request): ?Response
    {
        $fields = [];
        foreach ($error->errors() as $field => $messages) {
            $rules = $error->validator->failed()[$field] ?? [];
            $rule = array_key_first($rules);
            $key = match ($rule) {
                'Required' => 'validation.required',
                'String' => 'validation.string',
                'Max' => 'validation.max',
                'Min' => 'validation.min',
                'Integer' => 'validation.integer',
                'Between' => 'validation.between',
                'Uuid' => 'validation.uuid',
                'Regex' => in_array($field, ['operationKey', 'equipmentId'], true) ? 'validation.uuid' : 'validation.text',
                'NotRegex' => 'validation.text',
                default => $request->routeIs('login.store') && $field === 'login' && $rule === null ? 'identity.credentials' : 'validation.unknown',
            };
            $parameters = $rule === 'Max' ? ['max' => (int) ($rules[$rule][0] ?? 0)] : [];
            if ($rule === 'Min') {
                $parameters = ['min' => (int) ($rules[$rule][0] ?? 0)];
            }
            if ($rule === 'Between') {
                $parameters = ['min' => (int) ($rules[$rule][0] ?? 0), 'max' => (int) ($rules[$rule][1] ?? 0)];
            }
            $fields[$field] = [new FieldErrorData($key, $parameters)];
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['issue' => new IssueData('validation.invalid_input', [], $this->correlation($request), 'notApplied', new NextActionData('correctInput'), $fields)], 422)
                ->header('Cache-Control', 'private, no-store');
        }
        if ($request->hasSession()) {
            $request->session()->flash('validationPresentation', json_encode(new ValidationPresentationData(app()->getLocale(), $fields), JSON_THROW_ON_ERROR));
            $request->session()->flash('validationErrorBag', $error->errorBag);
        }

        return null;
    }

    public function respond(Response $response, Throwable $error, Request $request): Response
    {
        $status = $response->getStatusCode();
        $data = $response instanceof JsonResponse ? $response->getData(true) : null;
        if ($status < 400 || (is_array($data) && (isset($data['issue']) || isset($data['props']['issue'])))) {
            return $response;
        }
        $role = $request->hasSession() ? $request->session()->get('identity_access.role') : null;
        [$code, $next] = match ($status) {
            401, 419 => ['identity_access.authentication_required', 'signIn'],
            403 => ['identity_access.operation_not_allowed', 'reload'],
            404 => ['identity_access.resource_unavailable', 'reload'],
            429 => ['identity_access.rate_limited', 'reload'],
            503 => ['operations.temporarily_unavailable', 'reload'],
            default => ['operations.unexpected_failure', in_array($role, ['staff', 'manager'], true) ? 'contactManager' : 'contactStaff'],
        };
        $outcome = $status >= 500 && ! $request->isMethodSafe() ? 'unknown' : 'notApplied';
        if ($outcome === 'unknown' && is_string($request->attributes->get('operationKey'))) {
            $next = 'verifyOperation';
        }
        $issue = new IssueData($code, [], $this->correlation($request), $outcome, new NextActionData($next));
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            $result = response()->json(['issue' => $issue], $status);
        } else {
            $result = Inertia::render('Error', ['issue' => $issue])->toResponse($request);
            $result->setStatusCode($status);
        }
        $result->headers->set('Cache-Control', 'private, no-store');
        $result->headers->set('Content-Language', app()->getLocale());

        return $result;
    }

    private function correlation(Request $request): string
    {
        $value = $request->attributes->get('correlationId');

        return is_string($value) ? $value : (string) Str::uuid7();
    }
}
