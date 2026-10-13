<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 16)->index();
            $table->string('question', 255);
            $table->text('answer');
            $table->json('keywords')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('updated_by', 64);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('support_faqs'); }
};
