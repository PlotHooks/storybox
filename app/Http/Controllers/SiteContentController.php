<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use App\Services\MessageRichTextRenderer;
use Illuminate\Http\JsonResponse;

class SiteContentController extends Controller
{
    public function __construct(
        private readonly MessageRichTextRenderer $renderer,
    ) {
    }

    public function index(string $collection): JsonResponse
    {
        $documents = SiteContent::query()
            ->published()
            ->forPublicCollection($collection)
            ->ordered()
            ->get();

        $defaultDocument = $documents->first();

        return response()->json([
            'collection' => $collection,
            'default_document_slug' => $defaultDocument?->slug,
            'documents' => $documents
                ->map(fn (SiteContent $document) => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'slug' => $document->slug,
                    'collection' => $document->collection,
                    'category_label' => SiteContent::categoryLabel($document->collection),
                    'body' => $document->body,
                    'rendered_body_html' => $this->renderer->render($document->body),
                    'sort_order' => $document->sort_order,
                    'last_updated_at' => optional($document->updated_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
