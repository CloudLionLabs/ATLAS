<?php
$selectedFile = $_GET['file'] ?? '';
if ($selectedFile && !preg_match('/^atlas-businesses-u[0-9]+-[0-9]{8}-[0-9]{6}\.(csv|xlsx)$/', $selectedFile)) $selectedFile = '';
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 导出流程 / 公式安全交付</div>
            <h1 class="page-title">导出<span class="text-signal">中心</span></h1>
            <p class="page-subtitle">按筛选条件导出商家数据为 CSV 或 XLSX。CSV 默认添加 UTF-8 BOM 并防止公式注入；XLSX 将电话与邮编保存为文本。</p>
        </div>
        <a href="/businesses" class="btn btn-secondary"><?= icon('building') ?> 查看商家库</a>
    </div>
</section>

<?php if ($selectedFile): ?>
    <div class="reveal mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-signal/20 bg-signal/[0.04] p-4">
        <span class="grid h-9 w-9 place-items-center rounded-lg border border-signal/25 bg-signal/[0.08] text-signal"><?= icon('check-circle', 'h-4 w-4') ?></span>
        <div class="min-w-0 flex-1"><div class="text-[14px] font-medium text-slate-200">导出文件已生成</div><div class="mt-0.5 truncate font-mono text-[12px] text-slate-500"><?= e($selectedFile) ?></div></div>
        <a href="/exports/download/<?= e($selectedFile) ?>" class="btn btn-primary btn-sm"><?= icon('download', 'h-3.5 w-3.5') ?> 下载文件</a>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="panel reveal reveal-delay-1 xl:col-span-7">
        <div class="panel-head">
            <div><div class="eyebrow mb-1">导出配置</div><h2 class="panel-title"><?= icon('download', 'h-4 w-4 text-signal') ?> 生成数据包</h2></div>
            <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= fmt_number($counts['total']) ?> 可导出行数</span>
        </div>
        <form method="post" action="/exports/csv" class="space-y-4 p-5">
            <?= csrf_field() ?>
            <p class="font-mono text-[10px] uppercase tracking-wider text-slate-600">下拉只列出当前账号已采集到的国家和行业；括号内为可导出行数。</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div><label class="label">已采集国家</label><select name="country" class="select"><option value="0">全部已采集国家</option><?php foreach ($countries as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?>（<?= fmt_number($c['business_count']) ?>）</option><?php endforeach; ?><?php if ($unassignedCountry > 0): ?><option value="-1">未指定国家（<?= fmt_number($unassignedCountry) ?>）</option><?php endif; ?></select></div>
                <div><label class="label">已采集行业</label><select name="industry" class="select"><option value="0">全部已采集行业</option><?php foreach ($industries as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e(industry_name_display((int) $i['id'], $i['name'])) ?>（<?= fmt_number($i['business_count']) ?>）</option><?php endforeach; ?><?php if ($unassignedIndustry > 0): ?><option value="-1">未分类（<?= fmt_number($unassignedIndustry) ?>）</option><?php endif; ?></select></div>
                <div><label class="label">邮箱状态</label><select name="has_email" class="select"><option value="">全部</option><option value="1">仅已发现邮箱</option></select></div>
                <div><label class="label">最低完整度</label><select name="completeness_min" class="select"><option value="0">不限</option><option value="80">80% 以上</option><option value="60">60% 以上</option><option value="40">40% 以上</option></select></div>
            </div>
            <div class="rounded-xl border border-white/[0.07] bg-ink-950/40 p-4">
                <div class="flex items-center gap-2 text-[13px] text-slate-400"><?= icon('database', 'h-3.5 w-3.5 text-slate-500') ?> 导出字段</div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <?php foreach (['商家名称', '行业', '国家', '城市', '地址', '商家介绍', '电话', '官网', '邮箱', '评分', '评论数', '完整度', 'Data ID', '经纬度'] as $field): ?><span class="tag"><?= e($field) ?></span><?php endforeach; ?>
                </div>
                <p class="mt-2 font-mono text-[10px] uppercase tracking-wider text-slate-700">已排除原始评论正文 · 电话 / 邮编按文本保存</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="submit" formaction="/exports/csv" class="btn btn-primary flex-1"><?= icon('file', 'h-4 w-4') ?> 生成 CSV</button>
                <button type="submit" formaction="/exports/xlsx" class="btn btn-secondary flex-1"><?= icon('layers', 'h-4 w-4') ?> 生成 XLSX</button>
            </div>
            <div class="flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.14em] text-slate-700"><?= icon('shield', 'h-3 w-3') ?> 已启用公式注入防护</div>
        </form>
    </section>

    <div class="space-y-4 xl:col-span-5">
        <section class="panel reveal reveal-delay-2 p-5">
            <div class="eyebrow mb-1">数据范围</div>
            <h2 class="text-[17px] font-semibold text-white">当前数据集</h2>
            <div class="mt-4 grid grid-cols-3 gap-2">
                <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] p-3 text-center"><div class="font-display text-xl font-semibold text-white"><?= fmt_number($counts['total']) ?></div><div class="mt-1 font-mono text-[10px] uppercase tracking-wider text-slate-600">行</div></div>
                <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] p-3 text-center"><div class="font-display text-xl font-semibold text-signal"><?= fmt_number($counts['with_email']) ?></div><div class="mt-1 font-mono text-[10px] uppercase tracking-wider text-slate-600">邮箱</div></div>
                <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] p-3 text-center"><div class="font-display text-xl font-semibold text-electric"><?= fmt_number($counts['countries']) ?></div><div class="mt-1 font-mono text-[10px] uppercase tracking-wider text-slate-600">市场</div></div>
            </div>
            <div class="mt-4 rounded-xl border border-amber-400/15 bg-amber-400/[0.035] p-3 text-[12px] leading-5 text-amber-200/50">导出文件保存在本地受控目录，只能通过已验证的文件名下载。建议在交付业务团队前抽查邮箱与电话质量。</div>
        </section>

        <section class="panel reveal reveal-delay-3">
            <div class="panel-head"><div><div class="eyebrow mb-1">导出历史</div><h2 class="panel-title"><?= icon('clock', 'h-4 w-4 text-plasma') ?> 最近导出</h2></div></div>
            <?php if (!$history): ?><div class="px-5 py-10 text-center text-[13px] text-slate-600">还没有导出记录</div><?php else: ?>
                <div class="divide-y divide-white/[0.05]">
                    <?php foreach ($history as $h): ?>
                        <div class="flex items-center gap-3 px-5 py-3">
                            <span class="grid h-8 w-8 place-items-center rounded-lg border border-white/[0.07] bg-white/[0.03] font-mono text-[11px] uppercase text-slate-500"><?= e($h['format']) ?></span>
                            <span class="min-w-0 flex-1"><a href="/exports/download/<?= e($h['filename']) ?>" class="block truncate font-mono text-[12px] text-slate-300 hover:text-signal"><?= e($h['filename']) ?></a><span class="mt-0.5 block font-mono text-[11px] text-slate-600"><?= fmt_number($h['row_count']) ?> 行 · <?= e(fmt_datetime($h['created_at'])) ?></span></span>
                            <a href="/exports/download/<?= e($h['filename']) ?>" class="text-slate-600 hover:text-signal"><?= icon('download', 'h-3.5 w-3.5') ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
