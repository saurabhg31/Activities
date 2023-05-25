<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserExpenses extends Model
{
    protected $table = 'user_expenses';
    protected $fillable = [
        'expense_id', 'user_id'
    ];
}
