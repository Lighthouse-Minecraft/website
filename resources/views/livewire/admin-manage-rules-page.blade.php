<?php

use App\Actions\AddRuleToDraft;
use App\Actions\CreateRuleVersion;
use App\Actions\DeactivateRuleInDraft;
use App\Actions\UpdateRuleInDraft;
use App\Actions\UpdateRulesHeaderFooter;
use App\Models\Rule;
use App\Models\RuleCategory;
use App\Models\RuleVersion;
use App\Models\SiteConfig;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    // Header/footer
    public string $rulesHeader = '';
    public string $rulesFooter = '';

    // Add category
    public string $newCategoryName = '';

    // Add rule
    public ?int $addRuleCategoryId = null;
    public string $newRuleTitle = '';
    public string $newRuleDescription = '';

    // Edit/replace rule
    public ?int $editRuleId = null;
    public ?int $editRuleCategoryId = null;
    public string $editRuleTitle = '';
    public string $editRuleDescription = '';

    public function mount(): void
    {
        $this->rulesHeader = SiteConfig::getValue('rules_header', '');
        $this->rulesFooter = SiteConfig::getValue('rules_footer', '');
    }

    public function getCategories()
    {
        return RuleCategory::with(['rules' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    public function getDraft(): ?RuleVersion
    {
        return RuleVersion::currentDraft();
    }

    public function getDraftRuleIds(): array
    {
        $draft = $this->getDraft();
        if (! $draft) {
            return [];
        }

        return $draft->rules()
            ->wherePivot('deactivate_on_publish', false)
            ->pluck('rules.id')
            ->toArray();
    }

    public function getDeactivatingRuleIds(): array
    {
        $draft = $this->getDraft();
        if (! $draft) {
            return [];
        }

        return $draft->rules()
            ->wherePivot('deactivate_on_publish', true)
            ->pluck('rules.id')
            ->toArray();
    }

    public function saveHeaderFooter(): void
    {
        $this->authorize('rules.manage');

        $this->validate([
            'rulesHeader' => 'nullable|string|max:5000',
            'rulesFooter' => 'nullable|string|max:5000',
        ]);

        UpdateRulesHeaderFooter::run($this->rulesHeader, $this->rulesFooter);
        Flux::toast('Rules header and footer saved.', 'Saved', variant: 'success');
    }

    public function startDraft(): void
    {
        $this->authorize('rules.manage');

        if (RuleVersion::currentDraft()) {
            Flux::toast('A draft already exists.', 'Error', variant: 'danger');

            return;
        }

        CreateRuleVersion::run(auth()->user());
        Flux::toast('Draft version created.', 'Draft Started', variant: 'success');
    }

    public function addCategory(): void
    {
        $this->authorize('rules.manage');

        $this->validate(['newCategoryName' => 'required|string|max:255']);

        $maxOrder = RuleCategory::max('sort_order') ?? 0;
        RuleCategory::create(['name' => $this->newCategoryName, 'sort_order' => $maxOrder + 1]);

        $this->newCategoryName = '';
        Flux::modal('add-category-modal')->close();
        Flux::toast('Category added.', 'Added', variant: 'success');
    }

    public function moveCategoryUp(int $categoryId): void
    {
        $this->authorize('rules.manage');

        $category = RuleCategory::findOrFail($categoryId);
        $prev = RuleCategory::where('sort_order', '<', $category->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($prev) {
            [$category->sort_order, $prev->sort_order] = [$prev->sort_order, $category->sort_order];
            $category->save();
            $prev->save();
        }
    }

    public function moveCategoryDown(int $categoryId): void
    {
        $this->authorize('rules.manage');

        $category = RuleCategory::findOrFail($categoryId);
        $next = RuleCategory::where('sort_order', '>', $category->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            [$category->sort_order, $next->sort_order] = [$next->sort_order, $category->sort_order];
            $category->save();
            $next->save();
        }
    }

    public function openAddRuleModal(int $categoryId): void
    {
        $this->authorize('rules.manage');

        $this->addRuleCategoryId = $categoryId;
        $this->newRuleTitle = '';
        $this->newRuleDescription = '';
        Flux::modal('add-rule-modal')->show();
    }

    public function addRule(): void
    {
        $this->authorize('rules.manage');

        $this->validate([
            'newRuleTitle' => 'required|string|max:255',
            'newRuleDescription' => 'required|string|max:10000',
            'addRuleCategoryId' => 'required|integer|exists:rule_categories,id',
        ]);

        $draft = $this->getDraft();
        if (! $draft) {
            Flux::toast('Start a draft first.', 'Error', variant: 'danger');

            return;
        }

        $category = RuleCategory::findOrFail($this->addRuleCategoryId);
        AddRuleToDraft::run($draft, $category, $this->newRuleTitle, $this->newRuleDescription, auth()->user());

        $this->reset(['newRuleTitle', 'newRuleDescription', 'addRuleCategoryId']);
        Flux::modal('add-rule-modal')->close();
        Flux::toast('Rule added to draft.', 'Added', variant: 'success');
    }

    public function openEditRuleModal(int $ruleId): void
    {
        $this->authorize('rules.manage');

        $rule = Rule::findOrFail($ruleId);
        $this->editRuleId = $ruleId;
        $this->editRuleCategoryId = $rule->rule_category_id;
        $this->editRuleTitle = $rule->title;
        $this->editRuleDescription = $rule->description;
        Flux::modal('edit-rule-modal')->show();
    }

    public function updateRule(): void
    {
        $this->authorize('rules.manage');

        $this->validate([
            'editRuleTitle' => 'required|string|max:255',
            'editRuleDescription' => 'required|string|max:10000',
            'editRuleCategoryId' => 'required|integer|exists:rule_categories,id',
        ]);

        $draft = $this->getDraft();
        if (! $draft) {
            Flux::toast('Start a draft first.', 'Error', variant: 'danger');

            return;
        }

        $oldRule = Rule::findOrFail($this->editRuleId);
        $newCategory = RuleCategory::findOrFail($this->editRuleCategoryId);
        UpdateRuleInDraft::run($draft, $oldRule, $this->editRuleTitle, $this->editRuleDescription, auth()->user(), $newCategory);

        $this->reset(['editRuleId', 'editRuleTitle', 'editRuleDescription', 'editRuleCategoryId']);
        Flux::modal('edit-rule-modal')->close();
        Flux::toast('Rule replaced in draft.', 'Updated', variant: 'success');
    }

    public function deactivateRule(int $ruleId): void
    {
        $this->authorize('rules.manage');

        $draft = $this->getDraft();
        if (! $draft) {
            Flux::toast('Start a draft first.', 'Error', variant: 'danger');

            return;
        }

        $rule = Rule::findOrFail($ruleId);
        DeactivateRuleInDraft::run($draft, $rule);
        Flux::toast('Rule marked for deactivation.', 'Deactivated', variant: 'success');
    }

    public function moveRuleUp(int $ruleId): void
    {
        $this->authorize('rules.manage');

        $rule = Rule::findOrFail($ruleId);
        $prev = Rule::where('rule_category_id', $rule->rule_category_id)
            ->where('sort_order', '<', $rule->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($prev) {
            [$rule->sort_order, $prev->sort_order] = [$prev->sort_order, $rule->sort_order];
            $rule->save();
            $prev->save();
        }
    }

    public function moveRuleDown(int $ruleId): void
    {
        $this->authorize('rules.manage');

        $rule = Rule::findOrFail($ruleId);
        $next = Rule::where('rule_category_id', $rule->rule_category_id)
            ->where('sort_order', '>', $rule->sort_order)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            [$rule->sort_order, $next->sort_order] = [$next->sort_order, $rule->sort_order];
            $rule->save();
            $next->save();
        }
    }
}; ?>

<div class="space-y-8">
    {{-- Header/Footer Editing --}}
    @can('rules.manage')
        <flux:card class="space-y-4">
            <flux:heading size="md">Rules Header & Footer</flux:heading>
            <flux:text variant="subtle">Markdown text displayed above and below the community rules page.</flux:text>

            <flux:field>
                <flux:label>Header (Markdown)</flux:label>
                <flux:textarea wire:model="rulesHeader" rows="5" placeholder="Scripture quotes or introductory text..." />
            </flux:field>

            <flux:field>
                <flux:label>Footer (Markdown)</flux:label>
                <flux:textarea wire:model="rulesFooter" rows="3" placeholder="Officer discretion disclaimer or closing notes..." />
            </flux:field>

            <flux:button wire:click="saveHeaderFooter" variant="primary" size="sm">Save Header &amp; Footer</flux:button>
        </flux:card>
    @endcan

    {{-- Draft Management --}}
    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="md">Draft Version</flux:heading>
                @if($this->getDraft())
                    <flux:text variant="subtle">
                        Draft v{{ $this->getDraft()->version_number }} is open
                        @if($this->getDraft()->status === 'submitted')
                            — <flux:badge variant="warning" size="sm">Awaiting Approval</flux:badge>
                        @else
                            — <flux:badge variant="primary" size="sm">In Progress</flux:badge>
                        @endif
                    </flux:text>
                @else
                    <flux:text variant="subtle">No draft version is currently open.</flux:text>
                @endif
            </div>
            @can('rules.manage')
                @if(! $this->getDraft())
                    <flux:button wire:click="startDraft" variant="primary" icon="document-plus">Start New Draft</flux:button>
                @endif
            @endcan
        </div>
    </flux:card>

    {{-- Categories & Rules List --}}
    @foreach($this->getCategories() as $category)
        <flux:card wire:key="category-{{ $category->id }}" class="space-y-4">
            <div class="flex items-center gap-2">
                <flux:heading size="md" class="flex-1">{{ $category->name }}</flux:heading>
                @can('rules.manage')
                    <flux:button wire:click="moveCategoryUp({{ $category->id }})" size="sm" variant="ghost" icon="chevron-up" title="Move category up" />
                    <flux:button wire:click="moveCategoryDown({{ $category->id }})" size="sm" variant="ghost" icon="chevron-down" title="Move category down" />
                    @if($this->getDraft())
                        <flux:button wire:click="openAddRuleModal({{ $category->id }})" size="sm" variant="ghost" icon="plus" title="Add rule">Add Rule</flux:button>
                    @endif
                @endcan
            </div>

            @forelse($category->rules as $rule)
                @php
                    $draftRuleIds = $this->getDraftRuleIds();
                    $deactivatingIds = $this->getDeactivatingRuleIds();
                    $isDeactivating = in_array($rule->id, $deactivatingIds);
                    $isDraftRule = $rule->status === 'draft';
                @endphp
                <div wire:key="rule-{{ $rule->id }}" class="border rounded p-3 {{ $isDeactivating ? 'opacity-50 bg-red-50 dark:bg-red-950' : '' }}">
                    <div class="flex items-start gap-2">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-sm">{{ $rule->title }}</span>
                                @if($isDraftRule)
                                    <flux:badge variant="success" size="sm">NEW</flux:badge>
                                @endif
                                @if($isDeactivating)
                                    <flux:badge variant="danger" size="sm">Deactivating</flux:badge>
                                @endif
                                @if($rule->status === 'inactive')
                                    <flux:badge variant="zinc" size="sm">Inactive</flux:badge>
                                @endif
                                @if($rule->supersedes_rule_id)
                                    <flux:badge variant="warning" size="sm">Replaces #{{ $rule->supersedes_rule_id }}</flux:badge>
                                @endif
                            </div>
                            <p class="text-sm text-zinc-500 mt-1">{{ Str::limit($rule->description, 120) }}</p>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            @can('rules.manage')
                                <flux:button wire:click="moveRuleUp({{ $rule->id }})" size="sm" variant="ghost" icon="chevron-up" title="Move up" />
                                <flux:button wire:click="moveRuleDown({{ $rule->id }})" size="sm" variant="ghost" icon="chevron-down" title="Move down" />
                                @if($this->getDraft() && ! $isDeactivating)
                                    <flux:button wire:click="openEditRuleModal({{ $rule->id }})" size="sm" variant="ghost" icon="pencil-square" title="Edit / Replace" />
                                    @if($rule->status === 'active')
                                        <flux:button wire:click="deactivateRule({{ $rule->id }})" size="sm" variant="ghost" icon="trash" title="Mark for deactivation" />
                                    @endif
                                @endif
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <flux:text variant="subtle" class="text-sm">No rules in this category.</flux:text>
            @endforelse
        </flux:card>
    @endforeach

    @can('rules.manage')
        <div class="flex justify-end">
            <flux:modal.trigger name="add-category-modal">
                <flux:button variant="ghost" icon="folder-plus">Add Category</flux:button>
            </flux:modal.trigger>
        </div>
    @endcan

    {{-- Add Category Modal --}}
    @can('rules.manage')
        <flux:modal name="add-category-modal" variant="flyout" class="space-y-4">
            <flux:heading size="lg">Add Category</flux:heading>
            <flux:field>
                <flux:label>Category Name <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="newCategoryName" placeholder="e.g. Keep Language Clean" />
                <flux:error name="newCategoryName" />
            </flux:field>
            <div class="flex gap-2 justify-end">
                <flux:button x-on:click="$flux.modal('add-category-modal').close()" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="addCategory" variant="primary">Add Category</flux:button>
            </div>
        </flux:modal>

        {{-- Add Rule Modal --}}
        <flux:modal name="add-rule-modal" variant="flyout" class="space-y-4">
            <flux:heading size="lg">Add Rule to Draft</flux:heading>
            <flux:field>
                <flux:label>Title <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="newRuleTitle" placeholder="e.g. No Griefing" />
                <flux:error name="newRuleTitle" />
            </flux:field>
            <flux:field>
                <flux:label>Description <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="newRuleDescription" rows="4" placeholder="Full rule text (Markdown supported)..." />
                <flux:error name="newRuleDescription" />
            </flux:field>
            <div class="flex gap-2 justify-end">
                <flux:button x-on:click="$flux.modal('add-rule-modal').close()" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="addRule" variant="primary">Add Rule</flux:button>
            </div>
        </flux:modal>

        {{-- Edit Rule Modal --}}
        <flux:modal name="edit-rule-modal" variant="flyout" class="space-y-4">
            <flux:heading size="lg">Edit / Replace Rule</flux:heading>
            <flux:text variant="subtle" size="sm">A replacement rule will be created with the new text. The original rule will be deactivated when this version publishes.</flux:text>

            <flux:field>
                <flux:label>Category</flux:label>
                <flux:select wire:model="editRuleCategoryId">
                    @foreach($this->getCategories() as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="editRuleCategoryId" />
            </flux:field>
            <flux:field>
                <flux:label>Title <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="editRuleTitle" />
                <flux:error name="editRuleTitle" />
            </flux:field>
            <flux:field>
                <flux:label>Description <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="editRuleDescription" rows="4" />
                <flux:error name="editRuleDescription" />
            </flux:field>
            <div class="flex gap-2 justify-end">
                <flux:button x-on:click="$flux.modal('edit-rule-modal').close()" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="updateRule" variant="primary">Save Replacement</flux:button>
            </div>
        </flux:modal>
    @endcan
</div>
