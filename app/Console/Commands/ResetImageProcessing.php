<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Traits\Miscellaneous;

class ResetImageProcessing extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:images_processing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to truncate image_dimensions table & set erase all values from length column in images table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->printLine('Truncating image_dimensions table ... ', 2);
        DB::table('image_dimensions')->truncate();
        $this->printActionCompletedMsg();
        $this->printLine('Clearing value of length column in images table ... ', 2, true);
        $imageIds = DB::table('images')->select('id')->whereNotNull('length')->get()->pluck('id')->toArray();
        asort($imageIds);
        $progress = 0.0;
        $processedCount = 0;
        $totalImgs = count($imageIds);
        $limit = env('IMAGE_PROCESSING_CHUNK', 100);
        $imageIdChunks = array_chunk($imageIds, $limit);
        unset($imageIds);
        foreach ($imageIdChunks as $imageIds) {
            if ($processedCount) {
                $this->removeLastLine();
            }
            $this->printLine(str_repeat(' ', 4) . '(' . number_format($processedCount) . '/' . number_format($totalImgs) . '). ' . str_repeat(' ', 4) . $this->printProgressBar($progress * 100, '.', 20), 2, true);
            DB::table('images')->whereIn('id', $imageIds)->update(['length' => null]);
            $processedCount += $limit;
            $progress = $processedCount / $totalImgs;
        }
        $this->removeLastLine();
        $this->removeLastLine();
        if ($totalImgs) {
            $this->printActionCompletedMsg();
        }
        return Command::SUCCESS;
    }
}
