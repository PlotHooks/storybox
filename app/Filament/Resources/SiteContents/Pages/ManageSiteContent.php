<?php

namespace App\Filament\Resources\SiteContents\Pages;

use App\Filament\Resources\SiteContents\SiteContentResource;
use App\Models\SiteContent;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManageSiteContent extends Page
{
    protected static string $resource = SiteContentResource::class;

    protected static ?string $title = 'Site Content Manager';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.resources.site-contents.pages.manage-site-content';

    public string $selectedCategory = SiteContent::CATEGORY_RULES;

    public ?int $selectedDocumentId = null;

    public string $titleInput = '';

    public string $bodyInput = '';

    public string $slugInput = '';

    public int $sortOrderInput = 1;

    public bool $isPublishedInput = false;

    public bool $showAdvanced = false;

    public bool $slugManuallyEdited = false;

    public bool $isCreating = false;

    public function mount(): void
    {
        $this->syncSelection();
    }

    public function getCategoryOptionsProperty(): array
    {
        return SiteContent::categoryOptions();
    }

    public function getCategorySummaryProperty(): array
    {
        return collect($this->categoryOptions)
            ->map(fn (string $label, string $category): array => [
                'key' => $category,
                'label' => $label,
                'count' => SiteContent::query()->forCategory($category)->count(),
            ])
            ->values()
            ->all();
    }

    public function getDocumentsProperty(): array
    {
        return SiteContent::query()
            ->forCategory($this->selectedCategory)
            ->ordered()
            ->get()
            ->map(fn (SiteContent $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'slug' => $document->slug,
                'sort_order' => $document->sort_order,
                'is_published' => (bool) $document->is_published,
                'updated_at' => optional($document->updated_at)?->toIso8601String(),
            ])
            ->all();
    }

    public function getSelectedCategoryLabelProperty(): string
    {
        return SiteContent::categoryLabel($this->selectedCategory);
    }

    public function getSelectedDocumentProperty(): ?SiteContent
    {
        if (! $this->selectedDocumentId) {
            return null;
        }

        return SiteContent::query()->find($this->selectedDocumentId);
    }

    public function selectCategory(string $category): void
    {
        abort_unless(array_key_exists($category, SiteContent::categoryOptions()), 404);

        $this->selectedCategory = $category;
        $this->selectedDocumentId = null;
        $this->isCreating = false;
        $this->showAdvanced = false;
        $this->slugManuallyEdited = false;
        $this->syncSelection();
    }

    public function selectDocument(int $documentId): void
    {
        $document = SiteContent::query()->findOrFail($documentId);

        $this->selectedCategory = $this->resolveCategoryForDocument($document);
        $this->selectedDocumentId = $document->id;
        $this->fillFromDocument($document);
        $this->isCreating = false;
    }

    public function startCreating(): void
    {
        $this->selectedDocumentId = null;
        $this->titleInput = '';
        $this->bodyInput = '';
        $this->slugInput = '';
        $this->sortOrderInput = $this->nextSortOrderForCategory($this->selectedCategory);
        $this->isPublishedInput = false;
        $this->showAdvanced = false;
        $this->slugManuallyEdited = false;
        $this->isCreating = true;
    }

    public function saveDocument(): void
    {
        $validated = $this->validate([
            'titleInput' => ['required', 'string', 'max:255'],
            'bodyInput' => ['required', 'string'],
            'sortOrderInput' => ['required', 'integer', 'min:1'],
            'slugInput' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('site_contents', 'slug')->ignore($this->selectedDocumentId),
            ],
        ]);

        $slug = trim((string) ($validated['slugInput'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug($validated['titleInput']);
        }

        if ($slug === '') {
            $slug = 'document-' . Str::random(8);
        }

        $document = $this->selectedDocument ?? new SiteContent();
        $document->fill([
            'title' => trim($validated['titleInput']),
            'body' => trim($validated['bodyInput']),
            'slug' => $slug,
            'collection' => $this->selectedCategory,
            'sort_order' => (int) $validated['sortOrderInput'],
            'is_published' => $this->isPublishedInput,
        ]);
        $document->save();

        $this->selectedDocumentId = $document->id;
        $this->isCreating = false;
        $this->fillFromDocument($document->fresh());

        Notification::make()
            ->title('Document saved')
            ->success()
            ->send();
    }

    public function togglePublished(): void
    {
        $document = $this->selectedDocument;
        abort_if(! $document, 404);

        $document->forceFill([
            'is_published' => ! $document->is_published,
            'collection' => $this->selectedCategory,
        ])->save();

        $this->fillFromDocument($document->fresh());

        Notification::make()
            ->title($document->is_published ? 'Document published' : 'Document unpublished')
            ->success()
            ->send();
    }

    public function deleteSelectedDocument(): void
    {
        $document = $this->selectedDocument;
        abort_if(! $document, 404);

        $document->delete();
        $this->selectedDocumentId = null;
        $this->isCreating = false;
        $this->showAdvanced = false;
        $this->slugManuallyEdited = false;
        $this->syncSelection();

        Notification::make()
            ->title('Document deleted')
            ->success()
            ->send();
    }

    public function updatedTitleInput(?string $value): void
    {
        if ($this->slugManuallyEdited) {
            return;
        }

        $this->slugInput = Str::slug((string) $value);
    }

    public function updatedSlugInput(?string $value): void
    {
        $normalized = Str::slug((string) $value);
        $titleSlug = Str::slug($this->titleInput);

        $this->slugInput = $normalized;
        $this->slugManuallyEdited = $normalized !== '' && $normalized !== $titleSlug;
    }

    private function syncSelection(): void
    {
        $document = null;

        if ($this->selectedDocumentId) {
            $document = SiteContent::query()->find($this->selectedDocumentId);
        }

        if (! $document) {
            $document = SiteContent::query()->forCategory($this->selectedCategory)->ordered()->first();
        }

        if ($document) {
            $this->selectedDocumentId = $document->id;
            $this->fillFromDocument($document);
            $this->isCreating = false;

            return;
        }

        $this->startCreating();
    }

    private function fillFromDocument(SiteContent $document): void
    {
        $this->titleInput = $document->title;
        $this->bodyInput = $document->body;
        $this->slugInput = $document->slug;
        $this->sortOrderInput = (int) $document->sort_order;
        $this->isPublishedInput = (bool) $document->is_published;
        $this->showAdvanced = false;
        $this->slugManuallyEdited = true;
    }

    private function nextSortOrderForCategory(string $category): int
    {
        return ((int) SiteContent::query()->forCategory($category)->max('sort_order')) + 1;
    }

    private function resolveCategoryForDocument(SiteContent $document): string
    {
        if (array_key_exists($document->collection, SiteContent::categoryOptions())) {
            return $document->collection;
        }

        return match ($document->slug) {
            SiteContent::CATEGORY_RULES => SiteContent::CATEGORY_RULES,
            SiteContent::CATEGORY_FAQ => SiteContent::CATEGORY_FAQ,
            default => SiteContent::CATEGORY_RULES,
        };
    }
}
