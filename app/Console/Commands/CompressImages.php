<?php

namespace App\Console\Commands;

use App\Models\Images;
use App\Traits\Miscellaneous;
use Illuminate\Console\Command;
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
        $this->printHeading('Compressing unanimated images (converting to .avif)', '-', 20);
        $imageIds = Images::select('id')->where('isAnimated', false)->whereNot('imageType', 'avif')->pluck('id');
        $imagesCount = $imageIds->count();
        $this->printLine(number_format($imagesCount) . ' images to convert ...', 1, true);
        $processed = 0;
        $successCount = 0;
        $failedImageIds = [];
        foreach ($imageIds as $imageId) {
            $this->printLine('Converting image id: ' . $imageId . '. ' . $this->printProgressBar(($processed / $imagesCount) * 100), 2, true);
            if (compressImage($imageId)) {
                $successCount++;
            } else {
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
        }
        $this->printHeading('OPERATION COMPLETED', '-', 30);
        return Command::SUCCESS;
    }
}
