<?php

declare(strict_types=1);

use App\Infrastructure\Operations\ClosedOperationResultData;
use App\Infrastructure\Operations\CommandReceipt;
use App\Infrastructure\Operations\OperationClosureData;
use App\Infrastructure\Operations\OperationResultData;
use App\Modules\Catalog\Equipment\UseCases\ReadEquipment\ManagementPageData;
use App\Modules\Catalog\PublicApi\CatalogAudience;
use App\Modules\Catalog\PublicApi\EquipmentData;
use App\Modules\Catalog\PublicApi\EquipmentPage;
use App\Modules\Catalog\PublicApi\EquipmentQuery;
use App\Modules\IdentityAccess\Access\Data\SessionContextData;
use App\Modules\IdentityAccess\Identity\Data\AuthenticatedUserData;
use App\Modules\IdentityAccess\PublicApi\Role;
use App\Modules\IdentityAccess\PublicApi\SessionCandidateData;
use App\Modules\IdentityAccess\PublicApi\SessionCandidatePage;
use App\Shared\Errors\FieldErrorData;
use App\Shared\Errors\IssueData;
use App\Shared\Errors\NextActionData;
use App\Shared\Errors\ValidationPresentationData;
use App\Shared\Realtime\NotificationEnvelopeData;
use Illuminate\Contracts\Console\Kernel;
use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Tests\Fixtures\Contracts\ProbeData;
use Tests\Fixtures\Contracts\ProbeState;

try {
    $root = dirname(__DIR__);
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    /** @return array<string, string> */
    function generatedHashes(string $root): array
    {
        $hashes = [];
        foreach (['tests/Frontend/generated'] as $directory) {
            if (! is_dir($root.'/'.$directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile()) {
                    $hash = hash_file('sha256', $file->getPathname());
                    if ($hash === false) {
                        throw new RuntimeException('Could not hash generated file.');
                    }
                    $hashes[substr($file->getPathname(), strlen($root) + 1)] = $hash;
                }
            }
        }
        ksort($hashes);

        return $hashes;
    }

    $check = in_array('--check', $_SERVER['argv'], true);
    $before = generatedHashes($root);
    if (! is_dir($root.'/tests/Frontend/generated') && ! mkdir($root.'/tests/Frontend/generated', 0755, true)) {
        throw new RuntimeException('Could not create technical output directory.');
    }
    $config = TypeScriptTransformerConfigFactory::create()
        ->outputDirectory($root.'/tests/Frontend/generated')
        ->transformDirectories($root.'/tests/Fixtures/Contracts')
        ->replaceType(DateTimeImmutable::class, 'string')
        ->transformer(AttributedClassTransformer::class, EnumTransformer::class)
        ->extension(new LaravelDataTypeScriptTransformerExtension)
        ->writer(new GlobalNamespaceWriter('contracts.d.ts'));
    TypeScriptTransformer::create($config)->execute();
    $probe = new ProbeData(
        '019a23d0-0000-7000-8000-000000000001',
        new DateTimeImmutable('2026-10-09T12:30:00+00:00'),
        ProbeState::Ready,
        null,
        ['fr', 'en'],
    );
    if (file_put_contents($root.'/tests/Frontend/generated/sample.json', json_encode($probe->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL) === false) {
        throw new RuntimeException('Could not write sample serialization.');
    }
    $identity = new AuthenticatedUserData('019a23d0-0000-7000-8000-000000000001', 'synthetic-manager', 'Compte témoin', '019a23d0-0000-7000-8000-000000000002', 'Atelier témoin', Role::Manager);
    if (file_put_contents($root.'/tests/Frontend/generated/identity.json', json_encode($identity->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL) === false) {
        throw new RuntimeException('Could not write identity serialization.');
    }
    $id = '019a23d0-0000-7000-8000-000000000003';
    $token = str_repeat('a', 64);
    $field = new FieldErrorData('validation.required');
    $presentation = [
        'context' => new SessionContextData($identity, $token, 1, '2026-10-20T15:30:00.000Z', 'en'),
        'issue' => new IssueData('identity_access.authentication_required', [], $id, 'notApplied', new NextActionData('signIn')),
        'validation' => new ValidationPresentationData('fr', ['login' => [$field]]),
        'notification' => new NotificationEnvelopeData($id, 1, 'catalog.invalidated', ['projection' => 'catalog']),
        'candidates' => new SessionCandidatePage([new SessionCandidateData($identity->id, Role::Manager, $token)], null),
    ];
    if (file_put_contents($root.'/tests/Frontend/generated/presentation.json', json_encode($presentation, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL) === false) {
        throw new RuntimeException('Could not write presentation serialization.');
    }
    $equipment = new EquipmentData($id, 'Perceuse électricité', null, 'Outillage', 2, 'active', 1, null);
    $catalog = [
        'equipment' => $equipment,
        'page' => new EquipmentPage([$equipment], 1, 25, false, '2026-10-20T15:30:00.000Z'),
        'managementPage' => ManagementPageData::fromPage(new EquipmentPage([$equipment], 1, 25, false, '2026-10-20T15:30:00.000Z')),
        'query' => new EquipmentQuery('%_', 'Outillage', audience: CatalogAudience::Management),
        'operation' => new OperationResultData('applied', new CommandReceipt($id, 'catalog.equipment.create', 'equipment', $id, 1, '2026-10-20T15:30:00.000Z', false)),
        'closedOperation' => new ClosedOperationResultData(new OperationClosureData($id, '2026-10-20T15:30:00.000Z')),
    ];
    if (file_put_contents($root.'/tests/Frontend/generated/catalog.json', json_encode($catalog, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL) === false) {
        throw new RuntimeException('Could not write catalogue serialization.');
    }
    $after = generatedHashes($root);
    $sample = file_get_contents($root.'/tests/Frontend/generated/contracts.d.ts');
    if ($sample === false || ! str_contains($sample, 'ProbeData') || ! str_contains($sample, 'ProbeState')) {
        fwrite(STDERR, 'Required technical exports missing.'.PHP_EOL);
        exit(1);
    }
    if ($check && ($before === [] || $before !== $after)) {
        fwrite(STDERR, 'Generated outputs were missing or drifted; inspect before accepting regeneration.'.PHP_EOL);
        exit(1);
    }
    echo json_encode(['outputs' => count($after), 'stable' => $before === $after, 'sampleOnly' => false, 'qualifiedProductContracts' => ['AuthenticatedUserData', 'SessionContextData', 'IssueData', 'ValidationPresentationData', 'NotificationEnvelopeData', 'SessionCandidatePage', 'EquipmentData', 'EquipmentPage', 'EquipmentQuery', 'CommandReceipt', 'OperationResultData', 'OperationClosureData', 'ClosedOperationResultData']], JSON_THROW_ON_ERROR).PHP_EOL;

} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['errorClass' => $error::class, 'nativeCode' => $error->getCode(), 'messageOmitted' => true], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(6);
}
