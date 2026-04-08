<nav class="flex flex-wrap gap-2 pb-2 border-b border-zinc-200 dark:border-zinc-700">
    <flux:button
        variant="{{ request()->routeIs('finance.journal.*') ? 'filled' : 'ghost' }}"
        size="sm"
        href="{{ route('finance.journal.index') }}"
        wire:navigate
    >Journal</flux:button>

    <flux:button
        variant="{{ request()->routeIs('finance.reports.index') ? 'filled' : 'ghost' }}"
        size="sm"
        href="{{ route('finance.reports.index') }}"
        wire:navigate
    >Reports</flux:button>

    <flux:button
        variant="{{ request()->routeIs('finance.periods.index') ? 'filled' : 'ghost' }}"
        size="sm"
        href="{{ route('finance.periods.index') }}"
        wire:navigate
    >Periods</flux:button>

    @can('finance-manage')
        <flux:button
            variant="{{ request()->routeIs('finance.accounts.index') ? 'filled' : 'ghost' }}"
            size="sm"
            href="{{ route('finance.accounts.index') }}"
            wire:navigate
        >Accounts</flux:button>

        <flux:button
            variant="{{ request()->routeIs('finance.budgets.index') ? 'filled' : 'ghost' }}"
            size="sm"
            href="{{ route('finance.budgets.index') }}"
            wire:navigate
        >Budgets</flux:button>

        <flux:button
            variant="{{ request()->routeIs('finance.restricted-funds.index') ? 'filled' : 'ghost' }}"
            size="sm"
            href="{{ route('finance.restricted-funds.index') }}"
            wire:navigate
        >Restricted Funds</flux:button>

        <flux:button
            variant="{{ request()->routeIs('finance.vendors.index') ? 'filled' : 'ghost' }}"
            size="sm"
            href="{{ route('finance.vendors.index') }}"
            wire:navigate
        >Vendors</flux:button>

        <flux:button
            variant="{{ request()->routeIs('finance.tags.index') ? 'filled' : 'ghost' }}"
            size="sm"
            href="{{ route('finance.tags.index') }}"
            wire:navigate
        >Tags</flux:button>
    @endcan
</nav>
