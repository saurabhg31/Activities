<?php

namespace App\Console\Commands;

ini_set('max_execution_time', 3600);

use App\Jobs\UpdateImageLength;
use App\Traits\Miscellaneous;
use Error;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
}
