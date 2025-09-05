<?php

use App\Http\Controllers\CharController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UnitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HintController;
use App\Http\Controllers\EnglishHintController;
use App\Http\Controllers\JapaneseHintController;

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
Route::get('chars/{id}', [CharController::class, 'show']);
Route::post('chars', [CharController::class, 'store']);
Route::post('chars/{id}/comment', [CharController::class, 'storeComment']);
Route::post('comments/{id}/interactive', [CommentController::class, 'interactive']);
Route::post('reports', [ReportController::class, 'store']);
Route::get('books', [CharController::class, 'books']);
Route::get('units', [UnitController::class, 'units']);
Route::get('/hints', [HintController::class, 'getPhoneticHints']);
Route::get('/hints/english', [EnglishHintController::class, 'getPhoneticHints']);
Route::get('/hints/japanese', [JapaneseHintController::class, 'getPhoneticHints']);
Route::get('/exact', [CharController::class, 'exactSearch']); // New route for exact search
Route::get('char/{id}/radicals-tree', [CharController::class, 'radicalsTree']);
Route::post('/check-missing-words', [CharController::class, 'checkMissingWords']);
Route::post('chars/multi-exact-search', [CharController::class, 'multiExactSearch']);