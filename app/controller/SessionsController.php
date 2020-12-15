<?php

namespace app\controller;

use app\BaseController;
use app\model\User;
use app\Request;

class SessionsController extends BaseController
{
    protected $middleware = [
        'auth' => ['except' => ['create', 'store']]
    ];

    public function create()
    {
        return view('sessions/create');
    }

    public function store(Request $request)
    {
        $userCheck = new \app\validate\User;
        
        $check_result = $userCheck->check($request->param());
        
        if (!$check_result) {
            return json(['errorno' => 401, 'errorMsg' => $userCheck->getError()]);
        }

        $user = User::where('name', $request->param('name'))->find();
        
        if (md5($request->param('password')) !== $user->password) {
            return json(['errorno' => 401, 'errorMsg' => '账号密码不匹配']);
        }

        session('user', ['name' => $user->name]);
        return json(['errorno' => 0, 'errorMsg' => '', 'msg' => '登录成功', 'redirectUrl' => (string) url('home')]);
    }
}