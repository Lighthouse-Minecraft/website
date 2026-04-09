<?php

namespace App\Actions;

use App\Models\RuleVersion;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateRuleVersion
{
    use AsAction;

    /**
     * Create a new draft version, seeded with all currently active rules from the latest published version.
     */
    public function handle(User $createdBy): RuleVersion
    {
        $published = RuleVersion::currentPublished();
        $nextNumber = $published ? $published->version_number + 1 : 1;

        $draft = RuleVersion::create([
            'version_number' => $nextNumber,
            'status' => 'draft',
            'created_by_user_id' => $createdBy->id,
        ]);

        // Seed draft with all currently active rules from the published version
        if ($published) {
            $activeRuleIds = $published->activeRules()->pluck('rules.id');
            $attach = $activeRuleIds->mapWithKeys(fn ($id) => [$id => ['deactivate_on_publish' => false]]);
            $draft->rules()->attach($attach->all());
        }

        return $draft;
    }
}
