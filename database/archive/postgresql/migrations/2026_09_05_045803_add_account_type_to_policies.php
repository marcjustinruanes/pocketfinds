<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Terms & Conditions now vary by registration type — buyer/seller/rider/logistics each
 * get their own admin-editable document instead of sharing one. `type` stays the document
 * category ('terms_and_conditions', room for e.g. a privacy policy later); `account_type`
 * says which registration role it belongs to. The single existing row becomes the buyer
 * copy (it was already the generic default), and three siblings are seeded for the rest —
 * each carrying the base terms plus the role-specific clause that used to live in the old
 * registration quiz's JS (App\Http\Controllers\RegisterController-adjacent, now retired).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->string('account_type')->nullable()->after('type');
        });

        DB::statement('ALTER TABLE policies DROP CONSTRAINT IF EXISTS policies_type_unique');
        Schema::table('policies', function (Blueprint $table) {
            $table->unique(['type', 'account_type']);
        });

        $existing = DB::table('policies')->where('type', 'terms_and_conditions')->first();
        DB::table('policies')->where('id', $existing->id)->update(['account_type' => 'buyer']);

        $roleClauses = [
            'seller' => "\n\n6. Seller Listings & Fulfillment\nKeep listings accurate, disclose an item's condition, and fulfil confirmed orders as described. You must not post misleading prices, counterfeit goods, or items you cannot provide.",
            'rider' => "\n\n6. Rider Delivery & Safety\nFollow traffic laws, handle orders carefully, protect customer information, and communicate delivery updates through PocketFinds. Never mark an order delivered before it is safely handed over.",
            'logistics' => "\n\n6. Logistics Handling & Confidentiality\nHandle every parcel and its contents with care, keep buyer and seller information confidential, and record every pickup, sort, and assignment accurately in the system.",
        ];

        foreach ($roleClauses as $accountType => $clause) {
            DB::table('policies')->insert([
                'type' => 'terms_and_conditions',
                'account_type' => $accountType,
                'title' => 'Terms & Conditions',
                'content' => $existing->content . $clause,
                'history' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('policies')->where('type', 'terms_and_conditions')->where('account_type', '!=', 'buyer')->delete();
        Schema::table('policies', function (Blueprint $table) {
            $table->dropUnique(['type', 'account_type']);
            $table->dropColumn('account_type');
            $table->unique('type');
        });
    }
};
