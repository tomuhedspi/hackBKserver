<?php

use App\Http\Controllers\CharController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('chars', [CharController::class, 'index']);
Route::post('chars', [CharController::class, 'store']);
Route::post('chars/{id}/comment', [CharController::class, 'storeComment']);
Route::post('comments/{id}/interactive', [CommentController::class, 'interactive']);
Route::post('reports', [ReportController::class, 'store']);
Route::get('books', [CharController::class, 'books']);
