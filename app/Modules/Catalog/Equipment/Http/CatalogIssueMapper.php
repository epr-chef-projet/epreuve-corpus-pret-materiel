<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\Http;

use App\Infrastructure\Operations\IntentClosed;
use App\Shared\Errors\DomainError;
use App\Shared\Errors\IssueData;
use App\Shared\Errors\NextActionData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CatalogIssueMapper
{
    public function render(DomainError $error, Request $request): ?Response
    {
        if (! $request->routeIs('catalog.*', 'manageCatalog')) {
            return null;
        }
        $code = $error->errorCode();
        $conflict = in_array($code, ['catalog.content_conflict', 'operations.intent_mismatch', IntentClosed::ERROR_CODE], true);
        $issue = new IssueData($code, [], (string) $request->attributes->get('correlationId', Str::uuid7()), 'notApplied', new NextActionData($conflict ? 'reload' : 'contactManager'));
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['issue' => $issue], $conflict ? 409 : 500)->header('Cache-Control', 'private, no-store');
        }

        return back()->withErrors(['catalog' => __($issue->messageKey)])->with('catalogIssue', json_encode($issue, JSON_THROW_ON_ERROR));
    }
}
