<?php
$flashes = pull_flashes();
$active = $active ?? 'dashboard';
$pageTitle = $pageTitle ?? config('name');
$useMap = $useMap ?? false;
$navCounts = ['jobs_active' => 0, 'businesses' => 0, 'quality' => 0];
$currentUser = auth_user() ?: [];
$uid = auth_id();
$isAdminUser = is_admin();
$userInitial = mb_substr((string) ($currentUser['display_name'] ?? '管'), 0, 1);
try {
    $navCounts['jobs_active'] = (int) db()->query("SELECT COUNT(*) FROM scrape_jobs WHERE user_id = {$uid} AND status IN ('running','submitting','submitted','queued')")->fetchColumn();
    $navCounts['businesses'] = (int) db()->query("SELECT COUNT(*) FROM businesses WHERE user_id = {$uid}")->fetchColumn();
    $navCounts['quality'] = (int) db()->query("SELECT COUNT(*) FROM businesses WHERE user_id = {$uid} AND (phone IS NULL OR phone = '' OR website IS NULL OR website = '' OR industry_id IS NULL)")->fetchColumn();
} catch (Throwable) {
    // Keep the shell renderable even if database initialization fails.
}
$nav = [
    ['section' => '指挥', 'items' => [
        ['id' => 'dashboard', 'label' => '指挥中心', 'en' => 'Overview', 'href' => '/', 'icon' => 'grid'],
        ['id' => 'targeting', 'label' => '全球选点', 'en' => 'Targeting', 'href' => '/jobs/create', 'icon' => 'crosshair'],
        ['id' => 'jobs', 'label' => '采集任务', 'en' => '任务管理', 'href' => '/jobs', 'icon' => 'radar', 'count' => $navCounts['jobs_active']],
    ]],
    ['section' => '数据资产', 'items' => [
        ['id' => 'businesses', 'label' => '商家资料库', 'en' => '商家库', 'href' => '/businesses', 'icon' => 'building', 'count' => $navCounts['businesses']],
        ['id' => 'quality', 'label' => '数据质量', 'en' => 'Quality', 'href' => '/data-quality', 'icon' => 'shield', 'count' => $navCounts['quality']],
        ['id' => 'exports', 'label' => '导出中心', 'en' => 'Exports', 'href' => '/exports', 'icon' => 'download'],
    ]],
    ['section' => '配置网络', 'items' => [
        ['id' => 'countries', 'label' => '国家与地区', 'en' => 'Countries', 'href' => '/countries', 'icon' => 'globe'],
        ['id' => 'industries', 'label' => 'B2B 行业', 'en' => '行业分类', 'href' => '/industries', 'icon' => 'layers'],
        ['id' => 'keywords', 'label' => 'B2B 关键词', 'en' => '关键词', 'href' => '/keywords', 'icon' => 'key'],
        ...($isAdminUser ? [
            ['id' => 'settings', 'label' => '个人设置', 'en' => '偏好设置', 'href' => '/settings', 'icon' => 'settings'],
            ['id' => 'users', 'label' => '用户与权限', 'en' => 'Users', 'href' => '/users', 'icon' => 'shield'],
        ] : []),
    ]],
];
$sections = [
    'dashboard' => '指挥中心',
    'targeting' => '地图选点',
    'jobs' => '采集运营',
    'businesses' => '商家资产',
    'quality' => '数据质量',
    'exports' => '导出流程',
    'countries' => '覆盖网络',
    'industries' => '分类体系',
    'keywords' => '搜索词库',
    'settings' => '系统配置',
    'account' => '账号设置',
    'users' => '账号权限',
];
$currentSection = $sections[$active] ?? '指挥中心';
$statusCache = scraper_status_cache();
$commandItems = [
    ['label' => '指挥中心', 'hint' => '总览', 'href' => '/', 'icon' => 'grid'],
    ['label' => '全球选点 / 新建任务', 'hint' => '地图选点', 'href' => '/jobs/create', 'icon' => 'crosshair'],
    ['label' => '采集任务', 'hint' => '任务管理', 'href' => '/jobs', 'icon' => 'radar'],
    ['label' => '批量任务生成器', 'hint' => '批量生成', 'href' => '/jobs/batch', 'icon' => 'layers'],
    ['label' => '商家资料库', 'hint' => '商家库', 'href' => '/businesses', 'icon' => 'building'],
    ['label' => '数据质量审查', 'hint' => '质量审查', 'href' => '/data-quality', 'icon' => 'shield'],
    ['label' => '导出中心', 'hint' => 'CSV / XLSX', 'href' => '/exports', 'icon' => 'download'],
    ['label' => '国家与地区', 'hint' => '覆盖范围', 'href' => '/countries', 'icon' => 'globe'],
    ['label' => 'B2B 行业分类', 'hint' => '外贸品类', 'href' => '/industries', 'icon' => 'layers'],
    ['label' => 'B2B 关键词库', 'hint' => '采购搜索词', 'href' => '/keywords', 'icon' => 'key'],
    ...($isAdminUser ? [
        ['label' => '系统设置 / 采集引擎连接', 'hint' => '系统设置', 'href' => '/settings', 'icon' => 'settings'],
        ['label' => '用户与权限', 'hint' => '多用户', 'href' => '/users', 'icon' => 'shield'],
    ] : []),
];
?>
<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#04070d">
    <meta name="color-scheme" content="dark">
    <meta name="description" content="Atlas Global Ops — 全球商家采集管理后台">
    <title><?= e($pageTitle) ?> · <?= e(config('name')) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22><rect width=%2264%22 height=%2264%22 rx=%2214%22 fill=%22%23070d17%22/><circle cx=%2232%22 cy=%2232%22 r=%2217%22 fill=%22none%22 stroke=%22%2320e3bd%22 stroke-width=%222%22/><circle cx=%2232%22 cy=%2232%22 r=%226%22 fill=%22%2320e3bd%22/><path d=%22M32 8v8M32 48v8M8 32h8M48 32h8%22 stroke=%22%2320e3bd%22 stroke-width=%222%22/></svg>">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <?php if ($useMap): ?>
        <link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
        <script src="/assets/vendor/leaflet/leaflet.js"></script>
    <?php endif; ?>
    <script>
        window.ATLAS = {
            map: <?= json_encode(config('map'), JSON_UNESCAPED_SLASHES) ?>,
            csrf: <?= json_encode(csrf_token()) ?>
        };
    </script>
