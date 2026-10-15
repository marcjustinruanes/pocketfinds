<?php

namespace App\Http\Controllers;

use App\Http\Middleware\MobileScannerAccess;
use App\Models\LogisticsHub;
use App\Models\User;
use App\Services\GcashLocks;
use App\Services\LogisticsAccess;
use App\Services\PhoneScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MobileScannerController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $user = User::where('email', $data['email'])->first();
        abort_unless(Hash::check($data['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi') && $user, 401, 'Invalid credentials.');
        MobileScannerAccess::check($user);
        $expires = now()->addHours(8);

        return response()->json(['token' => $user->createToken('PocketFinds Scanner', ['scanner'], $expires)->plainTextToken,
            'expires_at' => $expires->toIso8601String(), 'user' => $this->identity($user)])->header('Cache-Control', 'no-store, private');
    }

    private function identity(User $user): array
    {
        $hub = LogisticsHub::where('company_name', $user->business_name)->find($user->logistics_hub_id);

        return ['id' => (string) $user->id, 'name' => trim($user->given_names.' '.$user->last_name),
            'role' => $user->logistics_role, 'company' => $user->business_name,
            'hub_municipality' => $hub?->municipality,
            'hub_id' => $user->logistics_hub_id ? (string) $user->logistics_hub_id : null];
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->identity($request->user()), 'offline_actions_enabled' => false]);
    }

    public function logout(Request $request)
    {
        abort_unless($request->user()?->currentAccessToken() instanceof PersonalAccessToken, 401);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    private function parcel(Request $request): array
    {
        $response = app(LogisticsController::class)->scanLookup($request);
        abort_unless($response->getStatusCode() === 200, 404, 'No permitted parcel was found.');
        $data = $response->getData(true);
        $shipment = $data['shipment'];
        $actions = [];
        if ($data['stage'] === 'receive') {
            $actions[] = ['id' => 'receive', 'label' => 'Receive and sort'];
        }
        if ($data['stage'] === 'in_hub_transfer' && ($shipment['current_leg']['can_complete'] ?? false)
            && ($shipment['current_leg']['rider_confirmed'] ?? false)) {
            $actions[] = ['id' => 'receive_transfer', 'label' => 'Receive at destination hub'];
        }

        return ['parcel' => array_intersect_key($shipment, array_flip(['id', 'tracking_number', 'order_number', 'status', 'status_label',
            'origin_hub', 'destination_hub', 'current_leg', 'hub_leg_progress', 'updated_at'])), 'actions' => $actions,
            'offline_actions_enabled' => false];
    }

    public function lookup(Request $request)
    {
        return response()->json($this->parcel($request));
    }

    public function syncScan(Request $request)
    {
        $data = $request->validate(['operation_id' => 'required|uuid', 'code' => 'required|string|max:100',
            'scanned_at' => 'required|date', 'company' => 'required|string|max:255', 'hub_id' => 'present|nullable|integer']);
        $hash = hash('sha256', json_encode(['scan', $data['code'], $data['scanned_at'], $data['company'], $data['hub_id']]));
        [$body, $status] = DB::transaction(function () use ($request, $data, $hash) {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            MobileScannerAccess::check($actor);
            abort_unless($actor->business_name === $data['company'] && $actor->logistics_hub_id == $data['hub_id'], 409, 'Your company or hub assignment changed. Review this scan.');
            auth()->setUser($actor);
            $request->setUserResolver(fn () => $actor);
            $receipt = DB::table('mobile_scanner_operations')->where('user_id', $actor->id)->where('operation_id', $data['operation_id'])->first();
            if ($receipt) {
                abort_unless(hash_equals($receipt->payload_hash, $hash), 409, 'This operation ID was used with a different payload.');
                if ($receipt->http_status === 200) {
                    $this->parcel($request); // Recheck visibility before returning a historical result.
                }

                return [json_decode($receipt->response, true), $receipt->http_status];
            }
            try {
                $body = $this->parcel($request) + ['operation_id' => $data['operation_id'], 'accepted' => true];
                $body['actions'] = []; // A sync receipt is not authority to act on cached state.
                $status = 200;
            } catch (HttpExceptionInterface $error) {
                if ($error->getStatusCode() >= 500) {
                    throw $error;
                }
                $status = $error->getStatusCode();
                $body = ['message' => $error->getMessage()];
            }
            DB::table('mobile_scanner_operations')->insert(['user_id' => $actor->id, 'operation_id' => $data['operation_id'],
                'payload_hash' => $hash, 'response' => json_encode($body), 'http_status' => $status, 'created_at' => now()]);

            return [$body, $status];
        });

        return response()->json($body, $status);
    }

    public function operate(Request $request)
    {
        $data = $request->validate(['operation_id' => 'required|uuid', 'code' => 'required|string|max:100',
            'action' => 'required|in:receive,receive_transfer', 'expected_status' => 'required|string|max:40']);
        $hash = hash('sha256', json_encode(['action', $data['code'], $data['action'], $data['expected_status']]));
        [$body, $status] = DB::transaction(function () use ($request, $data, $hash) {
            // Cached outcomes need only the actor lock. New operations acquire their
            // complete commerce/transfer footprint before actor or shipment locks.
            $cached = DB::table('mobile_scanner_operations')->where('user_id', $request->user()->id)->where('operation_id', $data['operation_id'])->first();
            $plannedShipment = null;
            if (! $cached) {
                try {
                    $peek = $this->parcel($request);
                    $plannedShipment = $peek['parcel']['id'];
                    GcashLocks::operation([$plannedShipment], [], [$request->user()->id]);
                } catch (HttpExceptionInterface $error) {
                    // Unknown/forbidden parcels retain the existing durable error path;
                    // footprint conflicts must roll back rather than become receipts.
                    if ($error->getStatusCode() === 409 || $error->getStatusCode() >= 500) {
                        throw $error;
                    }
                }
            }
            // Serialize retries for this owner before looking for a durable receipt.
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            MobileScannerAccess::check($actor);
            auth()->setUser($actor);
            $request->setUserResolver(fn () => $actor);
            $receipt = DB::table('mobile_scanner_operations')->where('user_id', $actor->id)->where('operation_id', $data['operation_id'])->first();
            if ($receipt) {
                abort_unless(hash_equals($receipt->payload_hash, $hash), 409, 'This operation ID was used with a different payload.');
                if ($receipt->http_status === 200) {
                    $this->parcel($request);
                }

                return [json_decode($receipt->response, true), $receipt->http_status];
            }
            try {
                $body = DB::transaction(function () use ($request, $data, $actor, $plannedShipment) {
                    $parcel = $this->parcel($request);
                    abort_unless($plannedShipment === $parcel['parcel']['id'], 409, 'Parcel footprint changed. Retry the operation.');
                    abort_unless(in_array($data['action'], array_column($parcel['actions'], 'id'), true), 422, 'This action is unavailable. Refresh the parcel.');
                    $shipment = app(LogisticsAccess::class)->shipments($actor)->lockForUpdate()->findOrFail($parcel['parcel']['id']);
                    abort_unless($shipment->shipping_status === $data['expected_status'], 409, 'Parcel status changed. Refresh before confirming again.');
                    $controller = app(LogisticsController::class);
                    $response = $data['action'] === 'receive' ? $controller->receiveAtSortingCenter($request, $shipment->id)
                        : $controller->completeHubTransfer($request, $shipment->id);
                    DB::table('logistics_activity_logs')->insert(['company_name' => $actor->business_name, 'actor_id' => $actor->id,
                        'hub_id' => $actor->logistics_hub_id, 'action' => 'mobile.'.$data['action'],
                        'record_id' => (string) $request->attributes->get('logistics_record_id', $shipment->id), 'created_at' => now()]);

                    return ['ok' => true, 'operation_id' => $data['operation_id'], 'result' => $response->getData(true)];
                });
                $status = 200;
            } catch (ValidationException $error) {
                $status = 422;
                $body = ['message' => 'This action is invalid.', 'errors' => $error->errors()];
            } catch (HttpExceptionInterface $error) {
                $status = $error->getStatusCode();
                if ($status >= 500) {
                    throw $error;
                }
                $body = ['message' => $error->getMessage()];
            }
            DB::table('mobile_scanner_operations')->insert(['user_id' => $actor->id, 'operation_id' => $data['operation_id'],
                'payload_hash' => $hash, 'response' => json_encode($body), 'http_status' => $status, 'created_at' => now()]);

            return [$body, $status];
        });

        return response()->json($body, $status);
    }

    public function pair(Request $request, string $token, PhoneScanner $scanner)
    {
        $id = $request->user()->currentAccessToken()->id;
        $expires = $scanner->session($request->user(), $token, function (&$state) use ($id) {
            abort_if(isset($state['mobile_token_id']) && $state['mobile_token_id'] !== $id, 409, 'Another phone has already paired with this computer.');
            $state['mobile_token_id'] = $id;

            return $state['expires_at'];
        }, true);

        return response()->json(['ok' => true, 'expires_at' => $expires]);
    }

    public function relay(Request $request, string $token, PhoneScanner $scanner)
    {
        $data = $request->validate(['code' => 'required|string|max:100', 'scan_id' => 'required|string|regex:/^[a-f0-9]{32}$/D']);
        $scanner->session($request->user(), $token, fn ($state) => abort_unless(($state['mobile_token_id'] ?? null) === $request->user()->currentAccessToken()->id, 403, 'Pair this phone first.'));

        return response()->json($scanner->send($request->user(), $token, trim($data['code']), $data['scan_id'], $request->user()->currentAccessToken()->id));
    }

    public function pairStatus(Request $request, string $token, PhoneScanner $scanner)
    {
        return $this->pairState($request, $token, $scanner, false);
    }

    public function disconnectPair(Request $request, string $token, PhoneScanner $scanner)
    {
        return $this->pairState($request, $token, $scanner, true);
    }

    private function pairState(Request $request, string $token, PhoneScanner $scanner, bool $close)
    {
        $result = $scanner->session($request->user(), $token, function (&$state) use ($request, $scanner, $close) {
            abort_unless(($state['mobile_token_id'] ?? null) === $request->user()->currentAccessToken()->id, 403, 'Use the phone that paired with this computer.');

            return $close ? $scanner->close($state, 'phone') : $scanner->status($state);
        }, $close, true);

        return response()->json($result)->header('Cache-Control', 'private, no-store');
    }
}
