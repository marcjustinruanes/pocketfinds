<?php
declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Models\User;

final class ApiError extends RuntimeException {
    public function __construct(string $message, public readonly int $status = 422) { parent::__construct($message); }
}
function ensure(bool $condition, string $message, int $status = 422): void {
    if (!$condition) throw new ApiError($message, $status);
}
function cleanRow(object|array|null $row): ?array {
    if ($row === null) return null;
    $row = (array)$row;
    unset($row['password'], $row['remember_token'], $row['delivery_otp_hash'], $row['delivery_otp_ciphertext']);
    foreach ($row as $key => &$value) {
        if (in_array($key, ['items','shipping_address','variations','images','details','image_signature','reactions','variant_axes','variant_rows','photos','item_keys'], true) && is_string($value)) {
            $value = json_decode($value, true);
        }
        if (in_array($key, ['is_available','is_read','read','is_online','notify_enabled','is_anonymous','is_active','accepting_applications','is_admin','is_logistics','is_on_vacation','requires_verification'], true) && $value !== null) $value = (bool)$value;
    }
    return $row;
}
function cleanRows(iterable $rows): array { return array_map('cleanRow', is_array($rows) ? $rows : iterator_to_array($rows)); }
function validateData(array $data, array $rules): array {
    $validator = validator($data, $rules);
    if ($validator->fails()) throw new ApiError($validator->errors()->first());
    return $validator->validated();
}

final class Identity {
    public function __construct(public ?User $user, public ?object $registration = null, public ?object $token = null) {}
    public function id(): int { ensure($this->user !== null, 'Sign in to continue.', 401); return (int)$this->user->id; }
    public function owner(): string { return $this->user ? (string)$this->user->id : 'signup_'.$this->registration->id; }
    public function approved(?string $role = null): int {
        $id = $this->id();
        ensure($this->user->status === 'approved', 'This account is not approved.', 403);
        if ($role) ensure($this->user->account_type === $role, 'This action is not allowed for your account.', 403);
        return $id;
    }
    public static function fromBearer(string $raw): self {
        if (!$raw) return new self(null);
        $hash = hash('sha256', $raw);
        $token = DB::table('personal_access_tokens')->where('token', $hash)->where('name', 'pocketfinds-app')->where('expires_at', '>', now())->first();
        if ($token) {
            $last=$token->last_used_at??$token->created_at;
            if ($last && strtotime($last)<time()-1800) {
                DB::table('personal_access_tokens')->where('id',$token->id)->delete();
                throw new ApiError('Your session expired after inactivity. Please sign in again.',401);
            }
            DB::table('personal_access_tokens')->where('id',$token->id)->update(['last_used_at'=>now()]);
            $user = User::find($token->tokenable_id);
            if ($user && $token->tokenable_type === User::class) return new self($user, null, $token);
        }
        $registration = DB::table('mobile_registration_tokens')->where('token_hash', $hash)->where('expires_at', '>', now())->first();
        if ($registration) return new self($registration->user_id ? User::find($registration->user_id) : null, $registration);
        throw new ApiError('Your session expired. Please sign in again.', 401);
    }
    public function session(?string $raw = null): array {
        if ($this->user) {
            if ($raw === null) {
                $raw = bin2hex(random_bytes(32));
                DB::table('personal_access_tokens')->insert([
                    'tokenable_type' => User::class, 'tokenable_id' => $this->user->id,
                    'name' => 'pocketfinds-app', 'token' => hash('sha256', $raw),
                    'abilities' => json_encode(['mobile']), 'expires_at' => now()->addDays(7),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return ['access_token' => $raw, 'user' => ['id' => $this->user->id, 'email' => $this->user->email],
                'media_token' => Crypt::encryptString(json_encode(['user_id' => $this->user->id, 'token_hash'=>hash('sha256',$raw), 'expires' => time()+604800]))];
        }
        ensure($this->registration !== null && $raw !== null, 'Verify your email first.', 401);
        return ['access_token' => $raw, 'user' => ['id' => $this->owner(), 'email' => $this->registration->email]];
    }
}
