<?php

namespace App\Console\Commands;

ini_set('max_execution_time', 3600);

use App\Jobs\UpdateImageLength;
use App\Traits\Miscellaneous;
use Error;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Query\Builder;
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
                $duplicates = $this->getDuplicatesMapping($this->getImagesLengthMapping($table, $totalImages));
                dd($duplicates);
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
     * 
     */
    private function getImagesLengthMapping(Builder &$table, int &$totalImagesCount)
    {
        $rate = 0.0; // rate of database hits in a second (changes dynamically based on response from mysql)
        $limit = 500;
        $start = null;
        $delay = 1;
        for ($offset = 0; $offset < $totalImagesCount; $offset += $limit) {
            if ($limit < 1) {
                $limit = 500;
            }
            $this->printLine('Mapping sizes ' . $this->printProgressBar($offset / $totalImagesCount * 100) . '   L: ' . $limit . ', R: ' . (isset($hitTime) ? round($rate, 2) : 0) . ', M: ' . number_format($offset), 2);
            $start = now();
            $table->skip($offset)->take($limit)->get()->map(function ($img) {
                UpdateImageLength::dispatch($img->id, strlen($img->image));
            });
            $hitTime = now()->diffInMilliseconds($start);
            $rate = 1000 / $hitTime;
            if ($rate < env('DUPLICATE_MINIMIUM_DB_RATE')) {
                $limit--; // limit halfed
            }
            $start = now();
            print(PHP_EOL);
            $this->removeLastLine();
        }
    }
}
