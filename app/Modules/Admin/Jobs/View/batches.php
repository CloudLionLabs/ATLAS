<?php
$openBatchId = (string) ($openBatchId ?? '');
$pageQuery = ['page' => $page];
if ($openBatchId !== '') $pageQuery['open'] = $openBatchId;
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 采集运营 / 批次任务</div>
            <h1 class="page-title">采集<span class="text-signal">任务</span></h1>
            <p class="page-subtitle">合并时一个批次只占一条；展开后任务并入同一个列表分页，未展开的批次仍然只占一条。</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/jobs/create" class="btn btn-primary"><?= icon('crosshair', 'h-3.5 w-3.5') ?> 新建采集任务</a>
        </div>
    </div>
</section>

<div class="mb-4 flex flex-wrap items-center gap-2">
    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">共 <?= fmt_number((int) $totalBatches) ?> 个批次</span>
    <span class="text-slate-700">/</span>
    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">未分组 <?= fmt_number((int) $unbatchedCount) ?> 个任务</span>
    <span class="text-slate-700">/</span>
    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">当前 <?= fmt_number((int) $totalUnits) ?> 条列表项</span>
</div>

<?php if (!$pageUnits): ?>
    <div class="panel empty">
        <div class="empty-icon"><?= icon('layers', 'h-6 w-6') ?></div>
        <div class="text-[16px] font-medium text-slate-300">还没有批次任务</div>
        <p class="mt-2 max-w-sm text-[14px] leading-5 text-slate-600">从新建任务页生成全市覆盖批次，或导入批量任务后会出现在这里。</p>
    </div>
