<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Contracts\ProbeData;
use Tests\Fixtures\Contracts\ProbeState;
use Tests\TestCase;

final class ContractTransformTest extends TestCase
{
    public function test_dates_enums_nullability_and_collections_serialize_without_queries(): void
    {
        $data = new ProbeData('opaque-id', new DateTimeImmutable('2026-10-09T12:30:00+00:00'), ProbeState::Ready, null, ['fr', 'en']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->assertSame(['id' => 'opaque-id', 'occurredAt' => '2026-10-09T12:30:00+00:00', 'state' => 'ready', 'note' => null, 'labels' => ['fr', 'en']], $data->toArray());
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
