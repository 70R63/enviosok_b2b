<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

final class ProviderFulfillmentConfirmedMail extends Mailable
{
    public function __construct(public array $confirmation) {}

    public function build()
    {
        return $this->subject('Tu envío local fue confirmado')->view('email.provider-fulfillment-confirmed');
    }
}
