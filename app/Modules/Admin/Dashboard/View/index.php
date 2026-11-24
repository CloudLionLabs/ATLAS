<?php
$businessTotal = (int) $stats['businesses'];
$coveragePct = $businessTotal > 0 ? min(100, (int) round(((int) $stats['with_website'] / max(1, $businessTotal)) * 100)) : 0;
$emailPct = $businessTotal > 0 ? min(100, (int) round(((int) $stats['with_email'] / max(1, $businessTotal)) * 100)) : 0;
$phonePct = $businessTotal > 0 ? min(100, (int) round(((int) $stats['with_phone'] / max(1, $businessTotal)) * 100)) : 0;
$series = $stats['volume_series'];
$maxSeries = max(1, max(array_map(static fn ($p) => (int) $p['count'], $series)));
$points = [];
$width = 180;
$height = 44;
foreach ($series as $i => $point) {
    $x = count($series) > 1 ? ($i / (count($series) - 1)) * $width : 0;
    $y = $height - (((int) $point['count'] / $maxSeries) * ($height - 6)) - 3;
    $points[] = round($x, 1) . ',' . round($y, 1);
}
$polyline = implode(' ', $points);
$donut = $stats['avg_completeness'];
$donutDash = 2 * M_PI * 42;
$donutOffset = $donutDash - ($donut / 100) * $donutDash;
$statusState = $scraperStatus['state'] ?? 'unknown';
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3">
                <span class="terminal-dot"></span>
                全球商业情报 / 实时运营
            </div>
            <h1 class="page-title text-balance">全球数据采集<span class="text-signal">指挥中心</span></h1>
            <p class="page-subtitle">从地理选点到多源采集、标准化入库与商业洞察，统一调度全球商家数据链路。所有统计直接来自 MySQL 业务库。</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if (is_admin()): ?>
            <form method="post" action="/settings/scraper/sync">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary"><?= icon('sync') ?> 同步采集任务</button>
            </form>
            <?php endif; ?>
            <a href="/jobs/create" class="btn btn-primary"><?= icon('crosshair') ?> 全球选点</a>
        </div>
    </div>
</section>

<div class="reveal reveal-delay-1 mb-5 overflow-hidden rounded-xl border border-white/[0.06] bg-white/[0.02]">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-2.5 font-mono text-[12px] uppercase tracking-[0.14em] text-slate-500">
        <span class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full <?= $statusState === 'online' ? 'bg-signal shadow-[0_0_8px_#20e3bd]' : 'bg-amber-400' ?>"></span> 采集引擎 <span data-scraper-node-state><?= $statusState === 'online' ? '在线' : ($statusState === 'offline' ? '离线' : '未知') ?></span></span>
        <span class="hidden text-slate-700 sm:inline">/</span>
        <span class="hidden sm:inline">活跃市场 <b class="font-medium text-slate-300"><?= fmt_number($stats['active_countries']) ?></b></span>
        <span class="hidden text-slate-700 md:inline">/</span>
        <span class="hidden md:inline">原始记录 <b class="font-medium text-slate-300"><?= fmt_number($stats['raw_records']) ?></b></span>
        <span class="hidden text-slate-700 lg:inline">/</span>
        <span class="hidden lg:inline">队列 <b class="font-medium text-signal"><?= fmt_number($stats['jobs_running']) ?></b></span>
        <span class="ml-auto flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-signal/60 animate-pulse-soft"></span> 本地时间 <?= e(gmdate('H:i:s')) ?> UTC</span>
    </div>
</div>

