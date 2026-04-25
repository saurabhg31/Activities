<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImages;
use App\Models\Images;
use App\Models\ImageDimensions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Traits\Miscellaneous;
use Error;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class ProcessAllImages extends Command
{

    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to process images.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $ignoreImageTypes = [/*'webp', 'gif'*/]; // ignoring unsupported image formats
        $this->printHeading('IMAGE PROCESSING OPERATION INITIALIZED', '-', 20);
        if (env('IMAGE_PROCESSING_REGENERATE_TABLES', false)) {
            $this->printLine('Getting total images count & resetting image processing table data ... ', 1, true);
            Artisan::call('reset:images_processing');
            $totalImages = Images::whereNotIn('imageType', $ignoreImageTypes)->count();
            $this->printLine('Fetching all image ids, total: ' . number_format($totalImages) . ' ... ', 1);
            $imageIds = array_column(
                Images::select('id')->whereNotIn('imageType', $ignoreImageTypes)->whereNull('length')->get()->toArray(),
                'id'
            );
            print('Done. ');
        } else {
            $this->printLine('Getting total unprocessed images ... ', 1);
            $ignoreImageTypes_str = '"' . str_replace(',', '","', implode(',', $ignoreImageTypes)) . '"';
            $totalImages = DB::select('select count(images.id) as count from images where images.id not in (select image_dimensions.image_id from image_dimensions) and images.imageType not in (' . $ignoreImageTypes_str . ')')[0]->count;
            $this->printActionCompletedMsg();
            if ($totalImages) {
                $this->printLine('Fetching all unprocessed image ids, total: ' . number_format($totalImages) . '.', 1);
                $imageIds = DB::select('select images.id as imageId from images where images.id not in (select image_dimensions.image_id from image_dimensions) and images.imageType not in (' . $ignoreImageTypes_str . ')');
                if (!empty($imageIds)) {
                    $imageIds = array_map(function ($obj) {
                        return $obj->imageId;
                    }, $imageIds);
                }
            } else {
                $this->printLine('No images with unlogged attributes found.', 1);
            }
        }
        if (!$this->getConfirmation('Proceed ?')) {
            $this->printLine('Process aborted by user.', 1, true);
            return Command::FAILURE;
        }
        if (isset($imageIds)) {
            $this->printLine('Sorting image ids in ascending order ... ', 1);
            asort($imageIds);
            $this->printActionCompletedMsg();
            $this->printLine('Processing images ' . (env('IMAGE_PROCESSING_USE_QUEUE', false) ? ' via queues ' : null) . '... ', 1, true);
            $imageIdChunks = array_chunk($imageIds, env('IMAGE_PROCESSING_CHUNK'));
            if (env('IMAGE_PROCESSING_USE_QUEUE', false)) {
                foreach ($imageIdChunks as $idChunk) {
                    print('Dispatching ids: ' . implode(', ', $idChunk) . ' ... ' . PHP_EOL);
                    ProcessImages::dispatch($idChunk);
                }
            } else {
                unset($imageIds);
                $imagesDimensionsBag = [];
                $processed = 0;
                $progress = 0.0; // processed ids/total ids
                foreach ($imageIdChunks as $idChunk) {
                    foreach ($idChunk as $imageId) {
                        if ($processed) {
                            $this->removeLastLine();
                        }
                        $this->printLine('Logging dimensions of image having image id: ' . $imageId . ' ' . $this->printProgressBar($progress * 100, '.', 20), 2);
                        try {
                            array_push($imagesDimensionsBag, array_merge([$imageId], $this->getImageDimension($imageId)));
                            $this->printActionCompletedMsg();
                        } catch (Throwable $error) {
                            Log::error($error->getMessage());
                            print('Error: "' . $error->getMessage() . '" encountered! Skipped.' . PHP_EOL);
                            continue;
                        }
                        $processed++;
                        $progress = $processed / $totalImages;
                    }
                }
                $this->printLine('Inserting/updating dimension data to table ... ', 2);
                ImageDimensions::addMultipleImagesDimensionsInfo($imagesDimensionsBag);
                $this->printActionCompletedMsg();
            }
            $this->printLine('Logging image sizes of ' . number_format($processed) . ' images ... ', 1, true);
            $processed = 0;
            $progress = 0.0;
            foreach ($imageIdChunks as $idChunk) {
                foreach ($idChunk as $imageId) {
                    if ($processed) {
                        $this->removeLastLine();
                    }
                    $this->printLine('Logging length of image having image id: ' . $imageId . ' ' . $this->printProgressBar($progress * 100, '.', 20), 2, true);
                    Images::logImageLength($imageId);
                    $processed++;
                    $progress = $processed / $totalImages;
                }
            }
            $this->printLine(number_format($processed) . ' images processed.', 1, true);
        }
        $this->printLine('Checking if any images are remaining to be logged in analytics tables ... ', 1, true);
        $ignoreImageTypes_str = '"' . str_replace(',', '","', implode(',', $ignoreImageTypes)) . '"';
        $unprocessedImageIds = DB::select('select count(images.id) as count from images where images.id not in (select image_dimensions.image_id from image_dimensions) and images.imageType not in (' . $ignoreImageTypes_str . ')');
        $unprocessedImageIds = reset($unprocessedImageIds);
        if (isset($unprocessedImageIds->count) && $unprocessedImageIds->count) {
            $this->printLine(number_format($unprocessedImageIds->count) . ' unlogged image analytics ids found! Correcting ... ', 2, true);
            $unprocessedImageIds = DB::select('select images.id as image_id from images where images.id not in (select image_dimensions.image_id from image_dimensions) and images.imageType not in (' . $ignoreImageTypes_str . ')');
            $unprocessedImageIds = array_map(function ($obj) {
                return (array) $obj;
            }, $unprocessedImageIds);
            $unprocessedImageIds = array_column($unprocessedImageIds, 'image_id');
        }
        // dd(DB::select('select distinct(imageType) as format from images where id in (' . implode(',', $unprocessedImageIds) . ')'));
        // TODO: Add left out images metadata logging code
        $this->printLine('Checking for duplicates in image analytics tables ... ', 1, true);
        Artisan::call('process:remove_image_duplicate_indices');
        $this->printHeading('IMAGE PROCESSING OPERATION COMPLETED', '-', 20);
        return Command::SUCCESS;
    }

    /**
     * Get image width and height
     * @param integer $id - image id
     * @return array [image_width, image_height]
     */
    private function getImageDimension(int $id)
    {
        $dimensions = getimagesizefromstring(base64_decode(Images::findOrFail($id)->image));
        if ($dimensions === false) {
            throw new Error('Unable to decipher the dimensions of the image.');
        }
        return [$dimensions[0], $dimensions[1]];
    }

    /**
     * truncate tables
     * @param array $tables
     * @return void
     */
    private function truncateTables(array $tables)
    {
        array_map(function ($tableName) {
            DB::table($tableName)->truncate();
        }, $tables);
    }
}
