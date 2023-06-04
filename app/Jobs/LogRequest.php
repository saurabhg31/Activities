<?php

namespace App\Jobs;

use App\Models\RequestLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class LogRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $startTime, $requestData, $responseData, $timeTaken, $peakMemoryUsage;
    /**
     * Create a new job instance.
     */
    public function __construct(Carbon $startTime, array $requestData, array $responseData, int $timeTaken, float $peakMemoryUsage)
    {
        $this->startTime = $startTime;
        $this->requestData = $requestData;
        $this->responseData = $responseData;
        $this->timeTaken = $timeTaken;
        $this->peakMemoryUsage = $peakMemoryUsage;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $logData = [
            'peak_memory_use' => $this->peakMemoryUsage / (1024 * 1024),
            'time_taken' => $this->timeTaken,
            'request_start_time' => $this->startTime->format(config('constants.DB_DATE_TIME')),
            'request_completed_on' => now()->format(config('constants.DB_DATE_TIME'))
        ];
        $logData = array_merge(
            $logData,
            $this->requestData,
            $this->responseData
        );
        RequestLog::store($logData);
    }
}
