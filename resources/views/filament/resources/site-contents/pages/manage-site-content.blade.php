
<x-filament-panels::page>
    <style>
        .sb-site-manager-shell {
            overflow: hidden;
            border: 4px solid #3a2d1b;
            border-radius: 0.75rem;
            background: #0b0b0c;
            color: #d6c8ad;
            box-shadow: 0 28px 72px rgba(0, 0, 0, 0.62);
            min-height: 76vh;
        }

        .sb-site-manager-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #3a2d1b;
            background: #111114;
            box-shadow: inset 0 -1px 0 rgba(245, 158, 11, 0.04);
        }

        .sb-site-manager-grid {
            display: grid;
            min-height: calc(76vh - 57px);
            grid-template-columns: 12rem 18rem minmax(0, 1fr);
            overflow: hidden;
        }

        .sb-site-manager-pane {
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sb-site-manager-rail {
            background: #0b0b0c;
            border-right: 1px solid #332817;
        }

        .sb-site-manager-list {
            background: #0d0d0f;
            border-right: 1px solid #332817;
        }

        .sb-site-manager-main {
            background: #080809;
        }

        .sb-site-manager-pane-header {
            padding: 0.75rem;
            border-bottom: 1px solid #2a241a;
            background: #101012;
        }

        .sb-site-manager-main-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #332817;
            background: #101012;
            box-shadow: inset 0 -1px 0 rgba(245, 158, 11, 0.03);
        }

        .sb-site-manager-main-body {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 1.25rem;
            background:
                radial-gradient(circle at top right, rgba(245, 158, 11, 0.03), transparent 24rem),
                #070707;
        }

        .sb-btn,
        .sb-input,
        .sb-textarea,
        .sb-chip,
        .sb-card,
        .sb-panel {
            border-radius: 0.5rem;
        }

        .sb-btn {
            border: 1px solid #332817;
            background: #141416;
            color: #d6c8ad;
            padding: 0.6rem 0.8rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            transition: 150ms ease;
        }

        .sb-btn:hover {
            border-color: rgba(245, 158, 11, 0.4);
            color: #f2dfb5;
            background: #191511;
        }

        .sb-btn-primary {
            border-color: rgba(245, 158, 11, 0.4);
            background: rgba(245, 158, 11, 0.1);
            color: #fef3c7;
        }

        .sb-btn-primary:hover {
            background: rgba(245, 158, 11, 0.2);
        }

        .sb-btn-danger {
            border-color: rgba(239, 68, 68, 0.4);
            background: rgba(239, 68, 68, 0.1);
            color: #fecaca;
        }

        .sb-btn-danger:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .sb-btn-success {
            border-color: rgba(16, 185, 129, 0.35);
            background: rgba(16, 185, 129, 0.1);
            color: #d1fae5;
        }

        .sb-btn-success:hover {
            background: rgba(16, 185, 129, 0.2);
        }

        .sb-card {
            border: 1px solid #332817;
            background: #141416;
        }

        .sb-card.is-active {
            border-color: rgba(245, 158, 11, 0.4);
            background: rgba(245, 158, 11, 0.1);
            box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.12);
        }

        .sb-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.15rem 0.5rem;
            border: 1px solid #332817;
            background: #0b0b0c;
            font-size: 0.625rem;
            color: #8f8675;
        }

        .sb-chip.is-published {
            border-color: rgba(16, 185, 129, 0.3);
            background: rgba(16, 185, 129, 0.1);
            color: #d1fae5;
        }

        .sb-rail-scroll,
        .sb-list-scroll {
            min-height: 0;
            overflow-y: auto;
        }

        .sb-rail-scroll {
            padding: 0.75rem;
        }

        .sb-list-scroll {
            padding: 0.5rem;
        }

        .sb-panel {
            border: 1px solid #332817;
            background: #0b0b0c;
        }

        .sb-input,
        .sb-textarea {
            width: 100%;
            border: 1px solid #332817;
            background: #0b0b0c;
            color: #f2dfb5;
            padding: 0.65rem 0.8rem;
            font-size: 0.95rem;
        }

        .sb-input:focus,
        .sb-textarea:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.35);
        }

        .sb-textarea {
            min-height: 24rem;
            resize: vertical;
        }

        .sb-kicker {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #f59e0b;
        }

        .sb-muted {
            color: #8f8675;
        }

        .sb-title {
            color: #f2dfb5;
            font-weight: 600;
        }

        .sb-form-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .sb-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: flex-end;
        }

        @media (max-width: 1279px) {
            .sb-site-manager-grid {
                grid-template-columns: 12rem minmax(0, 1fr);
            }

            .sb-site-manager-main {
                grid-column: 1 / -1;
                border-top: 1px solid #332817;
            }

            .sb-site-manager-list {
                border-right: none;
            }
        }

        @media (max-width: 768px) {
            .sb-site-manager-header,
            .sb-site-manager-main-header {
                flex-direction: column;
                align-items: stretch;
            }

            .sb-site-manager-grid {
                grid-template-columns: minmax(0, 1fr);
                min-height: auto;
            }

            .sb-site-manager-rail,
            .sb-site-manager-list {
                border-right: none;
                border-bottom: 1px solid #332817;
            }

            .sb-site-manager-main {
                grid-column: auto;
            }

            .sb-form-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>

    <div class="sb-site-manager-shell">
        <div class="sb-site-manager-header">
            <div>
                <div class="sb-kicker">StoryBox Admin</div>
                <div class="sb-title" style="font-size: 0.95rem;">Site Content Manager</div>
            </div>
            <div class="sb-chip" style="padding: 0.65rem 0.85rem; font-size: 0.7rem; text-transform: none; letter-spacing: 0; line-height: 1.4; max-width: 34rem; white-space: normal;">
                Published documents in the <span style="color:#d6c8ad; font-weight: 600; margin: 0 0.2rem;">Rules</span> and
                <span style="color:#d6c8ad; font-weight: 600; margin: 0 0.2rem;">FAQ</span> categories appear as tabs in the public Rules / FAQ window.
            </div>
        </div>

        <div class="sb-site-manager-grid">
            <aside class="sb-site-manager-pane sb-site-manager-rail">
                <div class="sb-site-manager-pane-header" style="padding: 0.75rem;">
                    <button type="button" wire:click="startCreating" class="sb-btn sb-btn-primary" style="width: 100%; text-align: left;">
                        + New Document
                    </button>
                </div>
                <div class="sb-rail-scroll">
                    <div class="sb-muted" style="padding: 0 0.25rem 0.5rem; font-size: 10px; font-weight: 600; letter-spacing: 0.16em; text-transform: uppercase;">Categories</div>
                    <div style="display: grid; gap: 0.35rem;">
                        @foreach ($this->categorySummary as $category)
                            <button
                                type="button"
                                wire:click="selectCategory('{{ $category['key'] }}')"
                                class="sb-card {{ $selectedCategory === $category['key'] ? 'is-active' : '' }}"
                                style="display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.75rem; text-align: left;"
                            >
                                <span style="font-size: 0.78rem; font-weight: 600; color: {{ $selectedCategory === $category['key'] ? '#fcdba5' : '#d6c8ad' }};">{{ $category['label'] }}</span>
                                <span class="sb-chip">{{ $category['count'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </aside>

            <section class="sb-site-manager-pane sb-site-manager-list">
                <div class="sb-site-manager-pane-header">
                    <div class="sb-muted" style="font-size: 10px; font-weight: 600; letter-spacing: 0.16em; text-transform: uppercase;">Documents</div>
                    <div class="sb-muted" style="margin-top: 0.25rem; font-size: 11px;">{{ $this->selectedCategoryLabel }}</div>
                </div>
                <div class="sb-list-scroll">
                    @forelse ($this->documents as $document)
                        <button
                            type="button"
                            wire:click="selectDocument({{ $document['id'] }})"
                            class="sb-card {{ $selectedDocumentId === $document['id'] && ! $isCreating ? 'is-active' : '' }}"
                            style="display: block; width: 100%; margin-bottom: 0.5rem; padding: 0.75rem; text-align: left;"
                        >
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;">
                                <div style="min-width: 0;">
                                    <div class="sb-title" style="font-size: 0.88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $document['title'] }}</div>
                                    <div class="sb-muted" style="margin-top: 0.25rem; font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase;">{{ $document['slug'] }}</div>
                                </div>
                                <span class="sb-chip {{ $document['is_published'] ? 'is-published' : '' }}">
                                    {{ $document['is_published'] ? 'Published' : 'Draft' }}
                                </span>
                            </div>
                            <div class="sb-muted" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-top: 0.75rem; font-size: 10px;">
                                <span>Order {{ $document['sort_order'] }}</span>
                                <span>{{ $document['updated_at'] ? \Illuminate\Support\Carbon::parse($document['updated_at'])->diffForHumans() : 'New' }}</span>
                            </div>
                        </button>
                    @empty
                        <div class="sb-panel sb-muted" style="padding: 1rem; font-size: 11px; border-style: dashed;">
                            No documents in this category yet.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="sb-site-manager-pane sb-site-manager-main">
                <div class="sb-site-manager-main-header">
                    <div style="min-width: 0;">
                        <div class="sb-title" style="font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            {{ $isCreating ? 'New ' . $this->selectedCategoryLabel . ' Document' : ($titleInput !== '' ? $titleInput : 'Untitled Document') }}
                        </div>
                        <div class="sb-muted" style="margin-top: 0.25rem; font-size: 11px;">{{ $this->selectedCategoryLabel }} category</div>
                    </div>
                    <div class="sb-actions">
                        @if ($selectedDocumentId)
                            <button type="button" wire:click="togglePublished" class="sb-btn {{ $isPublishedInput ? '' : 'sb-btn-success' }}">
                                {{ $isPublishedInput ? 'Unpublish' : 'Publish' }}
                            </button>
                            <button type="button" wire:click="deleteSelectedDocument" onclick="return confirm('Delete this document?');" class="sb-btn sb-btn-danger">
                                Delete
                            </button>
                        @endif
                        <button type="button" wire:click="saveDocument" class="sb-btn sb-btn-primary">
                            Save
                        </button>
                    </div>
                </div>

                <div class="sb-site-manager-main-body">
                    <div class="sb-form-grid">
                        <label style="display: block; font-size: 0.9rem; color: #d6c8ad;">
                            <div style="margin-bottom: 0.45rem; font-weight: 600;">Title</div>
                            <input type="text" wire:model.live="titleInput" class="sb-input">
                            @error('titleInput')
                                <div style="margin-top: 0.35rem; font-size: 0.75rem; color: #fca5a5;">{{ $message }}</div>
                            @enderror
                        </label>

                        <label style="display: block; font-size: 0.9rem; color: #d6c8ad;">
                            <div style="margin-bottom: 0.45rem; font-weight: 600;">Sort Order</div>
                            <input type="number" min="1" wire:model="sortOrderInput" class="sb-input">
                            <div class="sb-muted" style="margin-top: 0.35rem; font-size: 11px;">Lower numbers appear first in the public document tabs when the category is shown publicly.</div>
                            @error('sortOrderInput')
                                <div style="margin-top: 0.35rem; font-size: 0.75rem; color: #fca5a5;">{{ $message }}</div>
                            @enderror
                        </label>
                    </div>

                    <div style="margin-top: 1rem; display: flex; align-items: center; gap: 0.65rem; color: #d6c8ad; font-size: 0.9rem; font-weight: 600;">
                        <input type="checkbox" wire:model="isPublishedInput" style="width: 1rem; height: 1rem; accent-color: #f59e0b;">
                        <span>Published</span>
                    </div>

                    <label style="display: block; margin-top: 1rem; font-size: 0.9rem; color: #d6c8ad;">
                        <div style="margin-bottom: 0.45rem; font-weight: 600;">Body</div>
                        <textarea wire:model="bodyInput" rows="18" class="sb-textarea"></textarea>
                        <div class="sb-muted" style="margin-top: 0.35rem; font-size: 11px;">Uses StoryBox's safe rich-text tags like [b], [i], [u], [s], [small], and [large].</div>
                        @error('bodyInput')
                            <div style="margin-top: 0.35rem; font-size: 0.75rem; color: #fca5a5;">{{ $message }}</div>
                        @enderror
                    </label>

                    <div class="sb-panel" style="margin-top: 1rem; overflow: hidden;">
                        <button
                            type="button"
                            wire:click="$toggle('showAdvanced')"
                            style="display: flex; width: 100%; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; text-align: left; color: #d6c8ad; font-size: 0.9rem; font-weight: 600;"
                        >
                            <span>Advanced</span>
                            <span class="sb-muted" style="font-size: 0.75rem;">{{ $showAdvanced ? 'Hide' : 'Show' }}</span>
                        </button>
                        @if ($showAdvanced)
                            <div style="padding: 1rem; border-top: 1px solid #332817; background: #111113;">
                                <label style="display: block; font-size: 0.9rem; color: #d6c8ad;">
                                    <div style="margin-bottom: 0.45rem; font-weight: 600;">Slug</div>
                                    <input type="text" wire:model.live="slugInput" class="sb-input" style="background: #141416;">
                                    <div class="sb-muted" style="margin-top: 0.35rem; font-size: 11px;">Auto-generated from the title unless you override it here.</div>
                                    @error('slugInput')
                                        <div style="margin-top: 0.35rem; font-size: 0.75rem; color: #fca5a5;">{{ $message }}</div>
                                    @enderror
                                </label>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
