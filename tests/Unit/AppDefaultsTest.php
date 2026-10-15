<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

final class AppDefaultsTest extends TestCase
{
    public function test_application_clock_returns_an_immutable_instant(): void
    {
        $now = Date::now();
        $this->assertInstanceOf(CarbonImmutable::class, $now);
        $later = $now->addSecond();
        $this->assertNotSame($now, $later);
        $this->assertSame(1, (int) $now->diffInSeconds($later));
    }
}
