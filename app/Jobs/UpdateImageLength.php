<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateImageLength implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $imageId, $imageLength;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $imageId, int $imageLength)
    {
        $this->imageId = $imageId;
        $this->imageLength = $imageLength;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::table('images')->where('id', $this->imageId)->update([
            'length' => $this->imageLength,
            'updated_at' => now()
        ]);
    }
}
