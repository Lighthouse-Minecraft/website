<?php

namespace App\Actions;

use App\Models\Rule;
use App\Models\RuleCategory;
use App\Models\RuleVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Lorisleiva\Actions\Concerns\AsAction;

class AddRuleToDraft
{
    use AsAction;

    /**
     * Create a new rule in draft status and link it to the given draft version.
     */
    public function handle(
        RuleVersion $draft,
        RuleCategory $category,
        string $title,
        string $description,
        User $createdBy
    ): Rule {
        if ($draft->status !== 'draft') {
            throw new AuthorizationException('Rules can only be added to draft versions.');
        }

        $rule = Rule::create([
            'rule_category_id' => $category->id,
            'title' => $title,
            'description' => $description,
            'status' => 'draft',
            'created_by_user_id' => $createdBy->id,
        ]);

        $draft->rules()->attach($rule->id, ['deactivate_on_publish' => false]);

        RecordActivity::run($rule, 'rule_added_to_draft', "Rule \"{$rule->title}\" added to draft version.");

        return $rule;
    }
}
