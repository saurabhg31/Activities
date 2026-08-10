<?php

namespace App\Console\Commands;

use App\Models\ImageCompressionLog;
use App\Traits\Miscellaneous;
use Illuminate\Console\Command;

class GenrerateImageCompressionLogsReductionValues extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:imageReductionValues';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate image reduction values in image_compression_logs table.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->printHeading('GENERATING IMAGE_COMPRESSION_LOGS REDUCTION VALUES');
        $total = ImageCompressionLog::count();
        $processed = 0;
        $logData = ImageCompressionLog::orderBy('image_id', 'asc')->skip($processed)->take(1)->first();
        while (!is_null($logData)) {
            $this->printLine('Calculating value for image id: ' . $logData->image_id . '. (' . number_format($processed) . ' / ' . number_format($total) . ')', 1);
            $logData->reduction = (($logData->old_filesize - $logData->new_filesize) / $logData->old_filesize) * 100;
            $logData->save();
            $processed++;
            $progress = ($processed / $total) * 100;
            print($this->printProgressBar($progress, maxProgressChars: 20));
            print(PHP_EOL);
            $logData = ImageCompressionLog::orderBy('image_id', 'asc')->skip($processed)->take(1)->first();
            $this->removeLastLine();
        }
        $this->printHeading('OPERATION COMPLETED SUCCESSFULLY (' . number_format($processed) . ' / ' . number_format($total) . ') entries processed.');
        return Command::SUCCESS;
    }
}
