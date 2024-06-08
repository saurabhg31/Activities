<?php

namespace App\Console\Commands;

use App\Traits\Miscellaneous;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorQueue extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to monitor queue counts';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->clearScreen();
        $this->printHeading('QUEUE MONITORING STARTED (Press Ctrl + C to stop)', '-', 10);
        print(PHP_EOL);
        $processed = $rate = null;
        while (true) {
            $counts = $this->getQueueCounts();
            $this->printLine('In Queue: ' . number_format($counts['in_queue']) . ', In Failed Queue: ' . number_format($counts['in_failed_queue']) . ', Total: ' . number_format(array_sum($counts)) . ', Rate: ' . ($rate ? $rate . ' jobs / second' : 'N/A'), 1, true);
            $this->delayExecution(1);
            if ($processed) {
                $rate = $processed - $counts['in_queue'];
            }
            $processed = $counts['in_queue'];
            $this->removeLastLine();
        }
        return Command::SUCCESS;
    }

    private function getQueueCounts()
    {
        return [
            'in_queue' => DB::table('jobs')->count(),
            'in_failed_queue' => DB::table('failed_jobs')->count()
        ];
    }
}
