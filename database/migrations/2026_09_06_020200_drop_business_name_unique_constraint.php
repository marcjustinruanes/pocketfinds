<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * business_name was unique because only sellers used it (one shop = one row).
 * Now multiple logistics/rider users deliberately share the same business_name
 * when they join the same company — a blanket DB-level unique constraint can't
 * tell "two rows for the same company" apart from "an accidental duplicate shop
 * name", so uniqueness is enforced at the application layer instead (see
 * RegisterController::store() — sellers and new-company founders still get a
 * real `unique:users,business_name` validation rule; joining a company doesn't).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_business_name_unique');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_business_name_unique UNIQUE (business_name)');
    }
};
