<?php

use App\Actions\RecordActivity;
use App\Models\FinancialRestrictedFund;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component
{
    public string $newName = '';

    public string $newDescription = '';

    public ?int $editId = null;

    public string $editName = '';

    public string $editDescription = '';

    public bool $showInactive = false;

    public function mount(): void
    {
        $this->authorize('finance-view');
    }

    public function getFundsProperty()
    {
        $query = FinancialRestrictedFund::orderBy('name');

        if (! $this->showInactive) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function getFundSummariesProperty(): array
    {
        $fundIds = $this->funds->pluck('id');

        // Income entries credit the revenue account — use net credit movement for received
        $received = DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->whereIn('je.restricted_fund_id', $fundIds)
            ->where('je.entry_type', 'income')
            ->where('je.status', 'posted')
            ->where('fa.type', 'revenue')
            ->groupBy('je.restricted_fund_id')
            ->select('je.restricted_fund_id', DB::raw('COALESCE(SUM(jel.credit) - SUM(jel.debit), 0) as total'))
            ->get()
            ->keyBy('restricted_fund_id');

        // Expense entries debit the expense account — use net debit movement for spent
        $spent = DB::table('financial_journal_entry_lines as jel')
            ->join('financial_journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'jel.account_id')
            ->whereIn('je.restricted_fund_id', $fundIds)
            ->where('je.entry_type', 'expense')
            ->where('je.status', 'posted')
            ->where('fa.type', 'expense')
            ->groupBy('je.restricted_fund_id')
            ->select('je.restricted_fund_id', DB::raw('COALESCE(SUM(jel.debit) - SUM(jel.credit), 0) as total'))
            ->get()
            ->keyBy('restricted_fund_id');

        $summaries = [];
        foreach ($this->funds as $fund) {
            $rec = (int) ($received[$fund->id]->total ?? 0);
            $spt = (int) ($spent[$fund->id]->total ?? 0);
            $summaries[$fund->id] = [
                'received' => $rec,
                'spent' => $spt,
                'remaining' => $rec - $spt,
            ];
        }

        return $summaries;
    }

    public function createFund(): void
    {
        $this->authorize('finance-manage');

        $this->validate([
            'newName' => 'required|string|max:255|unique:financial_restricted_funds,name',
        ]);

        $fund = DB::transaction(function () {
            $fund = FinancialRestrictedFund::create([
                'name' => $this->newName,
                'description' => $this->newDescription ?: null,
                'is_active' => true,
            ]);

            RecordActivity::run($fund, 'create_restricted_fund', "Created restricted fund \"{$fund->name}\".");

            return $fund;
        });

        $this->newName = '';
        $this->newDescription = '';

        Flux::modal('add-fund-modal')->close();
        Flux::toast('Restricted fund created.', 'Done', variant: 'success');
    }

    public function startEdit(int $id): void
    {
        $this->authorize('finance-manage');

        $fund = FinancialRestrictedFund::findOrFail($id);

        $this->editId = $id;
        $this->editName = $fund->name;
        $this->editDescription = $fund->description ?? '';

        Flux::modal('edit-fund-modal')->show();
    }

    public function updateFund(): void
    {
        $this->authorize('finance-manage');

        $this->validate([
            'editName' => "required|string|max:255|unique:financial_restricted_funds,name,{$this->editId}",
        ]);

        DB::transaction(function () {
            $fund = FinancialRestrictedFund::findOrFail($this->editId);
            $fund->update([
                'name' => $this->editName,
                'description' => $this->editDescription ?: null,
            ]);

            RecordActivity::run($fund, 'update_restricted_fund', "Updated restricted fund \"{$fund->name}\".");
        });

        $this->editId = null;

        Flux::modal('edit-fund-modal')->close();
        Flux::toast('Restricted fund updated.', 'Done', variant: 'success');
    }

    public function deactivate(int $id): void
    {
        $this->authorize('finance-manage');

        DB::transaction(function () use ($id) {
            $fund = FinancialRestrictedFund::findOrFail($id);
            $fund->update(['is_active' => false]);

            RecordActivity::run($fund, 'deactivate_restricted_fund', "Deactivated restricted fund \"{$fund->name}\".");
        });

        Flux::toast('Fund deactivated.', 'Done', variant: 'success');
    }

    public function reactivate(int $id): void
    {
        $this->authorize('finance-manage');

        DB::transaction(function () use ($id) {
            $fund = FinancialRestrictedFund::findOrFail($id);
            $fund->update(['is_active' => true]);

            RecordActivity::run($fund, 'reactivate_restricted_fund', "Reactivated restricted fund \"{$fund->name}\".");
        });

        Flux::toast('Fund reactivated.', 'Done', variant: 'success');
    }
}; ?>

<div class="space-y-6">
    @include('livewire.finance.partials.nav')

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Restricted Funds</flux:heading>
            <flux:text variant="subtle">Named funds for donor-restricted gifts. Track received, spent, and remaining balances.</flux:text>
        </div>
        @can('finance-manage')
            <flux:button variant="primary" wire:click="$flux.modal('add-fund-modal').show()">New Fund</flux:button>
        @endcan
    </div>

    <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400 cursor-pointer">
            <input type="checkbox" wire:model.live="showInactive" class="rounded" />
            Show inactive funds
        </label>
    </div>

    <flux:card>
        @if ($this->funds->isEmpty())
            <p class="text-sm text-zinc-500 dark:text-zinc-400 py-8 text-center">
                @can('finance-manage')
                    No restricted funds found. Create one to get started.
                @else
                    No restricted funds found yet.
                @endcan
            </p>
        @else
            @php $summaries = $this->fundSummaries; @endphp
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Fund</flux:table.column>
                    <flux:table.column>Received</flux:table.column>
                    <flux:table.column>Spent</flux:table.column>
                    <flux:table.column>Remaining</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    @can('finance-manage')
                        <flux:table.column></flux:table.column>
                    @endcan
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->funds as $fund)
                        @php $s = $summaries[$fund->id]; @endphp
                        <flux:table.row wire:key="fund-{{ $fund->id }}">
                            <flux:table.cell>
                                <div>
                                    <span class="font-medium text-sm">{{ $fund->name }}</span>
                                    @if ($fund->description)
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $fund->description }}</p>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="font-mono text-sm text-green-600 dark:text-green-400">
                                ${{ number_format($s['received'] / 100, 2) }}
                            </flux:table.cell>

                            <flux:table.cell class="font-mono text-sm text-red-600 dark:text-red-400">
                                ${{ number_format($s['spent'] / 100, 2) }}
                            </flux:table.cell>

                            <flux:table.cell class="font-mono text-sm font-medium {{ $s['remaining'] >= 0 ? 'text-zinc-800 dark:text-zinc-200' : 'text-red-600 dark:text-red-400' }}">
                                ${{ number_format($s['remaining'] / 100, 2) }}
                            </flux:table.cell>

                            <flux:table.cell>
                                @if ($fund->is_active)
                                    <flux:badge color="green" size="sm">Active</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                                @endif
                            </flux:table.cell>

                            @can('finance-manage')
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <flux:button variant="ghost" size="sm" wire:click="startEdit({{ $fund->id }})">Edit</flux:button>
                                        @if ($fund->is_active)
                                            <flux:button variant="ghost" size="sm" wire:click="deactivate({{ $fund->id }})" wire:confirm="Deactivate this fund?">Deactivate</flux:button>
                                        @else
                                            <flux:button variant="ghost" size="sm" wire:click="reactivate({{ $fund->id }})">Reactivate</flux:button>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            @endcan
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    {{-- Add Fund Modal --}}
    <flux:modal name="add-fund-modal" class="max-w-md">
        <flux:heading size="lg" class="mb-4">New Restricted Fund</flux:heading>

        <div class="space-y-4">
            <flux:field>
                <flux:label>Fund Name</flux:label>
                <flux:input wire:model="newName" placeholder="e.g. Server Fund Drive 2025" />
                <flux:error name="newName" />
            </flux:field>

            <flux:field>
                <flux:label>Description <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                <flux:textarea wire:model="newDescription" rows="2" placeholder="Brief description of this fund's purpose" />
            </flux:field>
        </div>

        <div class="flex justify-end gap-3 mt-4">
            <flux:button variant="ghost" wire:click="$flux.modal('add-fund-modal').close()">Cancel</flux:button>
            <flux:button variant="primary" wire:click="createFund">Create Fund</flux:button>
        </div>
    </flux:modal>

    {{-- Edit Fund Modal --}}
    <flux:modal name="edit-fund-modal" class="max-w-md">
        <flux:heading size="lg" class="mb-4">Edit Restricted Fund</flux:heading>

        <div class="space-y-4">
            <flux:field>
                <flux:label>Fund Name</flux:label>
                <flux:input wire:model="editName" />
                <flux:error name="editName" />
            </flux:field>

            <flux:field>
                <flux:label>Description <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                <flux:textarea wire:model="editDescription" rows="2" />
            </flux:field>
        </div>

        <div class="flex justify-end gap-3 mt-4">
            <flux:button variant="ghost" wire:click="$flux.modal('edit-fund-modal').close()">Cancel</flux:button>
            <flux:button variant="primary" wire:click="updateFund">Save Changes</flux:button>
        </div>
    </flux:modal>
</div>
