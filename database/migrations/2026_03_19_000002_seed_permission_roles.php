<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roles = [
            [
                'name' => 'Page Editor',
                'description' => 'Edit and manage website content pages',
                'color' => 'purple',
                'icon' => 'newspaper',
            ],
            [
                'name' => 'Moderator',
                'description' => 'View flagged messages, moderator powers in Discussions, lock topics',
                'color' => 'red',
                'icon' => 'shield-exclamation',
            ],
            [
                'name' => 'Brig Warden',
                'description' => 'Handle brig appeals, release users from the brig',
                'color' => 'orange',
                'icon' => 'lock-closed',
            ],
            [
                'name' => 'Announcement Editor',
                'description' => 'Create, edit, and delete announcements',
                'color' => 'cyan',
                'icon' => 'megaphone',
            ],
            [
                'name' => 'Manage Membership Level',
                'description' => 'Promote and demote users through membership levels',
                'color' => 'emerald',
                'icon' => 'arrow-trending-up',
            ],
            [
                'name' => 'Manage Staff Meeting',
                'description' => 'Write access to staff meetings',
                'color' => 'blue',
                'icon' => 'pencil-square',
            ],
            [
                'name' => 'Manage Community Stories',
                'description' => 'Manage community questions and responses',
                'color' => 'pink',
                'icon' => 'chat-bubble-left-right',
            ],
            [
                'name' => 'Manage Discipline Reports',
                'description' => 'Create and manage discipline reports',
                'color' => 'yellow',
                'icon' => 'clipboard-document-list',
            ],
            [
                'name' => 'Publish Discipline Reports',
                'description' => 'Publish and finalize discipline reports',
                'color' => 'red',
                'icon' => 'clipboard-document-check',
            ],
            [
                'name' => 'Manage Site Config',
                'description' => 'Manage site configuration and application questions',
                'color' => 'slate',
                'icon' => 'cog-6-tooth',
            ],
            [
                'name' => 'View Logs',
                'description' => 'Access MC command log, Discord API log, and activity log',
                'color' => 'zinc',
                'icon' => 'document-magnifying-glass',
            ],
            [
                'name' => 'View All Ready Rooms',
                'description' => 'See all department ready rooms',
                'color' => 'teal',
                'icon' => 'building-office',
            ],
            [
                'name' => 'User Manager',
                'description' => 'View and edit users, manage MC and Discord accounts in the ACP',
                'color' => 'lime',
                'icon' => 'users',
            ],
            [
                'name' => 'View PII',
                'description' => 'View personally identifiable information such as email addresses and dates of birth',
                'color' => 'amber',
                'icon' => 'eye',
            ],
            [
                'name' => 'View Command Dashboard',
                'description' => 'Access the Command dashboard',
                'color' => 'indigo',
                'icon' => 'chart-bar',
            ],
        ];

        foreach ($roles as $role) {
            $exists = DB::table('roles')->where('name', $role['name'])->exists();

            if ($exists) {
                DB::table('roles')->where('name', $role['name'])->update([
                    'description' => $role['description'],
                    'color' => $role['color'],
                    'icon' => $role['icon'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('roles')->insert([
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'color' => $role['color'],
                    'icon' => $role['icon'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->whereIn('name', [
            'Announcement Editor',
            'Page Editor',
            'Moderator',
            'Brig Warden',
            'Manage Membership Level',
            'Manage Staff Meeting',
            'Manage Community Stories',
            'Manage Discipline Reports',
            'Publish Discipline Reports',
            'Manage Site Config',
            'View Logs',
            'User Manager',
            'View PII',
            'View All Ready Rooms',
            'View Command Dashboard',
        ])->delete();
    }
};
