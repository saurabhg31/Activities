<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class system_files_in_use extends Model
{
    protected $table = 'system_files_in_use';
    protected $fillable = [
        'command','pid','tid','taskcmd','user','fd','type','device','size_off','node','name'
    ];

    protected static function store(array &$columns, array &$data){
        $columns = array_map(function($column){
            return strtolower(str_replace('/', '_', $column));
        }, $columns);
        $count = 0;
        dd($columns, array_first($data));
        dd(array_first(array_unshift($data, $columns)));
        // forming data base insertion array
        $dbData = array();
        foreach($data as $fileData){
            if(strpos($fileData, '(readlink:')){
                // dd()
                $count++;
            }
            // array_push($dbData, [
            //     'command' => 
            // ]);
        }
        dd(array_filter(explode(' ', array_first($data))), $count, count($data));
    }
}
