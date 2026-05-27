<?php

namespace App\Ai\Tools;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TicketFactsTool implements Tool
{
    public function __construct(
        public int $ticketId,
        public ?User $user = null,
    )
    {

    }
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Fetches key facts about the current ticket (status, priority, department, sentiment, tags).';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = $this->user ?? auth()->user();
        if (!$user) {
            return 'You must be logged in to use this tool.';
        }

        $ticket = Ticket::with('tags')->find($this->ticketId);

        // TODO: Check if the user has permission to view the ticket.
        if (!$ticket) {
            return 'Ticket not found.';
        }

        $payload = [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'department' => $ticket->department,
            'sentiment' => $ticket->sentiment,
            'tags' => $ticket->tags->pluck('name')->values(),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
