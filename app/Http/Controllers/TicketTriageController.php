<?php

namespace App\Http\Controllers;

use App\Ai\Agents\TicketTriager;
use App\Models\AiRun;
use App\Models\AiUsages;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Laravel\Ai\Enums\Lab;

class TicketTriageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Ticket $ticket)
    {
        $run = AiRun::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'team_id' => $ticket->team_id,
            'feature_key' => 'ticket_triage',
            'status' => 'running',
            'provider' => Lab::Anthropic,
            'model' => 'claude-3-sonnet',
            'input_hash' => sha1($ticket->subject . $ticket->messages()->latest()->first()?->body),
            'started_at' => now(),
        ]);

        try {
            $response = (new TicketTriager)->prompt(
                "Subject: {$ticket->subject}\n\n{$ticket->messages()->latest()->first()?->body}"
            );

            $ticket->update([
                'priority' => $response['priority'],
                'department' => $response['department'],
                'sentiment' => $response['sentiment'],
                'ai_tags' => $response['tags'],
            ]);

            $ticket->update([
                'priority' => $response['priority'],
                'department' => $response['department'],
                'sentiment' => $response['sentiment'],
                'ai_tags' => $response['tags'],
            ]);

            if (!empty($response['summary'])) {
                $ticket->messages()->create([
                    'user_id' => null,
                    'role' => 'system',
                    'body' => 'AI Summary: ' . $response['summary'],
                ]);
            }

            $run->update([
                'status' => 'successded',
                'finshed_at' => now(),
            ]);

            if (isset($response->usage)) {
                $usage = $response->usage;

                AiUsages::create([
                    'ai_run_id' => $run->id,
                    'prompt_tokens' => $usage->promptTokens ?? 0,
                    'completion_tokens' => $usage->completionTokens ?? 0,
                    'total_tokens' => $usage->totalToken ?? 0,
                    'cost_usd' => $usage->costUsd ?? 0,
                ]);
            }

            return response()->json([
                'status' => 'ok',
                'data' => $response,
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
