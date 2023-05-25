<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Operations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Auth::routes();
Route::middleware('auth')->group(function(){
    Route::any('/password/change', [PasswordController::class, 'changePassword'])->name('changePassword');
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::any('/operation/{type}', [Operations::class, 'processActivity']);
    Route::get('/removeImage', [Operations::class, 'removeImage']);
    Route::post('/authorizeCriticalOperation', [VerificationController::class, 'authorizeCriticalOperation']);
    Route::any('/switchDomain', [VerificationController::class, 'switchDomain'])->name('switchDomain');
    Route::any('/getImageEditForm', [Operations::class, 'getImageEditForm']);
    /**
     * Routes for getting data processing hardware & software requirements (in progress)
     */
    Route::get('/getRequirements', [Operations::class, 'getRequirements']);
});
