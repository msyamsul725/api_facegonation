<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FaceRecognitionController;

/*
Important:
https://securinglaravel.com/p/security-tip-laravel-11s-middleware

*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api')->group(function () {

    Route::post('/recognize-face', [FaceRecognitionController::class, 'checkFaceRecognition']);

});
