<?php

namespace App\Jobs;

use App\Models\SearchQueryIndexing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogSearchQueries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $requestData, $domain;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $requestData, string $domain)
    {
        $this->requestData = $requestData;
        $this->domain = $domain;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        SearchQueryIndexing::logSearchQuery($this->requestData, $this->domain);
    }
}
