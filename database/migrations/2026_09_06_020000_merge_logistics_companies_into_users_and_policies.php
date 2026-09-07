<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapses the short-lived `logistics_companies` / `logistics_company_policies`
 * tables back into the app's existing two big tables, matching how everything
 * else here works: a "company" is just whichever name a logistics/rider row
 * shares with its teammates (users.business_name, already used for this by
 * sellers), plus one new logo column; a company's own Terms & Conditions is
 * just another row in the existing `policies` table (type='logistics_company_terms',
 * keyed by company_name instead of account_type), reusing the same
 * history-JSON audit trail the platform-wide docs already use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_logo')->nullable()->after('business_permit_file');
        });

        Schema::table('policies', function (Blueprint $table) {
            // NULL for the platform-wide docs (type='terms_and_conditions'); set for
            // company-authored ones (type='logistics_company_terms') instead of account_type.
            $table->string('company_name')->nullable()->after('account_type');
            $table->text('pending_content')->nullable();
            $table->foreignId('pending_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pending_submitted_at')->nullable();
            $table->string('rejection_reason')->nullable();
        });

        // A partial index, not a plain composite unique: account_type is NULL for every
        // company row and company_name is NULL for every platform-wide row, so a normal
        // multi-column unique wouldn't actually stop two rows for the same company.
        DB::statement("CREATE UNIQUE INDEX policies_company_terms_unique ON policies (company_name) WHERE type = 'logistics_company_terms'");

        // Backfill: fold each logistics_companies row's identity onto its member users'
        // business_name/company_logo, and any logistics_company_policies content into
        // a matching `policies` row, before the old tables are dropped for good.
        if (Schema::hasTable('logistics_companies')) {
            foreach (DB::table('logistics_companies')->get() as $company) {
                DB::table('users')->where('logistics_company_id', $company->id)->update([
                    'business_name' => $company->name,
                    'company_logo'  => $company->logo_path,
                ]);

                if (Schema::hasTable('logistics_company_policies')) {
                    $policy = DB::table('logistics_company_policies')->where('logistics_company_id', $company->id)->first();
                    if ($policy && (filled($policy->content) || filled($policy->pending_content))) {
                        DB::table('policies')->insert([
                            'type'                  => 'logistics_company_terms',
                            'company_name'          => $company->name,
                            'title'                 => $company->name . ' — Terms & Conditions',
                            'content'               => $policy->content,
                            'pending_content'       => $policy->pending_content,
                            'pending_submitted_by'  => $policy->pending_submitted_by,
                            'pending_submitted_at'  => $policy->pending_submitted_at,
                            'rejection_reason'      => $policy->rejection_reason,
                            'history'               => $policy->history,
                            'updated_by'            => $policy->updated_by,
                            'created_at'            => $policy->created_at,
                            'updated_at'            => $policy->updated_at,
                        ]);
                    }
                }
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logistics_company_id');
        });
        Schema::dropIfExists('logistics_company_policies');
        Schema::dropIfExists('logistics_companies');
    }

    public function down(): void
    {
        Schema::create('logistics_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('logistics_company_id')->nullable()->after('business_name')
                ->constrained('logistics_companies')->nullOnDelete();
        });

        Schema::create('logistics_company_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_company_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->text('pending_content')->nullable();
            $table->foreignId('pending_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pending_submitted_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->json('history')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (DB::table('users')->whereNotNull('business_name')->where('is_logistics', true)->select('business_name', 'company_logo')->distinct()->get() as $row) {
            $companyId = DB::table('logistics_companies')->insertGetId([
                'name'       => $row->business_name,
                'logo_path'  => $row->company_logo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('users')->where('business_name', $row->business_name)->where('is_logistics', true)
                ->update(['logistics_company_id' => $companyId]);

            $policy = DB::table('policies')->where('type', 'logistics_company_terms')->where('company_name', $row->business_name)->first();
            if ($policy) {
                DB::table('logistics_company_policies')->insert([
                    'logistics_company_id' => $companyId,
                    'content'              => $policy->content,
                    'pending_content'      => $policy->pending_content,
                    'pending_submitted_by' => $policy->pending_submitted_by,
                    'pending_submitted_at' => $policy->pending_submitted_at,
                    'rejection_reason'     => $policy->rejection_reason,
                    'history'              => $policy->history,
                    'updated_by'           => $policy->updated_by,
                    'created_at'           => $policy->created_at,
                    'updated_at'           => $policy->updated_at,
                ]);
            }
        }
        DB::table('policies')->where('type', 'logistics_company_terms')->delete();

        DB::statement('DROP INDEX IF EXISTS policies_company_terms_unique');
        Schema::table('policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_submitted_by');
            $table->dropColumn(['company_name', 'pending_content', 'pending_submitted_at', 'rejection_reason']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('company_logo');
        });
    }
};
