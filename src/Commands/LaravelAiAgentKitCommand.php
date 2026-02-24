<?php

namespace CreativeCrafts\LaravelAiAgentKit\Commands;

use Illuminate\Console\Command;

class LaravelAiAgentKitCommand extends Command
{
    public $signature = 'laravel-ai-agent-kit';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
