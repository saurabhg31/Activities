<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use function App\Helpers\compressImage;

class CompressImage implements ShouldQueue
{
    // must use minimum  --timeout=300 --memory=512 in queue worker for it to work
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected int $imageId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $imageId)
    {
        $this->imageId = $imageId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        compressImage($this->imageId);
    }
}
