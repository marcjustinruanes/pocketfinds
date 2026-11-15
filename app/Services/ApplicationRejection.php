<?php

namespace App\Services;

use App\Enums\RejectionDecision;
use App\Mail\ApplicationRejectedMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Mail, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApplicationRejection
{
    public function reject(User $user, string $reason, Request $request): void
    {
        $data = $request->validate(['rejection_decision'=>['sometimes','required',Rule::enum(RejectionDecision::class)]]);
        $allow = ($data['rejection_decision'] ?? RejectionDecision::FinalRejection->value) === RejectionDecision::AllowResubmission->value;
        DB::transaction(function () use ($user,$reason,$allow) {
            $role = app(HubHiring::class)->role($user);
            if ($role && $user->logistics_hub_id) \App\Models\LogisticsHub::whereKey($user->logistics_hub_id)->lockForUpdate()->firstOrFail();
            $fresh = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($fresh->status===$user->status && $fresh->business_name===$user->business_name,409,'This application changed. Refresh before deciding.');
            $actor=User::whereKey(auth()->id())->firstOrFail();
            abort_unless($actor->status==='approved' && (($actor->is_admin && !$role) || ($role && $actor->isLogisticsAdmin() && $actor->is_logistics && !$actor->is_admin && $actor->business_name===$user->business_name)),403);
            if ($role) $user->hiringDecision = ['action'=>'reject','reason'=>$reason,'can_resubmit'=>$allow];
            $user->forceFill(['status'=>'rejected','status_reason'=>$reason,'can_resubmit'=>$allow])->save();
            if (!$role && Schema::hasTable('hiring_application_history')) {
                DB::table('hiring_application_history')->insert(['user_id'=>$user->id,'attempt_id'=>(string)Str::uuid(),'company_name'=>$user->business_name ?? '',
                    'hub_id'=>$user->logistics_hub_id,'role'=>$role ?? $user->account_type,'status'=>'rejected','reason'=>$reason,
                    'review_details'=>json_encode(['action'=>'rejection_decision','can_resubmit'=>$allow]),'actor_id'=>auth()->id(),'occurred_at'=>now(),'recorded_at'=>now(),'legacy_snapshot'=>false]);
            }
            if (!$allow && Schema::hasTable('rider_account_activations')) DB::table('rider_account_activations')->where('user_id',$user->id)->whereNull('activated_at')->update(['expires_at'=>now(),'token_hash'=>hash('sha256',Str::random(64))]);
            DB::afterCommit(function () use ($user,$allow,$actor) {
                $current=$user->fresh();
                if (!$current || $current->status!=='rejected' || (bool)$current->can_resubmit!==$allow) return;
                $invitation=$current->account_type==='rider'?app(RiderActivation::class)->invitation($current):null;
                try {
                    if ($allow && $invitation && !$invitation->activated_at) {
                        app(RiderActivation::class)->issue($current,$actor);
                        return;
                    }
                    Mail::to($current->email)->send(new ApplicationRejectedMail($current));
                }
                catch (\Throwable $error) { Log::warning('Application decision email could not be sent.',['user_id'=>$current->id,'exception'=>$error::class]); }
            });
        });
    }
}
