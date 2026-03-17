<div class="flex aspect-square size-8 items-center justify-center">
    <img src="{{ asset('img/LighthouseMC_Logo.png') }}" alt="Lighthouse MC" class="size-8 rounded-md" />
</div>

@php
    $colors['local'] = 'text-fuchsia-400';
    $colors['staging'] = 'text-orange-400';
    $colors['testing'] = 'text-lime-400';
    $colors['other'] = 'text-red-600';

    $useColorKey = array_key_exists(config('app.env'), $colors) ? config('app.env') : 'other';
@endphp

<div class="ml-1 grid flex-1 text-left text-sm text- ">
    <span class="mb-0.5 truncate leading-none font-semibold">
        @if (config('app.env') != 'production')
                <span class="text-xs {{  $colors[$useColorKey] }}">{{ strtoupper(config('app.env')) }}</span>
        @endif
        {{ config('app.name') }}
    </span>
</div>
