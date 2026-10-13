<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('buyer_chat_messages', fn (Blueprint $table) => $table->string('provider', 16)->nullable());
    }
    public function down(): void
    {
        Schema::table('buyer_chat_messages', fn (Blueprint $table) => $table->dropColumn('provider'));
    }
};
