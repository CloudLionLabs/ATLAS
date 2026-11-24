<?php
declare(strict_types=1);

namespace App\Modules\Auth\Login\Controller;

use App\Core\Auth;
use App\Core\Controller;

final class LoginController extends Controller
{
    protected bool $requiresAuth = false;

    public function show(): string
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        return $this->view('Auth/Login/View/login', [
            'pageTitle' => '登录',
            'flashItems' => pull_flashes(),
        ], '');
    }

    public function login(): string
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
        if ($lockedUntil > time()) {
            $this->flashError('尝试次数过多，请稍后再试。');
            $this->redirect('/login');
        }
        $login = (string) $this->input('username', '');
        $password = (string) ($_POST['password'] ?? '');
        if (Auth::attempt($login, $password)) {
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            audit('auth.login', 'user', (string) Auth::id());
            $this->redirect('/');
        }
        $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts;
        if ($attempts >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $_SESSION['login_attempts'] = 0;
        }
        $this->flashError('账号或密码不正确，或账号已停用。');
        $this->redirect('/login');
    }

    public function logout(): string
    {
        if (Auth::check()) {
            audit('auth.logout', 'user', (string) Auth::id());
        }
        Auth::logout();
        $this->flashSuccess('已安全退出。');
        $this->redirect('/login');
    }
}
