<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalizes the seller-only, document-only "document_update_requests" table into
 * an "account_update_requests" table that can hold ANY combination of plain-field
 * changes (requested_changes) and re-uploaded documents (requested_documents) for
 * ANY role (buyer, seller, rider, logistics) — the whole account, not just documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('document_update_requests', 'account_update_requests');

        Schema::table('account_update_requests', function (Blueprint $table) {
            $table->json('requested_changes')->nullable()->after('user_id');
            $table->json('requested_documents')->nullable()->after('requested_changes');
        });

        // Fold the old dedicated columns into the new JSON shape before dropping them.
        DB::table('account_update_requests')->orderBy('id')->each(function ($row) {
            $documents = array_filter([
                'id_file'              => $row->id_file,
                'business_permit_file' => $row->business_permit_file,
            ]);
            $changes = array_filter([
                'id_type_id' => $row->id_type_id,
            ], fn ($v) => !is_null($v));

            DB::table('account_update_requests')->where('id', $row->id)->update([
                'requested_documents' => $documents ? json_encode($documents) : null,
                'requested_changes'   => $changes ? json_encode($changes) : null,
            ]);
        });

        Schema::table('account_update_requests', function (Blueprint $table) {
            $table->dropColumn(['id_type_id', 'id_file', 'business_permit_file']);
        });
    }

    public function down(): void
    {
        Schema::table('account_update_requests', function (Blueprint $table) {
            $table->string('id_type_id')->nullable();
            $table->string('id_file')->nullable();
            $table->string('business_permit_file')->nullable();
        });

        DB::table('account_update_requests')->orderBy('id')->each(function ($row) {
            $changes   = $row->requested_changes ? json_decode($row->requested_changes, true) : [];
            $documents = $row->requested_documents ? json_decode($row->requested_documents, true) : [];

            DB::table('account_update_requests')->where('id', $row->id)->update([
                'id_type_id'           => $changes['id_type_id'] ?? null,
                'id_file'              => $documents['id_file'] ?? null,
                'business_permit_file' => $documents['business_permit_file'] ?? null,
            ]);
        });

        Schema::table('account_update_requests', function (Blueprint $table) {
            $table->dropColumn(['requested_changes', 'requested_documents']);
        });

        Schema::rename('account_update_requests', 'document_update_requests');
    }
};
