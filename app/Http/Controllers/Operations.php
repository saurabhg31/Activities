<?php

namespace App\Http\Controllers;

use App\Jobs\CacheImageTagsAndSearchPrompts;
use App\Jobs\LogSearchQueries;
use App\Models\ImageIndex;
use App\Models\Images;
use App\Models\User;
use App\Models\SmokingCounter;
use App\system_files_in_use;
use App\Traits\Miscellaneous;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Operations extends Controller
{
    use Miscellaneous;

    /**
     * Process basic activities
     * @param string $type as requestType
     * @param \Illuminate\Http\Request $request
     * @return json response
     */
    protected function processActivity(string $type, Request $request)
    {
        try {
            if ($request->isMethod('GET')) {
                $validateData = $this->validateData($request->all(), $this->validationRules($type, 'GET'));
                if ($validateData->failed) {
                    return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
                }
                switch ($type) {
                    case 'imagesAdd':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, [
                                'images' => Images::list(),
                                'types' => Images::imageTypes()
                            ]),
                            $this->generateMsgBag($type)
                        );
                    case 'searchImages':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, ['search' => Images::list(), 'types' => Images::imageTypes()]),
                            $this->generateMsgBag($type, 'Ready to search', 'Search Images')
                        );
                    case 'imageTags':
                        return response()->json([
                            'tags' => ImageIndex::getCachedImageTags()
                        ]);
                    case 'expenses':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, [
                                'currentDate' => gmdate('Y-m-d', time())
                            ]),
                            [
                                'text' => 'Add an expense',
                                'heading' => 'Expenses'
                            ]
                        );
                    case 'updateTags':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, ['types' => Images::imageTypes()]),
                            $this->generateMsgBag($type, 'Ready to update tags in bulk', 'Update tags')
                        );
                    case 'viewDuplicates':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, ['images' => Images::showDuplicates(), 'types' => Images::imageTypes()]),
                            $this->generateMsgBag($type, 'Ready to delete duplicates', 'Duplicate Images')
                        );
                    case 'smokeCounter':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, [
                                'currentCount' => SmokingCounter::getCurrentCount(Auth::id()),
                                'list' => SmokingCounter::getList(Auth::id()),
                                'totalCount' => SmokingCounter::getTotalCount(Auth::id()),
                                'frequency' => SmokingCounter::getFrequency(Auth::id()),
                                'previousDatCount' => SmokingCounter::getPreviousDayCount(Auth::id()),
                                'dbl2c' => SmokingCounter::durationBetweenLastTwoCigarettes(Auth::id()),
                                'trend' => $this->getSmokingTrend(SmokingCounter::getTrend(Auth::id())),
                            ]),
                            $this->generateMsgBag($type, 'Ready to update smoking counter', 'Smoking Counter')
                        );
                    default:
                        return $this->sendError('Invalid type', ['type' => $type, 'method' => $request->method()], $this->accessDeniedResponseCode);
                }
            } elseif ($request->isMethod('POST')) {
                $validateData = $this->validateData($request->all(), $this->validationRules($type, 'POST'));
                if ($validateData->failed) {
                    return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
                }
                switch ($type) {
                    case 'imagesAdd':
                        set_time_limit(3600); // 60 mins
                        $images = $request->images;
                        $tags = $request->tags;
                        if (strpos($request->tags, 'links>') !== false) {
                            // TODO: currently rudimentary, make it more efficient & accurate
                            // parsing image links & tags
                            $images = explode('links>', $request->tags);
                            $images = array_map(function ($link) {
                                if (strpos($link, 'pbs.twimg.com') !== false) {
                                    // taking higer resolution pictures for twitter
                                    $link = str_replace('name=small', 'name=large', $link);
                                }
                                if (strpos($link, 'https://') !== false) {
                                    return str_replace(' ', '', str_replace('https://', 'http://', $link));
                                } elseif (strpos($link, 'http://') !== false) {
                                    return str_replace(' ', '', $link);
                                }
                            }, explode(',', next($images)));
                            $images = array_filter($images);
                            $tags = explode('tags>', $request->tags);
                            $tags = next($tags);
                        }
                        $addedImagesCount = $this->addImages($images, $tags, $request->type, $request->domain);
                        CacheImageTagsAndSearchPrompts::dispatch(Auth::id(), $request->domain);
                        return $this->sendResponse(
                            $addedImagesCount ? null : 'Unable to add images',
                            $this->renderView($type, [
                                'images' => Images::list(),
                                'types' => Images::imageTypes()
                            ]),
                            $this->generateMsgBag($type, $addedImagesCount . ' image(s) added', 'Current images')
                        );
                    case 'searchImages':
                        $search = Images::search($requestData = $request->all());
                        $domain = Session::get('domain') ?? 'public';
                        LogSearchQueries::dispatch($requestData, $domain);
                        if (!is_null($requestData['tags'])) {
                            CacheImageTagsAndSearchPrompts::dispatch(Auth::id(), $domain);
                        }
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, [
                                'search' => $search,
                                'types' => Images::imageTypes(),
                                'selectedType' => $request->types,
                                'selectedTags' => $request->tags
                            ]),
                            $this->generateMsgBag($type, $search->response, 'Search Images')
                        );
                    case 'expenses':
                        return $this->sendResponse(
                            null,
                            $this->renderView($type, [
                                'currentDate' => gmdate('Y-m-d', time())
                            ]),
                            [
                                'text' => 'Add an expense',
                                'heading' => 'Expenses'
                            ]
                        );
                    case 'smokeCounter':
                        if ($request->increment) {
                            SmokingCounter::create([
                                'user_id' => Auth::id(),
                                'cigarette_name' => $request->cigarette_name ?? 'Generic Cigarette'
                            ]);
                            return $this->sendResponse(
                                null,
                                $this->renderView($type, [
                                    'currentCount' => SmokingCounter::getCurrentCount(Auth::id()),
                                    'list' => SmokingCounter::getList(Auth::id()),
                                    'totalCount' => SmokingCounter::getTotalCount(Auth::id()),
                                    'frequency' => SmokingCounter::getFrequency(Auth::id()),
                                    'previousDatCount' => SmokingCounter::getPreviousDayCount(Auth::id()),
                                    'dbl2c' => SmokingCounter::durationBetweenLastTwoCigarettes(Auth::id()),
                                    'trend' => $this->getSmokingTrend(SmokingCounter::getTrend(Auth::id())),
                                ]),
                                $this->generateMsgBag($type, 'Smoking event added', 'Smoking Counter')
                            );
                        } else {
                            throw new Exception('Invalid request: increment must be true to add a smoking event.');
                        }
                    default:
                        return $this->sendError('Invalid type', ['type' => $type, 'method' => $request->method()], $this->accessDeniedResponseCode);
                }
            }
        } catch (QueryException $error) {
            report($error);
            return $this->sendError('Something went wrong', ['msg' => $error->getMessage()]);
        }
    }

    /**
     * to remove images one at a time
     */
    protected function removeImage(Request $request)
    {
        $validateData = $this->validateData($request->all(), $this->validationRules('removeImage', 'GET'));
        if ($validateData->failed) {
            return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
        }
        try {
            $deletedCount = Images::deleteImages([$request->imageId]);
            CacheImageTagsAndSearchPrompts::dispatch(Auth::id(), Session::get('domain') ?? 'public');
            return $this->sendResponse($deletedCount, null);
        } catch (QueryException $error) {
            return $this->sendError($error->getMessage());
        }
    }

    /**
     * get image edit form params
     */
    protected function getImageEditForm(Request $request)
    {
        $validateData = $this->validateData($request->all(), $this->validationRules('imageEdit', $request->method()));
        if ($validateData->failed) {
            return $this->sendError($this->validationFailedMsg, $validateData->messages, $this->validationErrorResponseCode);
        }
        if ($request->isMethod('GET')) {
            $imageData = Images::select(['id', 'user_id', 'type', 'tags'])->where('id', $request->imageId)->first();
            if ($imageData->user_id && $imageData->user_id !== Auth::id()) {
                return $this->sendError('You do not own this image', null, $this->accessDeniedResponseCode);
            }
            $data = array(
                'imageTypes' => Images::imageTypes(),
                'imageData' => $imageData
            );
            return $this->renderView('imageEdit', $data);
        } elseif ($request->isMethod('POST')) {
            if (Images::updateImageInfo(Arr::except($request->all(), ['_token']))) {
                CacheImageTagsAndSearchPrompts::dispatch(Auth::id(), Session::get('domain') ?? 'public');
                return $this->sendResponse(null, null, [
                    'text' => 'Image information updated',
                    'heading' => 'Operation complete.'
                ]);
            } else {
                return $this->sendError('Unable to update', null, $this->serverErrorResponseCode);
            }
        }
        return $this->sendError('Invalid request type', null, $this->accessDeniedResponseCode);
    }

    /**
     * detect duplicates and remove them (sql search)
     * @param int $limit: specifies how many images to load in one iteration
     * TODO: extensive testing
     */
    public function removeDuplicateImages(int $limit = 10, int $skip = 0)
    {
        try {
            print(PHP_EOL . 'Searching for duplicates in ' . Images::count() . ' images' . PHP_EOL . 'Loading ' . $limit . ' images for searching...' . str_repeat(PHP_EOL, 2));
            $imageIds = Images::select('id')->limit($limit)->when($skip, function ($query) use ($skip) {
                return $query->skip($skip);
            })->get();
            if (!isset($imageIds[0])) {
                print('Unable to retrieve images data. !' . PHP_EOL);
                return false;
            }
            $ignoreIds = $duplicateIds = array();
            foreach ($imageIds as $imageId) {
                print('Searching for duplicates of image: ' . $imageId->id . PHP_EOL);
                $imageData = Images::findOrFail($imageId->id);
                $duplicates = Images::listDuplicatesOf($imageData, $ignoreIds);
                if (isset($duplicates[0])) {
                    array_push($ignoreIds, $imageData->id);
                    print((array_key_last($duplicates->toArray()) + 1) . ' duplicates found' . PHP_EOL);
                    foreach ($duplicates as $duplicate) {
                        array_push($duplicateIds, $duplicate->id);
                    }
                } else {
                    print('No duplicates found for image: ' . $imageData->id . PHP_EOL);
                }
            }
            if (isset($duplicateIds[0])) {
                print((array_key_last($duplicateIds) + 1) . ' found for ' . (array_key_last($ignoreIds) + 1) . ' images. Deleting...' . PHP_EOL);
                $deleteImages = Images::whereIn('id', $duplicateIds)->whereNotIn('id', $ignoreIds)->delete();
                print($deleteImages . ' images deleted.' . PHP_EOL);
            }
            print('Process complete' . PHP_EOL);
            $imageIds = $ignoreIds = $duplicateIds = null;
            return true;
        } catch (Exception $error) {
            print('Error: ' . $error->getMessage());
            $imageIds = $ignoreIds = $duplicateIds = null;
            return false;
        }
    }

    /**
     * detect duplicates and remove them (processor search)
     * Note: fast but resource intensive
     */
    public function fastRemoveDuplicateImages()
    {
        try {
            print('Need to load ' . Images::count() . ' images for search. This could take a while...' . PHP_EOL . 'Do you want to proceed ? (yes/no)' . PHP_EOL);
            $reply = $this->getInput();
            if (strtolower($reply) !== 'yes') {
                print('Process aborted by user.' . PHP_EOL);
                return false;
            }
            if (!$this->runRequirementsCheck()) {
                print('Process aborted by user.' . PHP_EOL);
                return false;
            }
            print('Loading images to memory...');
            $freeMemory = memory_get_usage();
            $images = Images::select(['id', 'image'])->orderBy('created_at', 'desc')->get();
            print('Loaded.' . PHP_EOL);
            print('Converting images object to array for faster traversing in PHP...');
            $images = $images->toArray();
            print('Converted.' . PHP_EOL);
            $this->storeTotalImagesDataInfo(memory_get_usage() - $freeMemory);
            print('Image data loaded for searching.' . PHP_EOL);
            $duplicateIds = $ignoreIds = array();
            foreach ($images as $image) {
                print('Searching duplicates of image: ' . $image['id'] . PHP_EOL);
                foreach ($images as $searchImg) {
                    if (
                        ($image['id'] !== $searchImg['id']) && (in_array($searchImg['id'], $duplicateIds) === false) && (in_array($searchImg['id'], $ignoreIds) === false) && ($image['image'] === $searchImg['image'])
                    ) {
                        array_push($duplicateIds, $searchImg['id']);
                        print('Duplicate for image: ' . $image['id'] . ' found. Image: ' . $searchImg['id'] . PHP_EOL);
                    }
                }
                array_push($ignoreIds, $image['id']);
                print('Search completed for image: ' . $image['id'] . PHP_EOL);
            }
            print('Search complete. ' . count($duplicateIds) . ' duplicates found.' . PHP_EOL);
            if (isset($duplicateIds[0])) {
                print('Deleting images: ' . implode(',', $duplicateIds) . '...' . PHP_EOL);
                print(Images::whereIn('id', $duplicateIds)->delete() . ' images deleted.' . PHP_EOL);
            }
            print('Clearing memory. Please wait...' . PHP_EOL);
            $images = $duplicateIds = $ignoreIds = null;
            unset($images);
            print('Memory freed. Exiting...' . PHP_EOL);
            return true;
        } catch (Exception $error) {
            print('Error: ' . $error->getMessage() . PHP_EOL);
            return false;
        }
    }

    /**
     * list of files currently being accessed
     */
    public function listFilesCurrentlyInUse(bool $grantSuperUserAccess = false, $filter = null)
    {
        $currentlyOpenFiles = array();
        $totalFiles = null;
        $command = $grantSuperUserAccess ? 'sudo lsof' : 'lsof';
        // $command = $grantSuperUserAccess ? 'sudo -u root -S lsof < '.storage_path().'/app/myPass.secret' : 'lsof';
        foreach (explode(PHP_EOL, shell_exec($command)) as $index => $line) {
            if (!empty($line)) {
                if ($filter) {
                    array_push($currentlyOpenFiles, $filter($line));
                } else {
                    array_push($currentlyOpenFiles, str_replace('lsof', '', $line));
                }
                $totalFiles = $index + 1;
            }
        }
        $headers = array_values(array_filter(explode(' ', Arr::first($currentlyOpenFiles))));
        $data = array();
        array_shift($currentlyOpenFiles);
        // sending data for storage in database
        system_files_in_use::store($headers, $currentlyOpenFiles);
        foreach ($currentlyOpenFiles as $fileRow) {
            if (strpos($fileRow, 'Permission denied') === false) {
                array_push($data, array_values(array_filter(explode(' ', $fileRow))));
            }
        }
        return [
            // 'files currently being accessed by some program' => $currentlyOpenFiles,
            'file count' => $totalFiles,
            'headers' => implode(',', $headers),
            // 'data' => $data,
            // 'dataForStorage' => array_merge($headers, $data),
            'stored in' => $this->generateSpreadsheet($data, $headers, 'CurrentlyOpenFiles_' . gmdate('Y-m-d_H:i:s', time()) . ($grantSuperUserAccess ? ' (super user access enabled)' : null))
        ];
    }

    /**
     * Sets passed image id as wallpaper for public domain
     * @param integer $imageId
     * @return json response
     */
    public function useImageAsWallpaper(int $imageId, Request $request)
    {
        try {
            DB::beginTransaction();
            $status = User::setDefaultWallpaper($imageId, $request->user());
            if (is_string($status)) {
                return $this->sendError($status, ['imageId' => $imageId], 502);
            }
            DB::commit();
            return $this->sendResponse(null, null, ['text' => 'Refreshing page.', 'heading' => 'Wallpaper set successfully']);
        } catch (Exception $error) {
            DB::rollBack();
            Log::error($error->getMessage(), $error->getTrace());
            return $this->sendError('Unable to set wallpaper, all changes rolled back!');
        }
    }

    /**
     * Download image by id
     * @param integer $imageId
     * @return StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function downloadImage(int $imageId): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $imageData = Images::findOrFail($imageId);
        if ($imageData->user_id) {
            if ($imageData->user_id !== Auth::id() || !Session::has('domain') || strtolower(Session::get('domain')) !== 'private') {
                return $this->sendError('404: Image not found!', null, $this->accessDeniedResponseCode);
            }
        }
        return response()->streamDownload(function () use (&$imageData) {
            echo base64_decode($imageData->image, true);
        }, $imageData->id . '.' . $imageData->imageType);
    }

    /**
     * logic to generate smoking trend data status
     * @param Collection $smokingTrendData
     * @return array
     * @source: qwen3.6-27b-mtp (Local LLM)
     */
    private function getSmokingTrend(Collection $smokingTrendData, ?float $targetGoal = null, ?int $daysToReach = null): array
    {
        $targetGoal = is_null($targetGoal) ? config('constants.MAX_DAILY_CIGARETTE_GOAL') : $targetGoal;

        if (is_null($daysToReach)) {
            $targetDate = config('constants.CIGARETTE_TARGET_GOAL_DATE');
            if (is_null($targetDate)) {
                throw new RuntimeException('Target date to reach smoking goal not set! Please set CIGARETTE_TARGET_GOAL_DATE in constants.');
            }
            $targetDate = Carbon::parse($targetDate)->endOfDay();
            if ($targetDate->isPast()) {
                throw new RuntimeException('Target goal date is in the past! Please update CIGARETTE_TARGET_GOAL_DATE in constants.');
            }
            $daysToReach = now()->diffInDays($targetDate);
        }

        $collection = collect($smokingTrendData);
        unset($smokingTrendData);
        $count = $collection->count();

        // Default values
        $color = 'black';
        $status = 'N/A';

        if ($count >= 2) {
            // 🔑 FIX: Reindex keys after reverse to ensure sequential x-values (1,2,3...)
            $chronological = $collection->reverse()->values();

            // Prepare data points: x = day index, y = cigarette count
            $dataPoints = [];
            foreach ($chronological as $idx => $day) {
                $x = $idx + 1;
                $y = (float) ($day['total_cigarettes'] ?? 0);
                $dataPoints[] = ['x' => $x, 'y' => $y];
            }

            // Linear regression sums (high precision)
            $n = count($dataPoints);
            $sumX = $sumY = $sumXY = $sumX2 = 0.0;

            foreach ($dataPoints as $point) {
                $sumX += $point['x'];
                $sumY += $point['y'];
                $sumXY += $point['x'] * $point['y'];
                $sumX2 += $point['x'] ** 2;
            }

            $denominator = ($n * $sumX2) - ($sumX ** 2);

            if ($denominator > 0.0001) { // Prevent division by near-zero
                $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;

                // 1. Check GOAL first (recent half average ≤ threshold)
                $half = ceil($count / 2);
                $recentAverage = $collection->take($half)->avg('total_cigarettes');

                if ($recentAverage <= $targetGoal) {
                    $color = 'blue';
                    $status = 'GOAL';
                } else {
                    // 2. Dynamic tolerance (4 decimal precision for accuracy)
                    $currentAvg = $collection->avg('total_cigarettes');
                    $flatTolerance = $this->calculateSmokingTolerance($currentAvg, $targetGoal, $daysToReach);

                    if ($slope < -$flatTolerance) {
                        $color = 'blue';
                        $status = 'UP'; // Negative slope = decreasing consumption
                    } elseif ($slope > $flatTolerance) {
                        $color = 'red';
                        $status = 'DOWN'; // Positive slope = increasing consumption
                    } else {
                        $color = 'purple';
                        $status = 'FLAT'; // Within tolerance → normal variance
                    }
                }
            }
        }

        // 3. Calculate wait time using EXACT timestamps (maximum accuracy)
        $waitSeconds = $this->calculateWaitTime(
            $collection,
            $targetGoal,
            $daysToReach,
            SmokingCounter::getLastSmokedCigaretteTime(Auth::id())
        );

        // 4. Return consistent array
        return [
            'color' => $color,
            'status' => $status,
            'waitTime' => $this->getHumanReadableTimeDiffFromSeconds($waitSeconds),
        ];
    }


    /**
     * Calculates optimal tolerance for linear regression trend detection.
     * 
     * @param float $currentAvg  Current average daily cigarettes
     * @param float $targetGoal  Desired daily cigarette count (e.g., 5)
     * @param int   $daysToReach Number of days to reach the goal
     * @return float             Dynamic tolerance value
     * @source: qwen3.6-27b-mtp (Local LLM)
     */
    private function calculateSmokingTolerance(float $currentAvg, float $targetGoal, int $daysToReach): float
    {
        $requiredDailyChange = ($currentAvg - $targetGoal) / max(1, $daysToReach);
        $tolerance = abs($requiredDailyChange) * 0.4;

        // Clamp with 4-decimal precision for mathematical accuracy
        return max(0.1, min(0.5, round($tolerance, 4)));
    }

    /**
     * Calculates seconds to wait before next cigarette based on daily pacing.
     * @param Collection $collection
     * @param float $targetGoal
     * @param Carbon $lastCigaretteTimestamp
     * @source: qwen3.6-27b-mtp (Local LLM)
     */
    private function calculateWaitTime(SupportCollection $collection, float $targetGoal, int $daysToReach, ?Carbon $lastCigaretteTimestamp): int
    {
        if (!$lastCigaretteTimestamp) {
            return 0;
        }

        $now = now();
        $todayDate = $now->format('Y-m-d');

        // Count cigarettes already logged for today in aggregated data
        $cigarettesToday = (int) $collection->filter(fn($day) => $day['smoke_date'] === $todayDate)->sum('total_cigarettes');

        // Add +1 if the last smoked cigarette belongs to today but isn't yet in aggregated data
        if ($lastCigaretteTimestamp->format('Y-m-d') === $todayDate) {
            $cigarettesToday++;
        }

        // 🔑 FORWARD-LOOKING PACING: Projects allowance from TODAY toward goal
        $currentAvg = $collection->avg('total_cigarettes') ?? 0;
        $requiredDailyReduction = ($currentAvg - $targetGoal) / max(1, $daysToReach);

        // Today's allowance: Current average minus one step of required reduction
        // Ensures active progress toward target each day
        $allowedToday = max($targetGoal, (int) round($currentAvg - $requiredDailyReduction));

        if ($cigarettesToday >= $allowedToday) {
            // Daily limit reached. Wait until tomorrow's 00:00:00 reset
            return (int) $now->diffInSeconds(Carbon::tomorrow());
        } else {
            $remainingAllowed = max(1, $allowedToday - $cigarettesToday);
            $secondsLeftInDay = (int) $now->diffInSeconds($now->copy()->endOfDay());

            // Evenly pace remaining cigarettes across remaining time in the day
            return max(0, (int) floor($secondsLeftInDay / $remainingAllowed));
        }
    }
}
