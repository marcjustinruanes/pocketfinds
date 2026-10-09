<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'suspended'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET status = 'rejected' WHERE status = 'suspended'");
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
    }
};
