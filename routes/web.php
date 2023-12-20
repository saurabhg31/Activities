<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Operations;
use App\Http\Controllers\StatisticsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

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
    Session::forget('domain');
    return view('welcome');
});

Auth::routes();

Route::get('/email/verify', function () {
    return view('auth.verify');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect('/home');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('resent', true);
})->middleware(['auth', 'throttle:6,1'])->name('verification.resend');

Route::middleware(['auth', 'verified'])->group(function () {
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
    Route::get('/statistics', [StatisticsController::class, 'loadStatiscsDashboard']);
});
