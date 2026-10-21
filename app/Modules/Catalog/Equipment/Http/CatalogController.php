<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\Http;

use App\Modules\Catalog\Equipment\UseCases\ChangeEquipmentState\ChangeEquipmentStateAction;
use App\Modules\Catalog\Equipment\UseCases\CreateEquipment\CreateEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\EditEquipmentText\EditEquipmentTextAction;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\EquipmentQueryFactory;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\ManagementPageData;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\ReadEquipmentAction;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipmentOperation\ReadEquipmentOperationAction;
use App\Modules\Catalog\Equipment\UseCases\SaveEquipment\EquipmentInputFactory;
use App\Modules\Catalog\PublicApi\ReadEquipmentPage;
use App\Modules\IdentityAccess\PublicApi\ResolveActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CatalogController
{
    public function __construct(private ResolveActor $actors) {}

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

    public function show(Request $request, string $equipment, EquipmentInputFactory $inputs, ReadEquipmentAction $read, ReadEquipmentOperationAction $operations): Response
    {
        $actor = $this->actors->handle($request);
        $value = $read->handle($actor, $inputs->identifier($equipment));
        $messages = $this->messages($request);
        if ($request->hasHeader('X-EPR-Operation-Key') || $request->hasHeader('X-EPR-Operation-Type')) {
            $operation = $inputs->operation(['operationType' => $request->header('X-EPR-Operation-Type'), 'operationKey' => $request->header('X-EPR-Operation-Key')]);
            $receipt = $operations->handle($actor, $operation['operationType'], $operation['operationKey'])->receipt;
            $messages['commandReceipt'] = $receipt !== null && $receipt->resourceType === 'equipment' && $receipt->resourceId === $value->equipmentId ? $receipt : null;
        }

        return Inertia::render('Manager/Catalog/Show', ['equipment' => $value, ...$messages]);
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

    public function archive(Request $request, string $equipment, EquipmentInputFactory $inputs, ChangeEquipmentStateAction $change): RedirectResponse
    {
        return $this->changeState($request, $equipment, $inputs, $change, ChangeEquipmentStateAction::ARCHIVE);
    }

    public function reactivate(Request $request, string $equipment, EquipmentInputFactory $inputs, ChangeEquipmentStateAction $change): RedirectResponse
    {
        return $this->changeState($request, $equipment, $inputs, $change, ChangeEquipmentStateAction::REACTIVATE);
    }

    private function changeState(Request $request, string $equipment, EquipmentInputFactory $inputs, ChangeEquipmentStateAction $change, string $operationType): RedirectResponse
    {
        $input = $inputs->state($request->all());
        $request->attributes->set('operationKey', $input['operationKey']);
        $receipt = $change->handle($this->actors->handle($request), $inputs->identifier($equipment), $operationType, $input['expectedVersion'], $input['operationKey']);

        return to_route('catalog.show', ['equipment' => $receipt->resourceId])->with('catalogReceipt', json_encode($receipt, JSON_THROW_ON_ERROR));
    }

    public function detail(Request $request, string $equipment, EquipmentInputFactory $inputs, ReadEquipmentAction $read): JsonResponse
    {
        return response()->json($read->handle($this->actors->handle($request), $inputs->identifier($equipment)), options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)->header('Cache-Control', 'private, no-store');
    }

    public function operation(Request $request, EquipmentInputFactory $inputs, ReadEquipmentOperationAction $read): JsonResponse
    {
        $valid = $inputs->operation($request->query());
        $result = $read->handle($this->actors->handle($request), $valid['operationType'], $valid['operationKey']);

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }
}
