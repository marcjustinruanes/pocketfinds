<?php

namespace App\Services;

use App\Models\CompanyVehicle;
use App\Models\LogisticsHub;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HubHiring
{
    private ?User $registering = null;

    public function role(User $u): ?string
    {
        if ($u->is_admin) {
            return null;
        }

        return $u->account_type === 'rider' && ! $u->is_logistics ? 'rider'
            : ($u->account_type === 'logistics' && $u->is_logistics && $u->logistics_role === 'hub_staff' ? 'hub_staff' : null);
    }

    public function prefix(string $role): string
    {
        return $role === 'rider' ? 'rider' : 'staff';
    }

    public function personnel(LogisticsHub $hub, string $role)
    {
        return User::where('business_name', $hub->company_name)->where('logistics_hub_id', $hub->id)->where('status', 'approved')->where('is_admin', false)
            ->when($role === 'rider', fn ($q) => $q->where('account_type', 'rider')->where('is_logistics', false),
                fn ($q) => $q->where('account_type', 'logistics')->where('is_logistics', true)->where('logistics_role', 'hub_staff'));
    }

    public function availability(LogisticsHub $hub, string $role, ?int $count = null): array
    {
        $p = $this->prefix($role);
        $count ??= $this->personnel($hub, $role)->count();
        $limit = $hub->{$p.'_hire_limit'};
        $deadline = $hub->{$p.'_hiring_closes_at'};
        $open = $hub->{$p.'_hiring_open'} ?? $hub->is_hiring;
        $status = ! $hub->is_operational ? 'Hub not operational' : (! $open ? 'Closed' : ($deadline && now()->greaterThanOrEqualTo($deadline) ? 'Deadline passed'
            : ($limit !== null && $count >= $limit ? 'Full' : ($deadline ? 'Scheduled to close' : 'Open'))));

        return ['eligible' => in_array($status, ['Open', 'Scheduled to close'], true), 'status' => $status, 'count' => $count,
            'limit' => $limit, 'remaining' => $limit === null ? null : max(0, $limit - $count), 'over_capacity' => $limit !== null && $count > $limit];
    }

    public function assertIntake(LogisticsHub $hub, string $role, bool $locked = false): void
    {
        $count = $locked ? $this->personnel($hub, $role)->orderBy('id')->lockForUpdate()->get(['id'])->count() : null;
        $state = $this->availability($hub, $role, $count);
        if (! $state['eligible']) {
            throw ValidationException::withMessages(['logistics_hub_id' => 'This hub cannot accept this application: '.$state['status'].'.']);
        }
    }

    // All Eloquent account writes share the same hub lock, including generic Admin actions.
    public function persist(User $user, Closure $save): bool
    {
        return DB::transaction(function () use ($user, $save) {
            $before = $user->exists ? DB::table('users')->where('id', $user->id)->first() : null;
            $ids = array_unique(array_filter([$before?->logistics_hub_id, $user->logistics_hub_id]));
            $hubs = LogisticsHub::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($before) {
                $fresh = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
                foreach (['business_name', 'account_type', 'logistics_role', 'is_admin', 'is_logistics'] as $field) {
                    if ($fresh->{$field} != $user->getRawOriginal($field)) {
                        throw ValidationException::withMessages(['status' => 'This account changed. Refresh before saving.']);
                    }
                }
                if ($fresh->status !== $before->status || $fresh->logistics_hub_id !== $before->logistics_hub_id
                    || $fresh->status !== $user->getRawOriginal('status') || $fresh->logistics_hub_id != $user->getRawOriginal('logistics_hub_id')) {
                    throw ValidationException::withMessages(['status' => 'This account changed. Refresh before saving.']);
                }
            }
            $role = $this->role($user);
            $changed = ! $before || $user->isDirty(['status', 'logistics_hub_id', 'account_type', 'logistics_role', 'business_name']);
            if ($before && $role && $user->isDirty('status') && auth()->check() && $this->registering !== $user) {
                $review = clone $user;
                foreach ((array) $fresh as $field => $value) {
                    if (! $user->isDirty($field)) {
                        $review->setAttribute($field, $value);
                    }
                }
                $this->validateTransition($review, $role, $fresh->status);
            }
            $historyChanged = $changed || $user->hiringDecision || $user->isDirty(['status_reason', 'interview_scheduled_at', 'interview_location']);
            if ($role && $changed && $user->status === 'approved') {
                $hub = $hubs->get($user->logistics_hub_id);
                if (! $hub || ! $hub->is_operational || $hub->company_name !== $user->business_name) {
                    throw ValidationException::withMessages(['logistics_hub_id' => 'An operational hub in the account company is required.']);
                }
                $p = $this->prefix($role);
                $count = $this->personnel($hub, $role)->when($user->exists, fn ($q) => $q->where('id', '!=', $user->id))->orderBy('id')->lockForUpdate()->get(['id'])->count();
                if ($hub->{$p.'_hire_limit'} !== null && $count >= $hub->{$p.'_hire_limit'}) {
                    throw ValidationException::withMessages(['status' => 'The approved-hire limit is full. This account was not approved or moved.']);
                }
            }
            $result = $save();
            if ($result && $role === 'hub_staff' && $changed) app(HubStaffSalary::class)->membershipChanged($user, $before);
            if ($result && $role && $historyChanged) {
                $previous = DB::table('hiring_application_history')->where('user_id', $user->id)->orderByDesc('id')->first();
                $attempt = ! $previous || ($before?->status === 'rejected' && $user->status === 'pending') ? (string) Str::uuid() : $previous->attempt_id;
                DB::table('hiring_application_history')->insert(['user_id' => $user->id, 'attempt_id' => $attempt, 'company_name' => $user->business_name ?? '',
                    'hub_id' => $user->logistics_hub_id, 'role' => $role, 'status' => $user->status, 'reason' => $user->hiringDecision['reason'] ?? $user->status_reason,
                    'review_details' => json_encode(array_merge(($review ?? $user)->only(['interview_scheduled_at', 'interview_location']), $user->hiringDecision, ['from_status'=>$before?->status,'to_status'=>$user->status])),
                    'actor_id' => auth()->id(), 'occurred_at' => now(), 'recorded_at' => now(), 'legacy_snapshot' => false]);
            }

            $user->hiringDecision = [];
            return $result;
        });
    }

    private function validateTransition(User $user, string $role, string $from): void
    {
        $actor = User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
        abort_unless($actor->status === 'approved',403);
        $platform = (bool) $actor->is_admin;
        abort_unless($platform || ($actor->account_type==='logistics' && $actor->is_logistics && $actor->isLogisticsAdmin() && $actor->status === 'approved'
            && $actor->business_name === $user->business_name), 403);
        $allowed = $platform ? ['approved' => ['suspended'], 'suspended' => ['approved']]
            : ['pending' => $role === 'hub_staff' ? ['interview', 'rejected'] : ['approved', 'rejected'],
                'interview' => $role === 'hub_staff' ? ['approved', 'rejected'] : [],
                'approved' => ['suspended'], 'suspended' => ['approved']];
        if (! in_array($user->status, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'This recruitment status transition is not permitted.']);
        }
        if (in_array($user->status, ['rejected', 'suspended'], true)) {
            validator(['status_reason'=>trim((string)$user->status_reason)], ['status_reason' => 'required|string|min:3|max:1200|regex:/[\p{L}\p{N}]/u'])->validate();
        }
        if ($role === 'rider' && $from === 'suspended' && $user->status === 'approved') validator(['reason'=>$user->hiringDecision['reason']??null],['reason'=>'required|string|min:3|max:1200|regex:/[\p{L}\p{N}]/u'])->validate();
        if ($role === 'hub_staff' && $user->status === 'interview') {
            validator($user->getAttributes(), ['interview_scheduled_at' => 'required|date', 'interview_location' => 'required|string|max:255'])->validate();
        }
        if ($user->status === 'approved' && $from !== 'suspended') {
            $rules = ['id_file' => 'required|string', 'id_type_id' => 'required|exists:id_types,id', 'selfie_file' => 'required|string'];
            if ($role === 'hub_staff') {
                $rules += ['interview_scheduled_at' => 'required|date', 'interview_location' => 'required|string|max:255'];
            } else {
                $rules += ['license_file' => 'required|string', 'license_number' => 'required|string|max:50', 'license_expiry' => 'required|date|after_or_equal:today',
                    'vehicle_type' => 'required|exists:vehicle_types,slug', 'vehicle_ownership' => 'required|in:own,company'];
                if ($user->vehicle_ownership === 'own') {
                    $rules += ['vehicle_brand' => 'required|string|max:100', 'vehicle_model' => 'required|string|max:100',
                        'plate_number' => 'required|string|max:20', 'or_file' => 'required|string', 'cr_file' => 'required|string'];
                } elseif ($user->vehicle_ownership === 'company' && ! CompanyVehicle::typeAvailableAtHub($user->logistics_hub_id, (string) $user->vehicle_type)) {
                    throw ValidationException::withMessages(['vehicle_type' => 'The selected company vehicle type is unavailable.']);
                }
            }
            validator($user->getAttributes(), $rules)->validate();
            app(PersonnelApplication::class)->assertReadable($user,$role);
            if($role==='hub_staff') validator($user->hiringDecision,['interview_completed'=>'required|accepted'])->validate();
        }
    }

    public function identityQuery(array $data)
    {
        $same = User::where('status', '!=', 'rejected');
        $same->whereDate('birthday', $data['birthday']);
        foreach (['given_names', 'last_name', 'middle_name', 'suffix', 'sex'] as $key) {
            $same->whereRaw("LOWER(TRIM(COALESCE($key, ''))) = ?", [mb_strtolower(trim((string) ($data[$key] ?? '')))]);
        }
        return $same;
    }

    public function lockApplicationIdentity(array $data, ?string $role): array
    {
            $identity = implode('|', array_map(fn ($k) => mb_strtolower(trim((string) ($data[$k] ?? ''))), ['given_names', 'last_name', 'middle_name', 'suffix', 'birthday', 'sex']));
            if (DB::connection()->getDriverName() === 'mysql') {
                // Match the actual name collation, including accent equivalence, for the mutex.
                $collation = DB::selectOne("SELECT COLLATION_NAME AS collation_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='given_names'")->collation_name;
                if (! preg_match('/^[a-zA-Z0-9_]+$/D', $collation)) {
                    throw new \RuntimeException('Unsupported identity collation.');
                }
                $identity = DB::selectOne('SELECT HEX(WEIGHT_STRING(CONVERT(? USING utf8mb4) COLLATE '.$collation.')) AS identity_weight', [$identity])->identity_weight;
            }
            $keys = [$data['email'] ?? '', $data['username'] ?? '', $identity];
            if(($data['account_type']??null)==='logistics' && ($data['is_logistics']??false) && in_array($data['logistics_role']??null,[null,'admin'],true)) $keys[]=CompanyOwnership::mutex((string)$data['business_name']);
            if (! empty($data['contact_no'])) {
                $data['contact_no'] = \App\Support\PhilippineMobile::normalize($data['contact_no']);
                $keys[] = 'mobile:'.$data['contact_no'];
            }
            foreach (['license_number', 'plate_number'] as $field) {
                if ($role === 'rider' && ! empty($data[$field])) {
                    $data[$field] = trim($data[$field]);
                    $value = mb_strtolower($data[$field]);
                    if (isset($collation)) {
                        $value = DB::selectOne('SELECT HEX(WEIGHT_STRING(CONVERT(? USING utf8mb4) COLLATE '.$collation.')) AS identity_weight', [$value])->identity_weight;
                    }
                    $keys[] = $field.':'.$value;
                }
            }
            $buckets = array_unique(array_map(fn ($v) => hexdec(substr(hash('sha256', mb_strtolower($v)), 0, 2)), $keys));
            DB::table('hiring_identity_locks')->whereIn('id', $buckets)->orderBy('id')->lockForUpdate()->get();
        return $data;
    }

    public function register(array $data, ?string $role): User
    {
        return DB::transaction(function () use ($data, $role) {
            $data = $this->lockApplicationIdentity($data, $role);
            // Only the candidate rejected row may retain its own mobile. Full identity,
            // role/company and attempt matching below must still succeed before reuse.
            if (! empty($data['contact_no']) && User::whereIn('contact_no', \App\Support\PhilippineMobile::variants($data['contact_no']))->whereNot(fn($q)=>$q->where('status','rejected')->where('email',$data['email'])->where('username',$data['username']))->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['contact_no' => 'This mobile number is already registered.']);
            }
            $matchQuery = fn () => User::where(fn ($q) => $q->where('email', $data['email'])->orWhere('username', $data['username']));
            $preliminary = $matchQuery()->get();
            if ($role) {
                $ids = array_unique(array_filter(array_merge([$data['logistics_hub_id']], $preliminary->pluck('logistics_hub_id')->all())));
                $hubs = LogisticsHub::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $hub = $hubs->get($data['logistics_hub_id']);
                if (! $hub || $hub->company_name !== $data['business_name']) {
                    throw ValidationException::withMessages(['logistics_hub_id' => 'Choose a valid company hub.']);
                }
                $this->assertIntake($hub, $role, true);
                if ($role === 'rider' && $data['vehicle_ownership'] === 'company' && ! CompanyVehicle::typeAvailableAtHub($hub->id, $data['vehicle_type'])) {
                    throw ValidationException::withMessages(['vehicle_type' => 'The selected company vehicle type is unavailable.']);
                }
            }
            $matches = $matchQuery()->orderBy('id')->lockForUpdate()->get();
            foreach (['license_number', 'plate_number'] as $field) {
                if ($role === 'rider' && ! empty($data[$field]) && User::where('status', '!=', 'rejected')
                    ->whereRaw('LOWER(TRIM('.$field.')) = ?', [mb_strtolower($data[$field])])->lockForUpdate()->first()) {
                    throw ValidationException::withMessages([$field => 'This document or vehicle identifier is already registered.']);
                }
            }
            if ($matches->isNotEmpty() && $preliminary->firstWhere('id', $matches->first()->id)?->logistics_hub_id != $matches->first()->logistics_hub_id) {
                throw ValidationException::withMessages(['email' => 'This application changed. Refresh before reapplying.']);
            }
            $same = $this->identityQuery($data);
            if ($same->lockForUpdate()->first() || $matches->count() > 1 || ($matches->isNotEmpty() && ($matches->first()->status !== 'rejected'
                || strcasecmp($matches->first()->email, $data['email']) !== 0 || strcasecmp($matches->first()->username, $data['username']) !== 0
                || $this->role($matches->first()) !== $role || $matches->first()->account_type !== $data['account_type'] || $matches->first()->business_name !== $data['business_name']))) {
                throw ValidationException::withMessages(['email' => 'An account already exists or the previous application does not match.']);
            }
            $account = $matches->first() ?? new User;
            if ($account->exists) {
                foreach (['given_names', 'last_name', 'middle_name', 'suffix', 'sex'] as $key) {
                    $previous = $account->{$key};
                    if ($previous instanceof \BackedEnum) {
                        $previous = $previous->value;
                    }
                    if (mb_strtolower(trim((string) $previous)) !== mb_strtolower(trim((string) ($data[$key] ?? '')))) {
                        throw ValidationException::withMessages(['email' => 'The rejected application identity does not match.']);
                    }
                }
                if ($account->birthday?->toDateString() !== $data['birthday']) {
                    throw ValidationException::withMessages(['birthday' => 'The rejected application identity does not match.']);
                }
                if (! $role) {
                    $old = DB::table('hiring_application_history')->where('user_id', $account->id)->orderByDesc('id')->first();
                    DB::table('hiring_application_history')->insert(['user_id' => $account->id, 'attempt_id' => $old?->attempt_id ?? (string) Str::uuid(),
                        'company_name' => $account->business_name ?? '', 'hub_id' => $account->logistics_hub_id, 'role' => $account->account_type,
                        'status' => 'rejected', 'reason' => $account->status_reason, 'occurred_at' => $account->updated_at ?? now(), 'recorded_at' => now(), 'legacy_snapshot' => true]);
                }
                $data = array_merge($data, ['status_reason' => null, 'interview_scheduled_at' => null, 'interview_location' => null]);
            }
            $returning = $account->exists;
            $account->fill($data);
            $this->registering = $account;
            try {
                $account->save();
            } finally {
                $this->registering = null;
            }
            if (! $role && $returning) {
                DB::table('hiring_application_history')->insert(['user_id' => $account->id, 'attempt_id' => (string) Str::uuid(),
                    'company_name' => $account->business_name ?? '', 'hub_id' => $account->logistics_hub_id, 'role' => $account->account_type,
                    'status' => 'pending', 'occurred_at' => now(), 'recorded_at' => now(), 'legacy_snapshot' => false]);
            }

            return $account;
        });
    }
}
