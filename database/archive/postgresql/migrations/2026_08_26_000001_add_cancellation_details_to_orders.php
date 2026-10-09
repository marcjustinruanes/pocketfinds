<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'cancellation_reason')) {
                $table->string('cancellation_reason')->nullable();
            }
            if (! Schema::hasColumn('orders', 'cancellation_note')) {
                $table->text('cancellation_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancellation_note']);
        });
    }
};