<?php

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
Auth::routes(['verify' => true]);
Route::middleware(['verified'])->group(function(){
    Route::get('/home', 'HomeController@index')->name('home');
    Route::any('/operation/{type}', 'Operations@processActivity');
    Route::get('/removeImage', 'Operations@removeImage');
    Route::post('/authorizeCriticalOperation', 'Auth\VerificationController@authorizeCriticalOperation');
    Route::post('/switchDomain', 'Auth\VerificationController@switchDomain');
    Route::any('/getImageEditForm', 'Operations@getImageEditForm');
    /**
     * Routes for getting data processing hardware & software requirements (in progress)
     */
    Route::get('/getRequirements', 'Operations@getRequirements');
});