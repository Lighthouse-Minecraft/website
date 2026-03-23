<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $renames = [
        'Page Editor' => 'Page - Editor',
        'Announcement Editor' => 'Announcement - Editor',
        'Manage Membership Level' => 'Membership Level - Manager',
        'Manage Community Stories' => 'Community Stories - Manager',
        'Manage Discipline Reports' => 'Discipline Report - Manager',
        'Publish Discipline Reports' => 'Discipline Report - Publisher',
        'Manage Site Config' => 'Site Config - Manager',
        'Manage Staff Meeting' => 'Meeting - Manager',
        'View Logs' => 'Logs - Viewer',
        'View All Ready Rooms' => 'Ready Room - View All',
        'User Manager' => 'User - Manager',
        'View PII' => 'PII - Viewer',
        'View Command Dashboard' => 'Command Dashboard - Viewer',
        'Blog Author' => 'Blog - Author',
    ];

    public function up(): void
    {
        foreach ($this->renames as $oldName => $newName) {
            DB::table('roles')
                ->where('name', $oldName)
                ->update(['name' => $newName, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach ($this->renames as $oldName => $newName) {
            DB::table('roles')
                ->where('name', $newName)
                ->update(['name' => $oldName, 'updated_at' => now()]);
        }
    }
};
