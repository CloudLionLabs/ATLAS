<?php
$meta = status_meta($job['status']);
$jobLat = $job['latitude'] !== null ? (float) $job['latitude'] : null;
$jobLng = $job['longitude'] !== null ? (float) $job['longitude'] : null;
$elapsed = null;
if (!empty($job['submitted_at']) && !empty($job['completed_at'])) {
    $elapsed = max(0, strtotime($job['completed_at']) - strtotime($job['submitted_at']));
}
?>
<section class="reveal mb-6">
    <div class="mb-4 flex items-center gap-2 font-mono text-[12px] uppercase tracking-[0.14em] text-slate-600">
        <a href="/jobs" class="hover:text-slate-300">采集任务</a><span class="text-slate-700">/</span><span class="text-slate-500"><?= e(substr($job['id'], 0, 8)) ?></span>
    </div>
    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
        <div class="min-w-0">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <?= status_badge($job['status']) ?>
                <?php if (in_array($job['status'], ['running', 'submitting', 'submitted'], true) && $job['scraper_job_id']): ?>
                    <span class="tag tag-signal"><span class="h-1.5 w-1.5 rounded-full bg-signal animate-pulse-soft"></span> 自动同步已开启</span>
                <?php endif; ?>
                <?php if ($job['external_status']): ?><span class="tag"><?= e(external_status_label($job['external_status'])) ?></span><?php endif; ?>
                <?php if ($job['batch_id']): ?><span class="tag tag-signal">批次成员</span><?php endif; ?>
                <?php if (!empty($job['batch_queue_mode'])): ?><span class="tag tag-signal">自动队列</span><?php endif; ?>
                <?php if (!empty($job['batch_queue_paused'])): ?><span class="tag !border-amber-400/20 !text-amber-300">队列已暂停</span><?php endif; ?>
                <?php if ($job['extract_email']): ?><span class="tag">已开启邮箱</span><?php endif; ?>
            </div>
            <h1 class="page-title text-balance"><?= e($job['task_name']) ?></h1>
            <p class="mt-2 flex flex-wrap items-center gap-2 text-[15px] text-slate-500">
                <span class="flex items-center gap-1.5"><?= flag_emoji($job['iso2'] ?? '') ?> <?= e(country_name_cn($job['iso2'] ?? '', $job['country_name'] ?? '未指定国家')) ?><?= $job['region_name'] ? ' / ' . e(place_name_cn($job['region_name'])) : '' ?></span>
                <span class="text-slate-700">·</span>
                <span><?= e(industry_name_display($job['industry_id'] ? (int) $job['industry_id'] : null, $job['industry_name'] ?? null)) ?></span>
                <span class="text-slate-700">·</span>
                <span class="font-mono text-[13px]"><?= e($job['language_code']) ?></span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($job['status'] === 'submission_unknown'): ?>
                <span class="btn btn-danger btn-sm cursor-default" title="禁止自动重试"><?= icon('alert', 'h-3.5 w-3.5') ?> 提交结果未知</span>
            <?php elseif (in_array($job['status'], ['draft', 'queued', 'failed'], true)): ?>
                <form method="post" action="/jobs/<?= e($job['id']) ?>/retry" data-confirm="确认向采集引擎提交该任务？">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary btn-sm"><?= icon('zap', 'h-3.5 w-3.5') ?> <?= $job['status'] === 'failed' ? '重试提交' : '提交采集任务' ?></button>
                </form>
            <?php endif; ?>
            <?php if ($job['scraper_job_id']): ?>
                <form method="post" action="/jobs/<?= e($job['id']) ?>/sync"><?= csrf_field() ?><button class="btn btn-secondary btn-sm"><?= icon('sync', 'h-3.5 w-3.5') ?> 同步状态</button></form>
                <form method="post" action="/jobs/<?= e($job['id']) ?>/import" data-confirm="从采集引擎下载 CSV 并导入？重复导入不会重复创建原始记录。"><?= csrf_field() ?><button class="btn btn-secondary btn-sm"><?= icon('download', 'h-3.5 w-3.5') ?> 下载并导入</button></form>
            <?php endif; ?>
            <?php if (!empty($job['raw_csv_path'])): ?>
                <a href="/jobs/<?= e($job['id']) ?>/download" class="btn btn-secondary btn-sm"><?= icon('file', 'h-3.5 w-3.5') ?> 原始 CSV</a>
            <?php endif; ?>
            <?php if (!empty($job['batch_queue_mode'])): ?>
                <form method="post" action="/jobs/<?= e($job['id']) ?>/queue-toggle">
                    <?= csrf_field() ?>
                    <button class="btn btn-secondary btn-sm"><?= icon('sync', 'h-3.5 w-3.5') ?> <?= !empty($job['batch_queue_paused']) ? '继续自动队列' : '暂停自动队列' ?></button>
                </form>
            <?php endif; ?>
            <?php if (!in_array($job['status'], ['completed', 'partial', 'cancelled'], true)): ?>
                <form method="post" action="/jobs/<?= e($job['id']) ?>/cancel" data-confirm="确认取消这个任务？正在运行的外部 Scraper 任务会尝试删除，本任务不再自动同步。">
                    <?= csrf_field() ?>
                    <button class="btn btn-secondary btn-sm"><?= icon('x', 'h-3.5 w-3.5') ?> 取消任务</button>
                </form>
            <?php endif; ?>
            <?php if (!empty($job['batch_id'])): ?>
                <form method="post" action="/jobs/<?= e($job['id']) ?>/cancel-all" data-confirm="确认取消本批次所有未完成任务？正在运行的外部 Scraper 任务会尝试删除，已取消的任务不会继续自动提交。">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger btn-sm"><?= icon('x', 'h-3.5 w-3.5') ?> 全取消</button>
                </form>
                <?php if (!empty($batchCancelledCount)): ?>
                    <form method="post" action="/jobs/<?= e($job['id']) ?>/delete-cancelled" data-confirm="确认删除本批次所有已取消任务？任务专属的原始记录和商家会一并清理。">
                        <?= csrf_field() ?>
                        <button class="btn btn-secondary btn-sm"><?= icon('trash', 'h-3.5 w-3.5') ?> 删除本批已取消（<?= fmt_number($batchCancelledCount) ?>）</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php
            $runningWarning = in_array($job['status'], ['running', 'submitting', 'submitted', 'queued'], true)
                ? '该任务仍在运行或排队，删除本地任务不会取消外部 Scraper 任务。'
                : '';
            $deletePrompt = $runningWarning . (((int) $job['result_count'] > 0 || $rawCount > 0 || $businessCount > 0)
                ? '该任务有 ' . fmt_number($job['result_count']) . ' 行结果、' . fmt_number($rawCount) . ' 条原始记录、' . fmt_number($businessCount) . ' 家关联商家。删除后，仅属于该任务的商家和原始记录会一并删除；同时属于其他任务的商家会保留。此操作不可恢复，确认删除？'
                : '确认删除这个任务？');
            ?>
            <form method="post" action="/jobs/<?= e($job['id']) ?>/delete" data-confirm="<?= e($deletePrompt) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash', 'h-3.5 w-3.5') ?> 删除任务</button>
            </form>
        </div>
    </div>
