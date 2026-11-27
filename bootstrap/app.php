<?php

use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // =======================================================
        // TAMBAHKAN KODE INI UNTUK MENDAFTARKAN MIDDLEWARE SPATIE
        // =======================================================
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
        // =======================================================
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tangkap error 419 (Token Mismatch) dan arahkan kembali dengan notifikasi
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi halaman telah berakhir. Silakan muat ulang halaman.'
                ], 419);
            }

            return redirect()
                ->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', 'Sesi halaman telah berakhir karena terlalu lama tidak aktif. Silakan klik simpan kembali.');
        });
    })->create();
