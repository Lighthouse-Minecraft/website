<?php

namespace App\Actions;

use App\Models\RuleCategory;
use App\Models\RuleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRulesAgreementStatus
{
    use AsAction;

    /**
     * Return whether the user has agreed to the current published version, and
     * classify each rule in that version as 'new', 'updated', or 'unchanged'
     * relative to the versions the user has previously agreed to.
     *
     * Returns an array with keys:
     *   - has_agreed (bool)
     *   - current_version (RuleVersion|null)
     *   - categories (Collection of RuleCategory, each with ->rules bearing ->agreement_status and ->previous_rule)
     */
    public function handle(User $user): array
    {
        $currentVersion = RuleVersion::currentPublished();

        if (! $currentVersion) {
            return [
                'has_agreed' => true,
                'current_version' => null,
                'categories' => collect(),
            ];
        }

        $hasAgreed = $user->ruleAgreements()->where('rule_version_id', $currentVersion->id)->exists();

        // Rule IDs across ALL versions the user has agreed to
        $agreedVersionIds = $user->ruleAgreements()->pluck('rule_version_id');
        $agreedRuleIds = DB::table('rule_version_rules')
            ->whereIn('rule_version_id', $agreedVersionIds)
            ->where('deactivate_on_publish', false)
            ->pluck('rule_id')
            ->flip()
            ->all(); // keyed lookup

        // Rule IDs in the user's most-recently agreed version
        $lastAgreement = $user->ruleAgreements()->orderByDesc('agreed_at')->first();
        $lastVersionRuleIds = [];
        if ($lastAgreement) {
            $lastVersionRuleIds = DB::table('rule_version_rules')
                ->where('rule_version_id', $lastAgreement->rule_version_id)
                ->where('deactivate_on_publish', false)
                ->pluck('rule_id')
                ->flip()
                ->all();
        }

        // Active rule IDs in the current version (deactivate_on_publish = false)
        $currentRuleIds = DB::table('rule_version_rules')
            ->where('rule_version_id', $currentVersion->id)
            ->where('deactivate_on_publish', false)
            ->pluck('rule_id')
            ->flip()
            ->all();

        $categories = RuleCategory::with(['rules.supersedes'])
            ->orderBy('sort_order')
            ->get()
            ->map(function (RuleCategory $category) use ($currentRuleIds, $agreedRuleIds, $lastVersionRuleIds, $lastAgreement) {
                $category->setRelation('rules', $category->rules->filter(function ($rule) use ($currentRuleIds) {
                    return isset($currentRuleIds[$rule->id]);
                })->values()->map(function ($rule) use ($agreedRuleIds, $lastVersionRuleIds, $lastAgreement) {
                    if (! $lastAgreement) {
                        // First-time user: nothing is "new" or "updated" — all rules are simply the rules
                        $rule->agreement_status = 'unchanged';
                        $rule->previous_rule = null;
                    } elseif (isset($agreedRuleIds[$rule->id])) {
                        $rule->agreement_status = 'unchanged';
                        $rule->previous_rule = null;
                    } elseif ($rule->supersedes_rule_id && isset($lastVersionRuleIds[$rule->supersedes_rule_id])) {
                        $rule->agreement_status = 'updated';
                        $rule->previous_rule = $rule->supersedes;
                    } else {
                        $rule->agreement_status = 'new';
                        $rule->previous_rule = null;
                    }

                    return $rule;
                }));

                return $category;
            })
            ->filter(fn (RuleCategory $cat) => $cat->rules->isNotEmpty())
            ->values();

        return [
            'has_agreed' => $hasAgreed,
            'current_version' => $currentVersion,
            'categories' => $categories,
        ];
    }
}
