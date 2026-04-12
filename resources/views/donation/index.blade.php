<x-layouts.app>
    <flux:heading size="xl" class="mb-6">Support Lighthouse</flux:heading>

    <flux:text>LighthouseMC runs off of support from the community and donors who believe in the mission. We would not be here without their support. Consider making a donation to help us continue our work.</flux:text>


    <flux:heading class="mt-6">Community Support</flux:heading>
    <flux:text>Here's how the community is helping cover our monthly costs:</flux:text>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 my-6">
        @foreach ($closedPeriods as $period)
            @php $netPositive = $period['net'] >= 0; @endphp
            <flux:card class="overflow-hidden">
                <flux:heading size="sm" class="mb-3">{{ $period['name'] }}</flux:heading>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Income</span>
                        <span class="font-mono text-green-600 dark:text-green-400">${{ number_format($period['income'] / 100, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">Expenses</span>
                        <span class="font-mono text-red-600 dark:text-red-400">${{ number_format($period['expenses'] / 100, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-semibold border-t border-zinc-200 dark:border-zinc-700 pt-2 mt-2">
                        <span>Net</span>
                        @if ($netPositive)
                            <span class="font-mono text-green-600 dark:text-green-400">${{ number_format($period['net'] / 100, 2) }}</span>
                        @else
                            <span class="font-mono text-red-600 dark:text-red-400">-${{ number_format(abs($period['net']) / 100, 2) }}</span>
                        @endif
                    </div>
                </div>
            </flux:card>
        @endforeach

        @if(config('lighthouse.stripe.one_time_donation_url'))
            <flux:card class="overflow-hidden">
                <flux:heading>One-Time Gift</flux:heading>
                <flux:button href="{{  config('lighthouse.stripe.one_time_donation_url') }}" variant="primary" color="sky" class="mt-3">Make a One-Time Gift</flux:button>
            </flux:card>
        @endif
    </div>

    <flux:heading class="mt-6 mb-3">Monthly Support</flux:heading>
    <script async src="https://js.stripe.com/v3/pricing-table.js"></script>
    <stripe-pricing-table
        pricing-table-id="{{  config('lighthouse.stripe.donation_pricing_table_id') }}"
        publishable-key="{{  config('lighthouse.stripe.donation_pricing_table_key') }}">
    </stripe-pricing-table>

    <flux:callout icon="user-group" class="w-full md:w-1/2 my-6 mx-auto" color="sky">
        <flux:callout.heading>LighthouseMC is built on community support!</flux:callout.heading>
        <flux:callout.text>
            <p class="my-3">Every gift helps us keep Minecraft a safe, fun, and Christ-centered place for kids and teens. Thank you for being part of this mission!</p>
            <p class="my-3">All staff members are volunteers and do not receive any financial or other compensation for their time and efforts. <strong>100% of donations go straight into ministry expenses.</strong></p>
        </flux:callout.text>

        <x-slot name="actions">
            <flux:button href="{{ config('lighthouse.stripe.customer_portal_url') }}" size="sm">Manage Subscription</flux:button>
        </x-slot>
    </flux:callout>

</x-layouts.app>
