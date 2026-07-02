<?php

namespace App\Jobs;

use App\Models\Images;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteTempImageFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $filePath;
    protected int $imageId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $filePath, int $imageId)
    {
        $this->filePath = $filePath;
        $this->imageId = $imageId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!DB::table((new Images)->getTable())->where('id', $this->imageId)->where('image', 'like', config('constants.TMP_STORED_PREFIX') . '%')->exists()) {
            $filePath = trim(str_replace(config('constants.TMP_STORED_PREFIX'), '', $this->filePath));
            Storage::delete($filePath);
        }
    }
}
