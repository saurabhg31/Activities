<?php

namespace App\Console\Commands;

use App\Models\ImageDimensions;
use App\Models\Images;
use Illuminate\Console\Command;
use App\Traits\Miscellaneous;
use Error;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class ExportImages extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:images {--d=} {--t=} {--o=} {allowHigherDimensions=yes} {preserveAspectRatio=yes} {usePrivateDomainOnly?}';

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
        if (!env('ALLOW_IMAGE_EXPORT_WITHOUT_AUTHENTICATION', false)) {
            // display username & password prompts in terminal
            $this->authenticateUserViaTerminal();
        }
        print('------------------ Export image process started on ' . now()->format('d M, Y \a\t H:i:s T') . ' ------------------' . PHP_EOL);

        // declaring variable & getting input variable values from terminal
        $dimension = strtolower($this->option('d'));
        $tags = strtolower($this->option('t'));
        $orientation = strtolower($this->option('o'));
        $allowHigherDimensions = $this->argument('allowHigherDimensions');
        $preserveAspectRatio = $this->argument('preserveAspectRatio');
        $usePrivateDomainOnly = $this->argument('usePrivateDomainOnly');

        // processing received input variable values from terminal
        if ($dimension) {
            // validating & processing image(s) dimension
            if (!preg_match('/\b\d{1,5}x\d{1,5}\b/', $dimension, $matches)) {
                throw new Error('Invalid dimension.');
            }
            $dimension = $this->parseOrientation($dimension);
            if ($orientation != end($dimension)) {
                if (env('IMG_EXPORT_THROW_ERROR_ON_DIMENSION_MISMATCH', true)) {
                    throw new Error('Orientation mismatch, check dimensions.');
                }
                $orientation = end($dimension);
                $this->printLine('Orientation set to ' . strtoupper($orientation) . '.', 1, true);
            }
            $allowHigherDimensions = in_array(strtolower($allowHigherDimensions), ['1', 'y', 'yes', 'true']);
        }
        if ($usePrivateDomainOnly) {
            // getting include private images confirmation from terminal
            $usePrivateDomainOnly = in_array(strtolower($usePrivateDomainOnly), ['1', 'y', 'yes', 'true']);
        }

        // displaying current set configuration values
        print('    . Config set: ' . PHP_EOL);
        print('        . Dimension: ' . (!is_null($dimension) ? implode(' x ', array_slice($dimension, 0, 2)) : 'N/A') . PHP_EOL);
        print('        . Tags: ' . $tags . PHP_EOL);
        print('        . Orientation: ' . (!is_null($orientation) ? strtoupper($orientation) : 'N/A') . PHP_EOL);
        print('        . Allow higher dimensions: ' . ($allowHigherDimensions ? 'YES' : 'NO') . PHP_EOL);
        print('        . Preserve aspect ratio: ' . ($preserveAspectRatio ? 'YES' : 'NO') . PHP_EOL);
        print('        . Use private domain only: ' . ($usePrivateDomainOnly ? 'YES' : 'NO') . PHP_EOL);

        // generating query
        $imageIds = ImageDimensions::selectRaw('image_dimensions.image_id, image_dimensions.x_axis, image_dimensions.y_axis')
            ->join('images', function ($joinQuery) use ($usePrivateDomainOnly) {
                $joinQuery->on('images.id', '=', 'image_dimensions.image_id');
                $usePrivateDomainOnly ? $joinQuery->whereNotNull('images.user_id') : $joinQuery->whereNull('images.user_id');
            });
        if (!empty($tags)) {
            // processing tags
            $this->addTagsFilter($imageIds, $tags);
        }
        /* TODO: Add custom search code
        if ($this->getConfirmation('    . Use custom search ?')) {
            $this->addSearchParams($imageIds);
        }
        */
        if ($dimension) {
            // adding dimension check query
            [$xAxis, $yAxis] = array_splice($dimension, 0, 2);
            // appending higher dimension inclusion search query if requested
            $allowHigherDimensions ? $imageIds->where([['image_dimensions.x_axis', '>=', $xAxis], ['image_dimensions.y_axis', '>=', $yAxis]]) : $imageIds->where(['image_dimensions.x_axis' => $xAxis, 'image_dimensions.y_axis' => $yAxis]);
        }

        // getting image(s) ids along with their respective image dimensions
        $imagesData = $imageIds->get();
        if ($orientation && $preserveAspectRatio) {
            // TODO: Refine preserveAspectRatio code
            // filtering out images whose aspect ration doesn't match with orientation (landscape/portrait/square)
            $imagesData = $imagesData->filter(function ($obj, $pass = false) use ($orientation) {
                switch ($orientation) {
                    case 'portrait':
                        $pass = $obj->x_axis < $obj->y_axis;
                        break;
                    case 'landscape':
                        $pass = $obj->y_axis < $obj->x_axis;
                        break;
                    case 'square':
                        $pass = $obj->y_axis == $obj->x_axis;
                        break;
                    default:
                        throw new Error('Invalid orientation input');
                }
                return $pass;
            });
        }

        // fetching relevant image ids only matching filter criteria
        $imageIds = $imagesData->pluck('image_id');
        // clearing obsolete images data
        $imagesData = null;
        unset($imagesData);

        // verifying & processing fetched data
        if (!$imageIds->count()) {
            $this->printLine('No images matching the given criterial found!', 1, true);
            return Command::FAILURE;
        }
        $this->printLine('Found ' . number_format($imageIds->count()) . ' portrait images.', 1, true);
        $this->printLine('Filtering out images with wrong orientation ...', 1, true);

        // declaring variables
        $imgData = $file = $dir = null;
        $dir = $this->readInputFromCli(1, ['    . Enter storage directory: ']);
        $dir = reset($dir);
        if (empty($dir)) {
            $dir = env('IMAGE_EXPORT_DIRECTORY', '/mnt/c/Users/saura/OneDrive/Pictures/pw');
        }
        if (!file_exists($dir)) {
            // creating directory if allowed by user
            $choice = $this->readInputFromCli(1, ['        . Directory (' . $dir . ') does not exist. Create? (Y|N): ']);
            $choice = reset($choice);
            if (in_array(strtolower($choice), ['y', 'yes'])) {
                mkdir($dir, 0770, true);
            } else {
                print('    . Process aborted.' . PHP_EOL);
                return Command::FAILURE;
            }
        }

        // checking if directory is empty and confirming subsequent actions from user
        $filesInDir = array_filter(scandir($dir), function ($str) {
            return !in_array($str, ['.', '..']);
        });
        $fileDirCount = count($filesInDir);
        if ($fileDirCount) {
            $choice = $this->readInputFromCli(1, ['        . Directory not empty. Clear All Files (C), Ignore (I): ']);
            $choice = strtolower(reset($choice));
            if ('c' == $choice) {
                // clearing all files within image export directory if option "c" is chosen
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
                // ignoring present files
                print('            . Present files & folders ignored.' . PHP_EOL);
            } else {
                throw new Error('Invalid Choice!');
            }
        }

        // exporting images to specified directory one at a time in a loop
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
        $this->printLine('Custom search feature currently in progress.', 2, true);
    }

    /**
     * Get orientation from dimension string
     * @param string $dimension
     * @return array [xAxis, yAxis, orientation(portrait/landscape)]
     */
    private function parseOrientation(string $dimension)
    {
        [$x, $y] = array_map(function ($str) {
            return trim($str);
        }, explode('x', $dimension));
        return [$x, $y, $x > $y ? 'landscape' : ($x < $y ? 'portrait' : 'square')];
    }

    /**
     * Add tags to builder query
     * @param \Illuminate\Database\Eloquent\Builder $builderQuery
     * @param string $tags
     * @return void
     */
    private function addTagsFilter(Builder &$builderQuery, string $tags)
    {
        $builderQuery = $builderQuery->join('image_search_indexing', function ($query) use ($tags) {
            $query->on('image_dimensions.image_id', '=', 'image_search_indexing.image_id');
            $this->processAndAddTagsToQuery($query, $tags);
        });
    }

    /**
     * Process tags and add relevant query conditions
     * @param \Illuminate\Database\Eloquent\Builder $builderQuery
     * @param string $tags
     * @return void
     */
    private function processAndAddTagsToQuery(Builder|JoinClause &$query, string &$tags)
    {
        $tags = array_map(function ($str) {
            return str_replace(['#', '@'], '', trim($str));
        }, explode(',', $tags));
        $query->where(function ($tagsSubQuery) use ($tags) {
            $tagsSubQuery->where('image_search_indexing.tag', 'like', '%' . array_shift($tags) . '%');
            foreach ($tags as $tag) {
                $tagsSubQuery->orWhere('image_search_indexing.tag', 'like', '%' . $tag . '%');
            }
        });
    }
}
