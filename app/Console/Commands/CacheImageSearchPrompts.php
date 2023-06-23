<?php

namespace App\Console\Commands;

use App\Models\User;
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
        $offset = 0;
        $limit = 10;
        $users = User::select('id')->limit($limit)->get();
        while ($users->isNotEmpty()) {
            foreach ($users as $user) {
                cacheImageSearchPrompts('public', $user->id);
                cacheImageSearchPrompts('private', $user->id);
                $offset++;
            }
            $users = User::select('id')->offset($offset)->limit($limit)->get();
        }
        return Command::SUCCESS;
    }
}
