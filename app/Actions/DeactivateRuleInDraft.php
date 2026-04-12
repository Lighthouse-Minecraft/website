<?php

namespace App\Actions;

use App\Models\Rule;
use App\Models\RuleVersion;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

class DeactivateRuleInDraft
{
    use AsAction;

    /**
     * Mark a rule for deactivation when this draft version is published.
     *
     * Does not immediately deactivate the rule — deactivation happens when the version publishes.
     */
    public function handle(RuleVersion $draft, Rule $rule): void
    {
        if ($draft->status !== 'draft') {
            throw new AuthorizationException('Rules can only be deactivated in draft versions.');
        }

        if ($draft->rules()->where('rules.id', $rule->id)->exists()) {
            $draft->rules()->updateExistingPivot($rule->id, ['deactivate_on_publish' => true]);
        } else {
            $draft->rules()->attach($rule->id, ['deactivate_on_publish' => true]);
        }

        RecordActivity::run($rule, 'rule_marked_for_deactivation', "Rule \"{$rule->title}\" marked for deactivation on publish.");
    }
}
