<?php
$queryBase = array_filter([
    'status' => $filters['status'],
    'country' => $filters['countryId'] ?: null,
    'q' => $filters['q'],
], static fn ($v) => $v !== '' && $v !== null);
$statusLabels = [
    '' => '全部状态',
    'draft' => '草稿',
    'queued' => '等待提交',
    'submitting' => '提交中',
    'submitted' => '已提交',
    'running' => '运行中',
    'completed' => '已完成',
    'partial' => '部分导入',
    'failed' => '失败',
    'submission_unknown' => '提交未知',
    'cancelled' => '已取消',
];
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 采集运营 / 本地队列</div>
            <h1 class="page-title">采集<span class="text-signal">任务</span></h1>
            <p class="page-subtitle">管理本地任务与外部采集引擎的映射、状态同步、CSV 导入与失败重试。提交超时不会被盲目重发。</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/jobs/batch" class="btn btn-secondary"><?= icon('layers') ?> 批量任务</a>
            <a href="/jobs/create" class="btn btn-primary"><?= icon('crosshair') ?> 新建采集任务</a>
        </div>
    </div>
</section>

<div class="reveal reveal-delay-1 mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3">
        <div class="flex items-center justify-between"><span class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">任务总数</span><?= icon('radar', 'h-3.5 w-3.5 text-slate-600') ?></div>
        <div class="mt-2 font-display text-2xl font-semibold text-white" data-live-count="total"><?= fmt_number($counts['total'] ?? 0) ?></div>
    </div>
    <div class="rounded-xl border border-cyan-400/15 bg-cyan-400/[0.03] px-4 py-3">
        <div class="flex items-center justify-between"><span class="font-mono text-[11px] uppercase tracking-[0.16em] text-cyan-500/70">进行中</span><span class="h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse-soft"></span></div>
        <div class="mt-2 font-display text-2xl font-semibold text-cyan-200" data-live-count="active"><?= fmt_number($counts['active'] ?? 0) ?></div>
    </div>
    <div class="rounded-xl border border-emerald-400/15 bg-emerald-400/[0.03] px-4 py-3">
        <div class="flex items-center justify-between"><span class="font-mono text-[11px] uppercase tracking-[0.16em] text-emerald-500/70">已完成</span><?= icon('check-circle', 'h-3.5 w-3.5 text-emerald-500/60') ?></div>
        <div class="mt-2 font-display text-2xl font-semibold text-emerald-200" data-live-count="completed"><?= fmt_number($counts['completed'] ?? 0) ?></div>
    </div>
    <div class="rounded-xl border border-rose-400/15 bg-rose-400/[0.03] px-4 py-3">
        <div class="flex items-center justify-between"><span class="font-mono text-[11px] uppercase tracking-[0.16em] text-rose-500/70">失败 / 未知</span><?= icon('alert', 'h-3.5 w-3.5 text-rose-500/60') ?></div>
        <div class="mt-2 font-display text-2xl font-semibold text-rose-200" data-live-count="failed"><?= fmt_number($counts['failed'] ?? 0) ?></div>
    </div>
</div>

<div id="jobs-action-bar" class="reveal reveal-delay-1 mb-4 flex flex-wrap items-center gap-2">
    <?php if (!empty($queueBatch)): $qPausedBar = (int) ($queueBatch['queue_paused'] ?? 0) === 1; ?>
        <form method="post" action="/jobs/batches/<?= e($queueBatch['id']) ?>/queue-toggle">
            <?= csrf_field() ?>
            <button class="btn btn-secondary btn-sm"><?= icon('sync', 'h-3.5 w-3.5') ?> <?= $qPausedBar ? '继续自动队列' : '暂停自动队列' ?></button>
        </form>
        <form method="post" action="/jobs/batches/<?= e($queueBatch['id']) ?>/cancel-all" data-confirm="确认取消这个队列所有未完成任务？正在运行的外部 Scraper 任务会尝试删除，队列会停止自动提交。">
            <?= csrf_field() ?>
            <button class="btn btn-danger btn-sm"><?= icon('x', 'h-3.5 w-3.5') ?> 全取消</button>
        </form>
        <form method="post" action="/jobs/batches/<?= e($queueBatch['id']) ?>/delete" data-confirm="确认删除整个队列？本批次所有任务、原始记录和仅属于这些任务的商家都会被清理；外部 Scraper 任务不会删除。">
            <?= csrf_field() ?>
            <button class="btn btn-danger btn-sm"><?= icon('trash', 'h-3.5 w-3.5') ?> 删除整个队列</button>
        </form>
    <?php endif; ?>
    <?php if (!empty($cancelledCount)): ?>
        <form method="post" action="/jobs/delete-cancelled" data-confirm="确认删除当前账号所有已取消任务？任务专属的原始记录和商家会一并清理。">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash', 'h-3.5 w-3.5') ?> 删除全部已取消（<?= fmt_number($cancelledCount) ?>）</button>
        </form>
    <?php endif; ?>
    <?php if (!empty($emptyCount)): ?>
        <form method="post" action="/jobs/delete-empty" data-confirm="确认删除全部空结果任务？只删除已完成且 0 行、没有原始记录的任务。">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary btn-sm"><?= icon('trash', 'h-3.5 w-3.5') ?> 删除全部空结果（<?= fmt_number($emptyCount) ?>）</button>
        </form>
    <?php endif; ?>
