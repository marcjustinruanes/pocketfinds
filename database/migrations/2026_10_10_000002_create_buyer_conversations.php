<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buyer_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('buyer_id', 64)->index();
            $table->text('title');
            $table->string('engine', 16)->default('smart');
            $table->boolean('orders_shared')->default(false);
            $table->unsignedInteger('context_segment')->default(0);
            $table->timestamps();
        });
        Schema::create('buyer_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('buyer_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('engine', 16);
            $table->unsignedInteger('context_segment');
            $table->text('content');
            $table->longText('payload')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_chat_messages');
        Schema::dropIfExists('buyer_conversations');
    }
};