</head>
<body class="min-h-full">
<div class="app-shell">
    <div class="app-grid"></div>
    <div class="app-noise"></div>

    <aside id="sidebar" class="sidebar" aria-label="主导航">
        <div class="flex h-[72px] shrink-0 items-center gap-3 border-b border-white/[0.06] px-5">
            <a href="/" class="flex items-center gap-3" aria-label="Atlas 首页">
                <span class="brand-mark">
                    <?= icon('radar', 'h-5 w-5') ?>
                </span>
                <span class="leading-none">
                    <span class="block font-display text-[20px] font-semibold tracking-[0.13em] text-white">ATLAS</span>
                    <span class="mt-1 block font-mono text-[11px] uppercase tracking-[0.19em] text-slate-600">全球运营 · v1.0</span>
                </span>
            </a>
            <button type="button" class="icon-button ml-auto lg:hidden" data-nav-close aria-label="关闭导航"><?= icon('x', 'h-4 w-4') ?></button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 pb-6 scrollbar-none">
            <?php foreach ($nav as $group): ?>
                <div class="nav-section-label"><?= e($group['section']) ?></div>
                <div class="space-y-1">
                    <?php foreach ($group['items'] as $item): ?>
                        <a href="<?= e($item['href']) ?>" class="nav-link group <?= $active === $item['id'] ? 'is-active' : '' ?>">
                            <?= icon($item['icon']) ?>
                            <span><?= e($item['label']) ?></span>
                            <?php if (isset($item['count']) && (int) $item['count'] > 0): ?>
                                <span class="nav-count"><?= fmt_number($item['count']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="mt-7 rounded-xl border border-white/[0.06] bg-gradient-to-b from-white/[0.035] to-transparent p-3.5">
                <div class="flex items-center gap-2">
                    <span class="terminal-dot <?= ($statusCache['state'] ?? '') === 'offline' ? '!bg-rose-400' : '' ?>" data-scraper-dot></span>
                    <span class="font-mono text-[12px] uppercase tracking-[0.16em] text-slate-400" data-scraper-text><?= ($statusCache['state'] ?? '') === 'online' ? '已连接' : '连接中' ?></span>
                    <span class="ml-auto font-mono text-[11px] text-slate-600" data-scraper-latency><?= isset($statusCache['latency_ms']) ? e($statusCache['latency_ms'] . 'ms') : '—' ?></span>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-white/[0.05] pt-2.5">
                    <span class="font-mono text-[11px] uppercase tracking-[0.14em] text-slate-600">采集引擎节点</span>
                    <?php if ($isAdminUser): ?><a href="/settings" class="font-mono text-[11px] uppercase tracking-wider text-signal/70 hover:text-signal">连接 ↗</a><?php else: ?><span class="font-mono text-[11px] uppercase tracking-wider text-slate-700">只读</span><?php endif; ?>
                </div>
            </div>
        </nav>

        <div class="shrink-0 border-t border-white/[0.06] p-4">
            <div class="flex items-center gap-3">
                <div class="grid h-8 w-8 place-items-center rounded-lg border border-signal/20 bg-signal/[0.06] font-mono text-[12px] text-signal"><?= e($userInitial) ?></div>
                <div class="min-w-0">
                    <div class="truncate text-[14px] font-medium text-slate-300"><?= e($currentUser['display_name'] ?? '未登录') ?></div>
                    <div class="truncate font-mono text-[11px] uppercase tracking-wider text-slate-600">@<?= e($currentUser['username'] ?? '—') ?> · <?= $isAdminUser ? '管理员' : '成员' ?></div>
                </div>
                <form method="post" action="/logout" class="ml-auto"><?= csrf_field() ?><button type="submit" class="text-slate-600 hover:text-rose-300" title="退出登录" aria-label="退出登录"><?= icon('x', 'h-4 w-4') ?></button></form>
            </div>
        </div>
    </aside>
    <div id="nav-backdrop" class="fixed inset-0 z-40 hidden bg-black/60 backdrop-blur-sm lg:hidden"></div>

    <div class="main-frame">
        <header class="topbar">
            <button type="button" class="icon-button lg:hidden" data-nav-open aria-label="打开导航"><?= icon('menu', 'h-4 w-4') ?></button>
            <div class="breadcrumb hidden sm:flex">
                <a href="/" class="hover:text-slate-300">ATLAS</a>
                <span class="text-slate-700">/</span>
                <span class="text-slate-400"><?= e($currentSection) ?></span>
                <?php if ($active !== 'dashboard'): ?>
                    <span class="text-slate-700">/</span>
                    <span class="text-slate-500"><?= e($pageTitle) ?></span>
                <?php endif; ?>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" class="search-trigger hidden md:flex" data-command-open>
                    <?= icon('search', 'h-3.5 w-3.5') ?>
                    <span>搜索模块、任务、商家…</span>
                    <span class="kbd ml-8">⌘K</span>
                </button>
                <div class="hidden items-center gap-2 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2 xl:flex">
                    <span class="h-1.5 w-1.5 rounded-full bg-signal/80 animate-pulse-soft"></span>
                    <span class="font-mono text-[12px] tracking-wider text-slate-500" data-utc-clock>00:00:00 UTC</span>
                </div>
                <div class="hidden items-center gap-2 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2 lg:flex">
                    <span class="font-mono text-[12px] tracking-wider text-slate-500" data-shanghai-clock>00:00 CST</span>
                </div>
                <a href="<?= $isAdminUser ? '/settings' : '/account' ?>" class="icon-button" aria-label="账号设置"><?= icon('bell', 'h-4 w-4') ?><span class="absolute right-2 top-2 h-1.5 w-1.5 rounded-full bg-signal"></span></a>
                <a href="/account" class="ml-1 grid h-8 w-8 place-items-center rounded-lg border border-white/[0.08] bg-gradient-to-br from-slate-700/40 to-slate-900 font-mono text-[12px] text-slate-300" title="账号设置"><?= e($userInitial) ?></a>
            </div>
        </header>

        <main class="page-wrap">
            <?= $content ?>
        </main>

        <footer class="border-t border-white/[0.05] px-6 py-5 lg:px-8">
            <div class="mx-auto flex max-w-[1560px] flex-wrap items-center gap-3 font-mono text-[11px] uppercase tracking-[0.14em] text-slate-700">
                <span class="flex items-center gap-2"><span class="h-1 w-1 rounded-full bg-signal/70"></span> ATLAS 全球运营</span>
                <span class="hidden sm:inline">本地节点 · MySQL · PHP <?= e(PHP_VERSION) ?></span>
                <span class="ml-auto">数据主权 / 仅限本机</span>
            </div>
        </footer>
    </div>
</div>

<!-- Flash toasts -->
<div class="pointer-events-none fixed right-4 top-20 z-[120] flex w-[min(92vw,390px)] flex-col gap-2">
    <?php foreach ($flashes as $f): ?>
        <?php $isError = ($f['type'] ?? '') === 'error'; $isWarning = ($f['type'] ?? '') === 'warning'; ?>
        <div data-toast class="toast pointer-events-auto <?= $isError ? 'toast-error' : ($isWarning ? 'toast-warning' : 'toast-success') ?>">
            <span class="mt-0.5 shrink-0 <?= $isError ? 'text-rose-400' : ($isWarning ? 'text-amber-300' : 'text-signal') ?>"><?= icon($isError ? 'alert' : ($isWarning ? 'alert' : 'check-circle'), 'h-4 w-4') ?></span>
            <div class="min-w-0 flex-1 leading-5"><?= e($f['message'] ?? '') ?></div>
            <button type="button" class="shrink-0 text-slate-500 hover:text-white" data-toast-close aria-label="关闭"><?= icon('x', 'h-3.5 w-3.5') ?></button>
        </div>
    <?php endforeach; ?>
</div>

<!-- Command palette -->
<div id="command-palette" class="command-palette" aria-hidden="true" role="dialog" aria-label="命令面板">
    <div class="command-box">
        <div class="flex items-center gap-3 border-b border-white/[0.08] px-4 py-3.5">
            <?= icon('search', 'h-4 w-4 text-slate-500') ?>
            <input id="command-input" type="text" placeholder="输入模块名称或任务…" class="w-full border-0 bg-transparent text-[16px] text-white placeholder:text-slate-600 focus:outline-none" autocomplete="off">
            <span class="kbd">ESC</span>
        </div>
        <div id="command-list" class="max-h-[380px] overflow-y-auto p-2">
            <?php foreach ($commandItems as $index => $cmd): ?>
                <a href="<?= e($cmd['href']) ?>" class="command-item <?= $index === 0 ? 'is-selected' : '' ?>" data-command data-label="<?= e($cmd['label'] . ' ' . $cmd['hint']) ?>">
                    <span class="text-slate-500"><?= icon($cmd['icon'], 'h-4 w-4') ?></span>
                    <span class="text-slate-200"><?= e($cmd['label']) ?></span>
                    <span class="ml-auto font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= e($cmd['hint']) ?></span>
                    <?= icon('arrow-right', 'h-3 w-3 text-slate-700') ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="flex items-center gap-3 border-t border-white/[0.06] px-4 py-2.5 font-mono text-[11px] uppercase tracking-wider text-slate-600">
            <span><span class="kbd">↑↓</span> 移动</span>
            <span><span class="kbd">↵</span> 打开</span>
            <span class="ml-auto">ATLAS 命令面板</span>
        </div>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
