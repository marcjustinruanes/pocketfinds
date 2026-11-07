<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buyer-specific saved payment details are no longer used. GCash checkout
        // and purchase records remain handled by their separate workflows.
        Schema::dropIfExists('buyer_payment_accounts');
    }

    public function down(): void
    {
        // Dropped buyer account details cannot be restored from a schema rollback.
    }
};
