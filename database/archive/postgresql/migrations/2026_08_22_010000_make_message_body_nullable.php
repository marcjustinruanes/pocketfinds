<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Existing attachment-only messages cannot safely be made non-null again.
    }
};
