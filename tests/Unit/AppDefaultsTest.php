<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class AppDefaultsTest extends TestCase
{
    #[DataProvider('guardEnvironments')]
    public function test_bootstrap_enables_eloquent_guards_only_for_development_and_tests(string $environment, bool $enabled): void
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.pgsql.database') !== 'testing' || (int) config('database.redis.default.database') !== 14 || $app->storagePath() !== $app->basePath('storage/framework/testing')) {
    throw new RuntimeException('Unsafe guard bootstrap profile.');
}
echo json_encode([
    'environment' => $app->environment(),
    'lazyLoading' => Illuminate\Database\Eloquent\Model::preventsLazyLoading(),
    'missingAttributes' => Illuminate\Database\Eloquent\Model::preventsAccessingMissingAttributes(),
], JSON_THROW_ON_ERROR);
PHP;
        $process = new Process([PHP_BINARY, '-r', $code], base_path(), [
            ...array_filter($_ENV, is_string(...)),
            'APP_ENV' => $environment,
            'EPR_INSTANCE' => 'testing',
        ]);
        $process->setTimeout(10);

        $process->mustRun();

        $this->assertSame([
            'environment' => $environment,
            'lazyLoading' => $enabled,
            'missingAttributes' => $enabled,
        ], json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
    }

    /** @return list<array{string, bool}> */
    public static function guardEnvironments(): array
    {
        return [['local', true], ['testing', true], ['production', false]];
    }

    public function test_persisted_models_reject_omitted_attributes_but_allow_present_and_null_values(): void
    {
        $model = $this->guardProbe()->newFromBuilder(['id' => 1, 'label' => 'Synthetic', 'nullable' => null]);
        $this->assertSame('Synthetic', $model->getAttribute('label'));
        $this->assertNull($model->getAttribute('nullable'));

        $this->expectException(MissingAttributeException::class);

        $model->getAttribute('omitted');
    }

    public function test_hydrated_collections_reject_unloaded_relations_before_querying(): void
    {
        $models = $this->guardProbe()->newQuery()->hydrate([
            ['id' => 1, 'parent_id' => 3],
            ['id' => 2, 'parent_id' => 3],
        ]);
        $model = $models->firstOrFail();

        $queries = DB::connection()->pretend(function () use ($model): void {
            $this->assertThrows(fn () => $model->getRelationValue('probeParent'), LazyLoadingViolationException::class);
        });

        $this->assertSame([], $queries);
    }

    public function test_hydrated_collections_allow_explicitly_loaded_relations_without_querying(): void
    {
        $models = $this->guardProbe()->newQuery()->hydrate([
            ['id' => 1, 'parent_id' => 3],
            ['id' => 2, 'parent_id' => 3],
        ]);
        $model = $models->firstOrFail();
        $parent = $this->guardProbe()->newFromBuilder(['id' => 3]);
        $model->setRelation('probeParent', $parent);

        $queries = DB::connection()->pretend(function () use ($model, $parent): void {
            $this->assertSame($parent, $model->getRelationValue('probeParent'));
        });

        $this->assertSame([], $queries);
    }

    public function test_single_model_hydration_retains_the_native_lazy_loading_limit(): void
    {
        $model = $this->guardProbe()->newQuery()->hydrate([['id' => 1, 'parent_id' => 3]])->sole();

        $queries = DB::connection()->pretend(function () use ($model): void {
            $this->assertNull($model->getRelationValue('probeParent'));
        });

        $this->assertCount(1, $queries);
    }

    public function test_application_clock_returns_an_immutable_instant(): void
    {
        $now = Date::now();
        $this->assertInstanceOf(CarbonImmutable::class, $now);
        $later = $now->addSecond();
        $this->assertNotSame($now, $later);
        $this->assertSame(1, (int) $now->diffInSeconds($later));
    }

    private function guardProbe(): Model
    {
        return new class extends Model
        {
            protected $table = 'eloquent_guard_probes';

            /** @return BelongsTo<self, $this> */
            public function probeParent(): BelongsTo
            {
                return $this->belongsTo(self::class, 'parent_id');
            }
        };
    }
}
