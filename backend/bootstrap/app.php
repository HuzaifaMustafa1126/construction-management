<?php

use App\Http\Middleware\EnsureAccountAccess;
use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account.access' => EnsureAccountAccess::class,
            'company.context' => EnsureCompanyContext::class,
            'platform.admin' => EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/v1/tasks/*/attachments', 'api/v1/tasks/*/attachments/*')
                || $exception instanceof AuthenticationException
                || $exception instanceof ValidationException) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $message = match ($status) {
                403 => 'This action is unauthorized.',
                404 => 'The task, attachment, or file was not found.',
                413 => 'The upload is too large. The maximum attachment size is 10 MB.',
                415 => 'Only supported images can be previewed.',
                503 => 'The attachment operation could not finish. Retry the request.',
                default => 'The attachment request could not be completed.',
            };

            $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

            return response()->json(['message' => $message], $status, $headers);
        });

        // Always return JSON for API errors
        $exceptions->shouldRenderJsonWhen(
            function ($request, $exception) {
                return $request->is('api/*')
                    || $request->expectsJson();
            }
        );

    })->create();
