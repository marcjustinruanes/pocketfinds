<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Complaint;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Live-chat messaging (AJAX send, polling, read receipts, attachments, report-to-admin)
 * shared by internal-staff inboxes (Logistics, Admin). Buyer/seller keep their own
 * product-aware version of this in BuyerController/SellerController.
 */
trait HandlesMessaging
{
    /** Is this user someone the current staff role is allowed to message? */
    abstract protected function isAllowedContact(User $user): bool;

    private function formatStaffMessage(Message $m): array
    {
        return [
            'id'              => $m->id,
            'sender_id'       => $m->sender_id,
            'body'            => $m->body,
            'attachment_path' => $m->attachment_path ? route('message.media', ['path' => $m->attachment_path]) : null,
            'attachment_name' => $m->attachment_name,
            'attachment_type' => $m->attachment_type,
            'attachment_mime' => $m->attachment_mime,
            'attachment_size' => $m->attachment_size,
            'read'            => $m->read,
            'created_at'      => $m->created_at->format('g:i A'),
        ];
    }

    private function addStaffAttachment(array &$msg, $file): void
    {
        $mime                    = $file->getMimeType();
        $msg['attachment_path']  = $file->store('message_attachments', 'supabase_messages');
        $msg['attachment_name']  = $file->getClientOriginalName();
        $msg['attachment_mime']  = $mime;
        $msg['attachment_size']  = $file->getSize();
        $msg['attachment_type']  = str_starts_with($mime, 'image/')
            ? 'image'
            : (str_starts_with($mime, 'video/') ? 'video' : 'document');
    }

    public function messagesSend(Request $request)
    {
        $data = $request->validate([
            'receiver_id'   => ['required', 'integer', 'exists:users,id'],
            'body'          => ['nullable', 'string', 'max:2000'],
            'attachments'   => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,pdf,doc,docx,xls,xlsx'],
        ]);

        $receiver = User::findOrFail($data['receiver_id']);
        abort_unless($this->isAllowedContact($receiver), 403);

        $files = $request->file('attachments', []);
        abort_unless(filled($data['body'] ?? null) || $files, 422, 'Send a message, image, or file.');

        $messages = [];
        foreach ($files ?: [null] as $index => $file) {
            $msg = ['sender_id' => auth()->id(), 'receiver_id' => $receiver->id, 'read' => false];
            if ($index === 0 && filled($data['body'] ?? null)) $msg['body'] = $data['body'];
            if ($file) $this->addStaffAttachment($msg, $file);
            $saved      = Message::create($msg);
            $messages[] = $this->formatStaffMessage($saved);
        }

        return response()->json(['ok' => true, 'message' => $messages[0], 'messages' => $messages]);
    }

    public function messagesPoll(Request $request)
    {
        $data     = $request->validate(['receiver_id' => ['required', 'integer']]);
        $receiver = User::findOrFail($data['receiver_id']);
        abort_unless($this->isAllowedContact($receiver), 403);

        Message::where('sender_id', $receiver->id)->where('receiver_id', auth()->id())
            ->where('read', false)->update(['read' => true]);

        $messages = Message::where(function ($q) use ($receiver) {
            $q->where('sender_id', auth()->id())->where('receiver_id', $receiver->id);
        })->orWhere(function ($q) use ($receiver) {
            $q->where('sender_id', $receiver->id)->where('receiver_id', auth()->id());
        })->orderBy('created_at')->get()
            ->map(fn ($m) => $this->formatStaffMessage($m));

        return response()->json(['ok' => true, 'messages' => $messages]);
    }

    public function reportMessage(Request $request)
    {
        $data = $request->validate([
            'message_id'  => ['required', 'integer'],
            'reason'      => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'evidence'    => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi'],
        ]);
        $message = Message::with(['sender', 'receiver'])->whereKey($data['message_id'])
            ->where(fn ($q) => $q->where('sender_id', auth()->id())->orWhere('receiver_id', auth()->id()))
            ->firstOrFail();

        $reportedOn = $message->sender_id === auth()->id() ? $message->receiver : $message->sender;

        $evidence = $request->file('evidence');
        $values   = [
            'id' => (string) Str::uuid(), 'order_id' => null, 'complainant_id' => auth()->id(),
            'respondent_id'  => $reportedOn?->id,
            'complaint_type' => $data['reason'], 'subject' => 'Reported chat message',
            'description'    => $data['description'] ?? null, 'status' => 'open',
            'message_id'     => $message->id,
            'shop_name'      => $reportedOn ? trim(($reportedOn->given_names ?? '') . ' ' . ($reportedOn->last_name ?? '')) : null,
            'message_body'   => $message->body,
            'message_type'   => $message->attachment_type ?: ($message->body ? 'text' : 'message'),
        ];
        if ($evidence) {
            $values['evidence_path'] = $evidence->store('report_evidence', 'supabase');
            $values['evidence_name'] = $evidence->getClientOriginalName();
            $values['evidence_mime'] = $evidence->getMimeType();
            $values['evidence_type'] = str_starts_with($values['evidence_mime'], 'video/') ? 'video' : 'image';
            $values['evidence_size'] = $evidence->getSize();
        }
        Complaint::create($values);

        return response()->json(['ok' => true, 'message' => 'Report sent to admin.']);
    }
}
