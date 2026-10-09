<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignUuid('product_id')->nullable()->after('body')->constrained('products')->nullOnDelete();
            $table->string('attachment_path')->nullable()->after('product_id');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_type')->nullable()->after('attachment_name'); // image|video|document
            $table->string('attachment_mime')->nullable()->after('attachment_type');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['attachment_path','attachment_name','attachment_type','attachment_mime','attachment_size']);
        });
    }
};
