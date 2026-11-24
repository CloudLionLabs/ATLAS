<?php
$state = $status['state'] ?? 'unknown';
$online = $state === 'online';
$dbKb = ((int) $health['database_size']) / 1024;
$apiEndpoints = [
    ['GET', '/api/system/status', '采集引擎连接状态'],
    ['GET', '/api/geocode?q=', '全球地址搜索'],
    ['GET', '/api/reverse?lat=&lng=', '坐标逆地理编码'],
    ['GET', '/api/regions?country_id=', '城市节点列表'],
    ['GET', '/api/cities?q=&country=', '全球城市库搜索'],
    ['GET', '/api/region-boundary?region_id=', '城市行政区边界/面积'],
    ['GET', '/api/dashboard/stats', '仪表盘统计'],
    ['GET', '/api/businesses', '商家分页列表'],
    ['GET', '/api/jobs', '任务分页列表'],
    ['GET', '/api/scraper/jobs', '当前账号的外部任务'],
];
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 系统配置 / 本地运维</div>
            <h1 class="page-title">系统<span class="text-signal">设置</span></h1>
            <p class="page-subtitle">管理采集引擎连接、默认采集参数与本地运行状态。敏感配置只在服务端读取，不在页面暴露密钥。</p>
        </div>
        <a href="/data-quality" class="btn btn-secondary"><?= icon('shield') ?> 数据健康检查</a>
    </div>
