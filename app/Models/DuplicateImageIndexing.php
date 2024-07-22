<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DuplicateImageIndexing extends Model
{
    use HasFactory;
    protected $table = 'image_duplicate_indexing';
    protected $fillable = ['id', 'image_id', 'duplicate_image_id'];
}
