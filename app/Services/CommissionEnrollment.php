<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema, Validator};

class CommissionEnrollment
{
    public function installed(): bool { return Schema::hasTable('commission_enrollment_settings'); }

    public function state(bool $lock = false): ?object
    {
        if (!$this->installed()) return null;
        return DB::table('commission_enrollment_settings')->where('id',1)->when($lock,fn($q)=>$q->lockForUpdate())->first();
    }

    public function activation(bool $lock = false): ?CarbonImmutable
    {
        if ($this->installed()) {
            $state=$this->state($lock);
            return $state?->enabled && $state->activation_at ? CarbonImmutable::parse($state->activation_at) : null;
        }
        // Preserve pre-install deployments; after installation DB is authoritative,
        // including when config is cached or stale environment flags say enabled.
        return config('commission.enabled') && config('commission.activation_at') ? CarbonImmutable::parse(config('commission.activation_at')) : null;
    }

    public function change(User $user, array $input): void
    {
        $d=Validator::make($input,[
            'action'=>'required|in:activate,deactivate', 'revision'=>'required|integer|min:1',
            'confirmed'=>'required|accepted', 'reason'=>'required|string|min:3|max:1000',
            'commission_rate'=>'prohibited', 'activation_at'=>'prohibited', 'enabled'=>'prohibited',
        ])->validate();
        DB::transaction(function()use($user,$d){
            // Refresh and lock permissions; a stale authenticated model is insufficient.
            $actor=User::whereKey($user->id)->lockForUpdate()->first();
            abort_unless($actor && $actor->is_admin && !$actor->is_logistics && $actor->status==='approved',403);
            $state=$this->state(true);
            abort_unless($state && Schema::hasTable('commission_enrollment_events'),503,'Commission enrollment settings installation is required.');
            abort_unless((int)$state->revision===(int)$d['revision'],409,'Commission status changed. Refresh Settings before trying again.');
            $enabled=$d['action']==='activate';
            abort_if((bool)$state->enabled===$enabled,409,'Commission enrollment already has that status.');
            if ($enabled) {
                $destination=app(CommissionAccounts::class)->settings(true);
                abort_unless($destination?->active_account_id,422,'Activate the official Admin GCash receiving account first.');
                $raw=DB::table('commission_accounts')->find($destination->active_account_id);
                $account=$raw ? app(CommissionAccounts::class)->details($raw) : null;
                abort_unless($account && trim($account->account_name)!=='' && preg_match('/^09[0-9]{9}$/D',$account->account_number),422,'The active receiving account requires correction.');
            }
            // Orders use whole-second timestamps. Start at the next whole second
            // so an order placed earlier in this second can never be backfilled.
            $cutoff=$enabled ? now()->addSecond()->startOfSecond() : $state->activation_at;
            $revision=(int)$state->revision+1;
            DB::table('commission_enrollment_settings')->where('id',1)->update(['enabled'=>$enabled,'activation_at'=>$cutoff,'revision'=>$revision]);
            DB::table('commission_enrollment_events')->insert([
                'actor_id'=>$actor->id,'actor_name'=>$actor->username,'action'=>$d['action'],
                'previous_enabled'=>$state->enabled,'previous_activation_at'=>$state->activation_at,
                'activation_at'=>$cutoff,'revision'=>$revision,'reason'=>trim($d['reason']),'created_at'=>now(),
            ]);
        },3);
    }
}
