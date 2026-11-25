<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;

Route::get('/', [PageController::class, 'login'])->name('login');
Route::get('/{page}.php', [PageController::class, 'show'])
    ->where('page', 'admin|dashboard|deployment-detail|deployments|index|orders|profile|repair-detail|repairs|scanner|settings|shop|stocks')
    ->name('pages.php');
Route::get('/{page}.html', function (string $page) {
    return redirect('/'.$page.'.php', 301);
})->where('page', 'admin|dashboard|deployment-detail|deployments|index|orders|profile|repair-detail|repairs|scanner|settings|shop|stocks');
