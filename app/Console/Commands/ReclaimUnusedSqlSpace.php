<?php

namespace App\Console\Commands;

use App\Traits\Miscellaneous;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ReclaimUnusedSqlSpace extends Command
{
    use Miscellaneous;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reclaim:sqlSpace';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to reclaim ununsed space from sql.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->printHeading('Attempting to reclaim free sql space for "images" table', '-', 10);
        $this->printLine('Calculating reclaimable space ... ', 1);
        $spaceData = DB::select("SELECT TABLE_NAME, DATA_LENGTH AS 'data_size', DATA_FREE AS 'reclaimable_space' FROM information_schema.TABLES WHERE TABLE_NAME = 'images';");
        $this->printActionCompletedMsg();
        $spaceData = reset($spaceData);
        $this->printLine('Total data size after reclamation: ' . $this->convertByteCountToGreatestUnit($spaceData->data_size), 1, true);
        $this->printLine('Total reclaimable space: ' . $this->convertByteCountToGreatestUnit($spaceData->reclaimable_space), 1, true);
        $this->printLine('Proceed ?', 1);
        if ($this->getConfirmation()) {
            Artisan::call('clearLogs:sql');
            $this->printLine('Attempting to reclaim free space, the process may take considerable time ... ', 1);
            DB::statement('OPTIMIZE TABLE images;');
            $this->printActionCompletedMsg();
        }
        $this->printHeading('OPERATION COMPLETED', '-', 30);
        return Command::SUCCESS;
    }
}
