<?php
namespace App\Domain\Support\Events; use App\Domain\Support\Models\{SupportTicket,SupportTicketMessage}; use Illuminate\Foundation\Events\Dispatchable; use Illuminate\Queue\SerializesModels;
final class SupportTicketReplied{use Dispatchable,SerializesModels;public function __construct(public SupportTicket $ticket,public SupportTicketMessage $message){}}
