<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageCompressionLog extends Model
{
    use HasFactory;
    const UPDATED_AT = null;
    protected $fillable = ['image_id', 'old_extension', 'new_extension', 'old_filesize', 'new_filesize', 'filesize_diff', 'reduction', 'file_update_accepted', 'encountered_error', 'failure_reason'];

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
        if ($failureReason && strlen($failureReason) > 2000) {
            $failureReason = substr($failureReason, 0, 1996) . ' ...';
        }
        $fileUpdateAccepted = is_null($failureReason) && ($newFilesize < $oldFilesize);
        $reduction = 0.00;
        if ($newFilesize < $oldFilesize) {
            $reduction = (($oldFilesize - $newFilesize) / $oldFilesize) * 100;
        }
        self::create([
            'image_id' => $imageId,
            'old_extension' => $oldExtension,
            'new_extension' => $newExtension,
            'old_filesize' => $oldFilesize,
            'new_filesize' => $newFilesize,
            'filesize_diff' => $oldFilesize - $newFilesize,
            'reduction' => $reduction,
            'file_update_accepted' => $fileUpdateAccepted,
            'encountered_error' => !is_null($failureReason),
            'failure_reason' => $failureReason
        ]);
    }
}
