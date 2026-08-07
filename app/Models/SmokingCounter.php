<?php

namespace App\Models;

use App\Traits\Miscellaneous;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            ->whereBetween('created_at', [$yesterday->startOfDay(), $yesterday->endOfDay()])
            ->count();
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
}
