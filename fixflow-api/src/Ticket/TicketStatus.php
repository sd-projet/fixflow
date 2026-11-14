<?php
namespace App\Ticket;
enum TicketStatus: string { case New = 'new'; case InProgress = 'in_progress'; case Pending = 'pending'; case Resolved = 'resolved'; case Closed = 'closed'; }
