<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('shop_name')->nullable();
            $table->text('message_body')->nullable();
            $table->string('message_type')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->string('evidence_mime')->nullable();
            $table->string('evidence_type')->nullable();
            $table->unsignedBigInteger('evidence_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn([
                'message_id', 'shop_name', 'message_body', 'message_type',
                'evidence_path', 'evidence_name', 'evidence_mime',
                'evidence_type', 'evidence_size',
            ]);
        });
    }
};