</section>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <div class="space-y-4 xl:col-span-7">
        <section class="panel reveal reveal-delay-1 overflow-hidden">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">采集引擎适配器</div><h2 class="panel-title"><?= icon('server', 'h-4 w-4 text-signal') ?> 采集引擎连接</h2></div>
                <span class="status-badge <?= $online ? 'border-signal/25 bg-signal/[0.07] text-signal' : ($state === 'offline' ? 'border-rose-400/25 bg-rose-400/[0.06] text-rose-300' : 'border-amber-400/25 bg-amber-400/[0.06] text-amber-300') ?>"><span class="h-1.5 w-1.5 rounded-full <?= $online ? 'bg-signal' : ($state === 'offline' ? 'bg-rose-400' : 'bg-amber-400') ?>"></span><?= e($online ? '在线' : ($state === 'offline' ? '离线' : '未知')) ?></span>
            </div>
            <div class="p-5">
                <div class="rounded-xl border border-white/[0.08] bg-ink-950/60 p-4">
                    <div class="flex items-start gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-signal/20 bg-signal/[0.05] text-signal"><?= icon('server', 'h-5 w-5') ?></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-mono text-[14px] text-slate-200"><?= e($scraperUrl) ?></div>
                            <div class="mt-1 flex flex-wrap items-center gap-3 font-mono text-[11px] uppercase tracking-wider text-slate-600">
                                <span>JSON 接口</span><span class="text-slate-800">/</span>
                                <span>表单提交</span><span class="text-slate-800">/</span>
                                <span>仅限本机</span>
                            </div>
                        </div>
                        <span class="hidden font-mono text-[12px] text-slate-500 sm:block"><?= isset($status['latency_ms']) ? e($status['latency_ms']) . 'ms' : '—' ?></span>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <div class="rounded-lg border border-white/[0.05] bg-white/[0.02] px-3 py-2"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">状态</div><div class="mt-1 text-[13px] <?= $online ? 'text-signal' : 'text-amber-300' ?>"><?= e($status['message'] ?? '尚未检测') ?></div></div>
                        <div class="rounded-lg border border-white/[0.05] bg-white/[0.02] px-3 py-2"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">发现任务</div><div class="mt-1 font-mono text-[14px] text-slate-200"><?= isset($status['job_count']) ? fmt_number($status['job_count']) : '—' ?></div></div>
                        <div class="rounded-lg border border-white/[0.05] bg-white/[0.02] px-3 py-2"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">上次检测</div><div class="mt-1 font-mono text-[13px] text-slate-300"><?= e(fmt_datetime($status['checked_at'] ?? null, 'NEVER')) ?></div></div>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="post" action="/settings/scraper/test"><?= csrf_field() ?><button class="btn btn-primary"><?= icon('activity', 'h-4 w-4') ?> 测试连接</button></form>
                    <form method="post" action="/settings/scraper/sync" data-confirm="从采集引擎同步外部任务到本地？已有记录会更新，不会删除。"><?= csrf_field() ?><button class="btn btn-secondary"><?= icon('sync', 'h-4 w-4') ?> 同步外部任务</button></form>
                    <a href="http://127.0.0.1:8080" target="_blank" rel="noopener noreferrer" class="btn btn-ghost"><?= icon('external', 'h-4 w-4') ?> 打开采集引擎</a>
                </div>
                <div class="mt-4 rounded-xl border border-white/[0.06] bg-white/[0.02] p-3 font-mono text-[11px] uppercase leading-5 tracking-wider text-slate-600">
                    POST /scrape 提交 · GET /api/v1/jobs 查询 · GET /download 下载 · 未确认字段不做假设 · 提交超时不自动重发
                </div>
            </div>
        </section>

        <section class="panel reveal reveal-delay-2">
            <div class="panel-head"><div><div class="eyebrow mb-1">运行配置</div><h2 class="panel-title"><?= icon('settings', 'h-4 w-4 text-electric') ?> 连接参数</h2></div><span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">只读</span></div>
            <div class="grid grid-cols-2 gap-px bg-white/[0.04] sm:grid-cols-3">
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">连接超时</div><div class="mt-1 font-mono text-[14px] text-slate-300"><?= (int) $config['connect_timeout'] ?> 秒</div></div>
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">读取超时</div><div class="mt-1 font-mono text-[14px] text-slate-300"><?= (int) $config['read_timeout'] ?> 秒</div></div>
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">轮询间隔</div><div class="mt-1 font-mono text-[14px] text-slate-300"><?= (int) $config['poll_interval'] ?> 秒</div></div>
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">最大并发</div><div class="mt-1 font-mono text-[14px] text-slate-300"><?= (int) $config['max_concurrent_jobs'] ?></div></div>
                <div class="bg-ink-900/90 px-4 py-3 sm:col-span-2"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">地址服务</div><div class="mt-1 truncate font-mono text-[12px] text-slate-400"><?= e($config['geocoder']) ?></div></div>
            </div>
        </section>

        <section class="panel reveal reveal-delay-3">
            <div class="panel-head"><div><div class="eyebrow mb-1">内部接口</div><h2 class="panel-title"><?= icon('link', 'h-4 w-4 text-plasma') ?> 本地接口</h2></div><span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">JSON</span></div>
            <div class="divide-y divide-white/[0.05]">
                <?php foreach ($apiEndpoints as $endpoint): ?>
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <span class="w-10 rounded bg-signal/[0.06] py-0.5 text-center font-mono text-[11px] uppercase text-signal/80"><?= e($endpoint[0]) ?></span>
                        <a href="<?= e($endpoint[1]) ?>" target="_blank" rel="noopener noreferrer" class="min-w-0 flex-1 truncate font-mono text-[13px] text-slate-400 hover:text-signal"><?= e($endpoint[1]) ?></a>
                        <span class="hidden text-[12px] text-slate-600 sm:block"><?= e($endpoint[2]) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="space-y-4 xl:col-span-5">
        <section class="panel reveal reveal-delay-1">
            <div class="panel-head"><div><div class="eyebrow mb-1">操作偏好</div><h2 class="panel-title"><?= icon('sparkles', 'h-4 w-4 text-signal') ?> 采集默认值</h2></div></div>
            <form method="post" action="/settings" class="space-y-3 p-5">
                <?= csrf_field() ?>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">默认语言</label><select name="default_language" class="select"><?php foreach (['en' => '英语', 'zh' => '中文', 'de' => '德语', 'fr' => '法语', 'ja' => '日语', 'es' => '西班牙语', 'pt' => '葡萄牙语', 'nl' => '荷兰语', 'it' => '意大利语', 'ru' => '俄语', 'pl' => '波兰语', 'tr' => '土耳其语', 'id' => '印尼语', 'th' => '泰语', 'vi' => '越南语', 'hi' => '印地语', 'ar' => '阿拉伯语', 'ko' => '韩语'] as $k => $v): ?><option value="<?= $k ?>" <?= ($settings['default_language'] ?? 'en') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                    <div><label class="label">默认半径（米）</label><input type="number" name="default_radius" min="100" max="50000" value="<?= e($settings['default_radius'] ?? 10000) ?>" class="input font-mono"></div>
                    <div><label class="label">默认深度</label><input type="number" name="default_depth" min="1" max="50" value="<?= e($settings['default_depth'] ?? 10) ?>" class="input font-mono"></div>
                    <div><label class="label">数据保留 (天)</label><input type="number" name="data_retention_days" min="30" max="3650" value="<?= e($settings['data_retention_days'] ?? 365) ?>" class="input font-mono"></div>
                </div>
                <label class="flex items-center justify-between rounded-xl border border-white/[0.08] bg-ink-950/40 px-3.5 py-3">
                    <span><span class="block text-[14px] font-medium text-slate-200">新建任务默认快速模式</span><span class="mt-0.5 block text-[12px] leading-4 text-slate-600">开启后所有账号的新建任务统一使用快速模式；完整模式返回 0 时可作为兜底。快速模式最多 21 条基础结果，不含商家介绍。</span></span>
                    <span class="switch"><input type="checkbox" name="fastmode_default" value="1" <?= ($settings['fastmode_default'] ?? '0') === '1' ? 'checked' : '' ?>></span>
                </label>
                <label class="flex items-center justify-between rounded-xl border border-white/[0.08] bg-ink-950/40 px-3.5 py-3">
                    <span><span class="block text-[14px] font-medium text-slate-200">邮箱补全模块</span><span class="mt-0.5 block text-[12px] text-slate-600"><?= ($settings['email_finder_enabled'] ?? '0') === '1' ? '已开启 · 需手动触发' : '已关闭' ?></span></span>
                    <span class="switch"><input type="checkbox" name="email_finder_enabled" value="1" <?= ($settings['email_finder_enabled'] ?? '0') === '1' ? 'checked' : '' ?>></span>
                </label>
                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="label">官网域名黑名单</label>
                        <input name="email_domain_blacklist" value="<?= e($settings['email_domain_blacklist'] ?? '') ?>" class="input" placeholder="example.com, another-site.com">
                        <p class="field-hint">多个域名用逗号或换行分隔，命中的官网会被跳过。</p>
                    </div>
                    <div>
                        <label class="label">每个商家最多检查页数</label>
                        <input type="number" name="email_finder_max_pages" min="1" max="5" value="<?= e($settings['email_finder_max_pages'] ?? 3) ?>" class="input font-mono">
                    </div>
                </div>
                <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-3 text-[11px] leading-5 text-slate-500">
                    补全流程：读取官网首页 → 查找联系/关于页面 → 提取页面公开邮箱和 mailto 链接 → 保存来源 URL。只访问公开页面，遵守 robots.txt，不绕过登录/验证码，不猜测邮箱，也不进行 SMTP 验证。
                </div>
                <button class="btn btn-primary w-full"><?= icon('check', 'h-4 w-4') ?> 保存偏好</button>
                <p class="text-center font-mono text-[10px] uppercase tracking-[0.14em] text-slate-700">偏好保存于本地数据库</p>
            </form>
        </section>

        <section class="panel reveal reveal-delay-2">
            <div class="panel-head"><div><div class="eyebrow mb-1">节点健康</div><h2 class="panel-title"><?= icon('activity', 'h-4 w-4 text-electric') ?> 本地节点</h2></div><span class="status-badge border-signal/20 bg-signal/[0.05] text-signal">在线</span></div>
            <div class="grid grid-cols-2 gap-px bg-white/[0.04]">
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">PHP</div><div class="mt-1 font-mono text-[14px] text-slate-300"><?= e($health['php']) ?></div></div>
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">MYSQL</div><div class="mt-1 font-mono text-[14px] text-slate-300"><?= e($health['mysql']) ?></div></div>
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">数据库</div><div class="mt-1 font-mono text-[14px] text-slate-300"><?= number_format($dbKb, 1) ?> KB</div></div>
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">监听地址</div><div class="mt-1 font-mono text-[14px] text-slate-300">127.0.0.1</div></div>
            </div>
            <div class="space-y-2 p-4">
                <div class="flex justify-between text-[13px]"><span class="text-slate-600">活跃国家</span><span class="font-mono text-slate-300"><?= fmt_number($health['countries']) ?></span></div>
                <div class="flex justify-between text-[13px]"><span class="text-slate-600">行业分类</span><span class="font-mono text-slate-300"><?= fmt_number($health['industries']) ?></span></div>
                <div class="flex justify-between text-[13px]"><span class="text-slate-600">活跃关键词</span><span class="font-mono text-slate-300"><?= fmt_number($health['keywords']) ?></span></div>
            </div>
        </section>

        <section class="panel reveal reveal-delay-3 p-4">
            <div class="flex gap-2.5">
                <span class="mt-0.5 text-amber-400/70"><?= icon('lock', 'h-3.5 w-3.5') ?></span>
                <div>
                    <div class="font-mono text-[11px] uppercase tracking-[0.14em] text-amber-200/70">本地优先</div>
                    <p class="mt-1 text-[12px] leading-5 text-slate-500">服务默认只监听 127.0.0.1。若需局域网或公网访问，必须另行配置登录、CSRF、HTTPS 和防火墙。</p>
                </div>
            </div>
        </section>
    </div>
</div>
