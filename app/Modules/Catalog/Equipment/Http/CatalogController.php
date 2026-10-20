<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\Http;

use App\Infrastructure\Operations\OperationScope;
use App\Infrastructure\Operations\ReadOperationResult;
use App\Modules\Catalog\Equipment\UseCases\CreateEquipment\CreateEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\EditEquipmentText\EditEquipmentTextAction;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\EquipmentQueryFactory;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\ManagementPageData;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\ReadEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInputFactory;
use App\Modules\Catalog\PublicApi\ReadEquipmentPage;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\ResolveActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CatalogController
{
    public function __construct(private ResolveActor $actors, private AuthorizeActor $authorize) {}

    /** @return array<string, mixed> */
    private function messages(Request $request): array
    {
        return ['commandReceipt' => json_decode((string) $request->session()->get('catalogReceipt', 'null'), true), 'issue' => json_decode((string) $request->session()->get('catalogIssue', 'null'), true)];
    }

    public function index(Request $request, EquipmentQueryFactory $queries, ReadEquipmentPage $read): Response
    {
        $actor = $this->actors->handle($request);
        $query = $queries->management($request->query());

        return Inertia::render('Manager/Catalog/Index', ['catalogPage' => ManagementPageData::fromPage($read->handle($actor, $query)), 'catalogQuery' => $query, ...$this->messages($request)]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Manager/Catalog/Create', $this->messages($request));
    }

    public function show(Request $request, string $equipment, EquipmentInputFactory $inputs, ReadEquipmentAction $read): Response
    {
        return Inertia::render('Manager/Catalog/Show', ['equipment' => $read->handle($this->actors->handle($request), $inputs->identifier($equipment)), ...$this->messages($request)]);
    }

    public function store(Request $request, EquipmentInputFactory $inputs, CreateEquipmentAction $create): RedirectResponse
    {
        $input = $inputs->create($request->all());
        $request->attributes->set('operationKey', $input['operationKey']);
        $receipt = $create->handle($this->actors->handle($request), $input['input'], $input['totalQuantity'], $input['operationKey']);

        return to_route('catalog.show', ['equipment' => $receipt->resourceId])->with('catalogReceipt', json_encode($receipt, JSON_THROW_ON_ERROR));
    }

    public function update(Request $request, string $equipment, EquipmentInputFactory $inputs, EditEquipmentTextAction $edit): RedirectResponse
    {
        $input = $inputs->edit($request->all());
        $request->attributes->set('operationKey', $input['operationKey']);
        $receipt = $edit->handle($this->actors->handle($request), $inputs->identifier($equipment), $input['input'], $input['expectedVersion'], $input['operationKey']);

        return to_route('catalog.show', ['equipment' => $receipt->resourceId])->with('catalogReceipt', json_encode($receipt, JSON_THROW_ON_ERROR));
    }

    public function page(Request $request, EquipmentQueryFactory $queries, ReadEquipmentPage $read): JsonResponse
    {
        return response()->json(ManagementPageData::fromPage($read->handle($this->actors->handle($request), $queries->management($request->query()))), options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->header('Cache-Control', 'private, no-store');
    }

    public function detail(Request $request, string $equipment, EquipmentInputFactory $inputs, ReadEquipmentAction $read): JsonResponse
    {
        return response()->json($read->handle($this->actors->handle($request), $inputs->identifier($equipment)), options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->header('Cache-Control', 'private, no-store');
    }

    public function operation(Request $request, ReadOperationResult $read, SqlUnit $units): JsonResponse
    {
        $valid = Validator::make($request->query(), ['operationType' => ['required', 'in:'.CreateEquipmentAction::OPERATION.','.EditEquipmentTextAction::OPERATION], 'operationKey' => ['required', 'uuid', EquipmentInputFactory::UUID]])->validate();
        if (array_diff(array_keys($request->query()), ['operationType', 'operationKey']) !== []) {
            throw ValidationException::withMessages(['catalog' => __('catalog.validation.unknown_fields')]);
        }
        $actor = $this->actors->handle($request);
        $this->authorize->handle($actor, 'manage-catalog');
        $result = null;
        $units->forActorRead($actor, function () use ($actor, $valid, $read, &$result): void {
            $result = $read->handle(new OperationScope($actor->workshopId, $actor->actorId, $valid['operationType'], $valid['operationKey']));
        });

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }
}
