<?php

namespace App\Console\Commands;

use App\Models\ImageDimensions;
use App\Models\Images;
use Illuminate\Console\Command;
use App\Traits\Miscellaneous;
use Error;
use Illuminate\Database\Eloquent\Builder;

class ExportImages extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:wallpapers {--d=} {--t=} {--o=} {includePrivateDomain} {allowHigherDimensions?} {preserveAspectRatio?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to export images to a directory in system.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (env('ALLOW_IMAGE_EXPORT_WITHOUT_AUTHENTICATION', false)) {
            $this->authenticateUserViaTerminal();
        }
        print('------------------ Export image process started on ' . now()->format('d M, Y \a\t H:i:s T') . ' ------------------' . PHP_EOL);
        $dimension = $this->option('d');
        $tags = $this->option('t');
        $orientation = $this->option('o');
        $allowHigherDimensions = $this->argument('allowHigherDimensions');
        $includePrivateDomain = $this->argument('includePrivateDomain');
        $preserveAspectRatio = $this->argument('preserveAspectRatio');
        if ($dimension) {
            if (!preg_match('/\b\d{1,5}x\d{1,5}\b/', $dimension, $matches)) {
                throw new Error('Invalid dimension.');
            }
            $allowHigherDimensions = in_array(strtolower($allowHigherDimensions), ['1', 'y', 'yes', 'true']);
        }
        print('    . Config set: ' . PHP_EOL);
        print('        . Dimension: ' . $dimension . PHP_EOL);
        print('        . Tags: ' . $tags . PHP_EOL);
        print('        . Allow higher dimensions: ' . ($allowHigherDimensions ? 'YES' : 'NO') . PHP_EOL);
        print('        . Preserve aspect ratio: ' . ($preserveAspectRatio ? 'YES' : 'NO') . PHP_EOL);
        $imageIds = ImageDimensions::select('image_id');
        if ($this->getConfirmation('    . Use custom search ?')) {
            $this->addSearchParams($imageIds);
        }
        if ($dimension) {
            [$xAxis, $yAxis] = explode('x', $dimension);
            $imageIds = $allowHigherDimensions ? $imageIds->where([['x_axis', '>=', $xAxis], ['y_axis', '>=', $yAxis]]) : $imageIds->where(['x_axis' => $xAxis, 'y_axis' => $yAxis]);
        } else {
            $imageIds = $imageIds->where('is_portrait', true);
        }
        $imageIds = $imageIds->get()->pluck('image_id');
        print(PHP_EOL . 'Found ' . number_format($imageIds->count()) . ' portrait images.' . PHP_EOL);
        $imgData = $file = $dir = null;
        $dir = $this->readInputFromCli(1, ['    . Enter storage directory: ']);
        $dir = reset($dir);
        if (empty($dir)) {
            $dir = env('IMAGE_EXPORT_DIRECTORY', '/mnt/c/Users/saura/OneDrive/Pictures/pw');
        }
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

    /**
     * Add search parameter
     * @param \Illuminate\Database\Eloquent\Builder $builderQuery
     * @return void
     */
    private function addSearchParams(Builder &$builderQuery)
    {
        dd($builderQuery);
    }
}
