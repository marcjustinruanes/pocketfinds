<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class RegistrationCode extends Mailable
{
    public function __construct(public string $code) {}

    public function build(): static
    {
        return $this->subject('PocketFinds email verification')->text('mail.registration-code');
    }
}
