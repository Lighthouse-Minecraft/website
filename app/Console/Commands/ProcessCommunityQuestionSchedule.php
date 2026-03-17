<?php

namespace App\Console\Commands;

use App\Actions\ProcessQuestionSchedule;
use Illuminate\Console\Command;

class ProcessCommunityQuestionSchedule extends Command
{
    protected $signature = 'community:process-schedule';

    protected $description = 'Activate scheduled community questions and archive expired ones';

    public function handle(): int
    {
        $result = ProcessQuestionSchedule::run();

        if ($result['activated'] === 0 && $result['archived'] === 0) {
            $this->info('No community question schedule changes needed.');
        } else {
            $this->info("Activated: {$result['activated']}, Archived: {$result['archived']}.");
        }

        return Command::SUCCESS;
    }
}
