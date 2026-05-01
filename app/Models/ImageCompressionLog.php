<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageCompressionLog extends Model
{
    use HasFactory;
    const UPDATED_AT = null;
    protected $fillable = ['image_id', 'old_extension', 'new_extension', 'old_filesize', 'new_filesize', 'filesize_diff', 'file_update_accepted', 'encountered_error', 'failure_reason'];

    /**
     * Add success log
     * @param integer $imageId
     * @param string $oldExtension
     * @param string $newExtension
     * @param integer $oldFilesize
     * @param integer #newFilesize
     * @param string $failureReason
     * @return void
     */
    public static function addLog(
        int $imageId,
        string $oldExtension,
        string $newExtension,
        int $oldFilesize,
        int $newFilesize,
        ?string $failureReason = null
    ): void {
        $fileUpdateAccepted = is_null($failureReason) && ($newFilesize < $oldFilesize);
        self::create([
            'image_id' => $imageId,
            'old_extension' => $oldExtension,
            'new_extension' => $newExtension,
            'old_filesize' => $oldFilesize,
            'new_filesize' => $newFilesize,
            'filesize_diff' => $oldFilesize - $newFilesize,
            'file_update_accepted' => $fileUpdateAccepted,
            'encountered_error' => !is_null($failureReason),
            'failure_reason' => $failureReason
        ]);
    }
}
