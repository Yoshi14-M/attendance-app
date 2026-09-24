<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserIsNotAdmin
{
    /**
     * Handle an incoming request.
     * 一般ユーザー向け画面に管理者ユーザーが入り込まないようにする
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->admin_status) {
            abort(403);
        }

        return $next($request);
    }
}
