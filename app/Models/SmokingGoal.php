<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SmokingGoal extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'goal_count', 'goal_reach_date'];

    /**
     * Fetch current goal data
     * @param integer $userId
     * @return self|null
     */
    public static function getCurrentGoalData(int $userId): self|null
    {
        return self::where('user_id', $userId)->first();
    }

    /**
     * Save new goal data to table
     * @param integer $userId
     * @param array $data | example: ['goal_count' => 2, 'goal_reach_date' => '2026-08-31']
     * @return void
     */
    public static function saveNewGoal(int $userId, array $data): void
    {
        $fillables = array_values(array_filter((new self)->getFillable(), function ($value) {
            return $value !== 'user_id';
        }));
        $record = self::where('user_id', $userId)->first();
        if ($record) {
            foreach ($fillables as $column) {
                if (!isset($data[$column])) {
                    throw new InvalidArgumentException("Invalid data array, data field: {$column} not found or it's value is null!");
                }
                $record->$column = $data[$column];
            }
            $record->save();
            return;
        }
        $data = array_intersect_key($data, array_flip($fillables));
        self::create(array_merge(['user_id' => $userId], $data));
    }
}
