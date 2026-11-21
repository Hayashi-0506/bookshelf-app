<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * API へのリクエストは、Accept ヘッダーがなくても例外を JSON で返します。
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // web側はLaravelの標準挙動（404ページ）のまま
            }

            if ($e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json([
                    'error' => '書籍情報が見つかりませんでした。',
                ], 404);
            }
        });

        $this->renderable(function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*')) {
                return response()->json(
                    ['error' => 'この操作を実行する権限がありません。'],
                    JsonResponse::HTTP_FORBIDDEN
                );
            }

            return null;
        });
    }
}
