<?php

namespace App\Console\Commands;

ini_set('max_execution_time', -1); // WARNING: Infinite execution time set

use App\Jobs\UpdateImageLength;
use App\Traits\Miscellaneous;
use Error;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

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
        // Artisan::call('process:images');
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
                $this->printLine('Pass 1: Checking sizes ... Done.', 1, true);
                $this->printLine('Searching for duplicates', 1, true);
                $this->findDuplicates($totalImages);
                $this->printLine('Done', 0, true);
                break;
            case 'data':
                $this->printLine('Duplicate detection for normal data is still under development.', 1, true);
                break;
            default:
                throw new Error('Invalid type argument! Allowed types: ' . implode(', ', $allowed['types']));
        }
        return Command::SUCCESS;
    }

    /**
     * generates length of images in images table
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
     * 
     */
    private function findDuplicates(int $totalImages)
    {
        $tableName = 'images';
        $table = DB::table($tableName)->select([
            'images.id', 'images.image as data', 'images.length as size', 'image_dimensions.x_axis as length',
            'image_dimensions.y_axis as breadth', 'images.created_at'
        ])->join('image_dimensions', 'image_dimensions.image_id', '=', 'images.id');
        $offset = $possibleDuplicatsCount = $processed = $progress = $possibleDuplicateIdCount = 0;
        $limit = (int)env('IMAGE_PROCESSING_CHUNK_DUPLICATES', 10);
        $imgs = null;
        $possibleDuplicateIdBag = [];
        for ($i = 0; $i < $totalImages; $i++) {
            $this->printLine(number_format($progress, 2) . ' % -> ' . number_format($possibleDuplicateIdCount) . ' possible duplicates found. ' . number_format($processed) . ' / ' . number_format($totalImages), 2, true);
            $imgs = $table->skip($offset)->limit($limit)->get();
            foreach ($imgs as $img) {
                if (!$img->length) {
                    continue;
                }
                $this->printLine('Checking for image id: ' . $img->id . ' ... ', 2);
                $possibleDuplicateIds = DB::table($tableName)->select('images.id')->distinct('images.id')
                    ->join('image_dimensions', 'image_dimensions.image_id', '=', 'images.id')
                    ->where(function ($subQuery) use ($img) {
                        return $subQuery->where('images.id', '!=', $img->id)->where([
                            'images.length' => $img->size,
                            'image_dimensions.x_axis' => $img->length,
                            'image_dimensions.y_axis' => $img->breadth
                        ]);
                    })->get();
                $this->printActionCompletedMsg();
                array_push($possibleDuplicateIdBag, [
                    'id' => $img->id,
                    'duplicates' => array_column($possibleDuplicateIds->toArray(), 'id')
                ]);
                $processed++;
                $possibleDuplicateIdCount += $possibleDuplicateIds->count();
                $possibleDuplicatsCount += $possibleDuplicateIdCount;
                $progress = $processed / $totalImages * 100;
                dd($possibleDuplicateIdBag);
                $this->removeLastLine();
            }
            $offset += $limit;
            $this->removeLastLine();
        }
        Log::info('Possible duplicats: ', $possibleDuplicateIdBag);
        dd($possibleDuplicateIdBag);
    }
}
