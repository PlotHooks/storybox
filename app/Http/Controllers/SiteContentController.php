<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use App\Services\MessageRichTextRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

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

        $categoryOrder = array_keys(SiteContent::categoryOptions());

        $categories = $serializedDocuments
            ->groupBy('collection')
            ->map(fn ($categoryDocuments, string $category) => [
                'key' => $category,
                'label' => SiteContent::categoryLabel($category),
                'documents' => $categoryDocuments->values()->all(),
            ])
            ->sortBy(fn (array $category): int => array_search($category['key'], $categoryOrder, true) ?: 0)
            ->values();

        $defaultCategory = $categories->first();

        return response()->json([
            'collection' => $collection,
            'default_category' => $defaultCategory['key'] ?? null,
            'categories' => $categories->all(),
        ]);
    }

    public function showPublicCategory(string $category): View
    {
        $documents = SiteContent::query()
            ->published()
            ->forCategory($category)
            ->ordered()
            ->get()
            ->map(fn (SiteContent $document): array => [
                'title' => $document->title,
                'rendered_body_html' => $this->renderer->render($document->body),
                'updated_at' => optional($document->updated_at)?->toFormattedDateString(),
            ])
            ->values();

        abort_if($documents->isEmpty(), 404);

        return view('site-content.public', [
            'pageTitle' => SiteContent::categoryLabel($category),
            'headline' => SiteContent::categoryLabel($category),
            'documents' => $documents,
        ]);
    }

    public function showPublicCollection(string $collection): View
    {
        $documents = SiteContent::query()
            ->published()
            ->forPublicCollection($collection)
            ->ordered()
            ->get()
            ->map(function (SiteContent $document): array {
                $category = $document->collection === SiteContent::PUBLIC_COLLECTION_RULES_FAQ
                    && in_array($document->slug, [SiteContent::CATEGORY_RULES, SiteContent::CATEGORY_FAQ], true)
                    ? $document->slug
                    : $document->collection;

                return [
                    'title' => $document->title,
                    'category_label' => SiteContent::categoryLabel($category),
                    'rendered_body_html' => $this->renderer->render($document->body),
                    'updated_at' => optional($document->updated_at)?->toFormattedDateString(),
                ];
            })
            ->values();

        abort_if($documents->isEmpty(), 404);

        return view('site-content.public', [
            'pageTitle' => SiteContent::publicCollectionOptions()[$collection] ?? 'Storybox',
            'headline' => SiteContent::publicCollectionOptions()[$collection] ?? 'Storybox',
            'documents' => $documents,
        ]);
    }
}
