<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    protected $fillable = [
        'type', 'account_type', 'company_name', 'title', 'content', 'history', 'updated_by',
        'pending_content', 'pending_submitted_by', 'pending_submitted_at', 'rejection_reason',
    ];

    protected $casts = [
        'history'              => 'array',
        'pending_submitted_at' => 'datetime',
    ];

    public function editor() { return $this->belongsTo(User::class, 'updated_by'); }

    public function submittedBy() { return $this->belongsTo(User::class, 'pending_submitted_by'); }

    /**
     * Update the content, appending what it used to be onto `history` — never a silent
     * overwrite. History lives on this same row (a JSON array of {content, changed_by,
     * created_at}), newest last. For an admin editing their own platform-wide doc directly
     * — for a logistics company's own doc, see submitPending()/approvePending() instead.
     */
    public function updateContent(string $content, ?int $editorId): void
    {
        $history   = $this->history ?? [];
        $history[] = ['content' => $this->content, 'changed_by' => $this->updated_by, 'created_at' => now()->toDateTimeString()];

        $this->update(['content' => $content, 'updated_by' => $editorId, 'history' => $history]);
    }

    public function hasPending(): bool
    {
        return filled($this->pending_content);
    }

    /** A logistics company's own staff submit an edit — it waits for admin approval, the live `content` is untouched until then. */
    public function submitPending(string $content, int $userId): void
    {
        $this->update([
            'pending_content'      => $content,
            'pending_submitted_by' => $userId,
            'pending_submitted_at' => now(),
            'rejection_reason'     => null,
        ]);
    }

    /** Admin approves the pending submission — it becomes the live content, and the old content (if any) is archived into history. */
    public function approvePending(int $adminId): void
    {
        $history = $this->history ?? [];
        if (filled($this->content)) {
            $history[] = ['content' => $this->content, 'changed_by' => $this->updated_by, 'created_at' => now()->toDateTimeString()];
        }
        $this->update([
            'content'              => $this->pending_content,
            'updated_by'           => $adminId,
            'history'              => $history,
            'pending_content'      => null,
            'pending_submitted_by' => null,
            'pending_submitted_at' => null,
            'rejection_reason'     => null,
        ]);
    }

    /** Admin rejects the pending submission — the live content is untouched, the company sees why and can resubmit. */
    public function rejectPending(?string $reason = null): void
    {
        $this->update([
            'pending_content'      => null,
            'pending_submitted_by' => null,
            'pending_submitted_at' => null,
            'rejection_reason'     => $reason,
        ]);
    }

    /** History entries, newest first, each with its editor's name resolved for display. */
    public function historyForDisplay()
    {
        $userIds = collect($this->history ?? [])->pluck('changed_by')->filter()->unique();
        $users   = User::whereIn('id', $userIds)->get()->keyBy('id');

        return collect($this->history ?? [])->reverse()->values()->map(fn ($entry) => [
            'content'    => $entry['content'],
            'created_at' => $entry['created_at'],
            'editor'     => $users->get($entry['changed_by']),
        ]);
    }
}