</section>

<div class="reveal reveal-delay-1 mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">已导入行数</div>
        <div class="mt-2 font-display text-2xl font-semibold text-white"><?= fmt_number($job['result_count']) ?></div>
        <div class="mt-1 font-mono text-[11px] text-slate-700"><?= fmt_number($rawCount) ?> 原始记录</div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">关联商家</div>
        <div class="mt-2 font-display text-2xl font-semibold text-signal"><?= fmt_number($businessCount) ?></div>
        <div class="mt-1 font-mono text-[11px] text-slate-700">来源关联</div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">扫描范围</div>
        <div class="mt-2 font-display text-2xl font-semibold text-electric"><?= fmt_number((int) $job['radius'] / 1000) ?><span class="text-sm text-slate-600"> km</span></div>
        <div class="mt-1 font-mono text-[11px] text-slate-700">缩放 Z<?= (int) $job['zoom'] ?> · 深度 <?= (int) $job['depth'] ?></div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">运行时长</div>
        <div class="mt-2 font-display text-2xl font-semibold text-plasma"><?= $elapsed !== null ? gmdate('i:s', $elapsed) : '—' ?></div>
        <div class="mt-1 font-mono text-[11px] text-slate-700"><?= e(fmt_datetime($job['submitted_at'], '尚未提交')) ?></div>
    </div>
