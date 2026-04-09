<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $now = now();

            // --- Seed rule categories and rules ---

            $categories = [
                [
                    'name' => 'Honor God',
                    'sort_order' => 1,
                    'rules' => [
                        ['title' => 'No Inappropriate Use of God\'s Name', 'description' => 'Do not use God\'s name in an inappropriate manner.'],
                        ['title' => 'No Disrespectful Depictions of God or Jesus', 'description' => 'Do not share images that depict God or Jesus in a disrespectful way.'],
                        ['title' => 'No Promotion of Sinful Lifestyles', 'description' => 'Do not promote or encourage any sinful lifestyle (as defined by a traditional view of Scripture).'],
                    ],
                ],
                [
                    'name' => 'Be Respectful of Others',
                    'sort_order' => 2,
                    'rules' => [
                        ['title' => 'No Disrespectful Behavior', 'description' => 'Do not steal, grief, insult, slander, or gossip.'],
                    ],
                ],
                [
                    'name' => 'Keep Language Clean',
                    'sort_order' => 3,
                    'rules' => [
                        ['title' => 'No Cursing', 'description' => 'Do not curse or use acronyms for cursing.'],
                        ['title' => 'No Filthy Joking', 'description' => 'Do not make filthy jokes or promote sinful behavior.'],
                    ],
                ],
                [
                    'name' => 'Keep Sharing Clean',
                    'sort_order' => 4,
                    'rules' => [
                        ['title' => 'No NSFW Content', 'description' => 'Do not share NSFW (Not Safe For Work) images or content.'],
                    ],
                ],
                [
                    'name' => 'No Spamming',
                    'sort_order' => 5,
                    'rules' => [
                        ['title' => 'No ALL CAPS or Repeating Messages', 'description' => 'Do not send ALL CAPS messages or repeat phrases.'],
                        ['title' => 'No Begging for Items or Privileges', 'description' => 'Do not beg others for items or privileges.'],
                    ],
                ],
                [
                    'name' => 'No Talk About Self-Harm',
                    'sort_order' => 6,
                    'rules' => [
                        ['title' => 'No Discussion of Self-Harm', 'description' => 'Do not discuss suicide, cutting, or other harmful behaviors.'],
                        ['title' => 'No Encouraging Self-Harm', 'description' => 'Do not encourage others to harm themselves.'],
                    ],
                ],
                [
                    'name' => 'When Sharing Links',
                    'sort_order' => 7,
                    'rules' => [
                        ['title' => 'No Advertising Other Servers', 'description' => 'Do not advertise or promote other Minecraft or Discord servers.'],
                        ['title' => 'No Inappropriate Media', 'description' => 'Do not share videos or music with inappropriate images or language.'],
                        ['title' => 'No False Teaching', 'description' => 'Do not share sermons or teachings from false teachers (e.g. Bethel, prosperity gospel, "Name it and claim it").'],
                    ],
                ],
                [
                    'name' => 'Moderation',
                    'sort_order' => 8,
                    'rules' => [
                        ['title' => 'No Public Disputes with Staff', 'description' => 'Do not argue publicly with moderator decisions.'],
                        ['title' => 'Disagreements Go Through Officers', 'description' => 'If you disagree with a decision, contact the officers privately.'],
                    ],
                ],
                [
                    'name' => 'In-Game Conduct',
                    'sort_order' => 9,
                    'rules' => [
                        ['title' => 'No Begging for Handouts', 'description' => 'Do not beg for handouts in-game.'],
                        ['title' => 'No Stealing', 'description' => 'Do not steal from others\' chests or property.'],
                        ['title' => 'No Auto Clickers or Auto Aiming', 'description' => 'Do not use auto clickers or auto aiming tools.'],
                        ['title' => 'No XRay Mods or Exploits', 'description' => 'Do not use XRay mods or exploits.'],
                        ['title' => 'Keep Farms Server-Friendly', 'description' => 'Keep farms server-friendly: turn them off when not in use, and avoid hopper and entity lag.'],
                        ['title' => 'No Unsolicited PvP', 'description' => 'Only engage in PvP with willing participants.'],
                        ['title' => 'No Mass Destruction', 'description' => 'Do not use tunnel bores or cause mass destruction.'],
                        ['title' => 'No Using Others\' Farms Without Permission', 'description' => 'Do not use other players\' farms without permission.'],
                    ],
                ],
                [
                    'name' => 'Duping Rules',
                    'sort_order' => 10,
                    'rules' => [
                        ['title' => 'Allowed Duping: Carpet and TNT', 'description' => 'Carpet and TNT duplication is allowed when used in a farm that requires it.'],
                        ['title' => 'Allowed Duping: Sticks and String', 'description' => 'Sticks and String duplication is allowed.'],
                    ],
                ],
            ];

            $allRuleIds = [];

            foreach ($categories as $categoryData) {
                $categoryId = DB::table('rule_categories')->insertGetId([
                    'name' => $categoryData['name'],
                    'sort_order' => $categoryData['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($categoryData['rules'] as $ruleData) {
                    $ruleId = DB::table('rules')->insertGetId([
                        'rule_category_id' => $categoryId,
                        'title' => $ruleData['title'],
                        'description' => $ruleData['description'],
                        'status' => 'active',
                        'supersedes_rule_id' => null,
                        'created_by_user_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $allRuleIds[] = $ruleId;
                }
            }

            // --- Seed SiteConfig keys for rules header and footer ---

            $rulesHeader = <<<'MARKDOWN'
> "Love one another with brotherly affection. Outdo one another in showing honor."
> — Romans 12:10 (ESV)

> "You shall love the Lord your God with all your heart and with all your soul and with all your mind. This is the great and first commandment. And a second is like it: You shall love your neighbor as yourself."
> — Matthew 22:37–39 (ESV)
MARKDOWN;

            $rulesFooter = 'The Officers reserve the right to remove individuals from the community that we feel are harmful or a bad influence on members of Lighthouse.';

            DB::table('site_configs')->insertOrIgnore([
                'key' => 'rules_header',
                'value' => $rulesHeader,
                'description' => 'Markdown content displayed above the community rules (e.g. scripture quotes).',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('site_configs')->insertOrIgnore([
                'key' => 'rules_footer',
                'value' => $rulesFooter,
                'description' => 'Markdown content displayed below the community rules (e.g. officer discretion disclaimer).',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // --- Create version 1 as published ---

            $versionId = DB::table('rule_versions')->insertGetId([
                'version_number' => 1,
                'status' => 'published',
                'created_by_user_id' => null,
                'approved_by_user_id' => null,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($allRuleIds as $ruleId) {
                DB::table('rule_version_rules')->insert([
                    'rule_version_id' => $versionId,
                    'rule_id' => $ruleId,
                ]);
            }

            // --- Migrate existing user agreements to version 1 ---

            $usersWithAgreement = DB::table('users')
                ->whereNotNull('rules_accepted_at')
                ->select('id', 'rules_accepted_at')
                ->get();

            foreach ($usersWithAgreement as $user) {
                DB::table('user_rule_agreements')->insertOrIgnore([
                    'user_id' => $user->id,
                    'rule_version_id' => $versionId,
                    'agreed_at' => $user->rules_accepted_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }); // end DB::transaction
    }

    public function down(): void
    {
        $migrationDate = '2026-04-09 00:00:00';

        DB::table('user_rule_agreements')
            ->where('created_at', '>=', $migrationDate)
            ->delete();

        $versionId = DB::table('rule_versions')->where('version_number', 1)->value('id');
        if ($versionId) {
            DB::table('rule_version_rules')->where('rule_version_id', $versionId)->delete();
            DB::table('rule_versions')->where('id', $versionId)->delete();
        }

        DB::table('site_configs')
            ->whereIn('key', ['rules_header', 'rules_footer'])
            ->where('created_at', '>=', $migrationDate)
            ->delete();

        DB::table('rules')->where('created_at', '>=', $migrationDate)->delete();
        DB::table('rule_categories')->where('created_at', '>=', $migrationDate)->delete();
    }
};