<div class="reveal reveal-delay-1 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <article class="kpi group">
        <div class="flex items-start justify-between">
            <div class="icon-chip"><?= icon('building') ?></div>
            <span class="tag tag-signal"><?= icon('arrow-up-right', 'h-3 w-3') ?> <?= fmt_number($stats['businesses_today']) ?> 今日</span>
        </div>
        <div class="kpi-value"><?= fmt_number($businessTotal) ?></div>
        <div class="kpi-label">商家总数</div>
        <div class="mt-4 flex items-end justify-between gap-3">
            <div class="kpi-meta"><?= fmt_number($stats['with_phone']) ?> 条含电话</div>
            <svg viewBox="0 0 180 44" class="h-9 w-[132px] overflow-visible opacity-80">
                <defs><linearGradient id="spark1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#20e3bd" stop-opacity=".28"/><stop offset="1" stop-color="#20e3bd" stop-opacity="0"/></linearGradient></defs>
                <polyline points="<?= e($polyline) ?>" fill="none" stroke="#20e3bd" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <polygon points="0,44 <?= e($polyline) ?> 180,44" fill="url(#spark1)"/>
            </svg>
        </div>
    </article>

    <article class="kpi group">
        <div class="flex items-start justify-between">
            <div class="icon-chip text-electric"><?= icon('radar') ?></div>
            <span class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-wider text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse-soft"></span> <?= fmt_number($stats['jobs_running']) ?> 进行中</span>
        </div>
        <div class="kpi-value"><?= fmt_number($stats['jobs']) ?></div>
        <div class="kpi-label">采集任务</div>
        <div class="mt-4 flex items-center gap-2">
            <div class="progress flex-1"><span style="width: <?= $stats['jobs'] > 0 ? round(($stats['jobs_completed'] / max(1, $stats['jobs'])) * 100) : 0 ?>%; background:#5b8dff; box-shadow:0 0 12px rgba(91,141,255,.55)"></span></div>
            <span class="font-mono text-[12px] text-slate-600"><?= fmt_number($stats['jobs_completed']) ?> 已完成</span>
        </div>
    </article>

    <article class="kpi group">
        <div class="flex items-start justify-between">
            <div class="icon-chip text-plasma"><?= icon('globe') ?></div>
            <span class="font-mono text-[11px] uppercase tracking-wider text-slate-500">官网覆盖</span>
        </div>
        <div class="kpi-value"><?= $coveragePct ?><span class="ml-1 text-lg text-slate-500">%</span></div>
        <div class="kpi-label">官网覆盖率</div>
        <div class="mt-4 flex items-center gap-2">
            <div class="progress flex-1"><span style="width: <?= $coveragePct ?>%; background:#a68cff; box-shadow:0 0 12px rgba(166,140,255,.55)"></span></div>
            <span class="font-mono text-[12px] text-slate-600"><?= fmt_number($stats['with_website']) ?> 个官网</span>
        </div>
    </article>

    <article class="kpi group">
        <div class="flex items-start justify-between">
            <div class="icon-chip text-hazard"><?= icon('map-pin') ?></div>
            <span class="font-mono text-[11px] uppercase tracking-wider text-slate-500">地理网络</span>
        </div>
        <div class="kpi-value"><?= fmt_number($stats['countries_covered']) ?><span class="ml-1 text-lg text-slate-500">/ <?= fmt_number($stats['active_countries']) ?></span></div>
        <div class="kpi-label">已覆盖国家</div>
        <div class="mt-4 flex items-center gap-2">
            <div class="progress flex-1"><span style="width: <?= $stats['active_countries'] > 0 ? round(($stats['countries_covered'] / $stats['active_countries']) * 100) : 0 ?>%; background:#ffb454; box-shadow:0 0 12px rgba(255,180,84,.55)"></span></div>
            <span class="font-mono text-[12px] text-slate-600"><?= fmt_number($stats['active_countries']) ?> 市场</span>
        </div>
    </article>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="panel reveal reveal-delay-2 overflow-hidden xl:col-span-8">
        <div class="panel-head">
            <div>
                <div class="eyebrow mb-1">地理情报</div>
                <h2 class="panel-title"><?= icon('globe', 'h-4 w-4 text-signal') ?> 全球覆盖矩阵</h2>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden items-center gap-2 font-mono text-[11px] uppercase tracking-wider text-slate-600 sm:flex">
                    <span class="h-1.5 w-1.5 rounded-full bg-signal"></span> 活跃节点
                    <span class="ml-2 h-1.5 w-1.5 rounded-full bg-electric"></span> 就绪
                </span>
                <a href="/jobs/create" class="btn btn-secondary btn-sm"><?= icon('expand', 'h-3.5 w-3.5') ?> 打开全球选点</a>
            </div>
        </div>
        <div class="map-shell m-3 mt-0 overflow-hidden rounded-xl border-white/[0.05]">
            <div id="coverage-map" class="map-canvas h-[350px] sm:h-[390px]"></div>
            <div class="map-overlay"></div>
            <div class="map-scan"></div>
            <div class="map-hud left-4 top-4 !px-2.5 !py-1.5">
                <span class="text-slate-600">信号</span> <span class="ml-1 text-signal">● 实时</span>
            </div>
            <div class="map-hud bottom-4 left-4 hidden !py-1.5 sm:block">
                <span data-map-coords>纬度 20.0000 · 经度 10.0000</span>
            </div>
            <div class="map-hud bottom-4 right-4 hidden !py-1.5 sm:block">
                <span class="text-slate-600">市场</span> <span class="ml-1 text-slate-300"><?= fmt_number(count($stats['coverage'])) ?></span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/[0.06] px-4 py-3">
            <span class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">覆盖排名</span>
            <?php foreach (array_slice($stats['coverage'], 0, 6) as $c): ?>
                <a href="/businesses?country=<?= (int) $c['id'] ?>" class="group flex items-center gap-2 text-[13px] text-slate-500 hover:text-slate-200">
                    <span><?= flag_emoji($c['iso2']) ?></span>
                    <span><?= e($c['iso2']) ?></span>
                    <span class="font-mono text-[12px] text-slate-600 group-hover:text-signal"><?= fmt_number($c['business_count']) ?></span>
                </a>
            <?php endforeach; ?>
            <a href="/countries" class="ml-auto font-mono text-[12px] uppercase tracking-wider text-signal/80 hover:text-signal">管理覆盖网络 →</a>
        </div>
    </section>

    <aside class="flex flex-col gap-4 xl:col-span-4">
        <section class="panel reveal reveal-delay-2 overflow-hidden">
            <div class="panel-head">
                <div>
                    <div class="eyebrow mb-1">系统脉冲</div>
                    <h2 class="panel-title"><?= icon('activity', 'h-4 w-4 text-signal') ?> 运行脉冲</h2>
                </div>
                <span class="status-badge border-signal/25 bg-signal/[0.07] text-signal">实时</span>
            </div>
            <div class="grid grid-cols-[136px_1fr] items-center gap-4 p-5">
                <div class="relative mx-auto h-[136px] w-[136px]">
                    <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
                        <circle cx="50" cy="50" r="42" fill="none" stroke="rgba(255,255,255,.05)" stroke-width="5"/>
                        <circle cx="50" cy="50" r="42" fill="none" stroke="url(#ringGrad)" stroke-width="5" stroke-linecap="round" stroke-dasharray="<?= round($donutDash, 1) ?>" stroke-dashoffset="<?= round($donutOffset, 1) ?>"/>
                        <defs>
                            <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#20e3bd"/><stop offset="100%" stop-color="#5b8dff"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <div class="font-display text-[30px] font-semibold leading-none text-white"><?= $donut ?><span class="ml-0.5 text-[15px] text-slate-500">%</span></div>
                        <div class="mt-1.5 font-mono text-[10px] uppercase tracking-[0.12em] text-slate-500">数据完整度</div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="mb-1 flex justify-between font-mono text-[11px] uppercase tracking-wider text-slate-600"><span>电话</span><span><?= $phonePct ?>%</span></div>
                        <div class="progress"><span style="width: <?= $phonePct ?>%"></span></div>
                    </div>
                    <div>
                        <div class="mb-1 flex justify-between font-mono text-[11px] uppercase tracking-wider text-slate-600"><span>官网</span><span><?= $coveragePct ?>%</span></div>
                        <div class="progress"><span style="width: <?= $coveragePct ?>%; background:#a68cff; box-shadow:0 0 10px rgba(166,140,255,.45)"></span></div>
                    </div>
                    <div>
                        <div class="mb-1 flex justify-between font-mono text-[11px] uppercase tracking-wider text-slate-600"><span>邮箱</span><span><?= $emailPct ?>%</span></div>
                        <div class="progress"><span style="width: <?= $emailPct ?>%; background:#5b8dff; box-shadow:0 0 10px rgba(91,141,255,.45)"></span></div>
                    </div>
                </div>
            </div>
            <div class="mx-5 mb-5 grid grid-cols-3 gap-2">
                <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] px-2 py-2.5 text-center">
                    <div class="font-mono text-sm text-white"><?= fmt_number($stats['jobs_running']) ?></div>
                    <div class="mt-1 font-mono text-[10px] uppercase tracking-wider text-slate-600">队列</div>
                </div>
                <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] px-2 py-2.5 text-center">
                    <div class="font-mono text-sm text-white"><?= fmt_number($stats['raw_records']) ?></div>
                    <div class="mt-1 font-mono text-[10px] uppercase tracking-wider text-slate-600">原始记录</div>
                </div>
                <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] px-2 py-2.5 text-center">
                    <div class="font-mono text-sm text-white"><?= fmt_number($stats['with_email']) ?></div>
                    <div class="mt-1 font-mono text-[10px] uppercase tracking-wider text-slate-600">邮箱</div>
                </div>
            </div>
        </section>

        <section class="panel reveal reveal-delay-3 relative overflow-hidden p-5">
            <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-signal/10 blur-3xl" data-parallax-glow></div>
            <div class="relative">
                <div class="eyebrow mb-2">下一步</div>
                <h3 class="text-[17px] font-semibold text-white">扩展下一个市场</h3>
                <p class="mt-1.5 text-[14px] leading-5 text-slate-500">在全球地图上缩放、点击，锁定城市与扫描半径，配置一条新的采集链路。</p>
                <div class="mt-4 flex items-center gap-2">
                    <a href="/jobs/create" class="btn btn-primary btn-sm flex-1"><?= icon('crosshair', 'h-3.5 w-3.5') ?> 开始选点</a>
                    <a href="/jobs/batch" class="btn btn-secondary btn-sm"><?= icon('layers', 'h-3.5 w-3.5') ?> 批量</a>
                </div>
                <div class="mt-4 flex items-center gap-2 border-t border-white/[0.06] pt-3 font-mono text-[11px] uppercase tracking-wider text-slate-600">
                    <span class="h-1 w-1 rounded-full bg-signal"></span>
                    <?= fmt_number($stats['active_countries']) ?> 个市场在线 · <?= fmt_number($stats['countries_covered']) ?> 个有数据
                </div>
            </div>
        </section>
    </aside>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="panel reveal reveal-delay-3 xl:col-span-8">
        <div class="panel-head">
            <div>
                <div class="eyebrow mb-1">采集动态</div>
                <h2 class="panel-title"><?= icon('radar', 'h-4 w-4 text-signal') ?> 最近采集任务</h2>
            </div>
            <a href="/jobs" class="btn btn-ghost btn-sm">全部任务 <?= icon('arrow-right', 'h-3.5 w-3.5') ?></a>
        </div>
        <?php if (!$stats['recent_jobs']): ?>
            <div class="empty py-12">
                <div class="empty-icon"><?= icon('radar', 'h-6 w-6') ?></div>
                <div class="text-[16px] font-medium text-slate-300">采集链路尚未启动</div>
                <p class="mt-1.5 max-w-xs text-[14px] leading-5 text-slate-600">从全球选点创建第一个任务，或同步现有采集任务。</p>
                <div class="mt-4 flex gap-2">
                    <a href="/jobs/create" class="btn btn-primary btn-sm">创建任务</a>
                    <?php if (is_admin()): ?><form method="post" action="/settings/scraper/sync"><?= csrf_field() ?><button class="btn btn-secondary btn-sm">同步外部任务</button></form><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>任务</th><th>市场</th><th>关键词</th><th>状态</th><th>结果</th><th>时间</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['recent_jobs'] as $job): ?>
                        <tr>
                            <td>
                                <a href="/jobs/<?= e($job['id']) ?>" class="block max-w-[230px] truncate text-[15px] font-medium text-slate-200 hover:text-signal"><?= e($job['task_name']) ?></a>
                                <span class="mt-0.5 block font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= e(substr($job['id'], 0, 8)) ?></span>
                            </td>
                            <td><span class="flex items-center gap-1.5"><?= flag_emoji($job['iso2'] ?? '') ?> <?= e($job['region_name'] ? place_name_cn($job['region_name']) : country_name_cn($job['iso2'] ?? '', $job['country_name'] ?? '—')) ?></span></td>
                            <td><span class="block max-w-[190px] truncate text-[14px] text-slate-500"><?= e($job['keyword_text']) ?></span></td>
                            <td><?= status_badge($job['status']) ?></td>
                            <td><span class="font-mono text-[14px] text-slate-300"><?= fmt_number($job['result_count']) ?></span></td>
                            <td><span class="font-mono text-[12px] text-slate-600"><?= e(fmt_datetime($job['created_at'])) ?></span></td>
                            <td class="text-right"><a href="/jobs/<?= e($job['id']) ?>" class="inline-grid h-7 w-7 place-items-center rounded-md text-slate-600 hover:bg-white/5 hover:text-signal"><?= icon('chevron-right', 'h-3.5 w-3.5') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel reveal reveal-delay-4 xl:col-span-4">
        <div class="panel-head">
            <div>
                <div class="eyebrow mb-1">情报流</div>
                <h2 class="panel-title"><?= icon('sparkles', 'h-4 w-4 text-plasma') ?> 最新入库</h2>
            </div>
            <a href="/businesses" class="font-mono text-[12px] uppercase tracking-wider text-slate-600 hover:text-signal">查看全部</a>
        </div>
        <?php if (!$stats['recent_businesses']): ?>
            <div class="empty py-10">
                <div class="empty-icon"><?= icon('building', 'h-6 w-6') ?></div>
                <div class="text-[15px] text-slate-400">还没有商家数据</div>
                <p class="mt-1 text-[13px] text-slate-600">导入 CSV 后，情报流会在这里出现。</p>
                <a href="/jobs" class="mt-4 font-mono text-[12px] uppercase tracking-wider text-signal/80 hover:text-signal">前往任务中心 →</a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-white/[0.05]">
                <?php foreach ($stats['recent_businesses'] as $b): ?>
                    <a href="/businesses/<?= (int) $b['id'] ?>" class="group flex items-start gap-3 px-5 py-3.5 transition hover:bg-white/[0.02]">
                        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-white/[0.07] bg-white/[0.03] font-mono text-[12px] text-slate-400 group-hover:border-signal/20 group-hover:text-signal"><?= e(initials($b['business_name'])) ?></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[14px] font-medium text-slate-200 group-hover:text-white"><?= e($b['business_name']) ?></span>
                            <span class="mt-0.5 flex items-center gap-2 font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= flag_emoji($b['iso2'] ?? '') ?> <?= e($b['city'] ? place_name_cn($b['city']) : '—') ?> <span class="text-slate-700">/</span> <?= e(industry_name_display($b['industry_id'] ?? null, $b['industry_name'] ?? null)) ?></span>
                        </span>
                        <span class="font-mono text-[12px] <?= ((int) $b['completeness_score'] >= 70) ? 'text-signal' : 'text-slate-600' ?>"><?= (int) $b['completeness_score'] ?>%</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof L === 'undefined') {
        const el = document.getElementById('coverage-map');
        if (el) el.innerHTML = '<div class="grid h-full place-items-center font-mono text-[13px] uppercase tracking-wider text-slate-600">地图引擎离线</div>';
        return;
    }
    const coverage = <?= $coverageJson ?: '[]' ?>;
    const map = L.map('coverage-map', {
        zoomControl: false,
        scrollWheelZoom: false,
        attributionControl: true,
        worldCopyJump: true,
        minZoom: 2,
        maxZoom: 12
    }).setView([<?= (float) $mapConfig['default_lat'] ?>, <?= (float) $mapConfig['default_lng'] ?>], <?= (int) $mapConfig['default_zoom'] ?>);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        maxNativeZoom: 16,
        attribution: '&copy; Esri, HERE, Garmin, OpenStreetMap contributors'
    }).addTo(map);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Reference/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        maxNativeZoom: 16,
        pane: 'overlayPane'
    }).addTo(map);

    const coords = document.querySelector('[data-map-coords]');
    map.on('mousemove', function (e) {
        if (coords) coords.textContent = '纬度 ' + e.latlng.lat.toFixed(4) + ' · 经度 ' + e.latlng.lng.toFixed(4);
    });

    coverage.forEach(function (c) {
        if (c.center_lat == null || c.center_lng == null) return;
        const count = Number(c.business_count || 0);
        const marker = L.marker([Number(c.center_lat), Number(c.center_lng)], {
            icon: L.divIcon({
                className: '',
                html: '<div class="coverage-marker ' + (count === 0 ? 'is-zero' : '') + '">' + count + '</div>',
                iconSize: [22, 22],
                iconAnchor: [11, 11]
            })
        }).addTo(map);
        marker.bindPopup('<strong>' + (c.name_cn || c.name_en) + '</strong><br><span style="color:#64748b">' + count + ' 家商家 / ' + (c.iso2 || '') + '</span><br><a style="color:#20e3bd" href="/businesses?country=' + c.id + '">查看该市场 →</a>');
    });
});
</script>
