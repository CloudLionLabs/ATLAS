<?php
declare(strict_types=1);

namespace App\Modules\Admin\Users\Controller;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;
use App\Services\KeywordSeeder;

final class UserController extends Controller
{
    public function index(): string
    {
        $this->requireAdmin();
        $model = new User();
        $users = $model->all();
        $counts = [];
        foreach ($users as $user) {
            $id = (int) $user['id'];
            $counts[$id] = [
                'jobs' => (int) db()->query("SELECT COUNT(*) FROM scrape_jobs WHERE user_id = {$id}")->fetchColumn(),
                'businesses' => (int) db()->query("SELECT COUNT(*) FROM businesses WHERE user_id = {$id}")->fetchColumn(),
                'keywords' => (int) db()->query("SELECT COUNT(*) FROM keywords WHERE user_id = {$id}")->fetchColumn(),
            ];
        }
        return $this->view('Admin/Users/View/index', [
            'pageTitle' => '用户与权限',
            'active' => 'users',
            'users' => $users,
            'counts' => $counts,
        ]);
    }

    public function store(): string
    {
        $this->requireAdmin();
        try {
            $id = (new User())->create(
                (string) $this->input('username', ''),
                (string) $this->input('display_name', ''),
                (string) ($_POST['password'] ?? ''),
                (string) $this->input('role', 'member'),
                trim((string) $this->input('email', '')) ?: null
            );
            try {
                $seededKeywords = (new KeywordSeeder())->seedForUser($id);
            } catch (\Throwable $e) {
                $seededKeywords = 0;
                error_log('Tenant keyword seed failed: ' . $e->getMessage());
            }
            audit('user.create', 'user', (string) $id, ['role' => $this->input('role', 'member'), 'keywords' => $seededKeywords]);
            $this->flashSuccess('账号已创建，并初始化 ' . $seededKeywords . ' 个独立 B2B 关键词。');
        } catch (\InvalidArgumentException $e) {
            $this->flashError($e->getMessage());
        }
        $this->redirect('/users');
    }

    public function update(array $params): string
    {
        $this->requireAdmin();
        $id = (int) $params['id'];
        $target = (new User())->find($id);
        if (!$target) {
            $this->flashError('用户不存在。');
            $this->redirect('/users');
        }
        $role = (string) $this->input('role', $target['role']);
        $active = $this->boolean('is_active');
        if ($id === $this->userId() && (!$active || $role !== 'admin')) {
            $this->flashError('不能停用或降级当前登录的管理员账号。');
            $this->redirect('/users');
        }
        if ($target['role'] === 'admin' && ($role !== 'admin' || !$active)) {
            $activeAdmins = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($activeAdmins <= 1) {
                $this->flashError('系统至少需要保留一个启用的管理员。');
                $this->redirect('/users');
            }
        }
        try {
            $displayName = trim((string) $this->input('display_name', $target['display_name'])) ?: (string) $target['username'];
            (new User())->update($id, $displayName, $role, $active, trim((string) $this->input('email', '')) ?: null);
            audit('user.update', 'user', (string) $id, ['role' => $role, 'active' => $active]);
            $this->flashSuccess('账号信息已更新。');
        } catch (\PDOException $e) {
            $this->flashError(str_contains($e->getMessage(), 'UNIQUE') ? '该邮箱已被其他账号使用。' : '保存账号失败。');
        }
        $this->redirect('/users');
    }

    public function resetPassword(array $params): string
    {
        $this->requireAdmin();
        $id = (int) $params['id'];
        try {
            (new User())->setPassword($id, (string) ($_POST['new_password'] ?? ''));
            audit('user.password_reset', 'user', (string) $id);
            $this->flashSuccess('密码已重置。');
        } catch (\InvalidArgumentException $e) {
            $this->flashError($e->getMessage());
        }
        $this->redirect('/users');
    }

    public function account(): string
    {
        return $this->view('Admin/Users/View/account', [
            'pageTitle' => '账号设置',
            'active' => 'account',
            'user' => Auth::user(),
            'canEditProfile' => is_admin(),
        ]);
    }

    public function updateAccount(): string
    {
        $this->requireAdmin();
        $id = $this->userId();
        $user = (new User())->find($id);
        if (!$user) { $this->redirect('/login'); }
        $display = trim((string) $this->input('display_name', $user['display_name']));
        $email = trim((string) $this->input('email', '')) ?: null;
        try {
            (new User())->update($id, $display ?: $user['display_name'], (string) $user['role'], (bool) $user['is_active'], $email);
            Auth::forceUser($id);
            audit('account.update', 'user', (string) $id);
            $this->flashSuccess('账号资料已保存。');
        } catch (\PDOException $e) {
            $this->flashError(str_contains($e->getMessage(), 'UNIQUE') ? '该邮箱已被其他账号使用。' : '保存账号失败。');
        }
        $this->redirect('/account');
    }

    public function changePassword(): string
    {
        $id = $this->userId();
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        $model = new User();
        if (!$model->verifyPassword($id, $current)) {
            $this->flashError('当前密码不正确。');
            $this->redirect('/account');
        }
        if ($new !== $confirm) {
            $this->flashError('两次输入的新密码不一致。');
            $this->redirect('/account');
        }
        try {
            $model->setPassword($id, $new);
            audit('account.password', 'user', (string) $id);
            $this->flashSuccess('密码已更新。');
        } catch (\InvalidArgumentException $e) {
            $this->flashError($e->getMessage());
        }
        $this->redirect('/account');
    }
}
