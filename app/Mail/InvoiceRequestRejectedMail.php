<?php
namespace App\Mail;use App\Models\B2cInvoiceRequest;use Illuminate\Bus\Queueable;use Illuminate\Mail\Mailable;use Illuminate\Queue\SerializesModels;
final class InvoiceRequestRejectedMail extends Mailable{use Queueable,SerializesModels;public function __construct(public B2cInvoiceRequest $invoiceRequest,public string $portalUrl){}public function build(){return $this->subject('Acción requerida para tu solicitud de facturación ZIGO')->view('email.invoice-request-rejected');}}
