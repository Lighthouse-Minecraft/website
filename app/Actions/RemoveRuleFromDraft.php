<?php

namespace App\Actions;

use App\Models\Rule;
use App\Models\RuleVersion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveRuleFromDraft
{
    use AsAction;

    /**
     * Remove a draft-status rule from the draft version, deleting the rule entirely.
     *
     * If the rule is a replacement (supersedes_rule_id is set), also removes the
     * deactivation mark on the superseded rule so it remains active on publish.
     */
    public function handle(RuleVersion $draft, Rule $rule): void
    {
        if ($draft->status !== 'draft') {
            throw new AuthorizationException('Rules can only be removed from draft versions.');
        }

        if ($rule->status !== 'draft') {
            throw new AuthorizationException('Only draft rules can be removed. Use DeactivateRuleInDraft for active rules.');
        }

        DB::transaction(function () use ($draft, $rule): void {
            $title = $rule->title;

            // If this rule supersedes another (it's a replacement), remove the
            // deactivation mark on the original rule so it stays active.
            if ($rule->supersedes_rule_id) {
                $draft->rules()->detach($rule->supersedes_rule_id);
            }

            // Detach this draft rule from the version pivot and delete it.
            $draft->rules()->detach($rule->id);
            $rule->delete();

            RecordActivity::run($draft, 'rule_removed_from_draft', "Draft rule \"{$title}\" removed from draft version.");
        });
    }
}
