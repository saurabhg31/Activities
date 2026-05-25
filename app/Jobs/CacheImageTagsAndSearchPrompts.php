<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use function App\Helpers\cacheImageSearchPrompts;

class CacheImageTagsAndSearchPrompts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $userId;
    protected string $domain;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $userId, string $domain)
    {
        $this->userId = $userId;
        $this->domain = $domain;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        cacheImageSearchPrompts($this->domain, $this->userId);
    }
}
