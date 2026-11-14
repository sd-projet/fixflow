<?php
namespace App\Notification;
final class TicketEvent { public function __construct(public int $ticketId, public string $type, public int $actorId) {} }
