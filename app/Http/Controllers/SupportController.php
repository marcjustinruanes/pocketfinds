<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    /**
     * A self-contained "Contact Support" path that doesn't depend on the visitor's own
     * browser/OS having a mail client configured — unlike a mailto: link, this always
     * works the same way for everyone, since the server sends the email itself.
     */
    public function contact(Request $request)
    {
        $data = $request->validate([
            'email'   => 'required|email|max:255',
            'message' => 'required|string|max:2000',
            'context' => 'nullable|string|max:150',
        ]);

        try {
            // Mail::send() injects its own $message (the Mailer's Message object) into the
            // view, silently clobbering a data key of the same name — rename ours to $body
            // so the visitor's actual message text survives into the template.
            $supportEmail = \App\Models\Setting::current()->support_email;
            $viewData = ['email' => $data['email'], 'body' => $data['message'], 'context' => $data['context'] ?? null];
            Mail::send('emails.support-contact', $viewData, function ($m) use ($data, $supportEmail) {
                $m->to($supportEmail)
                    ->replyTo($data['email'])
                    ->subject('PocketFinds — Support Request' . (filled($data['context'] ?? null) ? " ({$data['context']})" : ''));
            });
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Could not send your message right now — please try again in a moment.'], 500);
        }

        return response()->json(['success' => true]);
    }
}
