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
        $timeFormat = 'Y-m-d H:i a p';
        $startTime = now()->format($timeFormat);
        $this->printHeading('HARD IMAGE DUPLICATE SEARCH STARTED AT ' . $startTime);
        $duplicateCount = $processed = $progress = 0;
        $totalImages = Images::count();
        $needleImg = Images::first();
        $duplicateIds = $duplicateIdBag = [];
        $etaInSeconds = $loopStartTime = false;
        while ($needleImg) {
            $loopStartTime = now();
            $statusMsg = 'Searching for duplicates of image with id: ' . number_format($needleImg->id) . '. ';
            $statusMsg .= str_repeat('.', floor($progress * 20)) . ' ' . number_format($progress * 100, 5);
            $statusMsg .= ' % ---- Duplicates: ' . number_format($duplicateCount);
            $statusMsg .= ' ---- ( ' . number_format($processed + 1) . ' / ' . number_format($totalImages) . ' )';
            if ($etaInSeconds !== false) {
                $statusMsg .= ' ---- ETA: ' . $this->getHumanReadableTimeDiffFromSeconds($etaInSeconds);
            }
            $this->printLine($statusMsg, 1, true);
            $duplicateIds = Images::select('id')->where('id', '!=', $needleImg->id)
                ->where('image', 'like', '%' . $needleImg->image . '%')->get()->pluck('id')->toArray();
            if (!empty($duplicateIds)) {
                /*array_push($duplicateIdBag, [
                    'original' => $needleImg->id,
                    'duplicates' => $duplicateIds
                ]);*/
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
                    'startTime' => $startTime,
                    'endTime' => now()->format($timeFormat),
                    'totalTimeRequired' => $this->getHumanReadableTimeDiffFromSeconds(now()->diffInSeconds($startTime))
                ]
            ]
        ]));*/
        return Command::SUCCESS;
    }
}
