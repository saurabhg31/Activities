<?php

namespace App\Jobs;

use App\Models\Images;
use App\Models\ImageDimensions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected array $imageIds;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $imageIds)
    {
        $this->imageIds = $imageIds;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // determining image dimensions
        $imagesDimensionsBag = [];
        foreach ($this->imageIds as $imageId) {
            array_push($imagesDimensionsBag, array_merge([$imageId], $this->getImageDimension($imageId)));
        }
        ImageDimensions::addMultipleImagesDimensionsInfo($imagesDimensionsBag);
    }

    /**
     * @return array
     */
    private function getImageDimension(int $id)
    {
        $img = imagecreatefromstring(base64_decode(Images::findOrFail($id)->image));
        return [imagesx($img), imagesy($img)];
    }
}
