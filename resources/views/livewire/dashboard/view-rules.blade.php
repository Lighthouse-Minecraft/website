<?php

use Livewire\Volt\Component;
use Flux\Flux;
use App\Models\Role;
use App\Models\User;
use App\Enums\MembershipLevel;

new class extends Component {

    public function acceptRules()
    {
        auth()->user()->update([
            'rules_accepted_at' => now(),
        ]);
        \App\Actions\RecordActivity::run(auth()->user(), 'rules_accepted', 'User accepted community rules and was promoted to Stowaway');

        \App\Actions\PromoteUser::run(auth()->user(), MembershipLevel::Stowaway);
        Cache::forget('user:' . auth()->user()->id . ':is_stowaway');

        Flux::modal('view-rules-modal')->close();
        Flux::toast('Rules accepted successfully! Promoted to Stowaway.', 'Success', variant: 'success');

        return redirect()->route('dashboard');
    }
}; ?>

<div>
    <flux:modal.trigger name="view-rules-modal">
        @if (auth()->user()->rules_accepted_at)
            <flux:button size="xs">View Rules</flux:button>
        @else
            <flux:button variant="primary">Read & Accept Rules</flux:button>
        @endif
    </flux:modal.trigger>

    <flux:modal name="view-rules-modal" size="lg" variant="flyout" class="w-full md:w-2/3">
        <div id="editor_content">
            <h1>Lighthouse Community Rules</h1>

            <blockquote>
                “Love one another with brotherly affection. Outdo one another in showing honor.”
                <br>
                <span class="block text-sm text-gray-500">Romans 12:10 (ESV)</span>
            </blockquote>

            <blockquote>
                “You shall love the Lord your God with all your heart and with all your soul and with all your mind. This is the great and first commandment. And a second is like it: You shall love your neighbor as yourself.”
                <br>
                <span class="block text-sm text-gray-500">Matthew 22:37–39 (ESV)</span>
            </blockquote>

            <h2>Honor God</h2>
            <ul>
                <li>No using God's name in an inappropriate manner</li>
                <li>No sharing images that depict God or Jesus in a disrespectful way</li>
                <li>No promoting or encouraging any sinful lifestyle (as defined by a traditional view of Scripture)</li>
            </ul>

            <h2>Be Respectful of Others</h2>
            <ul>
                <li>No stealing, griefing, insulting, slandering, or gossiping</li>
            </ul>

            <h2>Keep Language Clean</h2>
            <ul>
                <li>No cursing or using acronyms for cursing</li>
                <li>No filthy joking or promoting sinful behavior</li>
            </ul>

            <h2>Keep Sharing Clean</h2>
            <ul>
                <li>No NSFW (Not Safe For Work) images or content</li>
            </ul>

            <h2>No Spamming</h2>
            <ul>
                <li>No ALL CAPS messages or repeating phrases</li>
                <li>No begging others for items or privileges</li>
            </ul>

            <h2>No Talk About Self-Harm</h2>
            <ul>
                <li>No discussion of suicide, cutting, or harmful behaviors</li>
                <li>Do not encourage others to harm themselves</li>
            </ul>

            <h2>When Sharing Links</h2>
            <ul>
                <li>No advertising or promoting other Minecraft or Discord servers</li>
                <li>No videos/music with inappropriate images or language</li>
                <li>No sermons or teachings from false teachers (e.g. Bethel, prosperity gospel, "Name it and claim it")</li>
            </ul>

            <h2>Moderation</h2>
            <ul>
                <li>Do not argue publicly with moderator decisions</li>
                <li>If you disagree, contact the officers privately</li>
            </ul>

            <p class="italic text-gray-500">The Officers reserve the right to remove individuals from the community that we feel are harmful or a bad influence on members of Lighthouse.</p>

            <h2>In-Game Conduct</h2>
            <p>This is a Christ-Centered, Family-Friendly, Modded Survival Server. As such…</p>
            <ul>
                <li>No begging for handouts</li>
                <li>No stealing from others’ chests or property</li>
                <li>No auto clickers or auto aiming</li>
                <li>No XRay mods or exploits</li>
                <li>Keep farms server-friendly (turn off when not in use, avoid hopper/entity lag)</li>
                <li>Only PvP with willing participants</li>
                <li>No tunnel bores or mass destruction</li>
                <li>No using other people’s farms without permission</li>
            </ul>

            <h2>Duping Rules</h2>
            <p>Allowed duped items:</p>
            <ul>
                <li>Carpet and TNT when used in a farm that requires it</li>
                <li>Sticks and String are allowed</li>
            </ul>
        </div>
        <div class="w-full text-right">
            @if (!auth()->user()->rules_accepted_at || auth()->user()->isLevel(MembershipLevel::Drifter))
                <flux:button color="amber" wire:click="acceptRules" variant="primary">I Have Read the Rules and Agree to Follow Them</flux:button>
            @endif
        </div>
    </flux:modal>
</div>
