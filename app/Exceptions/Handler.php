<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
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
     * Register the exception handling callbacks for the application.
     * 例外ハンドラーを登録する。
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // api/* で、ルートモデルバインディングの対象が見つからない場合は404のJSONを返す
        $this->renderable(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['error' => '勤怠情報が見つかりませんでした。'], 404);
            }

            return null;
        });

        // api/* で、Policyによる認可に失敗した場合は403のJSONを返す
        $this->renderable(function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            if ($request->is('api/*') && $e->getPrevious() instanceof AuthorizationException) {
                return response()->json(['error' => 'この操作を実行する権限がありません。'], 403);
            }

            return null;
        });
    }
}
