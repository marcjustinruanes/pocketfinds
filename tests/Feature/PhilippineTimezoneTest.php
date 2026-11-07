<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\TestCase;

class PhilippineTimezoneTest extends TestCase
{
    public function test_application_and_database_connections_use_philippine_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', date_default_timezone_get());
        $this->assertSame('+08:00', config('database.connections.mysql.timezone'));
        $this->assertSame('+08:00', config('database.connections.mariadb.timezone'));

        $this->travelTo(CarbonImmutable::parse('2026-10-27T20:48:00+08:00'));
        try {
            $this->assertSame('2026-10-27 20:48:00', now()->toDateTimeString());
            $this->assertSame(28800, now()->offset);
        } finally {
            $this->travelBack();
        }
    }
}
