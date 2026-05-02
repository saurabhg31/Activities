<?php

namespace App\Console\Commands;

use App\Traits\Miscellaneous;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearSqlLogs extends Command
{
    use Miscellaneous;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clearLogs:sql';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to clear binary logs from sql data folder and clear space.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->printLine('Clearing sql data logs ... ', 1);
        DB::statement('PURGE BINARY LOGS BEFORE NOW();');
        $this->printActionCompletedMsg();
        return Command::SUCCESS;
    }
}
