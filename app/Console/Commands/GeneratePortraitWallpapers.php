<?php

namespace App\Console\Commands;

use App\Models\ImageDimensions;
use App\Models\Images;
use Illuminate\Console\Command;
use App\Traits\Miscellaneous;
use Error;

class GeneratePortraitWallpapers extends Command
{
    use Miscellaneous;

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
        $this->authenticateUserViaTerminal();
        $imageIds = ImageDimensions::where('is_portrait', true)->select('image_id')->get()->pluck('image_id');
        print(PHP_EOL . 'Found ' . number_format($imageIds->count()) . ' portrait images.' . PHP_EOL);
        $imgData = $file = $dir = null;
        $dir = $this->readInputFromCli(1, ['    . Enter storage directory: ']);
        $dir = reset($dir);
        if (!file_exists($dir)) {
            $choice = $this->readInputFromCli(1, ['        . Directory does not exist. Create? (Y|N): ']);
            $choice = reset($choice);
            if (in_array(strtolower($choice), ['y', 'yes'])) {
                mkdir($dir, 0770, true);
            } else {
                print('    . Process aborted.' . PHP_EOL);
                return Command::FAILURE;
            }
        } else {
            $filesInDir = array_filter(scandir($dir), function ($str) {
                return !in_array($str, ['.', '..']);
            });
            $fileDirCount = count($filesInDir);
            if ($fileDirCount) {
                $choice = $this->readInputFromCli(1, ['        . Directory not empty. Clear All Files (C), Ignore (I): ']);
                $choice = strtolower(reset($choice));
                if ('c' == $choice) {
                    print('            . Removing ' . number_format($fileDirCount) . ' files ... ' . PHP_EOL);
                    $progress = 0.0;
                    foreach ($filesInDir as $index => $file) {
                        print('                . ' . $file . ' ' . str_repeat('.', floor($progress / 10)) . '    ' . number_format($progress, 2) . '%' . PHP_EOL);
                        unlink($dir . DIRECTORY_SEPARATOR . $file);
                        $progress = ($index + 1) / $fileDirCount * 100;
                        $this->removeLastLine();
                    }
                    $this->removeLastLine();
                    print('            . Removed ' . number_format($fileDirCount) . ' files.' . PHP_EOL);
                } elseif ('i' == $choice) {
                    print('            . Present files & folders ignored.' . PHP_EOL);
                } else {
                    throw new Error('Invalid Choice!');
                }
            }
        }
        $imageIdCount = $imageIds->count();
        foreach ($imageIds as $index => $id) {
            $progress = ($index + 1) / $imageIdCount * 100;
            print('        . Generating wallpaper for image id: ' . number_format($id) . ' ' . str_repeat('.', floor($progress / 10)) . ' ' . number_format($progress, 2) . '%. (' . ($index + 1) . '/' . $imageIdCount . ') ');
            $imgData = Images::find($id);
            if (in_array($id . '.' . $imgData->imageType, $filesInDir)) {
                print('File "' . $id . '.' . $imgData->imageType . '" is already present, skipped.' . PHP_EOL);
                continue;
            }
            $file = $dir . DIRECTORY_SEPARATOR . $id . '.' . $imgData->imageType;
            file_put_contents($file, base64_decode($imgData->image));
            print('Done: ' . basename($file) . PHP_EOL);
            $this->removeLastLine();
        }
        return Command::SUCCESS;
    }
}
