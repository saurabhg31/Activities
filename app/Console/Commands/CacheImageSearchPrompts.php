<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use function App\Helpers\cacheImageSearchPrompts;

class CacheImageSearchPrompts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:image_search_prompts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to cache image search prompts.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        cacheImageSearchPrompts();
        return Command::SUCCESS;
    }
}
