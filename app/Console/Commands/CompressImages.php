<?php

namespace App\Console\Commands;

use App\Traits\Miscellaneous;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function App\Helpers\compressImage;

class CompressImages extends Command
{
    use Miscellaneous;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compress:images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to compress unanimated images';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->printHeading('Attempting images\' compression (will automatically choose best format)', '-', 20);
        $imageIds = DB::select('select images.id from images where not exists (select 1 from image_compression_logs where image_compression_logs.image_id=images.id)');
        $imageIds = array_map(function ($item) {
            return $item->id;
        }, $imageIds);
        $imagesCount = count($imageIds);
        $this->printLine(number_format($imagesCount) . ' images to convert ...', 1, true);
        $processed = 0;
        $successCount = 0;
        $failedImageIds = [];
        $compressionResultedInGreaterFilesizeForImageIds = [];
        foreach ($imageIds as $imageId) {
            $this->printLine('Converting image id: ' . $imageId . '. ' . $this->printProgressBar(($processed / $imagesCount) * 100), 2, true);
            $compression = compressImage($imageId);
            if ($compression === true) {
                $successCount++;
            } else {
                if (is_null($compression)) {
                    array_push($compressionResultedInGreaterFilesizeForImageIds, $imageId);
                }
                array_push($failedImageIds, $imageId);
            }
            $processed++;
            $this->removeLastLine();
        }
        $this->printLine(number_format($successCount) . ' images successfully converted.', 1, true);
        $this->printLine('Failed to convert ' . number_format(count($failedImageIds)) . ' images.', 1, true);
        if (!empty($failedImageIds)) {
            $this->printLine('Logging failed image ids ... ', 1);
            Log::channel('imageCompression')->info('Failed to compress image ids at ' . now() . '. Failed image ids: ' . implode(', ', $failedImageIds));
            $this->printActionCompletedMsg();
            if (!empty($compressionResultedInGreaterFilesizeForImageIds)) {
                $this->printLine(number_format(count($compressionResultedInGreaterFilesizeForImageIds)) . ' images\' compression resulted in greater than or equal to old filesize', 1, true);
            }
            $this->printActionCompletedMsg();
        }
        $this->printLine('Purging sql logs ... ', 1);
        DB::statement('PURGE BINARY LOGS BEFORE NOW();');
        $this->printActionCompletedMsg();
        $this->printHeading('OPERATION COMPLETED', '-', 30);
        return Command::SUCCESS;
    }
}
