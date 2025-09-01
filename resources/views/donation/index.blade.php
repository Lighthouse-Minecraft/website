<x-layouts.app>
    <flux:heading size="xl" class="mb-6">Support Lighthouse</flux:heading>

    <flux:text>LighthouseMC runs off of support from the community and donors who believe in the mission. We would not be here without their support. Consider making a donation to help us continue our work.</flux:text>


    <flux:heading class="mt-4">Community Support</flux:heading>
    <flux:text>Here’s how the community is helping cover our monthly costs:</flux:text>
    <div class="flex w-full">
        @if(config('lighthouse.donation_current_month_name'))
            <flux:card class="overflow-hidden min-w-[12rem] my-6 w-full md:w-1/6 mx-2">
                <flux:text>{{ config('lighthouse.donation_current_month_name') }}</flux:text>
                <flux:heading size="xl" class="mt-2 tabular-nums flex">${{ config('lighthouse.donation_current_month_amount') }} <flux:text class="text-lg mt-1 mx-4">/ ${{ config('lighthouse.donation_goal') }}</flux:text></flux:heading>
            </flux:card>
        @endif

        @if(config('lighthouse.donation_last_month_name'))
            <flux:card class="overflow-hidden min-w-[12rem] my-6 w-full md:w-1/6 mx-2">
                <flux:text>{{ config('lighthouse.donation_last_month_name') }}</flux:text>
                <flux:heading size="xl" class="mt-2 tabular-nums flex">${{ config('lighthouse.donation_last_month_amount') }} <flux:text class="text-lg mt-1 mx-4">/ ${{ config('lighthouse.donation_goal') }}</flux:text></flux:heading>
            </flux:card>
        @endif
    </div>
    <flux:text variant="subtle" class="my-1">Amounts are currently updated weekly</flux:text>

    <flux:callout icon="user-group" class="w-full md:w-1/2 my-6" color="cyan">
        <flux:callout.heading>LighthouseMC is built on community support!</flux:callout.heading>
        <flux:callout.text>
            <p class="my-3">Every gift helps us keep Minecraft a safe, fun, and Christ-centered place for kids and teens. Thank you for being part of this mission!</p>
            <p class="my-3">All staff members are volunteers and do not receive any financial or other compensation for their time and efforts. <strong>100% of donations go straight into ministry expenses.</strong></p>
        </flux:callout.text>
    </flux:callout>
</x-layouts.app>
