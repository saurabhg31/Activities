<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Traits\Miscellaneous;
use Exception;
use Illuminate\Console\Command;

class GeneratePassword extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:password {user_email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate user password for email.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $password = \Illuminate\Support\Str::random(10);
            $user = User::where('email', $this->argument('user_email'))->first();
            if (!$user) {
                print('No such user.' . PHP_EOL);
                return Command::FAILURE;
            } else {
                $user->password = bcrypt($password);
                $user->save();
                print('New Password: ' . $password . PHP_EOL);
                return Command::SUCCESS;
            }
        } catch (Exception $err) {
            print('Error: ' . $err->getMessage() . PHP_EOL);
            return Command::FAILURE;
        }
    }
}
