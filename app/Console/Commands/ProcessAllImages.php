<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImages;
use App\Models\Images;
use Illuminate\Console\Command;

class ProcessAllImages extends Command
{
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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        print(PHP_EOL . 'Fetching all image ids, total: ' . number_format(Images::count()) . ' ... ');
        $imageIds = array_column(Images::get('id')->toArray(), 'id');
        print('Done.' . PHP_EOL . 'Processing images in queue ... ' . PHP_EOL);
        $imageIdChunks = array_chunk($imageIds, env('IMAGE_PROCESSING_CHUNK'));
        // dd(count($imageIdChunks), reset($imageIdChunks), end($imageIdChunks));
        foreach ($imageIdChunks as $idChunk) {
            print('Dispatching ids: ' . implode(', ', $idChunk) . ' ... ' . PHP_EOL);
            ProcessImages::dispatch($idChunk);
        }
        print(PHP_EOL);
        return Command::SUCCESS;
    }
}
