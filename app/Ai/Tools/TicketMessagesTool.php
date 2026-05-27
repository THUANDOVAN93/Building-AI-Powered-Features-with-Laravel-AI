<?php

namespace App\Ai\Tools;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TicketMessagesTool implements Tool
{
    public function __construct(
        public int $ticketId,
        public ?User $user = null,
    )
    {}
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Return the most recent ticket messages.';
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

        $ticket =Ticket::find($this->ticketId);

        // TODO: Check if the user has permission to view the ticket.
        if (!$ticket) {
            return 'Ticket not found.';
        }

        $count = $request->integer('count', 3);

        $count = max(1, min($count, 5));

        $messages = $ticket->messages()
            ->latest()
            ->take($count)
            ->get()
            ->reverse()
            ->map(fn ($message) => [
                'role' => $message->role,
                'body' => $message->body,
            ])
            ->values();

        return $messages->toJson(JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'count' => $schema->integer()
                ->min(1)
                ->max(5)
                ->description('How many recent messages to retrieve.')
                ->required(),
        ];
    }
}
