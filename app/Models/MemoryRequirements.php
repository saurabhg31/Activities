<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoryRequirements extends Model
{
    protected $table='memory_requirements';
    protected $fillable=[
        'table', 'operation', 'requirements'
    ];

    /**
     * add memory requiremnent
     */
    protected static function addRequirement(array &$requirements, string &$table = 'images', string &$operation = 'duplicateSearch'){
        $data = [
            'table' => $table,
            'operation' => $operation,
            'requirements' => json_encode($requirements)
        ];
        $record = self::where(array_except($data, ['requirements']));
        if($record->exists()){
            $record->delete();
        }
        return self::create($data);
    }

    /**
     * get memory cap
     * @param string $table
     * @param string $operation
     * @return object|null
     */
    protected static function getMemoryCap(string &$table = 'images', string &$operation = 'duplicateSearch'){
        $requirements = self::select('requirements')->where([
            'table' => $table,
            'operation' => $operation,
        ])->first();
        if($requirements){
            return json_decode($requirements->requirements);
        }
        return null;
    }
}
