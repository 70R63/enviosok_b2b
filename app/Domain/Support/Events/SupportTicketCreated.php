<?php
namespace App\Domain\Support\Events; use App\Domain\Support\Models\SupportTicket; use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
final class SupportTicketCreated{use Dispatchable,SerializesModels;public function __construct(public SupportTicket $ticket){}}
