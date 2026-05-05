<?php

namespace App\Console\Commands;

use App\Models\ImageDifferenceHash;
use App\Traits\Miscellaneous;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateImagesDifferenceHash extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:imagesHash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to generate difference hash of images';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->printHeading('Generating difference hash of unanimated images', '=', 20);
        $this->printLine('Fetching image ids of images with no hash data ... ', 1);
        $imageIds = DB::select('select images.id from images where not exists (select 1 from images_difference_hash where images_difference_hash.image_id=images.id) and images.isAnimated=0;');
        $imageIds = array_map(function ($item) {
            return $item->id;
        }, $imageIds);
        $this->printActionCompletedMsg();
        $this->printLine(number_format($totalImages = count($imageIds)) . ' images to calculate hash for ...', 1, true);
        $processed = 0;
        foreach ($imageIds as $imageId) {
            $this->printLine('Calculating & saving dHash for image id: ' . $imageId . ' | ' . $this->printProgressBar(($processed / $totalImages) * 100), 2, true);
            ImageDifferenceHash::storeImageDifferenceHash($imageId);
            $processed++;
            $this->removeLastLine();
        }
        $this->printLine('Generated difference hash for ' . number_format($processed) . ' image(s).', 1, true);
        $this->printHeading('OPERATION COMPLETED', '-', 30);
        return Command::SUCCESS;
    }
}
