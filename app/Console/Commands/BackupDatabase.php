<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to backup all tables present in the database of the application. ';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $key = 'Tables_in_' . getenv('DB_DATABASE');
        $tables = array_map(function ($obj) use (&$key) {
            return $obj->$key;
        }, DB::select('SHOW TABLES;'));
        dd($tables);
        return Command::SUCCESS;
    }
}
