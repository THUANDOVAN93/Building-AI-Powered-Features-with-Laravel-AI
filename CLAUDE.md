# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

A Laravel 12 + Livewire 4 (Flux) starter-kit application demonstrating AI-powered features built on the `laravel/ai` package. The domain is a support ticket system with AI triage, an in-ticket chat assistant, and streamed AI reply drafting.

## Commands

- `composer dev` — Runs the full local dev stack concurrently: `php artisan serve`, `php artisan queue:listen --tries=1`, and `npm run dev` (Vite).
- `composer test` — Clears config, runs Pint lint check, then runs Pest tests via `php artisan test`.
- `composer test:lint` / `composer lint` — Pint formatting (check / fix).
- Run a single test: `php artisan test --filter=TicketIndexTest` (Pest is the test runner; tests live under `tests/Feature` and `tests/Unit`).
- `composer setup` — First-time setup (install, key:generate, migrate, npm install, build).
- DB is SQLite at `database/database.sqlite`.

## Architecture

### AI layer (the focus of the project)

All AI integration goes through the `laravel/ai` package (`Laravel\Ai\*`). Two architectural primitives live in `app/Ai/`:

- **Agents** (`app/Ai/Agents/`) — Classes implementing `Laravel\Ai\Contracts\Agent`. They declare a provider/model via PHP attributes (`#[Provider(Lab::Anthropic)]`, `#[UseCheapestModel]`, `#[MaxTokens(...)]`) and define `instructions()`. Variants:
  - `TicketTriager` implements `HasStructuredOutput` — returns a typed JSON object defined by `schema(JsonSchema $schema)`. Called via `->prompt(...)` and the result is array-accessible (`$response['priority']`).
  - `TicketAssistant` implements `Conversational` and uses `RemembersConversations` — supports `->forUser($user)->prompt(...)` (new conversation, returns a `conversationId` to persist on the ticket) and `->continue($conversationId, as: $user)->prompt(...)` (resume). Also supports streaming via `->stream($prompt)` returning a `StreamedAgentResponse` you can `->then(...)` on.
- **Tools** (`app/Ai/Tools/`) — Classes implementing `Laravel\Ai\Contracts\Tool` with `description()`, `handle(Request)`, and a `schema(JsonSchema)` definition. `TicketFactsTool` and `TicketMessagesTool` are currently scaffold stubs.

The AI provider config lives in `config/ai.php`. The default text provider is `openai`, but the current agents are pinned to `Lab::Anthropic` via attribute, so `ANTHROPIC_API_KEY` must be set in `.env`.

### AI observability

Every AI invocation is bracketed by writes to two tables:
- `ai_runs` (`App\Models\AiRun`) — one row per invocation with `feature_key`, `status` (`running` → `succeeded`/`failed`), `provider`, `model`, `input_hash`, `started_at`/`finished_at`, `output_text`, `error_message`.
- `ai_usages` (`App\Models\AiUsages`) — token/cost rollup linked by `ai_run_id`, populated from `$response->usage` after success.

When adding a new AI feature, follow the existing controllers' pattern: create the `AiRun` with `status=running` before the call, wrap the call in try/catch, set `succeeded`/`failed` + `finished_at` accordingly, then write `AiUsages` from `$response->usage` if present. There are real bugs in the current controllers (typos: `successded`, `finshed_at`; double `$ticket->update(...)` in `TicketTriageController`) — don't propagate them when copying patterns.

### HTTP entry points

`routes/web.php` exposes the AI features as single-action invokable controllers under `tickets/{ticket}/ai/*`:
- `TicketTriageController` — synchronous structured triage; updates `tickets.priority/department/sentiment/ai_tags` and writes a system message with the summary.
- `TicketChatController` — turn-based chat; persists `ai_conversation_id` on the ticket and stores both the user message and the agent reply in `ticket_messages`.
- `TicketDraftReplyStreamController` — returns the streaming response directly to the client; finalization (run/usage update) happens inside `$stream->then(...)`.

UI pages are Livewire components mounted via `Route::livewire('tickets/...', 'pages::tickets.show')`. Auth/settings scaffolding comes from Fortify + the Livewire starter kit (`app/Actions/Fortify`, `routes/settings.php`).

### Domain model

`Ticket` hasMany `TicketMessage`, belongsToMany `TicketTag` (via `ticket_ticket_tag`), belongsTo `Team`. `TicketMessage.role` is `user` | `agent` | `system`. AI columns on `tickets`: `priority`, `department`, `sentiment`, `ai_tags`, `ai_conversation_id`.

## Conventions

- Pint config is in `pint.json`; run before pushing — CI/`composer test` will fail otherwise.
- Migrations use a `2026_02_20_*` numbering scheme for the domain tables; AI tables were added later (`2026_05_*`). Add new migrations with the current date.
- The `ai_runs.feature_key` string identifies the feature (`ticket_triage`, `ticket_chat`). Reuse existing keys or add a new one rather than overloading.
