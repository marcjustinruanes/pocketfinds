<?php

namespace Tests\Feature;

use App\Services\GcashSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\WorkflowSchema;
use Tests\TestCase;

class GcashSchemaTest extends TestCase
{
    use WorkflowSchema;

    private function incompatible(string $variant): void
    {
        $this->createWorkflowSchema();
        Schema::create('gcash_purchase_history', function ($t) use ($variant) {
            if (in_array($variant, ['missing_primary', 'wrong_primary'])) {
                $t->unsignedBigInteger('id');
            } else {
                $t->id();
            }
            $t->uuid('purchase_id');
            if ($variant === 'wrong_primary') {
                $t->primary('purchase_id');
            }
            $t->bigInteger('actor_id')->nullable();
            $t->string('event', 40);
            $t->json('before');
            $t->json('after');
            if ($variant === 'wrong_type') {
                $t->integer('reason')->nullable();
            } else {
                $column = $t->text('reason');
                if ($variant !== 'wrong_nullable') {
                    $column->nullable();
                }
            }
            $t->timestamp('created_at');
            if ($variant === 'wrong_target') {
                $t->foreign('actor_id')->references('id')->on('payment_methods')->restrictOnDelete();
            }
            if ($variant === 'cascade') {
                $t->foreign('actor_id')->references('id')->on('users')->cascadeOnDelete();
            }
        });
        DB::table('gcash_purchase_history')->insert(['id' => 1, 'purchase_id' => '11111111-1111-4111-8111-111111111111', 'actor_id' => null, 'event' => 'retained', 'before' => '{}', 'after' => '{}', 'reason' => $variant === 'wrong_type' ? 7 : 'Preserve evidence', 'created_at' => now()]);
        $before = DB::table('gcash_purchase_history')->get()->toJson();
        $problems = GcashSchema::problems(allowMissing: true);
        $this->assertNotEmpty($problems);
        if (str_contains($variant, 'primary')) {
            $this->assertStringContainsString('Invalid primary key', implode('; ', $problems));
        }
        try {
            (require database_path('migrations/2026_10_14_000004_add_gcash_purchases.php'))->up();
            $this->fail('Incompatible existing schema must be rejected');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Incompatible existing GCash schema', $error->getMessage());
        }
        $this->assertSame($before, DB::table('gcash_purchase_history')->get()->toJson());
        $this->assertFalse(Schema::hasTable('gcash_purchases'));
    }

    public function test_incorrect_column_type_is_rejected_without_ddl_or_row_changes(): void
    {
        $this->incompatible('wrong_type');
    }

    public function test_incorrect_nullability_is_rejected_without_ddl_or_row_changes(): void
    {
        $this->incompatible('wrong_nullable');
    }

    public function test_missing_primary_key_is_rejected_without_recreating_history(): void
    {
        $this->incompatible('missing_primary');
    }

    public function test_incorrect_primary_key_is_rejected_without_recreating_history(): void
    {
        $this->incompatible('wrong_primary');
    }

    public function test_wrong_foreign_target_is_rejected_without_rewriting_history(): void
    {
        $this->incompatible('wrong_target');
    }

    public function test_cascade_history_deletion_is_rejected_without_rewriting_history(): void
    {
        $this->incompatible('cascade');
    }
}
