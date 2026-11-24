<?php
$total = max(0, (int) $quality['total']);
$score = $total > 0 ? max(0, 100 - (int) round((($quality['missing_phone'] + $quality['missing_website'] + $quality['missing_email'] + $quality['missing_industry']) / (4 * $total)) * 100)) : 100;
$issues = [
    ['key' => 'missing_phone', 'label' => '缺少电话', 'en' => '缺少电话', 'value' => $quality['missing_phone'], 'icon' => 'phone', 'color' => 'text-hazard', 'href' => '/businesses?has_phone=0'],
    ['key' => 'missing_website', 'label' => '缺少官网', 'en' => '缺少官网', 'value' => $quality['missing_website'], 'icon' => 'link', 'color' => 'text-electric', 'href' => '/businesses?has_website=0'],
    ['key' => 'missing_email', 'label' => '缺少邮箱', 'en' => '缺少邮箱', 'value' => $quality['missing_email'], 'icon' => 'mail', 'color' => 'text-plasma', 'href' => '/businesses?has_email=0'],
    ['key' => 'missing_industry', 'label' => '缺少行业', 'en' => '缺少行业', 'value' => $quality['missing_industry'], 'icon' => 'layers', 'color' => 'text-slate-300', 'href' => '/businesses'],
    ['key' => 'missing_address', 'label' => '地址不完整', 'en' => '地址不完整', 'value' => $quality['missing_address'], 'icon' => 'map-pin', 'color' => 'text-rose-300', 'href' => '/businesses'],
    ['key' => 'bad_coords', 'label' => '坐标异常', 'en' => '坐标异常', 'value' => $quality['bad_coords'], 'icon' => 'crosshair', 'color' => 'text-rose-300', 'href' => '/businesses'],
];
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 数据完整性 / 质量检查</div>
            <h1 class="page-title">数据<span class="text-signal">质量</span></h1>
            <p class="page-subtitle">基于字段完整度、联系方式、地理坐标和重复候选生成可执行的质量审查。分数只表示资料完整程度，不代表商家真实性。</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($emailFinderEnabled): ?>
                <form method="post" action="/businesses/enrich-emails" data-confirm="将检查最多 3 家有官网但缺少邮箱的商家，只访问公开页面并遵守 robots.txt。继续？">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary"><?= icon('mail') ?> 补全缺失邮箱</button>
                </form>
            <?php endif; ?>
            <a href="/exports" class="btn btn-secondary"><?= icon('download') ?> 导出完整数据</a>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
    <section class="panel reveal reveal-delay-1 p-5 lg:col-span-4">
        <div class="flex items-start justify-between">
            <div><div class="eyebrow mb-1">质量指数</div><h2 class="text-[17px] font-semibold text-white">数据健康度</h2></div>
            <span class="status-badge border-signal/20 bg-signal/[0.05] text-signal">实时</span>
        </div>
        <div class="relative mx-auto my-6 h-[180px] w-[180px]">
            <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90">
                <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(255,255,255,.05)" stroke-width="6"/>
                <circle cx="60" cy="60" r="50" fill="none" stroke="url(#qualityGrad)" stroke-width="6" stroke-linecap="round" stroke-dasharray="<?= round(2 * M_PI * 50, 1) ?>" stroke-dashoffset="<?= round(2 * M_PI * 50 * (1 - $score / 100), 1) ?>"/>
                <defs><linearGradient id="qualityGrad" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#20e3bd"/><stop offset="1" stop-color="#5b8dff"/></linearGradient></defs>
            </svg>
            <div class="absolute inset-0 grid place-items-center text-center">
                <div><div class="font-display text-4xl font-semibold text-white"><?= $score ?><span class="text-base text-slate-500">%</span></div><div class="mt-1 font-mono text-[11px] uppercase tracking-[0.18em] text-slate-600">健康指数</div></div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] p-3 text-center"><div class="font-display text-lg font-semibold text-white"><?= fmt_number($total) ?></div><div class="mt-1 font-mono text-[10px] uppercase tracking-wider text-slate-600">条记录</div></div>
            <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] p-3 text-center"><div class="font-display text-lg font-semibold text-hazard"><?= fmt_number($quality['duplicate_phone']) ?></div><div class="mt-1 font-mono text-[10px] uppercase tracking-wider text-slate-600">重复组</div></div>
        </div>
    </section>

    <div class="grid grid-cols-2 gap-3 lg:col-span-8 lg:grid-cols-3">
        <?php foreach ($issues as $i => $issue): ?>
            <a href="<?= e($issue['href']) ?>" class="panel panel-hover reveal p-4" style="animation-delay:<?= 80 + $i * 40 ?>ms">
                <div class="flex items-start justify-between">
                    <span class="icon-chip <?= e($issue['color']) ?>"><?= icon($issue['icon'], 'h-4 w-4') ?></span>
                    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-700"><?= e($issue['en']) ?></span>
                </div>
                <div class="mt-4 font-display text-2xl font-semibold text-white"><?= fmt_number($issue['value']) ?></div>
                <div class="mt-1 text-[13px] text-slate-500"><?= e($issue['label']) ?></div>
                <div class="progress mt-3"><span style="width:<?= $total > 0 ? min(100, round(($issue['value'] / $total) * 100)) : 0 ?>%"></span></div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="panel reveal reveal-delay-2 xl:col-span-7">
        <div class="panel-head">
            <div><div class="eyebrow mb-1">重复检测</div><h2 class="panel-title"><?= icon('shield', 'h-4 w-4 text-hazard') ?> 重复候选</h2></div>
            <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= count($duplicatePhones) ?> 组</span>
        </div>
        <?php if (!$duplicatePhones): ?>
            <div class="px-5 py-12 text-center"><div class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-xl border border-white/[0.07] bg-white/[0.03] text-slate-600"><?= icon('shield', 'h-5 w-5') ?></div><div class="text-[14px] text-slate-500">未发现相同标准化电话的候选组</div></div>
        <?php else: ?>
            <div class="divide-y divide-white/[0.05]">
                <?php foreach ($duplicatePhones as $dup): ?>
                    <div class="px-5 py-3.5">
                        <div class="flex items-center gap-2"><span class="font-mono text-[13px] text-slate-300"><?= e($dup['normalized_phone']) ?></span><span class="tag"><?= fmt_number($dup['n']) ?> 条记录</span></div>
                        <p class="mt-1.5 text-[13px] leading-5 text-slate-500"><?= e($dup['names']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="border-t border-white/[0.06] px-5 py-3 font-mono text-[10px] uppercase tracking-[0.14em] text-slate-700">电话匹配仅作为候选 · 不自动合并</div>
    </section>

    <section class="panel reveal reveal-delay-3 xl:col-span-5">
        <div class="panel-head">
            <div><div class="eyebrow mb-1">修复队列</div><h2 class="panel-title"><?= icon('alert', 'h-4 w-4 text-rose-300') ?> 导入异常</h2></div>
            <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= count($failedImports) ?> 条日志</span>
        </div>
        <?php if (!$failedImports): ?>
            <div class="px-5 py-12 text-center text-[13px] text-slate-600">没有失败或部分导入的日志</div>
        <?php else: ?>
            <div class="divide-y divide-white/[0.05]">
                <?php foreach ($failedImports as $log): ?>
                    <div class="px-5 py-3.5">
                        <div class="flex items-center gap-2"><?= status_badge($log['status']) ?><span class="ml-auto font-mono text-[11px] text-slate-600"><?= e(fmt_datetime($log['created_at'])) ?></span></div>
                        <div class="mt-1.5 truncate text-[13px] text-slate-300"><?= e($log['task_name'] ?: '—') ?></div>
                        <p class="mt-1 line-clamp-2 text-[12px] leading-4 text-slate-600"><?= e($log['message']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <?php foreach ([
        ['title' => '缺少电话', 'rows' => $missingPhone, 'icon' => 'phone', 'href' => '/businesses?has_phone=0'],
        ['title' => '缺少官网', 'rows' => $missingWebsite, 'icon' => 'link', 'href' => '/businesses?has_website=0'],
        ['title' => '缺少邮箱', 'rows' => $missingEmail, 'icon' => 'mail', 'href' => '/businesses?has_email=0'],
        ['title' => '缺少行业', 'rows' => $missingIndustry, 'icon' => 'layers', 'href' => '/businesses'],
    ] as $block): ?>
        <section class="panel reveal reveal-delay-3">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">问题样本</div><h2 class="panel-title"><?= icon($block['icon'], 'h-4 w-4 text-hazard') ?> <?= e($block['title']) ?></h2></div>
                <a href="<?= e($block['href']) ?>" class="font-mono text-[11px] uppercase tracking-wider text-signal/70 hover:text-signal">查看清单 →</a>
            </div>
            <?php if (!$block['rows']): ?><div class="px-5 py-8 text-center text-[13px] text-slate-600">该问题没有待修复记录</div><?php else: ?>
                <div class="divide-y divide-white/[0.05]">
                    <?php foreach ($block['rows'] as $b): ?>
                        <a href="/businesses/<?= (int) $b['id'] ?>" class="flex items-center gap-3 px-5 py-2.5 hover:bg-white/[0.02]">
                            <span class="text-[14px]"><?= flag_emoji($b['iso2'] ?? '') ?></span>
                            <span class="min-w-0 flex-1 truncate text-[13px] text-slate-300"><?= e($b['business_name']) ?></span>
                            <span class="font-mono text-[11px] text-slate-600"><?= e($b['city'] ? place_name_cn($b['city']) : '—') ?></span>
                            <?= icon('chevron-right', 'h-3 w-3 text-slate-700') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
