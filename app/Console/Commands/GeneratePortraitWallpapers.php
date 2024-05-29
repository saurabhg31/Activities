<?php

namespace App\Console\Commands;

use App\Models\ImageDimensions;
use App\Models\Images;
use Illuminate\Console\Command;

class GeneratePortraitWallpapers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:portrait_wallpapers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $imageIds = ImageDimensions::where('is_portrait', true)->select('image_id')->get()->pluck('image_id');
        print(PHP_EOL . 'Found ' . number_format($imageIds->count()) . ' portrait images.' . PHP_EOL);
        $imgData = $file = null;
        $dir = storage_path('wallpapers/portrait');
        if (!file_exists($dir)) {
            mkdir($dir, 0770, true);
        }
        foreach ($imageIds as $id) {
            print('    . Generating wallpaper for image id: ' . number_format($id) . ' ... ');
            $imgData = Images::find($id);
            $file = $dir . '/' . $id . '.' . $imgData->imageType;
            file_put_contents($file, base64_decode($imgData->image));
            print('Done. ' . basename($file) . PHP_EOL);
        }
        return Command::SUCCESS;
    }
}
