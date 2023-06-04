<?php

namespace App\Console\Commands;

use App\Traits\Miscellaneous;
use Exception;
use Illuminate\Console\Command;

class GenerateLogReadApiAccessKey extends Command
{
    use Miscellaneous;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:log_api_access_key';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate a log api access security key.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accessKey = bin2hex(openssl_random_pseudo_bytes(64));
        if ($this->appendToEnv('LOG_API_ACCESS_KEY', bcrypt($accessKey))) {
            print('Log api access key added sucessfully.' . PHP_EOL);
            print('Access key: ' . $accessKey . PHP_EOL);
            return true;
        }
        throw new Exception('Unable to add log api access key to ENV file.');
    }
}
