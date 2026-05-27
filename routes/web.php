<?php

use App\Http\Controllers\AiKnowledgeSearchController;
use App\Http\Controllers\TicketChatController;
use App\Http\Controllers\TicketDraftReplyStreamController;
use App\Http\Controllers\TicketTriageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::livewire('tickets', 'pages::tickets.index')->name('tickets.index');
    Route::livewire('tickets/{ticket}', 'pages::tickets.show')->name('tickets.show');

    Route::post('tickets/{ticket}/ai/triage', TicketTriageController::class)
        ->name('tickets.ai.triage');

    Route::post('tickets/{ticket}/ai/chat', TicketChatController::class)
        ->name('tickets.ai.chat');

    Route::post('tickets/{ticket}/ai/draft-reply/stream', TicketDraftReplyStreamController::class)
        ->name('tickets.ai.draft-reply.stream');

    Route::get('ai/knowledge-search', AiKnowledgeSearchController::class)
        ->name('ai.knowledge-search');
});

require __DIR__.'/settings.php';