</div>


<section class="panel reveal reveal-delay-2">
    <div class="border-b border-white/[0.06] px-4 py-3">
        <form method="get" action="/jobs" class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[220px] flex-1">
                <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-600') ?>
                <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="搜索任务名称、关键词或外部 ID…" class="input !pl-9">
            </div>
            <select name="status" class="select !w-auto min-w-[130px]" data-auto-submit>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="country" class="select !w-auto min-w-[140px]" data-auto-submit>
                <option value="">全部国家</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['countryId'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary"><?= icon('filter', 'h-3.5 w-3.5') ?> 筛选</button>
            <?php if ($queryBase): ?><a href="/jobs" class="btn btn-ghost btn-sm">清除</a><?php endif; ?>
            <span class="ml-auto flex items-center gap-2 font-mono text-[12px] uppercase tracking-wider text-slate-600"><span class="terminal-dot" id="jobs-live-dot"></span><span id="jobs-live-state">自动刷新 · 10s</span><span class="text-slate-700">/</span><span id="jobs-live-total"><?= fmt_number($total) ?></span> 条结果</span>
        </form>
    </div>

    <div id="jobs-live-content">
    <?php if (!empty($queueBatch)): 
        $qCounts = $queueBatch['status_counts'] ?? [];
        $qTotal = array_sum($qCounts);
        $qDone = (int) ($qCounts['completed'] ?? 0) + (int) ($qCounts['partial'] ?? 0);
        $qActive = (int) ($qCounts['running'] ?? 0) + (int) ($qCounts['submitted'] ?? 0) + (int) ($qCounts['submitting'] ?? 0);
        $qQueued = (int) ($qCounts['queued'] ?? 0) + (int) ($qCounts['draft'] ?? 0);
        $qCancelled = (int) ($qCounts['cancelled'] ?? 0);
        $qPaused = (int) ($queueBatch['queue_paused'] ?? 0) === 1;
        $qStatus = '运行中';
        $qStatusClass = 'tag-signal';
        if ($qActive === 0 && $qQueued === 0) {
            $qStatus = $qDone >= $qTotal ? '已完成' : '已结束';
            $qStatusClass = $qDone >= $qTotal ? '!border-emerald-400/20 !text-emerald-300' : '!border-slate-400/20 !text-slate-300';
        } elseif ($qPaused) {
            $qStatus = '已暂停';
            $qStatusClass = '!border-amber-400/20 !text-amber-300';
        }
    ?>
        <div class="border-b border-white/[0.07] bg-gradient-to-r from-signal/[0.045] via-transparent to-transparent px-4 py-3.5">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="eyebrow !mb-0">自动覆盖队列</span>
                        <span class="tag <?= $qStatusClass ?>"><?= $qStatus ?></span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-[14px] font-medium text-slate-200"><span class="truncate"><?= e($queueBatch['name']) ?></span><span class="font-mono text-[10px] font-normal uppercase tracking-wider text-slate-600"><?= e(fmt_datetime($queueBatch['created_at'])) ?></span></div>
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 font-mono text-[11px] uppercase tracking-wider text-slate-600">
                        <span>总数 <?= fmt_number($qTotal) ?></span>
                        <span class="text-emerald-400/80">完成 <?= fmt_number($qDone) ?></span>
                        <span class="text-cyan-400/80">运行 <?= fmt_number($qActive) ?></span>
                        <span class="text-amber-400/80">等待 <?= fmt_number($qQueued) ?></span>
                        <?php if ($qCancelled): ?><span class="text-slate-500">取消 <?= fmt_number($qCancelled) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!$jobs): ?>
        <div class="empty">
            <div class="empty-icon"><?= icon('radar', 'h-6 w-6') ?></div>
            <div class="text-[16px] font-medium text-slate-300"><?= $queryBase ? '没有匹配的采集任务' : '等待第一条采集链路' ?></div>
            <p class="mt-2 max-w-sm text-[14px] leading-5 text-slate-600"><?= $queryBase ? '调整筛选条件，或清除筛选查看全部任务。' : '在全球地图上选择目标区域并创建任务，或从采集引擎同步已有任务。' ?></p>
            <div class="mt-5 flex flex-wrap justify-center gap-2">
                <?php if ($queryBase): ?>
                    <a href="/jobs" class="btn btn-secondary btn-sm">清除筛选</a>
                <?php else: ?>
                    <a href="/jobs/create" class="btn btn-primary btn-sm"><?= icon('crosshair', 'h-3.5 w-3.5') ?> 全球选点</a>
                    <?php if (is_admin()): ?><form method="post" action="/settings/scraper/sync"><?= csrf_field() ?><button class="btn btn-secondary btn-sm">同步外部任务</button></form><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>任务</th>
                    <th>目标市场</th>
                    <th>关键词</th>
                    <th>状态</th>
                    <th>外部 ID</th>
                    <th>结果</th>
                    <th>创建时间</th>
                    <th class="text-right">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td>
                            <a href="/jobs/<?= e($job['id']) ?>" class="block max-w-[250px] truncate text-[15px] font-medium text-slate-200 hover:text-signal"><?= e($job['task_name']) ?></a>
                            <div class="mt-1 flex items-center gap-2 font-mono text-[11px] uppercase tracking-wider text-slate-600">
                                <span><?= e(substr($job['id'], 0, 8)) ?></span>
                                <?php if ($job['batch_id']): ?><span class="text-slate-700">/</span><span class="text-plasma/70">批次</span><?php endif; ?>
                                <?php if ($job['external_status']): ?><span class="text-slate-700">/</span><span class="text-slate-500"><?= e(external_status_label($job['external_status'])) ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="flex items-center gap-1.5 whitespace-nowrap"><?= flag_emoji($job['iso2'] ?? '') ?> <?= e($job['region_name'] ? place_name_cn($job['region_name']) : country_name_cn($job['iso2'] ?? '', $job['country_name'] ?? '未指定')) ?></span>
                            <span class="mt-1 block text-[12px] text-slate-600"><?= e(country_name_cn($job['iso2'] ?? '', $job['country_name'] ?? '—')) ?></span>
                            <?php if ($job['latitude'] !== null && $job['longitude'] !== null): ?>
                                <span class="mt-1 block font-mono text-[10px] tracking-wide text-slate-700" title="纬度, 经度"><?= e(number_format((float) $job['latitude'], 4, '.', '')) ?>, <?= e(number_format((float) $job['longitude'], 4, '.', '')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="block max-w-[210px] truncate text-[14px] text-slate-400"><?= e($job['keyword_text']) ?></span>
                            <span class="mt-1 block font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= e(language_name($job['language_code'])) ?> · Z<?= (int) $job['zoom'] ?> · <?= fmt_number((int) $job['radius'] / 1000) ?> 公里</span>
                        </td>
                        <td><?= status_badge($job['status']) ?></td>
                        <td>
                            <?php if ($job['scraper_job_id']): ?>
                                <span class="block max-w-[110px] truncate font-mono text-[12px] text-slate-500" title="<?= e($job['scraper_job_id']) ?>"><?= e(substr($job['scraper_job_id'], 0, 8)) ?>…</span>
                            <?php else: ?>
                                <span class="font-mono text-[12px] text-slate-700">未关联</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="font-mono text-[14px] text-slate-300"><?= fmt_number($job['result_count']) ?></span></td>
                        <td><span class="font-mono text-[12px] text-slate-600"><?= e(fmt_datetime($job['created_at'])) ?></span></td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                <?php if (in_array($job['status'], ['draft', 'queued', 'failed'], true)): ?>
                                    <form method="post" action="/jobs/<?= e($job['id']) ?>/retry" data-confirm="确认向采集引擎提交该任务？">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-secondary btn-sm !px-2 !py-1" title="提交到采集引擎"><?= icon('zap', 'h-3 w-3') ?> 提交</button>
                                    </form>
                                <?php endif; ?>
                                <?php
                                $runningWarning = in_array($job['status'], ['running', 'submitting', 'submitted', 'queued'], true)
                                    ? '该任务仍在运行或排队，删除本地任务不会取消外部 Scraper 任务。'
                                    : '';
                                $deletePrompt = $runningWarning . ((int) $job['result_count'] > 0
                                    ? '该任务有 ' . fmt_number($job['result_count']) . ' 行结果。删除任务后，仅属于该任务的商家和原始记录会一并删除；同时属于其他任务的商家会保留。确认删除？'
                                    : '确认删除这个任务？');
                                ?>
                                <form method="post" action="/jobs/<?= e($job['id']) ?>/delete" data-confirm="<?= e($deletePrompt) ?>">
                                    <?= csrf_field() ?>
                                    <button class="grid h-7 w-7 place-items-center rounded-md text-slate-600 transition hover:bg-rose-400/[0.05] hover:text-rose-300" title="删除任务"><?= icon('trash', 'h-3.5 w-3.5') ?></button>
                                </form>
                                <a href="/jobs/<?= e($job['id']) ?>" class="grid h-7 w-7 place-items-center rounded-md text-slate-600 transition hover:bg-white/[0.05] hover:text-signal" title="查看详情"><?= icon('chevron-right', 'h-3.5 w-3.5') ?></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <div class="flex items-center justify-between border-t border-white/[0.06] px-4 py-3">
                <span class="font-mono text-[12px] uppercase tracking-wider text-slate-600">第 <?= $page ?> / <?= $pages ?></span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="/jobs?<?= e(http_build_query(array_merge($queryBase, ['page' => $page - 1]))) ?>" class="btn btn-secondary btn-sm">上一页</a>
                    <?php endif; ?>
                    <?php if ($page < $pages): ?>
                        <a href="/jobs?<?= e(http_build_query(array_merge($queryBase, ['page' => $page + 1]))) ?>" class="btn btn-secondary btn-sm">下一页</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const region = document.getElementById('jobs-live-content');
    const state = document.getElementById('jobs-live-state');
    const dot = document.getElementById('jobs-live-dot');
    if (!region) return;
    let running = false;
    async function refreshLiveList() {
        if (running || document.visibilityState === 'hidden') return;
        running = true;
        if (dot) dot.classList.add('animate-pulse-soft');
        try {
            // Sync active external tasks first so the list reflects completion
            // and the coverage queue can advance without a page reload.
            try {
                const activeResponse = await fetch('/api/jobs/active', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } });
                const activeJson = await activeResponse.json();
                if (activeJson.success && Array.isArray(activeJson.data)) {
                    for (const active of activeJson.data) {
                        if (!active.id) continue;
                        await fetch('/jobs/' + encodeURIComponent(active.id) + '/sync', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ _token: (window.ATLAS && window.ATLAS.csrf) || '' }).toString(),
                            redirect: 'follow'
                        });
                    }
                }
            } catch (e) {
                // Status refresh still works if the scraper is temporarily unavailable.
            }

            const url = new URL(window.location.href);
            url.searchParams.set('_live', String(Date.now()));
            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'fetch', 'Accept': 'text/html' }
            });
            if (!response.ok) return;
            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextRegion = doc.getElementById('jobs-live-content');
            if (nextRegion && nextRegion.innerHTML !== region.innerHTML) {
                region.innerHTML = nextRegion.innerHTML;
            }
            doc.querySelectorAll('[data-live-count]').forEach(function (source) {
                const target = document.querySelector('[data-live-count="' + source.dataset.liveCount + '"]');
                if (target) target.textContent = source.textContent;
            });
            const nextTotal = doc.getElementById('jobs-live-total');
            const total = document.getElementById('jobs-live-total');
            if (nextTotal && total) total.textContent = nextTotal.textContent;
            const nextActions = doc.getElementById('jobs-action-bar');
            const actions = document.getElementById('jobs-action-bar');
            if (nextActions && actions && nextActions.innerHTML !== actions.innerHTML) {
                actions.innerHTML = nextActions.innerHTML;
            }
            if (state) state.textContent = '自动刷新 · ' + new Date().toLocaleTimeString('zh-CN', { hour12: false });
        } catch (e) {
            if (state) state.textContent = '自动刷新暂停';
        } finally {
            running = false;
            if (dot) dot.classList.remove('animate-pulse-soft');
        }
    }
    window.setInterval(refreshLiveList, 10000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') refreshLiveList();
    });
});
</script>
