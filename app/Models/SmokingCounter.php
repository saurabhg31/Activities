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
            ->orderBy('created_at', 'desc')
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
        return (new self)->getHumanReadableTimeDiffFromSeconds($frequencyData->avg_seconds_between);
    }
}
