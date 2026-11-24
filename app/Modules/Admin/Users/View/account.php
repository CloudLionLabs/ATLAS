<div class="page-head">
    <div>
        <div class="eyebrow">账号设置 / ACCOUNT</div>
        <h1 class="page-title">我的账号</h1>
        <p class="page-subtitle"><?= $canEditProfile ? '修改显示名称、登录邮箱与密码。账号数据与其它用户完全隔离。' : '普通账号只能修改登录密码；显示名称和邮箱由管理员维护。' ?></p>
    </div>
    <form method="post" action="/logout"><?= csrf_field() ?><button type="submit" class="btn">退出登录</button></form>
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    <section class="panel">
        <div class="panel-head"><div><div class="eyebrow">个人资料</div><h2 class="panel-title">账号信息</h2></div><span class="tag tag-signal">@<?= e($user['username']) ?></span></div>
        <?php if ($canEditProfile): ?>
        <form method="post" action="/account/profile" class="space-y-3 p-4">
            <?= csrf_field() ?>
            <div><label class="label" for="display_name">显示名称</label><input id="display_name" name="display_name" value="<?= e($user['display_name']) ?>" class="input" required></div>
            <div><label class="label" for="email">邮箱</label><input id="email" name="email" type="email" value="<?= e($user['email'] ?? '') ?>" class="input" placeholder="用于登录或通知，可选"></div>
            <div><span class="label">角色</span><span class="tag <?= $user['role'] === 'admin' ? 'tag-signal' : '' ?>"><?= $user['role'] === 'admin' ? '管理员' : '成员' ?></span></div>
            <button type="submit" class="btn btn-primary">保存资料</button>
        </form>
        <?php else: ?>
        <div class="space-y-3">
            <div><span class="label">显示名称</span><div class="rounded-lg border border-white/[0.07] bg-white/[0.02] px-3 py-2 text-[14px] text-slate-200"><?= e($user['display_name']) ?></div></div>
            <div><span class="label">登录用户名</span><div class="rounded-lg border border-white/[0.07] bg-white/[0.02] px-3 py-2 font-mono text-[13px] text-slate-300">@<?= e($user['username']) ?></div></div>
            <div><span class="label">邮箱</span><div class="rounded-lg border border-white/[0.07] bg-white/[0.02] px-3 py-2 text-[13px] text-slate-400"><?= e($user['email'] ?? '未设置') ?></div></div>
            <div><span class="label">角色</span><span class="tag">成员</span></div>
            <p class="field-hint">如需修改显示名称或邮箱，请联系管理员。你只能修改自己的密码。</p>
        </div>
        <?php endif; ?>
    </section>
    <section class="panel">
        <div class="panel-head"><div><div class="eyebrow">安全</div><h2 class="panel-title">修改密码</h2></div></div>
        <form method="post" action="/account/password" class="space-y-3 p-4">
            <?= csrf_field() ?>
            <div><label class="label" for="current_password">当前密码</label><input id="current_password" name="current_password" type="password" class="input" required autocomplete="current-password"></div>
            <div><label class="label" for="new_password">新密码</label><input id="new_password" name="new_password" type="password" class="input" minlength="8" required autocomplete="new-password"></div>
            <div><label class="label" for="new_password_confirm">确认新密码</label><input id="new_password_confirm" name="new_password_confirm" type="password" class="input" minlength="8" required autocomplete="new-password"></div>
            <button type="submit" class="btn">更新密码</button>
        </form>
    </section>
</div>
