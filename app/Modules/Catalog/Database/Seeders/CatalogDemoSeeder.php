<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Database\Factories\EquipmentFactory;
use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\IdentityAccess\PublicApi\ActorContext;
use App\Modules\IdentityAccess\PublicApi\AuthorizeActor;
use App\Modules\IdentityAccess\PublicApi\SqlUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Explicit preparation only; never registered as an automatic application seed. */
final class CatalogDemoSeeder extends Seeder
{
    public function __construct(private SqlUnit $units, private AuthorizeActor $authorize) {}

    public function prepare(string $target): string
    {
        $identity = DB::connection('pgsql')->selectOne('SELECT session_user,current_user,current_database() AS database,current_schema() AS schema,host(inet_server_addr()) AS address');
        if (! app()->environment('local', 'testing') || app()->configurationIsCached() || ! in_array($target, ['laravel', 'testing'], true) || $identity === null || $identity->session_user !== 'epr_app' || $identity->current_user !== 'epr_app' || $identity->database !== $target || $identity->schema !== 'epr' || ! in_array($identity->address, gethostbynamel('pgsql') ?: [], true) || DB::connection('pgsql')->transactionLevel() !== 0) {
            throw new RuntimeException('Catalog demo requires an explicit isolated local runtime target without an outer transaction.');
        }
        $receipt = base_path('.epr-local/identity-'.$target.'.json');
        if (! is_file($receipt) || filesize($receipt) > 32768) {
            throw new RuntimeException('Prepare the synthetic accounts before the Catalog demo.');
        }
        $accounts = json_decode(file_get_contents($receipt) ?: '', true, flags: JSON_THROW_ON_ERROR);
        $actors = [];
        foreach (is_array($accounts) && is_array($accounts['users'] ?? null) ? $accounts['users'] : [] as $user) {
            if (is_array($user) && ($user['role'] ?? null) === 'manager' && is_string($user['id'] ?? null) && is_string($user['workshopId'] ?? null)) {
                $actors[] = new ActorContext($user['id'], $user['workshopId'], 'manager');
            }
        }
        if (count($actors) !== 2 || $actors[0]->workshopId === $actors[1]->workshopId) {
            throw new RuntimeException('Catalog demo requires two distinct current synthetic workshop managers.');
        }
        foreach ($actors as $actor) {
            $this->units->forActorRead($actor, function () use ($actor): void {
                $this->authorize->handle($actor, 'manage-catalog');
                $this->assertEmpty($actor);
            });
        }
        $rows = [];
        foreach ($actors as $actor) {
            foreach ([['Perceuse', 5, true], ['Perceuse', 2, true], ['Marteau', 1, true], ['Ponceuse', 0, true], ['Scie', 3, true], ['Ancienne perceuse', 2, false]] as [$name, $total, $active]) {
                $rows[] = ['id' => (string) Str::uuid7(), 'workshop_id' => $actor->workshopId, 'name' => $name, 'category' => 'Outillage', 'description' => null, 'total_quantity' => $total, 'is_active' => $active, 'version' => 1];
            }
        }
        $path = base_path('.epr-local/catalog-demo-'.$target.'-'.Str::uuid7().'.json');
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('Could not create the private demo fixture manifest.');
        }
        chmod($path, 0600);
        try {
            $content = json_encode(['database' => $target, 'equipment' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
            if (fwrite($handle, $content) !== strlen($content) || ! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException('Incomplete demo manifest; inspect before continuing.');
            }
        } finally {
            fclose($handle);
        }
        foreach ($actors as $actor) {
            $this->units->forActor($actor, function () use ($actor, $rows): void {
                $this->authorize->handle($actor, 'manage-catalog');
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['catalog.prepare-demo:'.$actor->workshopId]);
                $this->assertEmpty($actor);
                foreach ($rows as $row) {
                    if ($row['workshop_id'] === $actor->workshopId) {
                        EquipmentFactory::new()->createOne($row);
                    }
                }
            });
        }

        return $path;
    }

    private function assertEmpty(ActorContext $actor): void
    {
        if (Equipment::query()->where('workshop_id', $actor->workshopId)->exists()) {
            throw new RuntimeException('Existing equipment preserved; Catalog demo requires an empty Catalog in both workshops.');
        }
    }
}
