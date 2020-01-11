<?php

namespace App\Models;

use App\Http\Controllers\Controller;
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

    /**
     * append extra required memory to memory requirements
     */
    protected static function appendExtraDataToRequirements(int &$extraDataSizeInBytes, string &$table = 'images', string &$operation = 'duplicateSearch'){
        $requirements = self::select(['id', 'requirements'])->where([
            'table' => $table,
            'operation' => $operation,
        ])->first();
        if($requirements){
            $requirementsId = $requirements->id;
            $requirements = json_decode($requirements->requirements);
            $extraDataSizeInGb = (new Controller())->convertDataSizes($extraDataSizeInBytes);
            $requirements->minimumFreeMemory += $extraDataSizeInGb;
            $requirements->recommendedFreeMemory += $extraDataSizeInGb;
            return self::find($requirementsId)->update([
                'requirements' => json_encode($requirements)
            ]);
        }
        return false;
    }
}