<?php else: ?>
    <div class="panel overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>任务 / 批次</th><th>市场</th><th>坐标</th><th>状态</th><th>结果</th><th>创建时间</th><th class="text-right">操作</th></tr></thead>
                <tbody>
                <?php foreach ($pageUnits as $unit): ?>
                    <?php if ($unit['type'] === 'batch'):
                        $batch = $unit['batch'];
                        $bTotal = (int) ($batch['job_count'] ?? 0);
                        $bDone = (int) ($batch['completed_jobs'] ?? 0);
                        $bActive = (int) ($batch['active_jobs'] ?? 0);
                        $bQueued = (int) ($batch['queued_jobs'] ?? 0);
                        $bCancelled = (int) ($batch['cancelled_jobs'] ?? 0);
                        $bFailed = (int) ($batch['failed_jobs'] ?? 0);
                        $bPaused = (int) ($batch['queue_paused'] ?? 0) === 1;
                        $bQueue = (int) ($batch['queue_mode'] ?? 0) === 1;
                        $isOpen = $openBatchId === (string) $batch['id'];
                        $bStatus = '运行中';
                        $bStatusClass = 'tag-signal';
                        if ($bActive === 0 && $bQueued === 0) {
                            $bStatus = $bDone >= $bTotal ? '已完成' : '已结束';
                            $bStatusClass = $bDone >= $bTotal ? '!border-emerald-400/20 !text-emerald-300' : '!border-slate-400/20 !text-slate-300';
                        } elseif ($bPaused) {
                            $bStatus = '已暂停';
                            $bStatusClass = '!border-amber-400/20 !text-amber-300';
                        }
                        $toggleUrl = '/jobs?' . http_build_query(['page' => 1, 'open' => $isOpen ? null : $batch['id']]) . '#batch-' . rawurlencode($batch['id']);
                    ?>
                        <tr id="batch-<?= e($batch['id']) ?>" class="bg-white/[0.025]">
                            <td colspan="7">
                                <div class="flex flex-col gap-2.5 xl:flex-row xl:items-center xl:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="<?= e($toggleUrl) ?>" class="text-[15px] font-medium text-slate-200 hover:text-signal"><?= e($batch['name']) ?></a>
                                            <span class="tag <?= $bStatusClass ?>"><?= $bStatus ?></span>
                                            <?php if ($bQueue): ?><span class="tag <?= $bPaused ? '!border-amber-400/20 !text-amber-300' : 'tag-signal' ?>"><?= $bPaused ? '队列暂停' : '自动队列' ?></span><?php endif; ?>
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 font-mono text-[11px] uppercase tracking-wider text-slate-600">
                                            <span><?= e(fmt_datetime($batch['created_at'], '—')) ?></span>
                                            <span>任务 <?= fmt_number($bTotal) ?></span>
                                            <span class="text-emerald-400/80">完成 <?= fmt_number($bDone) ?></span>
                                            <span class="text-cyan-400/80">运行 <?= fmt_number($bActive) ?></span>
                                            <span class="text-amber-400/80">等待 <?= fmt_number($bQueued) ?></span>
                                            <?php if ($bFailed): ?><span class="text-rose-400/80">失败 <?= fmt_number($bFailed) ?></span><?php endif; ?>
                                            <?php if ($bCancelled): ?><span class="text-slate-500">取消 <?= fmt_number($bCancelled) ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <?php if ($bQueue): ?>
                                            <form method="post" action="/jobs/batches/<?= e($batch['id']) ?>/queue-toggle"><?= csrf_field() ?><button class="btn btn-secondary btn-sm"><?= icon('sync', 'h-3.5 w-3.5') ?> <?= $bPaused ? '继续自动队列' : '暂停自动队列' ?></button></form>
                                            <form method="post" action="/jobs/batches/<?= e($batch['id']) ?>/cancel-all" data-confirm="确认取消这个队列所有未完成任务？正在运行的外部 Scraper 任务会尝试删除。"><?= csrf_field() ?><button class="btn btn-danger btn-sm"><?= icon('x', 'h-3.5 w-3.5') ?> 全取消</button></form>
                                        <?php endif; ?>
                                        <form method="post" action="/jobs/batches/<?= e($batch['id']) ?>/delete" data-confirm="确认删除整个批次及所有任务、原始记录和仅属于这些任务的商家？外部 Scraper 任务不会删除。"><?= csrf_field() ?><button class="btn btn-danger btn-sm"><?= icon('trash', 'h-3.5 w-3.5') ?> 删除整个批次</button></form>
                                        <a href="<?= e($toggleUrl) ?>" class="btn btn-secondary btn-sm"><?= icon('layers', 'h-3.5 w-3.5') ?> <?= $isOpen ? '收起' : '展开' ?></a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php elseif ($unit['type'] === 'unbatched_header'): $isOpen = $openBatchId === 'unbatched'; $toggleUrl = '/jobs?' . http_build_query(['page' => 1, 'open' => $isOpen ? null : 'unbatched']) . '#unbatched'; ?>
                        <tr id="unbatched" class="bg-white/[0.025]">
                            <td colspan="7">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div><span class="text-[15px] font-medium text-slate-200">未分组任务</span><span class="ml-2 font-mono text-[11px] text-slate-600"><?= fmt_number((int) $unbatchedCount) ?> 个</span></div>
                                    <div class="flex flex-wrap gap-2">
                                        <form method="post" action="/jobs/delete-unbatched" data-confirm="确认删除全部未分组任务？任务专属的原始记录和商家会一并清理。"><?= csrf_field() ?><button class="btn btn-danger btn-sm"><?= icon('trash', 'h-3.5 w-3.5') ?> 删除全部未分组（<?= fmt_number((int) $unbatchedCount) ?>）</button></form>
                                        <a href="<?= e($toggleUrl) ?>" class="btn btn-secondary btn-sm"><?= icon('layers', 'h-3.5 w-3.5') ?> <?= $isOpen ? '收起' : '展开' ?></a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php else: $job = $unit['job']; $jobBatch = $unit['batch'] ?? null; ?>
                        <tr>
                            <td>
                                <a href="/jobs/<?= e($job['id']) ?>" class="block max-w-[280px] truncate text-[14px] font-medium text-slate-200 hover:text-signal"><?= e($job['task_name']) ?></a>
                                <div class="mt-0.5 flex items-center gap-2 font-mono text-[10px] text-slate-700">
                                    <span><?= e(substr($job['id'], 0, 8)) ?></span>
                                    <?php if ($jobBatch): ?><span class="text-plasma/60">· <?= e($jobBatch['name']) ?></span><?php else: ?><span>· 未分组</span><?php endif; ?>
                                </div>
                            </td>
                            <td><span class="whitespace-nowrap"><?= flag_emoji($job['iso2'] ?? '') ?> <?= e($job['region_name'] ? place_name_cn($job['region_name']) : country_name_cn($job['iso2'] ?? '', $job['country_name'] ?? '—')) ?></span></td>
                            <td><span class="whitespace-nowrap font-mono text-[11px] text-slate-600"><?= $job['latitude'] !== null ? e(number_format((float) $job['latitude'], 4, '.', '')) . ', ' . e(number_format((float) $job['longitude'], 4, '.', '')) : '—' ?></span></td>
                            <td><?= status_badge($job['status']) ?></td>
                            <td><span class="font-mono text-[13px] text-slate-400"><?= fmt_number((int) $job['result_count']) ?></span></td>
                            <td><span class="font-mono text-[11px] text-slate-600"><?= e(fmt_datetime($job['created_at'])) ?></span></td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="/jobs/<?= e($job['id']) ?>" class="grid h-7 w-7 place-items-center rounded-md text-slate-600 transition hover:bg-white/[0.05] hover:text-signal" title="查看详情"><?= icon('chevron-right', 'h-3.5 w-3.5') ?></a>
                                    <form method="post" action="/jobs/<?= e($job['id']) ?>/delete" data-confirm="确认删除此任务？任务专属的原始记录和商家会一并清理；外部 Scraper 任务不会删除。">
                                        <?= csrf_field() ?>
                                        <button class="grid h-7 w-7 place-items-center rounded-md text-slate-600 transition hover:bg-rose-400/[0.08] hover:text-rose-300" title="删除任务"><?= icon('trash', 'h-3.5 w-3.5') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <div class="mt-4 flex items-center justify-between">
            <span class="font-mono text-[12px] uppercase tracking-wider text-slate-600">第 <?= $page ?> / <?= $pages ?> 页 · <?= fmt_number((int) $totalUnits) ?> 条列表项</span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="/jobs?<?= e(http_build_query(array_merge($pageQuery, ['page' => $page - 1]))) ?>" class="btn btn-secondary btn-sm">上一页</a><?php endif; ?>
                <?php if ($page < $pages): ?><a href="/jobs?<?= e(http_build_query(array_merge($pageQuery, ['page' => $page + 1]))) ?>" class="btn btn-secondary btn-sm">下一页</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
