<?php

namespace App\Actions;

use App\Models\Rule;
use App\Models\RuleCategory;
use App\Models\RuleVersion;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateRuleInDraft
{
    use AsAction;

    /**
     * Replace an existing rule in a draft version.
     *
     * Creates a replacement rule (with supersedes_rule_id pointing to the old rule),
     * adds it to the draft, and marks the old rule for deactivation on publish.
     */
    public function handle(
        RuleVersion $draft,
        Rule $oldRule,
        string $newTitle,
        string $newDescription,
        User $createdBy,
        ?RuleCategory $newCategory = null
    ): Rule {
        $replacement = Rule::create([
            'rule_category_id' => $newCategory?->id ?? $oldRule->rule_category_id,
            'title' => $newTitle,
            'description' => $newDescription,
            'status' => 'draft',
            'supersedes_rule_id' => $oldRule->id,
            'created_by_user_id' => $createdBy->id,
        ]);

        // Add the replacement rule to the draft as "activate on publish"
        $draft->rules()->attach($replacement->id, ['deactivate_on_publish' => false]);

        // Mark the old rule for deactivation on publish (it may or may not be in the version already)
        if ($draft->rules()->where('rules.id', $oldRule->id)->exists()) {
            $draft->rules()->updateExistingPivot($oldRule->id, ['deactivate_on_publish' => true]);
        } else {
            $draft->rules()->attach($oldRule->id, ['deactivate_on_publish' => true]);
        }

        return $replacement;
    }
}
