<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $roles = [
        [
            'name' => 'Staff Access',
            'description' => 'View ACP, ready room, staff docs, internal notes, discipline reports, meetings, and edit own staff bio',
            'color' => 'sky',
            'icon' => 'identification',
        ],
        [
            'name' => 'Ticket - User',
            'description' => 'Create tickets, respond, close, reopen, and view archived tickets',
            'color' => 'blue',
            'icon' => 'ticket',
        ],
        [
            'name' => 'Ticket - Manager',
            'description' => 'Archive and delete tickets in addition to all Ticket - User capabilities',
            'color' => 'blue',
            'icon' => 'inbox-stack',
        ],
        [
            'name' => 'Task - Department',
            'description' => 'Create and edit tasks for own department',
            'color' => 'green',
            'icon' => 'clipboard-document-list',
        ],
        [
            'name' => 'Task - Manager',
            'description' => 'Create and edit tasks for any department',
            'color' => 'green',
            'icon' => 'clipboard-document-check',
        ],
        [
            'name' => 'Meeting - Department',
            'description' => 'Edit meeting notes for own department only',
            'color' => 'violet',
            'icon' => 'document-text',
        ],
        [
            'name' => 'Meeting - Secretary',
            'description' => 'Edit meeting notes for all departments',
            'color' => 'violet',
            'icon' => 'pencil-square',
        ],
        [
            'name' => 'Internal Note - Manager',
            'description' => 'Add internal notes to threads',
            'color' => 'amber',
            'icon' => 'document-plus',
        ],
        [
            'name' => 'Discipline Report - Publisher',
            'description' => 'Publish and finalize discipline reports',
            'color' => 'red',
            'icon' => 'clipboard-document-check',
        ],
        [
            'name' => 'Applicant Review - Department',
            'description' => 'Review staff applications for own department',
            'color' => 'teal',
            'icon' => 'user-group',
        ],
        [
            'name' => 'Applicant Review - All',
            'description' => 'Review staff applications for every department',
            'color' => 'teal',
            'icon' => 'users',
        ],
        [
            'name' => 'Officer Docs - Viewer',
            'description' => 'Access officer-level documentation',
            'color' => 'emerald',
            'icon' => 'book-open',
        ],
    ];

    public function up(): void
    {
        foreach ($this->roles as $role) {
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

    public function down(): void
    {
        // Don't delete roles that existed before this migration (renamed from previous names)
        $preExisting = ['Staff Access', 'Discipline Report - Publisher'];
        $toDelete = array_diff(array_column($this->roles, 'name'), $preExisting);

        DB::table('roles')->whereIn('name', $toDelete)->delete();
    }
};
