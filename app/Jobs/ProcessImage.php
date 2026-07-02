<?php

namespace App\Jobs;

use App\Models\ImageDimensions;
use App\Models\Images;
use Error;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessImage implements ShouldQueue
{
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
        $dimensions = getimagesizefromstring(base64_decode(Images::findOrFail($this->imageId)->image));
        if ($dimensions === false) {
            throw new Error('Unable to decipher the dimensions of the image.');
        }
        ImageDimensions::addImageDimensionInfo($this->imageId, $dimensions[0], $dimensions[1]);
        Images::logImageLength($this->imageId);
        CompressImage::dispatch($this->imageId);
        $tmpFileCheckData = DB::table((new Images)->getTable())->select('image')
            ->where('id', $this->imageId)->first();
        if (str_starts_with($tmpFileCheckData->image, config('constants.TMP_STORED_PREFIX'))) {
            DeleteTempImageFile::dispatch($tmpFileCheckData->image, $this->imageId);
        }
    }
}
