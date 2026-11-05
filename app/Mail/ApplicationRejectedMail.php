<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

class ApplicationRejectedMail extends Mailable
{
    public string $applicantName;
    public string $role;
    public ?string $reason;
    public bool $canResubmit;
    public string $correctionUrl;
    public bool $hasInvitationLink;

    public function __construct(User $user, ?string $correctionUrl = null)
    {
        $this->applicantName = trim($user->given_names.' '.$user->last_name);
        $this->role = $user->logistics_role==='hub_staff' ? 'hub staff' : $user->account_type;
        $this->reason = $user->account_type==='rider' ? null : $user->status_reason;
        $this->canResubmit = (bool)$user->can_resubmit;
        $this->correctionUrl = $correctionUrl ?? route('login');
        $this->hasInvitationLink = $correctionUrl !== null;
    }

    public function build()
    {
        return $this->subject($this->canResubmit ? 'PocketFinds — Application corrections requested' : 'PocketFinds — Application rejected')->view('emails.application-rejected');
    }
}
