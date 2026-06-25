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

        $serializedDocuments = $documents
            ->map(function (SiteContent $document): array {
                $category = $document->collection === SiteContent::PUBLIC_COLLECTION_RULES_FAQ
                    && in_array($document->slug, [SiteContent::CATEGORY_RULES, SiteContent::CATEGORY_FAQ], true)
                    ? $document->slug
                    : $document->collection;

                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'slug' => $document->slug,
                    'collection' => $category,
                    'category_label' => SiteContent::categoryLabel($category),
                'body' => $document->body,
                'rendered_body_html' => $this->renderer->render($document->body),
                'sort_order' => $document->sort_order,
                'last_updated_at' => optional($document->updated_at)->toIso8601String(),
                ];
            })
            ->values();

        $categories = $serializedDocuments
            ->groupBy('collection')
            ->map(fn ($categoryDocuments, string $category) => [
                'key' => $category,
                'label' => SiteContent::categoryLabel($category),
                'documents' => $categoryDocuments->values()->all(),
            ])
            ->values();

        $defaultCategory = $categories->first();

        return response()->json([
            'collection' => $collection,
            'default_category' => $defaultCategory['key'] ?? null,
            'categories' => $categories->all(),
        ]);
    }
}