</div>

<?php if ($rawCount === 0 && $job['status'] === 'completed'): ?>
    <div class="reveal reveal-delay-1 mb-4 flex gap-3 rounded-xl border border-amber-400/20 bg-amber-400/[0.04] p-4">
        <span class="mt-0.5 shrink-0 text-amber-300"><?= icon('alert', 'h-4 w-4') ?></span>
        <div>
            <div class="font-mono text-[11px] uppercase tracking-[0.14em] text-amber-300">空结果 / 0 行</div>
            <p class="mt-1 text-[13px] leading-6 text-amber-100/70">外部任务已完成，但采集引擎返回 0 字节 CSV。常见原因是目标坐标与关键词不匹配、缩放级别过低，或该区域确实没有搜索结果。请检查下方目标位置；也可以修正选点后重新创建任务。</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="/jobs/create" class="btn btn-secondary btn-sm"><?= icon('crosshair', 'h-3.5 w-3.5') ?> 重新选点创建</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($job['error_message']): ?>
    <div class="reveal reveal-delay-1 mb-4 flex gap-3 rounded-xl border border-rose-400/20 bg-rose-400/[0.04] p-4">
        <span class="mt-0.5 shrink-0 text-rose-400"><?= icon('alert', 'h-4 w-4') ?></span>
        <div>
            <div class="font-mono text-[12px] uppercase tracking-[0.14em] text-rose-300">任务异常</div>
            <p class="mt-1 whitespace-pre-wrap text-[14px] leading-5 text-rose-100/70"><?= e($job['error_message']) ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <div class="space-y-4 xl:col-span-8">
        <section class="panel overflow-hidden reveal reveal-delay-2">
            <div class="panel-head">
                <div>
                    <div class="eyebrow mb-1">目标区域</div>
                    <h2 class="panel-title"><?= icon('map-pin', 'h-4 w-4 text-signal') ?> 目标区域</h2>
                </div>
                <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= $jobLat !== null ? e(number_format($jobLat, 5) . ' / ' . number_format($jobLng, 5)) : 'NO COORDINATES' ?></span>
            </div>
            <?php if ($jobLat !== null && $jobLng !== null): ?>
                <div class="relative m-3 overflow-hidden rounded-xl border border-white/[0.06]">
                    <div id="job-map" class="map-canvas h-[300px] sm:h-[340px]"></div>
                    <div class="map-overlay"></div>
                    <div class="map-scan"></div>
                    <div class="map-hud left-3 top-3 !px-2.5 !py-1.5"><span class="text-signal">●</span> 目标已锁定</div>
                    <div class="absolute bottom-3 left-3 z-[600] max-w-[80%] rounded-lg border border-white/[0.08] bg-ink-950/85 px-3 py-2 backdrop-blur-md">
                        <div class="truncate text-[13px] text-slate-300"><?= e($job['target_address'] ?: $job['keyword_text']) ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty py-14"><div class="empty-icon"><?= icon('map-pin', 'h-6 w-6') ?></div><div class="text-[15px] text-slate-400">该任务没有坐标信息</div></div>
            <?php endif; ?>
            <div class="grid grid-cols-2 gap-px border-t border-white/[0.06] bg-white/[0.04] sm:grid-cols-4">
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">关键词</div><div class="mt-1 truncate text-[13px] text-slate-300"><?= e($job['keyword_text']) ?></div></div>
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">纬度</div><div class="mt-1 font-mono text-[13px] text-slate-300"><?= $jobLat !== null ? e(number_format($jobLat, 6)) : '—' ?></div></div>
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">经度</div><div class="mt-1 font-mono text-[13px] text-slate-300"><?= $jobLng !== null ? e(number_format($jobLng, 6)) : '—' ?></div></div>
                <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">目标类型</div><div class="mt-1 font-mono text-[13px] text-slate-300">半径 <?= fmt_number((int) $job['radius']) ?> 米</div></div>
            </div>
        </section>

        <section class="panel reveal reveal-delay-3">
            <div class="panel-head">
                <div>
                    <div class="eyebrow mb-1">来源追踪</div>
                    <h2 class="panel-title"><?= icon('database', 'h-4 w-4 text-plasma') ?> 原始记录 / 原始记录</h2>
                </div>
                <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= fmt_number($rawCount) ?> 行</span>
            </div>
            <?php if (!$rawRecords): ?>
                <div class="empty py-10">
                    <div class="empty-icon"><?= icon('database', 'h-6 w-6') ?></div>
                    <div class="text-[15px] text-slate-400">尚未导入原始数据</div>
                    <p class="mt-1 max-w-sm text-[13px] leading-5 text-slate-600">下载采集引擎 CSV 或手动上传。所有原始字段将以 JSON 完整保留，清洗不会覆盖原值。</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>行号</th><th>外部数据 ID</th><th>内容哈希</th><th>导入时间</th></tr></thead>
                        <tbody>
                        <?php foreach ($rawRecords as $raw): ?>
                            <tr>
                                <td><span class="font-mono text-[13px] text-slate-400">#<?= (int) $raw['row_no'] ?></span></td>
                                <td><span class="font-mono text-[12px] text-slate-500"><?= e($raw['external_data_id'] ?: '—') ?></span></td>
                                <td><span class="font-mono text-[12px] text-slate-600"><?= e(substr($raw['content_hash'], 0, 16)) ?>…</span></td>
                                <td><span class="font-mono text-[12px] text-slate-600"><?= e(fmt_datetime($raw['imported_at'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <div class="border-t border-white/[0.06] p-4">
                <form method="post" action="/jobs/import/upload" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="job_id" value="<?= e($job['id']) ?>">
                    <input type="file" name="csv" accept=".csv,text/csv" required class="block w-full text-[13px] text-slate-500 file:mr-3 file:rounded-lg file:border file:border-white/[0.08] file:bg-white/[0.04] file:px-3 file:py-2 file:text-[13px] file:text-slate-300 hover:file:border-signal/30">
                    <button class="btn btn-secondary btn-sm shrink-0"><?= icon('upload', 'h-3.5 w-3.5') ?> 上传 CSV</button>
                </form>
                <p class="mt-2 font-mono text-[10px] uppercase tracking-wider text-slate-700">UTF-8 / UTF-8 BOM · MAX 50 MB · 已启用表头校验</p>
            </div>
        </section>
    </div>

    <div class="space-y-4 xl:col-span-4">
        <section class="panel reveal reveal-delay-2">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">任务生命周期</div><h2 class="panel-title"><?= icon('clock', 'h-4 w-4 text-signal') ?> 时间线</h2></div>
            </div>
            <div class="p-5">
                <div class="timeline">
                    <div class="timeline-item"><div class="text-[14px] font-medium text-slate-200">任务创建</div><div class="mt-1 font-mono text-[12px] text-slate-600"><?= e(fmt_datetime($job['created_at'])) ?></div></div>
                    <?php if ($job['submitted_at']): ?><div class="timeline-item"><div class="text-[14px] font-medium text-slate-200">提交至采集引擎</div><div class="mt-1 font-mono text-[12px] text-slate-600"><?= e(fmt_datetime($job['submitted_at'])) ?></div></div><?php endif; ?>
                    <?php if ($job['started_at']): ?><div class="timeline-item"><div class="text-[14px] font-medium text-slate-200">外部任务开始</div><div class="mt-1 font-mono text-[12px] text-slate-600"><?= e(fmt_datetime($job['started_at'])) ?></div></div><?php endif; ?>
                    <?php if ($job['completed_at']): ?><div class="timeline-item"><div class="text-[14px] font-medium text-slate-200">CSV 导入完成</div><div class="mt-1 font-mono text-[12px] text-slate-600"><?= e(fmt_datetime($job['completed_at'])) ?></div></div><?php endif; ?>
                </div>
                <div class="mt-5 space-y-2 border-t border-white/[0.06] pt-4 font-mono text-[12px]">
                    <div class="flex justify-between"><span class="text-slate-600">本地 ID</span><span class="max-w-[180px] truncate text-slate-400" title="<?= e($job['id']) ?>"><?= e($job['id']) ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-600">采集引擎 ID</span><span class="max-w-[180px] truncate text-slate-400" title="<?= e($job['scraper_job_id'] ?? '') ?>"><?= e($job['scraper_job_id'] ?: '未关联') ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-600">上次同步</span><span class="text-slate-400"><?= e(fmt_datetime($job['last_synced_at'], 'NEVER')) ?></span></div>
                </div>
            </div>
        </section>

        <section class="panel reveal reveal-delay-3">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">导入日志</div><h2 class="panel-title"><?= icon('file', 'h-4 w-4 text-hazard') ?> 导入日志</h2></div>
            </div>
            <?php if (!$logs): ?>
                <div class="px-5 py-7 text-center text-[13px] text-slate-600">暂无导入日志</div>
            <?php else: ?>
                <div class="divide-y divide-white/[0.05]">
                    <?php foreach ($logs as $log): ?>
                        <div class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <?= status_badge($log['status']) ?>
                                <span class="ml-auto font-mono text-[11px] text-slate-600"><?= e(fmt_datetime($log['created_at'])) ?></span>
                            </div>
                            <p class="mt-2 text-[13px] leading-4 text-slate-500"><?= e($log['message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel p-4 reveal reveal-delay-4">
            <div class="eyebrow mb-2">注意事项</div>
            <p class="text-[13px] leading-5 text-slate-500">任务删除、原始 CSV 删除与商家数据删除是不同操作。本系统不会自动删除外部任务。</p>
            <?php if (is_admin()): ?><a href="/settings" class="mt-3 inline-flex items-center gap-1 font-mono text-[11px] uppercase tracking-wider text-signal/80 hover:text-signal">连接配置 →</a><?php endif; ?>
        </section>
    </div>
</div>

<?php if ($jobLat !== null && $jobLng !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof L === 'undefined') return;
    const map = L.map('job-map', { zoomControl: true, scrollWheelZoom: false, attributionControl: true }).setView([<?= $jobLat ?>, <?= $jobLng ?>], Math.min(15, <?= max(2, (int) $job['zoom']) ?>));
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, maxNativeZoom: 16, attribution: '&copy; Esri, HERE, Garmin, OpenStreetMap contributors' }).addTo(map);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Reference/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, maxNativeZoom: 16 }).addTo(map);
    L.circle([<?= $jobLat ?>, <?= $jobLng ?>], { radius: <?= (int) $job['radius'] ?>, color: '#20e3bd', weight: 1.2, opacity: .75, fillColor: '#20e3bd', fillOpacity: .08, dashArray: '4 6' }).addTo(map);
    L.marker([<?= $jobLat ?>, <?= $jobLng ?>], {
        icon: L.divIcon({ className: '', html: '<div class="atlas-marker"><span class="ring"></span><span class="ring"></span><span class="crosshair-x"></span><span class="crosshair-y"></span><span class="core"></span></div>', iconSize: [28, 28], iconAnchor: [14, 14] })
    }).addTo(map);
});
</script>
<?php endif; ?>

<?php if (in_array($job['status'], ['running', 'submitting', 'submitted'], true) && $job['scraper_job_id']): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // While a submitted job is running, refresh its external status and import
    // any completed CSV automatically. The task page can be left open.
    setTimeout(function () {
        const form = document.querySelector('form[action="/jobs/<?= e($job['id']) ?>/sync"]');
        if (form) form.requestSubmit();
    }, 12000);
});
</script>
<?php endif; ?>
