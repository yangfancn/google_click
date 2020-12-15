<?php
declare (strict_types = 1);

namespace app\middleware;

class Auth
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        if (!session('?user')) {
            if (request()->isAjax()) {
                return json(['errorno' => 403, 'errorMsg' => '请登录']);
            } else {
                return redirect((string) url('login'));
            }
        }

        return $next($request);
    }
}
