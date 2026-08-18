<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_account_type_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_account_type_check CHECK (account_type::text = ANY (ARRAY['buyer','rider','seller','admin','logistics']::text[]))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_account_type_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_account_type_check CHECK (account_type::text = ANY (ARRAY['buyer','rider','seller','admin']::text[]))");
    }
};
