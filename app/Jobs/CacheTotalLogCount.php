<?php

namespace App\Jobs;

use App\Models\RequestLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Laravel\Octane\Facades\Octane;

class CacheTotalLogCount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $refreshInMinutes = 30;
        if (Cache::has('total_log_count_runtime')) {
            if (time() - Cache::get('total_log_count_runtime') > $refreshInMinutes*60) {
                [$totalLogTask, $log_count_runtime_task] = Octane::concurrently([
                    fn () => Cache::put('total_log_count', RequestLog::count()),
                    fn () => Cache::put('total_log_count_runtime', time()),
                ]);
            }
        } else {
            [$totalLogTask, $log_count_runtime_task] = Octane::concurrently([
                fn () => Cache::put('total_log_count', RequestLog::count()),
                fn () => Cache::put('total_log_count_runtime', time()),
            ]);
        }
    }
}
