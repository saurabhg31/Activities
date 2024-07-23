<?php

namespace App\Console\Commands;

set_time_limit(0); // WARNING: SCRIPT CAN RUN INDEFINITELY (RETURN VALUE MANDATORY)

use App\Models\Images;
use App\Traits\Miscellaneous;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FindDuplicatesUsingHardSearch extends Command
{
    use Miscellaneous;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hardSearchFind:duplicates';
    protected $duplicateDataResultFile = 'data/duplicatesHardSearchResult.jsonl';
    protected $resultFile = 'data/duplicatesSearchResult.jsonl';
    protected $logFile = 'data/hardDulplicateSearchLog.json';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to find duplicate images by comparing content of each entry (resource intensive)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startTime = now();
        $timeFormat = 'Y-m-d H:i a p';
        $this->printHeading('HARD DUPLICATE IMAGE SEARCH STARTED AT ' . $startTime->format($timeFormat));
        $duplicateCount = $processed = $progress = 0;
        $totalImages = Images::count();
        $duplicateIds = [];
        if (Storage::exists($this->logFile)) {
            $progressData = Storage::read($this->logFile);
            if ($progressData) {
                $progressData = (array)json_decode($progressData);
                if (
                    isset($progressData['processedCount']) && $progressData['processedCount'] > 0 &&
                    $this->getConfirmation('Log file data present, last processed id: ' . number_format($progressData['lastProcessedImage']) . ', continue?')
                ) {
                    $processed = $progressData['processedCount'];
                    $duplicateCount = isset($progressData['progress']) ? $progressData['progress'] : $progress;
                    $duplicateCount = isset($progressData['duplicatesCount']) ? $progressData['duplicatesCount'] : $duplicateCount;
                }
            }
        } else {
            $progressData = [
                'lastProcessedImage' => null,
                'duplicatesCount' => $duplicateCount,
                'processedCount' => $processed,
                'progress' => $progress
            ];
        }
        $needleImg = $processed > 0 ? Images::skip($processed)->first() : Images::first();
        Storage::write($this->logFile, json_encode($progressData));
        $etaInSeconds = $loopStartTime = false;
        while ($needleImg) {
            $loopStartTime = now();
            $statusMsg = 'Searching for image: ' . number_format($needleImg->id) . '. ';
            $statusMsg .= str_repeat('.', floor($progress * 20)) . ' ' . number_format($progress * 100, 5);
            $statusMsg .= ' % ---- Duplicates: ' . number_format($duplicateCount);
            $statusMsg .= ' ---- ( ' . number_format($processed + 1) . ' / ' . number_format($totalImages) . ' )';
            if ($etaInSeconds !== false) {
                $statusMsg .= ' ---- TIME: [Passed: ' . $this->getHumanReadableTimeDiffFromSeconds(now()->diffInSeconds($startTime)) . ', ';
                $statusMsg .= 'Remaining: ' . $this->getHumanReadableTimeDiffFromSeconds($etaInSeconds) . ']';
            }
            $this->printLine($statusMsg, 1, true);
            $duplicateIds = Images::select('id')->where('id', '!=', $needleImg->id)
                ->where('image', 'like', '%' . $needleImg->image . '%')->get()->pluck('id')->toArray();
            if (!empty($duplicateIds)) {
                Storage::append($this->duplicateDataResultFile, json_encode([
                    'original' => $needleImg->id,
                    'duplicates' => $duplicateIds
                ]));
                $duplicateCount += count($duplicateIds);
            }
            $processed++;
            $progress = $processed / $totalImages;
            $timeTakenForOneIteration = now()->diffInSeconds($loopStartTime);
            $etaInSeconds = ($totalImages - $processed) * $timeTakenForOneIteration;
            $progressData = [
                'lastProcessedImage' => $needleImg->id,
                'duplicatesCount' => $duplicateCount,
                'processedCount' => $processed,
                'progress' => $progress
            ];
            Storage::write($this->logFile, json_encode($progressData));
            $needleImg = Images::skip($processed)->first();
            $this->removeLastLine();
        }
        if (!$duplicateCount) {
            $this->printLine('No duplicates found!', 1, true);
        }
        /*Storage::write($this->duplicateDataResultFile, json_encode([
            'duplicatesSearchResult' => [
                'time' => now()->format($timeFormat),
                'result' => $duplicateIdBag,
                'metadata' => [
                    'searchMethodUsed' => 'HARD (RESOURCE INTENSIVE)',
                    'startTime' => $startTime->format($timeFormat),
                    'endTime' => now()->format($timeFormat),
                    'totalTimeRequired' => $this->getHumanReadableTimeDiffFromSeconds(now()->diffInSeconds($startTime))
                ]
            ]
        ]));*/
        return Command::SUCCESS;
    }
}
