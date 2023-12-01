<?php

use App\Http\Controllers\Controller;
use App\Services\SSO\Middleware as SSOMiddleware;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

$brokerId = 'cbae3a4d-f976-4ddc-8a4a-ebe26fc49c82';
$brokerSecret = '7kspxFFs1tUauiggXktlSHEAK8SFSzaO';
$brokerUrl = 'https://aller-id.test/sso/auth';

$encrypter = new Encrypter($brokerSecret, config('app.cipher'));

Route::group([], function() {
    Route::get('/', [Controller::class, 'index'])->name('index');
    Route::withoutMiddleware(SSOMiddleware::class)->post('/auth', [Controller::class, 'login'])->name('login');
    Route::withoutMiddleware(SSOMiddleware::class)->get('/auth/validate', [Controller::class, 'validate'])->name('login.validate');
    Route::withoutMiddleware(SSOMiddleware::class)->get('/auth/logout', [Controller::class, 'logout'])->name('logout');
});
