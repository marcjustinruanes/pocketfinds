<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PhoneScanner
{
    public function create(User $user, ?string $browserSession = null): array
    {
        abort_unless(Cache::getStore() instanceof LockProvider, 503, 'Phone scanning requires a cache store with locks.');
        $token = Str::random(64);
        $expires = now()->addMinutes(10)->timestamp;
        $stored = Cache::put($this->key($token), ['user_id' => $user->id, 'company' => $user->business_name, 'hub_id' => $user->logistics_hub_id,
            'role' => $user->logistics_role, 'browser_session_hash' => $browserSession ? hash('sha256', $browserSession) : null,
            'expires_at' => $expires, 'sequence' => 0, 'acknowledged' => 0, 'events' => [], 'seen' => []], 600);
        abort_unless($stored, 503, 'The scanner connection could not be saved.');

        return ['token' => $token, 'expires_at' => $expires];
    }

    private function key(string $token): string
    {
        return 'phone-scanner:'.hash('sha256', $token);
    }

    public function session(User $user, string $token, Closure $callback, bool $write = false, bool $allowClosed = false): mixed
    {
        $key = $this->key($token);
        try {
            return Cache::lock($key.':lock', 10)->block(3, function () use ($key, $user, $callback, $write, $allowClosed) {
                $state = Cache::get($key);
                abort_unless(is_array($state) && $state['expires_at'] > now()->timestamp, 410, 'This phone connection has expired.');
                abort_unless((int) $state['user_id'] === (int) $user->id && $state['company'] === $user->business_name
                    && $state['hub_id'] == $user->logistics_hub_id && $state['role'] === $user->logistics_role, 403, 'Sign in with the same account that connected the computer.');
                abort_if(! $allowClosed && ! empty($state['closed']), 410, 'This phone connection has disconnected.');
                $result = $callback($state);
                if ($write) {
                    abort_unless(Cache::put($key, $state, max(1, $state['expires_at'] - now()->timestamp)), 503, 'The scanner connection could not be saved.');
                }

                return $result;
            });
        } catch (LockTimeoutException $e) {
            abort(503, 'The scanner connection is busy. Please retry.');
        }
    }

    public function status(array $state): array
    {
        return ['state' => empty($state['closed']) ? 'connected' : 'disconnected',
            'disconnected_by' => $state['disconnected_by'] ?? null, 'expires_at' => $state['expires_at']];
    }

    public function close(array &$state, string $actor): array
    {
        if (empty($state['closed'])) {
            $state['closed'] = true;
            $state['disconnected_by'] = $actor;
            $state['events'] = [];
            $state['seen'] = [];
        }

        return $this->status($state);
    }

    public function send(User $user, string $token, string $code, string $scanId, ?int $mobileTokenId = null): array
    {
        return $this->session($user, $token, function (&$state) use ($user, $code, $scanId, $mobileTokenId) {
            if (isset($state['mobile_token_id'])) {
                abort_unless($state['mobile_token_id'] === $mobileTokenId, 403, 'Use the paired Android app to send codes.');
            }
            if (isset($state['seen'][$scanId])) {
                abort_unless(hash_equals($state['seen'][$scanId]['code_hash'], hash('sha256', $code)), 422, 'This scan identifier was already used for another code.');

                return ['sequence' => $state['seen'][$scanId]['sequence'], 'duplicate' => true];
            }
            abort_if(count($state['events']) >= 20, 429, 'The computer has not collected earlier scans. Wait before scanning another parcel.');
            abort_if(count($state['seen']) >= 1000, 422, 'Reconnect the phone to start a new scanning session.');
            abort_unless(app(LogisticsAccess::class)->shipments($user)
                ->where(fn ($query) => $query->where('tracking_number', $code)->orWhereHas('order', fn ($order) => $order->where('order_number', $code)))->exists(),
                404, 'No permitted parcel was found for this code.');
            $sequence = ++$state['sequence'];
            $state['events'][] = ['sequence' => $sequence, 'code' => $code];
            $state['seen'][$scanId] = ['sequence' => $sequence, 'code_hash' => hash('sha256', $code)];

            return ['sequence' => $sequence, 'duplicate' => false];
        }, true);
    }

    public function acknowledge(User $user, string $token, int $sequence): void
    {
        $this->session($user, $token, function (&$state) use ($sequence) {
            abort_if($sequence > $state['sequence'], 422, 'Unknown scan sequence.');
            $state['acknowledged'] = max($sequence, $state['acknowledged']);
            $state['events'] = array_values(array_filter($state['events'], fn ($event) => $event['sequence'] > $state['acknowledged']));
        }, true);
    }
}
