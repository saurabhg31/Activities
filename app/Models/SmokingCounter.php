<?php

namespace App\Models;

use App\Traits\Miscellaneous;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SmokingCounter extends Model
{
    use HasFactory, Miscellaneous;
    protected $fillable = ['user_id', 'cigarette_name', 'created_at'];
    const UPDATED_AT = null; // Disable the updated_at timestamp

    /**
     * Get the current count of smoking events for the authenticated user.
     */
    public static function getCurrentCount(int $userId)
    {
        return self::where('user_id', $userId)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
    }

    /**
     * Get the list of smoking events for the authenticated user.
     */
    public static function getList(int $userId)
    {
        return self::where('user_id', $userId)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get the total count of smoking events for the authenticated user.
     */
    public static function getTotalCount(int $userId)
    {
        return self::where('user_id', $userId)->count();
    }

    /**
     * Get the frequency of smoking events for the authenticated user.
     */
    public static function getFrequency(int $userId)
    {
        $frequencyData = self::selectRaw('TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) / (COUNT(*) - 1) AS avg_seconds_between')->where('user_id', $userId)->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->havingRaw('COUNT(*) > 1;')->get()->first();
        if (!$frequencyData) {
            return null;
        }
        return (new self)->getHumanReadableTimeDiffFromSeconds($frequencyData->avg_seconds_between);
    }

    /**
     * Get previous day smoking count for the authenticated user.
     */
    public static function getPreviousDayCount(int $userId)
    {
        $yesterday = now()->subDay();
        return self::where('user_id', $userId)
            ->whereBetween('created_at', [
                $yesterday->copy()->startOfDay(),
                $yesterday->copy()->endOfDay()
            ])->count();
    }

    /**
     * Get the duration between the last two cigarettes for the authenticated user.
     */
    public static function durationBetweenLastTwoCigarettes(int $userId)
    {
        $lastTwoRowData = self::where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->take(2)
            ->get();
        if ($lastTwoRowData->count() < 2) {
            return 'N/A';
        }
        $timeDiff = $lastTwoRowData[0]->created_at->diffInSeconds($lastTwoRowData[1]->created_at);
        return (new self)->getHumanReadableTimeDiffFromSeconds($timeDiff);
    }

    /**
     * Get trend
     * @param integer $userId
     * @return Collection
     */
    public static function getTrend(int $userId): Collection
    {
        return self::selectRaw('DATE(created_at) AS smoke_date, COUNT(*) AS total_cigarettes')->where('user_id', $userId)->groupBy('smoke_date')->orderBy('smoke_date', 'desc')->get();
    }

    /**
     * Get last smoked cigarette timestamp
     */
    public static function getLastSmokedCigaretteTime(int $userId)
    {
        $record = self::select('created_at')->where('user_id', $userId)->orderBy('id', 'desc')->first();
        if ($record) {
            return $record->created_at;
        }
        return null;
    }

    /**
     * Get Smoking weekly logs
     * @param integer $userId
     * @return Collection
     */
    public static function getSmokingWeeklyLogs(int $userId): Collection
    {
        return self::selectRaw('DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY) AS week_start_date, DATE_ADD(DATE_SUB(DATE(created_at), INTERVAL WEEKDAY(created_at) DAY), INTERVAL 6 DAY) AS week_end_date, COUNT(*) AS total_cigarettes')->where('user_id', $userId)->whereRaw("created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND created_at < DATE_FORMAT(CURRENT_DATE() + INTERVAL 1 MONTH, '%Y-%m-01')")->groupBy(['week_start_date', 'week_end_date'])->orderByDesc('week_start_date')->get();
    }

    /**
     * Get smoking data of last 24 hours sorted by created_at
     * @param integer $userId
     * @return Collection
     */
    public static function getLast24HourData(int $userId): Collection
    {
        return self::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('id')
            ->get()
            ->sortBy('created_at');
    }

    /**
     * Get BINGE smoking data
     * @param integer $userId
     * @return Collection
     */
    public static function getBingeSmokingData(int $userId): Collection
    {
        return self::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(config('constants.CIGARETTE_BINGE_RESET_HOURS')))
            ->orderByDesc('id')
            ->get()
            ->sortBy('created_at');
    }

    /**
     * Get smoking logs for a specific day
     * @param int $userId
     * @param Carbon|string $date
     * @return Collection
     */
    public static function getDayData(int $userId, Carbon|string $date): Collection
    {
        if ($date instanceof Carbon) {
            $date = $date->format('Y-m-d');
        }
        return self::where('user_id', $userId)->whereDate('created_at', $date)
            ->orderBy('id', 'asc')->get();
    }
}
