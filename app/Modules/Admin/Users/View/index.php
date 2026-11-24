<div class="page-head">
    <div>
        <div class="eyebrow">账号权限 / USERS</div>
        <h1 class="page-title">用户与权限</h1>
        <p class="page-subtitle">每个账号拥有独立的任务、商家、关键词、导出和设置。管理员只负责账号管理，不共享业务数据。</p>
    </div>
</div>

<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="metric"><span class="metric-label">账号总数</span><span class="metric-value"><?= fmt_number(count($users)) ?></span></div>
    <div class="metric"><span class="metric-label">启用账号</span><span class="metric-value"><?= fmt_number(count(array_filter($users, fn($u) => (int) $u['is_active'] === 1))) ?></span></div>
    <div class="metric"><span class="metric-label">管理员</span><span class="metric-value"><?= fmt_number(count(array_filter($users, fn($u) => $u['role'] === 'admin'))) ?></span></div>
    <div class="metric"><span class="metric-label">数据边界</span><span class="metric-value text-[22px]">按账号隔离</span></div>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-[1.5fr_0.8fr]">
    <section class="panel">
        <div class="panel-head">
            <div><div class="eyebrow">账号列表</div><h2 class="panel-title">用户与数据量</h2></div>
            <span class="tag"><?= fmt_number(count($users)) ?> 个账号</span>
        </div>
        <div class="space-y-3">
            <?php foreach ($users as $u): $id = (int) $u['id']; $c = $counts[$id] ?? []; ?>
                <div class="rounded-xl border border-white/[0.07] bg-white/[0.02] p-4">
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-signal/20 bg-signal/[0.06] font-mono text-[14px] text-signal"><?= e(mb_substr($u['display_name'] ?: $u['username'], 0, 1)) ?></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[16px] font-medium text-slate-100"><?= e($u['display_name']) ?></span>
                                <span class="font-mono text-[11px] text-slate-500">@<?= e($u['username']) ?></span>
                                <span class="tag <?= $u['role'] === 'admin' ? 'tag-signal' : '' ?>"><?= $u['role'] === 'admin' ? '管理员' : '成员' ?></span>
                                <span class="tag <?= (int) $u['is_active'] === 1 ? '' : '!border-rose-400/20 !text-rose-300' ?>"><?= (int) $u['is_active'] === 1 ? '启用' : '停用' ?></span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 font-mono text-[11px] text-slate-600">
                                <span>任务 <?= fmt_number($c['jobs'] ?? 0) ?></span>
                                <span>商家 <?= fmt_number($c['businesses'] ?? 0) ?></span>
                                <span>关键词 <?= fmt_number($c['keywords'] ?? 0) ?></span>
                                <span>最近登录 <?= e(fmt_datetime($u['last_login_at'], '从未')) ?></span>
                            </div>
                        </div>
                    </div>
                    <form method="post" action="/users/<?= $id ?>" class="mt-4 grid gap-2 md:grid-cols-[1.1fr_1fr_0.7fr_auto]">
                        <?= csrf_field() ?>
                        <input name="display_name" value="<?= e($u['display_name']) ?>" class="input !py-2 !text-[13px]" required aria-label="显示名称">
                        <input name="email" value="<?= e($u['email'] ?? '') ?>" class="input !py-2 !text-[13px]" placeholder="邮箱（可选）" aria-label="邮箱">
                        <select name="role" class="select !py-2 !text-[13px]" aria-label="角色">
                            <option value="member" <?= $u['role'] === 'member' ? 'selected' : '' ?>>成员</option>
                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>管理员</option>
                        </select>
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-1.5 text-[12px] text-slate-500"><input type="checkbox" name="is_active" value="1" <?= (int) $u['is_active'] === 1 ? 'checked' : '' ?> class="accent-signal">启用</label>
                            <button class="btn !px-3 !py-2 !text-[12px]" type="submit">保存</button>
                        </div>
                    </form>
                    <form method="post" action="/users/<?= $id ?>/password" class="mt-2 flex flex-wrap items-center gap-2">
                        <?= csrf_field() ?>
                        <input type="password" name="new_password" class="input !w-auto !flex-1 !py-2 !text-[13px]" placeholder="重置密码（至少 8 位）" minlength="8" autocomplete="new-password" aria-label="重置密码">
                        <button class="btn !px-3 !py-2 !text-[12px]" type="submit">重置</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="panel h-fit">
        <div class="panel-head"><div><div class="eyebrow">新建账号</div><h2 class="panel-title">添加用户</h2></div></div>
        <form method="post" action="/users" class="space-y-3">
            <?= csrf_field() ?>
            <div><label class="label" for="new_username">用户名 *</label><input id="new_username" name="username" class="input" placeholder="例如 sales01" required></div>
            <div><label class="label" for="new_display">显示名称 *</label><input id="new_display" name="display_name" class="input" placeholder="例如 销售一组" required></div>
            <div><label class="label" for="new_email">邮箱</label><input id="new_email" name="email" type="email" class="input" placeholder="可选"></div>
            <div><label class="label" for="new_password">初始密码 *</label><input id="new_password" name="password" type="password" class="input" minlength="8" required autocomplete="new-password"></div>
            <div><label class="label" for="new_role">角色</label><select id="new_role" name="role" class="select"><option value="member">成员</option><option value="admin">管理员</option></select></div>
            <button type="submit" class="btn btn-primary w-full justify-center">创建账号</button>
        </form>
        <p class="field-hint mt-3">成员只能看到自己创建的采集任务、商家、关键词和导出文件；国家、城市和 B2B 行业作为公共字典共享。</p>
    </aside>
</div>
