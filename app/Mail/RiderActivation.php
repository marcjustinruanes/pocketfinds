<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

class RiderActivation extends Mailable
{
    public function __construct(public User $rider, public string $token) {}

    public function build(): static
    {
        return $this->subject('Activate your PocketFinds rider account')->text('mail.rider-activation');
    }
}
