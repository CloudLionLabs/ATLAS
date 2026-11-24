<?php $flashItems = $flashItems ?? pull_flashes(); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#04070d">
    <meta name="color-scheme" content="dark">
    <title>登录 · <?= e(config('name')) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22><rect width=%2264%22 height=%2264%22 rx=%2214%22 fill=%22%23070d17%22/><circle cx=%2232%22 cy=%2232%22 r=%2217%22 fill=%22none%22 stroke=%22%2320e3bd%22 stroke-width=%222%22/><circle cx=%2232%22 cy=%2232%22 r=%226%22 fill=%22%2320e3bd%22/></svg>">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body class="min-h-screen bg-ink-950 text-slate-200">
<div class="app-grid"></div>
<div class="app-noise"></div>
<main class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-[440px]">
        <div class="mb-8 flex items-center gap-3">
            <span class="brand-mark"><?= icon('radar', 'h-5 w-5') ?></span>
            <span class="leading-none">
                <span class="block font-display text-[22px] font-semibold tracking-[0.13em] text-white">ATLAS</span>
                <span class="mt-1 block font-mono text-[11px] uppercase tracking-[0.19em] text-slate-600">全球运营 · 多用户控制台</span>
            </span>
        </div>
        <section class="panel p-6 sm:p-7">
            <div class="eyebrow">身份验证 / SIGN IN</div>
            <h1 class="mt-2 font-display text-[28px] font-semibold tracking-tight text-white">登录工作台</h1>
            <p class="mt-2 text-[14px] leading-6 text-slate-500">每个账号只能访问自己名下的任务、商家、关键词、导出与设置。</p>
            <?php if ($flashItems): ?>
                <div class="mt-5 space-y-2">
                    <?php foreach ($flashItems as $f): ?>
                        <div class="rounded-lg border px-3 py-2 text-[13px] <?= ($f['type'] ?? '') === 'error' ? 'border-rose-400/20 bg-rose-400/[0.06] text-rose-300' : 'border-amber-400/20 bg-amber-400/[0.06] text-amber-200' ?>"><?= e($f['message'] ?? '') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/login" class="mt-6 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="label" for="username">用户名或邮箱</label>
                    <input id="username" name="username" class="input" autocomplete="username" required autofocus>
                </div>
                <div>
                    <label class="label" for="password">密码</label>
                    <input id="password" name="password" type="password" class="input" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-primary w-full justify-center">进入控制台</button>
            </form>
            <p class="mt-5 border-t border-white/[0.06] pt-4 text-center font-mono text-[10px] uppercase tracking-[0.16em] text-slate-700">Atlas · 数据按账号隔离</p>
        </section>
    </div>
</main>
</body>
</html>
