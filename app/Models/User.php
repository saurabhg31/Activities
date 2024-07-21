<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'defaultWallpaperId',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Store default wallpaper image id in users table
     * @param integer $imageId
     * @param \App\Models\User $user
     * @return void|string
     */
    public static function setDefaultWallpaper(int $imageId, self $user)
    {
        if (!Images::where('id', $imageId)->exists()) {
            return 'Invalid image id provided!';
        }
        $user->defaultWallpaperId = $imageId;
        $user->save();
    }

    /**
     * Get default wallpaper data
     * @param \App\Models\User $user
     * @return string (base64 encoded image data)
     */
    public static function getDefaultWallpaperData(self $user)
    {
        if (is_null($user->defaultWallpaperId)) {
            return null;
        }
        $imageData = Images::find($user->defaultWallpaperId);
        return 'data:image/' . $imageData->imageType . ';base64, ' . $imageData->image;
    }
}
