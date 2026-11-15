<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class RiderActivationMail extends Mailable
{
    public function __construct(public string $riderName, public string $companyName, public string $activationUrl) {}

    public function build()
    {
        return $this->subject('PocketFinds — Set your rider password')->view('emails.rider-activation');
    }
}
