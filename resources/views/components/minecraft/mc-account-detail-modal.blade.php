@props(['account' => null])

<flux:modal name="mc-account-detail" class="max-w-lg">
    @if($account)
        <div class="space-y-4">
            {{-- Header: avatar + username + badges --}}
            <div class="flex items-center gap-4">
                @if($account->avatar_url)
                    <img src="{{ $account->avatar_url }}"
                         alt="{{ $account->username }}"
                         class="w-16 h-16 rounded" />
                @else
                    <div class="w-16 h-16 rounded bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-zinc-400 text-2xl">
                        ?
                    </div>
                @endif

                <div>
                    <flux:heading size="xl">{{ $account->username }}</flux:heading>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <flux:badge size="sm" color="{{ $account->status->color() }}">
                            {{ $account->status->label() }}
                        </flux:badge>
                        <flux:badge size="sm" color="{{ $account->account_type === \App\Enums\MinecraftAccountType::Java ? 'green' : 'blue' }}">
                            {{ $account->account_type->label() }}
                        </flux:badge>
                        @if($account->is_primary)
                            <flux:badge size="sm" color="blue">Primary</flux:badge>
                        @endif
                    </div>
                </div>
            </div>

            <flux:separator />

            @php $tz = auth()->user()->timezone ?? 'UTC'; @endphp
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">UUID</dt>
                    <dd class="font-mono text-xs break-all text-right">{{ $account->uuid }}</dd>
                </div>

                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Linked User</dt>
                    <dd>
                        @if($account->user)
                            <flux:link href="{{ route('profile.show', $account->user) }}">
                                {{ $account->user->name }}
                            </flux:link>
                        @else
                            <em class="text-zinc-400">None</em>
                        @endif
                    </dd>
                </div>

                @if($account->verified_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Verified At</dt>
                        <dd title="{{ $account->verified_at->setTimezone($tz)->format('Y-m-d H:i:s T') }}">
                            {{ $account->verified_at->setTimezone($tz)->format('M j, Y g:i A') }}
                        </dd>
                    </div>
                @endif

                @if($account->last_username_check_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Last Username Check</dt>
                        <dd title="{{ $account->last_username_check_at->setTimezone($tz)->format('Y-m-d H:i:s T') }}">
                            {{ $account->last_username_check_at->setTimezone($tz)->format('M j, Y g:i A') }}
                        </dd>
                    </div>
                @endif

                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500 dark:text-zinc-400 font-medium shrink-0">Created At</dt>
                    <dd title="{{ $account->created_at->setTimezone($tz)->format('Y-m-d H:i:s T') }}">
                        {{ $account->created_at->setTimezone($tz)->format('M j, Y g:i A') }}
                    </dd>
                </div>
            </dl>

            @canany(['reactivate', 'forceDelete'], $account)
                <flux:separator />
                <div class="flex gap-2 justify-end">
                    @can('reactivate', $account)
                        <flux:button
                            wire:click="reactivateMinecraftAccount({{ $account->id }})"
                            variant="primary"
                            size="sm"
                            wire:confirm="Reactivate this Minecraft account? It will be re-whitelisted.">
                            Reactivate
                        </flux:button>
                    @endcan
                    @can('forceDelete', $account)
                        <flux:button
                            wire:click="forceDeleteMinecraftAccount({{ $account->id }})"
                            variant="ghost"
                            size="sm"
                            wire:confirm="PERMANENTLY delete this Minecraft account? This will release the UUID so it can be registered by anyone. This action cannot be undone.">
                            Delete Permanently
                        </flux:button>
                    @endcan
                </div>
            @endcanany
        </div>
    @endif
</flux:modal>
