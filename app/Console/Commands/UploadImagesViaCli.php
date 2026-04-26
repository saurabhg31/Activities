<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use App\Traits\Miscellaneous;

class UploadImagesViaCli extends Command
{
    use Miscellaneous;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to upload images via cli.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->printHeading('Uploading images process from directory via cli started.');
        $dir = storage_path('pics' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'import');
        $this->printLine('Directory: ' . $dir, 1, true);
        $imageFiles = array_filter(scandir($dir), function ($str) {
            return $str !== '.' && $str !== '..';
        });
        $this->printLine(number_format(count($imageFiles)) . ' files found. Proceed?', 1);
        if ($this->getConfirmation()) {
            $this->printLine('Select domain (public/private):', 1);
            $choice = $this->readInputFromCli(1, [], function ($str) {
                return strtolower($str) === 'public' || strtolower($str) === 'private';
            });
            $userId = null;
            if (reset($choice) === 'private') {
                $this->printLine('Enter user email:', 2);
                $email = $this->readInputFromCli(1, [], function ($str) {
                    return filter_var($str, FILTER_VALIDATE_EMAIL);
                });
                $email = reset($email);
                if (!$email) {
                    $this->printLine('Invalid email entered. PROCESS ABORTED.', 2, true);
                    return Command::FAILURE;
                }
                $user = User::select(['id', 'password'])->where('email', $email)->first();
                if (!$user) {
                    $this->printLine('User not found, PROCESS ABORTED.', 2, true);
                    return Command::FAILURE;
                }
                $this->printLine('Enter password:', 2);
                if ($this->readAndComparePasswordInputFromCli($user->password)) {
                    $userId = $user->id;
                } else {
                    $this->printLine('Invalid password. PROCESS ABORTED.', 2, true);
                    return Command::FAILURE;
                }
            }
        } else {
            $this->printLine('PROCESS ABORTED BY USER', 1);
        }
        $this->printHeading('OPERATION COMPLETED', '-', 15);
        print(PHP_EOL);
        return Command::SUCCESS;
    }
}
