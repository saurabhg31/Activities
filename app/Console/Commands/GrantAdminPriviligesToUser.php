<?php

namespace App\Console\Commands;

use Error;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantAdminPriviligesToUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grant:adminPrivilige {user_email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to set the user with the given email as admin';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $user = DB::table('users')->where('email', $this->argument('user_email'))->first();
        if ($user->isAdmin) {
            throw new Error("{$user->name} ({$user->email}) is already set as the admin.");
        }
        if (DB::table('users')->where('isAdmin', true)->whereNot('id', $user->id)->exists()) {
            throw new Error("Another user is already set as admin!");
        }
        DB::table('users')->where('id', $user->id)->update([
            'isAdmin' => true
        ]);
        print("{$user->name} ({$user->email}) is now admin." . PHP_EOL);
        return Command::SUCCESS;
    }
}
