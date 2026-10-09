<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('PocketFinds requires MySQL for its baseline schema.');
        }
        if (Schema::hasTable('users')) {
            // The verified transfer already installed the application schema.
            return;
        }
        $sql = file_get_contents(database_path('pocketfinds-mysql-schema.sql'));
        $sql = preg_replace('/^(CREATE SCHEMA .*|USE `pocketfinds`;)[\r\n]+/m', '', $sql);
        // Laravel owns its migration repository, so do not create it twice.
        $sql = preg_replace('/CREATE TABLE `migrations` \(.*?\) ENGINE=.*?;\s*/s', '', $sql);
        DB::unprepared($sql);
    }

    public function down(): void
    {
        throw new RuntimeException('Restore a database backup to undo the imported baseline; automatic rollback would erase application data.');
    }
};
