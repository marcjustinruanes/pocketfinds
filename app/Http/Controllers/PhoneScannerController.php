<?php

namespace App\Http\Controllers;

use App\Services\PhoneScanner;
use Illuminate\Http\Request;

class PhoneScannerController extends Controller
{
    private function json(array $data)
    {
        return response()->json($data)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function create(Request $request, PhoneScanner $scanner)
    {
        $session = $scanner->create($request->user(), $request->session()->getId());
        $url = route('logistics.scan.phone', $session['token']);

        return $this->json($session + ['url' => $url, 'events_url' => route('logistics.scan.phone.events', $session['token']),
            'ack_url' => route('logistics.scan.phone.ack', $session['token']), 'disconnect_url' => route('logistics.scan.phone.disconnect', $session['token'])]);
    }

    public function phone(Request $request, string $token, PhoneScanner $scanner)
    {
        $expiresAt = $scanner->session($request->user(), $token, fn ($state) => $state['expires_at']);

        return response()->view('logistics.phone-scanner', compact('token', 'expiresAt'))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function poll(Request $request, string $token, PhoneScanner $scanner)
    {
        $this->browser($request, $token, $scanner, true);

        return $this->json($scanner->session($request->user(), $token, fn ($state) => $scanner->status($state) + ['events' => $state['events']], false, true));
    }

    public function send(Request $request, string $token, PhoneScanner $scanner)
    {
        $scanner->session($request->user(), $token, fn ($state) => abort_if(isset($state['mobile_token_id']), 403, 'Use the paired Android app to send codes.'));
        $data = $request->validate(['code' => 'required|string|max:100', 'scan_id' => 'required|string|regex:/^[a-f0-9]{32}$/D']);
        $code = trim($data['code']);
        abort_if($code === '', 422, 'Enter a tracking or order number.');

        return $this->json(['ok' => true] + $scanner->send($request->user(), $token, $code, $data['scan_id']));
    }

    public function acknowledge(Request $request, string $token, PhoneScanner $scanner)
    {
        $this->browser($request, $token, $scanner);
        $data = $request->validate(['sequence' => 'required|integer|min:1']);
        $scanner->acknowledge($request->user(), $token, $data['sequence']);

        return $this->json(['ok' => true]);
    }

    public function disconnect(Request $request, string $token, PhoneScanner $scanner)
    {
        $this->browser($request, $token, $scanner, true);

        return $this->json(['ok' => true] + $scanner->session($request->user(), $token, fn (&$state) => $scanner->close($state, 'computer'), true, true));
    }

    private function browser(Request $request, string $token, PhoneScanner $scanner, bool $allowClosed = false): void
    {
        $scanner->session($request->user(), $token, fn ($state) => abort_unless(
            isset($state['browser_session_hash']) && hash_equals($state['browser_session_hash'], hash('sha256', $request->session()->getId())),
            403, 'Use the browser session that created this connection.'), false, $allowClosed);
    }
}
