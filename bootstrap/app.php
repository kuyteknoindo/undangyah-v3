<?php

use App\Http\Middleware\CloudflareIp;
use App\Http\Middleware\ModePemeliharaan;
use App\Http\Middleware\PastikanPeran;
use App\Http\Middleware\PengamanAkun;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lalu lintas masuk lewat cloudflared -> nginx (127.0.0.1); hanya proxy lokal yang dipercaya.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->prepend(CloudflareIp::class);

        $middleware->web(append: [ModePemeliharaan::class, PengamanAkun::class]);
        $middleware->alias(['peran' => PastikanPeran::class]);
        // Cek peran sebelum route-model binding: pengguna biasa harus mendapat 403 (bukan 404 vs 403 yang membocorkan id yang ada).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: PastikanPeran::class);
        $middleware->redirectGuestsTo(fn () => route('masuk'));
        $middleware->redirectUsersTo(fn () => route('beranda'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
