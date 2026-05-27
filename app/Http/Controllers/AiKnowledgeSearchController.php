<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\Vector;
use Illuminate\Http\Request;
use Laravel\Ai\Embeddings;
class AiKnowledgeSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $teamId = $user->current_team_id;

        $query = $request->string('q')->trim();

        $documentResults = collect();

        $minSimilarity = 0.30;

        if ($query->isNotEmpty()) {

            $queryEmbedding = Embeddings::for([$query])
                ->generate()
                ->first();

            $documents  = Document::where('team_id', $teamId)->get();

            $documentResults = $documents->map(function ($document) use ($queryEmbedding) {
                $embedding = Embeddings::for([
                    "{$document->title}\n\n{$document->body}",
                ])->generate()->first();

                $document->update([
                    'embedding' => $embedding,
                ]);

                return [
                    'document' => $document,
                    'score' => Vector::cosine($queryEmbedding, $embedding),
                ];
            })
            ->filter(fn ($result) => $result['score'] >= $minSimilarity)
            ->sortByDesc('score')
            ->take(5)
            ->values();
        }

        return view('ai.knowledge-search', [
            'query' => $query->toString(),
            'documentResults' => $documentResults,
            'minSimilarity' => $minSimilarity,
        ]);
    }
}
