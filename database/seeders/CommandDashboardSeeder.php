<?php

namespace Database\Seeders;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\MembershipLevel;
use App\Enums\MinecraftAccountStatus;
use App\Enums\ReportStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Enums\TaskStatus;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\DisciplineReport;
use App\Models\DiscordAccount;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MinecraftAccount;
use App\Models\Task;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class CommandDashboardSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('This seeder cannot run in production!');

            return;
        }

        $this->command->info('Seeding Command Dashboard demo data...');

        Cache::flush();

        // ── Staff Members ──────────────────────────────────────────────
        $this->command->info('  Creating staff members...');

        $staff = [];
        $departments = StaffDepartment::cases();

        // One officer per department
        foreach ($departments as $dept) {
            $staff[] = User::factory()->create([
                'name' => $dept->label().' Officer',
                'email' => 'officer-'.$dept->value.'@seed.test',
                'staff_department' => $dept,
                'staff_rank' => StaffRank::Officer,
                'membership_level' => MembershipLevel::Citizen,
                'last_login_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
            ]);
        }

        // Two crew members per department
        foreach ($departments as $dept) {
            for ($i = 1; $i <= 2; $i++) {
                $staff[] = User::factory()->create([
                    'name' => $dept->label()." Crew $i",
                    'email' => "crew-{$dept->value}-{$i}@seed.test",
                    'staff_department' => $dept,
                    'staff_rank' => StaffRank::CrewMember,
                    'membership_level' => MembershipLevel::Citizen,
                    'last_login_at' => fake()->dateTimeBetween('-3 weeks', 'now'),
                ]);
            }
        }

        // One JrCrew per department
        foreach ($departments as $dept) {
            $staff[] = User::factory()->create([
                'name' => $dept->label().' JrCrew',
                'email' => 'jrcrew-'.$dept->value.'@seed.test',
                'staff_department' => $dept,
                'staff_rank' => StaffRank::JrCrew,
                'membership_level' => MembershipLevel::Resident,
                'last_login_at' => fake()->dateTimeBetween('-4 weeks', 'now'),
            ]);
        }

        // ── Completed Staff Meetings (iteration boundaries) ────────────
        $this->command->info('  Creating meetings (6 iterations over 3 months)...');

        $meetings = [];
        // Create 6 completed meetings, roughly biweekly going back 3 months
        $meetingDaysAgo = [84, 70, 56, 42, 28, 14];
        foreach ($meetingDaysAgo as $daysAgo) {
            $meetings[] = Meeting::factory()->create([
                'title' => 'Staff Meeting - '.now()->subDays($daysAgo)->format('M j'),
                'type' => MeetingType::StaffMeeting,
                'status' => MeetingStatus::Completed,
                'scheduled_time' => now()->subDays($daysAgo)->setTime(19, 0),
                'start_time' => now()->subDays($daysAgo)->setTime(19, 0),
                'end_time' => now()->subDays($daysAgo)->setTime(20, 30),
                'day' => now()->subDays($daysAgo)->format('Y-m-d'),
            ]);
        }

        // One pending upcoming meeting (next iteration's meeting)
        Meeting::factory()->create([
            'title' => 'Staff Meeting - '.now()->addDays(3)->format('M j'),
            'type' => MeetingType::StaffMeeting,
            'status' => MeetingStatus::Pending,
            'scheduled_time' => now()->addDays(3)->setTime(19, 0),
            'day' => now()->addDays(3)->format('Y-m-d'),
        ]);

        // ── Meeting Attendance ─────────────────────────────────────────
        $this->command->info('  Seeding meeting attendance...');

        foreach ($meetings as $meetingIndex => $meeting) {
            foreach ($staff as $member) {
                // Officers and crew attend most meetings, JrCrew less often
                $attendChance = match (true) {
                    $member->staff_rank === StaffRank::Officer => 90,
                    $member->staff_rank === StaffRank::CrewMember => 75,
                    default => 50,
                };

                if (fake()->numberBetween(1, 100) <= $attendChance) {
                    $meeting->attendees()->attach($member->id, [
                        'added_at' => $meeting->start_time,
                    ]);
                }
            }
        }

        // ── Meeting Reports ────────────────────────────────────────────
        $this->command->info('  Seeding staff reports...');

        foreach ($meetings as $meeting) {
            foreach ($staff as $member) {
                $submitChance = match (true) {
                    $member->staff_rank === StaffRank::Officer => 95,
                    $member->staff_rank === StaffRank::CrewMember => 80,
                    default => 60,
                };

                MeetingReport::create([
                    'meeting_id' => $meeting->id,
                    'user_id' => $member->id,
                    'submitted_at' => fake()->numberBetween(1, 100) <= $submitChance
                        ? $meeting->scheduled_time->copy()->subDays(fake()->numberBetween(1, 5))
                        : null,
                ]);
            }
        }

        // ── Community Users (created across iterations) ────────────────
        $this->command->info('  Creating community users across iterations...');

        // Varying user growth per iteration
        $usersPerIteration = [3, 5, 8, 12, 7, 10, 15]; // growing trend with variance
        $iterationStarts = [84, 70, 56, 42, 28, 14, 0];

        for ($i = 0; $i < count($usersPerIteration); $i++) {
            // Non-overlapping ranges: each cohort occupies its own day interval
            $max = $iterationStarts[$i];
            $min = ($i === count($usersPerIteration) - 1) ? 0 : $iterationStarts[$i + 1] + 1;

            for ($j = 0; $j < $usersPerIteration[$i]; $j++) {
                $daysAgo = fake()->numberBetween($min, $max);
                $user = User::factory()->create([
                    'created_at' => now()->subDays($daysAgo),
                    'membership_level' => fake()->randomElement([
                        MembershipLevel::Stowaway,
                        MembershipLevel::Traveler,
                        MembershipLevel::Resident,
                    ]),
                    'last_login_at' => fake()->boolean(70) ? now()->subDays(fake()->numberBetween(0, 14)) : null,
                ]);

                // ~60% link a Minecraft account
                if (fake()->boolean(60)) {
                    MinecraftAccount::factory()->create([
                        'user_id' => $user->id,
                        'status' => MinecraftAccountStatus::Active,
                        'created_at' => $user->created_at->addHours(fake()->numberBetween(1, 48)),
                    ]);
                }

                // ~30% link Discord
                if (fake()->boolean(30)) {
                    DiscordAccount::factory()->create([
                        'user_id' => $user->id,
                        'created_at' => $user->created_at->addHours(fake()->numberBetween(1, 72)),
                    ]);
                }
            }
        }

        // A few accounts stuck in pending verification
        $this->command->info('  Creating pending MC verifications...');
        for ($i = 0; $i < 4; $i++) {
            $user = User::factory()->create([
                'created_at' => now()->subDays(fake()->numberBetween(1, 10)),
                'membership_level' => MembershipLevel::Stowaway,
            ]);
            MinecraftAccount::factory()->verifying()->create([
                'user_id' => $user->id,
                'created_at' => $user->created_at->addMinutes(30),
            ]);
        }

        // ── Tickets (per department, across iterations) ────────────────
        $this->command->info('  Creating tickets...');

        $ticketConfigs = [
            // [department, count_per_iteration_range, close_pct]
            [StaffDepartment::Command, [0, 2], 80],
            [StaffDepartment::Chaplain, [0, 1], 90],
            [StaffDepartment::Engineer, [1, 4], 60],
            [StaffDepartment::Quartermaster, [2, 6], 70],
            [StaffDepartment::Steward, [1, 3], 75],
        ];

        foreach ($ticketConfigs as [$dept, $countRange, $closePct]) {
            for ($iter = 0; $iter < 7; $iter++) {
                // Non-overlapping windows between meetings (values are "days ago")
                $max = $iter === 0 ? $meetingDaysAgo[0] + 14 : $meetingDaysAgo[$iter - 1] - 1;
                $min = $iter === 6 ? 0 : $meetingDaysAgo[$iter] + 1;

                $count = fake()->numberBetween($countRange[0], $countRange[1]);
                for ($j = 0; $j < $count; $j++) {
                    $daysAgo = fake()->numberBetween($min, $max);
                    $createdAt = now()->subDays($daysAgo);
                    $isClosed = fake()->numberBetween(1, 100) <= $closePct;

                    Thread::factory()->create([
                        'type' => ThreadType::Ticket,
                        'department' => $dept,
                        'status' => $isClosed ? ThreadStatus::Closed : fake()->randomElement([ThreadStatus::Open, ThreadStatus::Pending]),
                        'created_at' => $createdAt,
                        'updated_at' => $isClosed
                            ? $createdAt->copy()->addDays(fake()->numberBetween(1, 5))
                            : $createdAt,
                    ]);
                }
            }
        }

        // ── Tasks / Todos (per department, assigned to staff) ──────────
        $this->command->info('  Creating tasks/todos...');

        foreach ($departments as $dept) {
            $deptStaff = collect($staff)->filter(fn ($s) => $s->staff_department === $dept);

            for ($iter = 0; $iter < 7; $iter++) {
                // Non-overlapping windows between meetings (values are "days ago")
                $max = $iter === 0 ? $meetingDaysAgo[0] + 14 : $meetingDaysAgo[$iter - 1] - 1;
                $min = $iter === 6 ? 0 : $meetingDaysAgo[$iter] + 1;

                $taskCount = fake()->numberBetween(2, 6);
                for ($j = 0; $j < $taskCount; $j++) {
                    $daysAgo = fake()->numberBetween($min, $max);
                    $createdAt = now()->subDays($daysAgo);
                    $assignee = $deptStaff->random();
                    $creator = $deptStaff->random();

                    $isCompleted = fake()->boolean(65);

                    $meeting = $iter < 6 ? $meetings[$iter] : null;

                    Task::factory()->create([
                        'name' => fake()->sentence(4),
                        'section_key' => $dept->value,
                        'assigned_to_user_id' => $assignee->id,
                        'created_by' => $creator->id,
                        'assigned_meeting_id' => $meeting?->id,
                        'status' => $isCompleted ? TaskStatus::Completed : fake()->randomElement([TaskStatus::Pending, TaskStatus::InProgress]),
                        'completed_at' => $isCompleted ? $createdAt->copy()->addDays(fake()->numberBetween(1, 10)) : null,
                        'completed_by' => $isCompleted ? $assignee->id : null,
                        'completed_meeting_id' => $isCompleted && $meeting ? $meeting->id : null,
                        'created_at' => $createdAt,
                    ]);
                }
            }
        }

        // ── Discipline Reports ─────────────────────────────────────────
        $this->command->info('  Creating discipline reports...');

        $communityUsers = User::where('staff_rank', StaffRank::None)->inRandomOrder()->limit(10)->get();
        $reporters = collect($staff)->filter(fn ($s) => $s->staff_rank->value >= StaffRank::CrewMember->value);

        // Published reports across iterations
        for ($i = 0; $i < 12; $i++) {
            $daysAgo = fake()->numberBetween(0, 84);
            $subject = $communityUsers->isNotEmpty() ? $communityUsers->random() : User::factory()->create();
            $reporter = $reporters->random();

            DisciplineReport::factory()->published()->create([
                'subject_user_id' => $subject->id,
                'reporter_user_id' => $reporter->id,
                'published_at' => now()->subDays($daysAgo),
                'created_at' => now()->subDays($daysAgo + fake()->numberBetween(0, 2)),
            ]);
        }

        // A few draft reports
        for ($i = 0; $i < 3; $i++) {
            $subject = $communityUsers->isNotEmpty() ? $communityUsers->random() : User::factory()->create();
            $reporter = $reporters->random();

            DisciplineReport::factory()->create([
                'subject_user_id' => $subject->id,
                'reporter_user_id' => $reporter->id,
                'status' => ReportStatus::Draft,
                'created_at' => now()->subDays(fake()->numberBetween(0, 7)),
            ]);
        }

        // ── Additional active users (for the "active users" metric) ────
        $this->command->info('  Setting login activity...');

        // Make some existing users have recent logins
        User::whereNull('last_login_at')
            ->inRandomOrder()
            ->limit(20)
            ->get()
            ->each(function ($user) {
                $user->update(['last_login_at' => now()->subDays(fake()->numberBetween(0, 30))]);
            });

        Cache::flush();

        $this->command->info('Command Dashboard demo data seeded successfully!');
        $this->command->info('  Staff: '.count($staff).' members across '.count($departments).' departments');
        $this->command->info('  Meetings: '.count($meetings).' completed + 1 upcoming');
        $this->command->info('  Login as any @seed.test user (e.g. officer-command@seed.test) to view the dashboard.');
    }
}
