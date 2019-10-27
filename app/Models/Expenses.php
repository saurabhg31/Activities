<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expenses extends Model
{
    protected $table = 'expenses';
    protected $fillable = ['expense', 'description', 'amount', 'status', 'deadline', 'log'];
    protected $hidden = ['id', 'log'];

    /**
     * List all expenses
     */
    protected static function list(array &$ids = null, array &$fields = ['*'], int &$paginate = null){
        $list = self::when($ids, function($query) use ($ids){
            return $query->whereIn('id', $ids);
        })->selectRaw(implode(',', $fields));
        if($paginate){
            return $list->paginate($paginate);
        }
        return $list->get();
    }
}
