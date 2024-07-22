<?php

namespace App\Console\Commands;

ini_set('max_execution_time', 7200); // maximum execution time set to 2 hours

use Error;
use App\Models\Images;
use App\Traits\Miscellaneous;
use App\Jobs\UpdateImageLength;
use App\Models\ImageDimensions;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

class ProcessDuplicateImages extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'find:duplicates {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to detect & process duplicate images';
    protected $duplicateMappingDataFile = 'data/duplicatesMapping.jsonl'; // used by "findPossibleDuplicates" method
    protected $duplicateDataResultFile = 'data/duplicatesSearchResult.jsonl'; // used by "findPossibleDuplicates" method
    protected $duplicateExportDir = 'data/duplicates'; // duplicate image confirmation storage directory

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $time = [
            'start' => now(),
            'end' => null
        ];
        $this->clearScreen();
        $this->printHeading('DUPLICATE IMAGES DETECTION AND PROCESSING OPERATION STARTED ON ' . $time['start']->format('d M, Y \A\T H:i:s A (e)'));
        Artisan::call('process:images');
        $allowed = [
            'types' => ['images', 'data']
        ];
        print(str_repeat('-', 120) . PHP_EOL);
        switch ($this->argument('type')) {
            case null:
            case 'images':
                $this->printLine('Getting necessary information for initiation of duplicate IMAGES processing ... ', 1);
                $table = DB::table('images');
                $totalImages = $table->count();
                print('Done. ' . number_format($totalImages) . ' images found.' . PHP_EOL);
                $this->printLine('Pass 1: Checking sizes ...', 1, true);
                $this->generateImagesLengthData($table);
                $this->removeLastLine();
                $this->printLine('Pass 1: Checking sizes ... Done.', 1, true);
                $this->printLine('Searching for duplicates', 1, true);
                $this->findPossibleDuplicates($totalImages);
                $this->findActualDuplicates(); // uses $storageFile to hone in on actual duplicates (method used for performance benefits)
                $this->printLine('Done.', 0, true);
                break;
            case 'data':
                $this->printLine('Duplicate detection for normal data is still under development.', 1, true);
                break;
            default:
                throw new Error('Invalid type argument! Allowed types: ' . implode(', ', $allowed['types']));
        }
        $this->printHeading('DUPLICATE(S) DATA MAPPING GENERATED IN FILE: ' . $this->duplicateMappingDataFile);
        if ($this->getConfirmation('Export duplicates to "' . $this->duplicateExportDir . '" ?')) {
            $this->exportDuplicates();
        }
        return Command::SUCCESS;
    }

    /**
     * Generates length of images in images table
     * @return void
     */
    private function generateImagesLengthData()
    {
        $limit = env('IMAGE_PROCESSING_CHUNK', 20);
        $imageIdsToProcess = DB::table('images')->select('id')->whereNull('length')->orderBy('id', 'asc')->get()->pluck('id')->toArray();
        $idChunks = array_chunk($imageIdsToProcess, $limit);
        $totalChunksCount = count($idChunks);
        $processedCount = $progress = 0;
        $totalIdsCount = count($imageIdsToProcess);
        $img = null;
        foreach ($idChunks as $index => $imageIds) {
            $this->printLine('Processing image id chunk ' . ($index + 1) . '/' . $totalChunksCount . ' containing ' . $limit . ' id(s) each. ' . $this->printProgressBar($progress * 100, '.', 20), 2, true);
            foreach ($imageIds as $imgId) {
                $img = DB::table('images')->select('image')->where('id', $imgId)->first()->image;
                UpdateImageLength::dispatch($imgId, strlen($img));
                $processedCount++;
                $progress = $processedCount / $totalIdsCount;
            }
            $this->removeLastLine();
        }
    }

    /**
     * Finds possible duplicates & stores output as a jsonl file ($storageFile)
     * @param integer $totalImages
     * @return void
     */
    private function findPossibleDuplicates(int $totalImages)
    {
        if (Storage::exists($this->duplicateMappingDataFile)) {
            Storage::delete($this->duplicateMappingDataFile);
        }
        $tableObj = DB::table('images');
        $table = $tableObj->select([
            'images.id', 'images.length', 'image_dimensions.x_axis', 'image_dimensions.y_axis',
            'images.created_at'
        ])->join('image_dimensions', 'image_dimensions.image_id', '=', 'images.id');
        $processed = $progress = $possibleDuplicateIdCount = 0;
        $limit = (int)env('IMAGE_PROCESSING_CHUNK_DUPLICATES', 10);
        $imgs = null;
        $possibleDuplicateIds = [];
        for ($offset = 0; $offset < $totalImages; $offset += $limit) {
            $this->printLine(number_format($progress * 100, 9) . ' % -> ' . number_format($possibleDuplicateIdCount) . ' possible duplicates found. ' . number_format($processed) . ' / ' . number_format($totalImages) . '. PDR: ' . ($processed ? number_format(($possibleDuplicateIdCount / $processed) * 100, 2) : 0.0) . ' %', 2, true);
            $imgs = $table->skip($offset)->limit($limit)->get();
            foreach ($imgs as $img) {
                if (!$img->length) {
                    $this->printLine('No image length, skipping image with id: ' . number_format($img->id) . PHP_EOL);
                    continue;
                }
                $possibleDuplicateIds = ImageDimensions::select('image_id')
                    ->where([
                        'x_axis' => $img->x_axis,
                        'y_axis' => $img->y_axis,
                        'length' => $img->length
                    ])->where('image_id', '!=', $img->id)
                    ->pluck('image_id')->toArray();
                if (!empty($possibleDuplicateIds)) {
                    Storage::append($this->duplicateMappingDataFile, json_encode([
                        'imageId' => $img->id,
                        'possibleDuplicateIds' => $possibleDuplicateIds
                    ]));
                    $possibleDuplicateIdCount += count($possibleDuplicateIds);
                }
                $processed++;
            }
            $progress = $processed / $totalImages;
            $this->removeLastLine();
        }
    }

    /**
     * Generates actual duplicate images id mapping, uses $storageFile to hone in on actual duplicates and appends result to $storageFile
     * @return void
     */
    private function findActualDuplicates()
    {
        $this->printLine('Finding actual duplicates ...', 2, true);
        $possibleDuplicateIdMapping = Storage::read($this->duplicateMappingDataFile);
        $possibleDuplicateIdMapping = new Collection(array_map(function ($data) {
            return json_decode($data);
        }, explode(PHP_EOL, $possibleDuplicateIdMapping)));
        $totalPossibleDuplicateFileSourceCount = $possibleDuplicateIdMapping->pluck('imageId')->count();
        $duplicateKeyIdMapping = [];
        $duplicateCount = $progress = $processed = 0;
        $needleImgData = $originalImgData = $status = null;
        foreach ($possibleDuplicateIdMapping as $mappingData) {
            $status = 'Comparing possible duplicate data for image id: ' . number_format($mappingData->imageId) . '. ';
            $status .= 'Duplicate(s) found: ' . number_format($duplicateCount) . '. ';
            $status .= str_repeat('.', floor($progress * 20)) . ' ' . number_format(($progress * 100), 5) . ' %';
            $this->printLine($status, 3, true);
            $originalImgData = Images::find($mappingData->imageId);
            foreach ($mappingData->possibleDuplicateIds as $possibleDuplicateId) {
                $needleImgData = Images::find($possibleDuplicateId);
                if ($needleImgData->image == $originalImgData->image) {
                    if (isset($duplicateKeyIdMapping[$originalImgData->id])) {
                        array_push($duplicateKeyIdMapping[$originalImgData->id], $needleImgData->id);
                    } else {
                        $duplicateKeyIdMapping[$originalImgData->id] = [$needleImgData->id];
                    }
                    $duplicateCount++;
                }
            }
            $processed++;
            $progress = $processed / $totalPossibleDuplicateFileSourceCount;
            $this->removeLastLine();
        }
        $this->printActionCompletedMsg();
        $duplicatesDetectionResult = [];
        foreach ($duplicateKeyIdMapping as $originalImageId => $duplicateImageIds) {
            array_push($duplicatesDetectionResult, [
                'original' => $originalImageId,
                'duplicates' => $duplicateImageIds
            ]);
        }
        Storage::append($this->duplicateDataResultFile, json_encode([
            'duplicatesSearchResult' => ['time' => now()->format('d M, Y \a\t H:i:s A P'), 'result' => $duplicatesDetectionResult]
        ]));
    }

    /**
     * Export duplicate images
     */
    private function exportDuplicates()
    {
        $results = Storage::read($this->duplicateDataResultFile);
        if ($results) {
            $results = array_map(function ($jsonStr) {
                return json_decode($jsonStr);
            }, explode(PHP_EOL, $results));
            $results = new Collection($results);
        }
        dd($results->first());
    }
}
