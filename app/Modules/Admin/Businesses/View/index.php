<?php
$queryBase = array_filter([
    'q' => $filters['q'],
    'country' => $filters['countryId'] ?: null,
    'industry' => $filters['industryId'] ?: null,
    'has_phone' => $filters['hasPhone'] !== '' ? $filters['hasPhone'] : null,
    'has_website' => $filters['hasWebsite'] !== '' ? $filters['hasWebsite'] : null,
    'has_email' => $filters['hasEmail'] !== '' ? $filters['hasEmail'] : null,
    'rating_min' => $filters['ratingMin'] !== '' ? $filters['ratingMin'] : null,
    'completeness_min' => $filters['completenessMin'] ?: null,
    'sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : null,
], static fn ($v) => $v !== '' && $v !== null);
$avg = (int) round((float) ($summary['avg_completeness'] ?? 0));
$activeFilters = count($queryBase);
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 商业情报 / 标准化资产</div>
            <h1 class="page-title">商家<span class="text-signal">资料库</span></h1>
            <p class="page-subtitle">按国家、城市、行业、联系方式与完整度筛选全球商家。原始采集行永久保留，标准化资料用于检索和导出。</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/exports" class="btn btn-secondary"><?= icon('download') ?> 导出数据</a>
            <a href="/jobs/create" class="btn btn-primary"><?= icon('crosshair') ?> 采集新市场</a>
        </div>
    </div>
</section>

<div class="reveal reveal-delay-1 mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3">
        <div class="flex items-center justify-between"><span class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">记录总数</span><?= icon('building', 'h-3.5 w-3.5 text-slate-600') ?></div>
        <div class="mt-2 font-display text-2xl font-semibold text-white"><?= fmt_number($summary['total'] ?? 0) ?></div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3">
        <div class="flex items-center justify-between"><span class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">含邮箱</span><?= icon('mail', 'h-3.5 w-3.5 text-signal/70') ?></div>
        <div class="mt-2 font-display text-2xl font-semibold text-signal"><?= fmt_number($summary['with_email'] ?? 0) ?></div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3">
        <div class="flex items-center justify-between"><span class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">已定位</span><?= icon('map-pin', 'h-3.5 w-3.5 text-electric/70') ?></div>
        <div class="mt-2 font-display text-2xl font-semibold text-electric"><?= fmt_number($summary['with_coords'] ?? 0) ?></div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3">
        <div class="flex items-center justify-between"><span class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">平均完整度</span><?= icon('activity', 'h-3.5 w-3.5 text-plasma/70') ?></div>
        <div class="mt-2 font-display text-2xl font-semibold text-plasma"><?= $avg ?><span class="text-sm text-slate-600">%</span></div>
    </div>
</div>

<section class="panel reveal reveal-delay-2">
    <div class="border-b border-white/[0.06] p-3.5">
        <form method="get" action="/businesses" class="space-y-2.5">
            <div class="flex flex-wrap gap-2">
                <div class="relative min-w-[240px] flex-1">
                    <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-600') ?>
                    <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="搜索商家名称、地址、官网或电话…" class="input !pl-9">
                </div>
                <select name="country" class="select !w-auto min-w-[150px]">
                    <option value="">全部国家</option>
                    <?php foreach ($countries as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['countryId'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="industry" class="select !w-auto min-w-[150px]">
                    <option value="">全部行业</option>
                    <?php foreach ($industries as $i): ?>
                        <option value="<?= (int) $i['id'] ?>" <?= (int) $filters['industryId'] === (int) $i['id'] ? 'selected' : '' ?>><?= e(industry_name_display((int) $i['id'], $i['name'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="sort" class="select !w-auto min-w-[130px]" data-auto-submit>
                    <?php foreach (['newest' => '最新入库', 'completeness' => '完整度最高', 'rating' => '评分最高', 'reviews' => '评论最多', 'name' => '名称 A-Z'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['sort'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-secondary"><?= icon('filter', 'h-3.5 w-3.5') ?> 应用</button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">电话</span>
                    <div class="segmented">
                        <?php foreach (['' => '全部', '1' => '有', '0' => '无'] as $v => $label): ?>
                            <label class="cursor-pointer"><input type="radio" name="has_phone" value="<?= e($v) ?>" class="sr-only" onchange="this.form.submit()" <?= $filters['hasPhone'] === $v ? 'checked' : '' ?>><span class="<?= $filters['hasPhone'] === $v ? 'is-active' : '' ?> inline-block rounded-lg px-2.5 py-1"><?= e($label) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">官网</span>
                    <div class="segmented">
                        <?php foreach (['' => '全部', '1' => '有', '0' => '无'] as $v => $label): ?>
                            <label class="cursor-pointer"><input type="radio" name="has_website" value="<?= e($v) ?>" class="sr-only" onchange="this.form.submit()" <?= $filters['hasWebsite'] === $v ? 'checked' : '' ?>><span class="<?= $filters['hasWebsite'] === $v ? 'is-active' : '' ?> inline-block rounded-lg px-2.5 py-1"><?= e($label) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">邮箱</span>
                    <div class="segmented">
                        <?php foreach (['' => '全部', '1' => '有', '0' => '无'] as $v => $label): ?>
                            <label class="cursor-pointer"><input type="radio" name="has_email" value="<?= e($v) ?>" class="sr-only" onchange="this.form.submit()" <?= $filters['hasEmail'] === $v ? 'checked' : '' ?>><span class="<?= $filters['hasEmail'] === $v ? 'is-active' : '' ?> inline-block rounded-lg px-2.5 py-1"><?= e($label) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <select name="rating_min" class="select !w-auto min-w-[120px] !py-1.5 !text-[13px]" onchange="this.form.submit()">
                    <option value="">全部评分</option>
                    <?php foreach ([4.5, 4.0, 3.5, 3.0] as $r): ?>
                        <option value="<?= e($r) ?>" <?= (string) $filters['ratingMin'] === (string) $r ? 'selected' : '' ?>>★ <?= number_format($r, 1) ?>+</option>
                    <?php endforeach; ?>
                </select>
                <select name="completeness_min" class="select !w-auto min-w-[140px] !py-1.5 !text-[13px]" onchange="this.form.submit()">
                    <option value="0">全部完整度</option>
                    <?php foreach ([80, 60, 40] as $r): ?>
                        <option value="<?= $r ?>" <?= (int) $filters['completenessMin'] === $r ? 'selected' : '' ?>><?= $r ?>% 以上</option>
                    <?php endforeach; ?>
                </select>
                <?php if ($activeFilters): ?><a href="/businesses" class="btn btn-ghost btn-sm">清除筛选</a><?php endif; ?>
                <span class="ml-auto font-mono text-[12px] uppercase tracking-wider text-slate-600"><?= fmt_number($total) ?> 条匹配</span>
            </div>
        </form>
    </div>

    <?php if (!$businesses): ?>
        <div class="empty">
            <div class="empty-icon"><?= icon('building', 'h-6 w-6') ?></div>
            <div class="text-[16px] font-medium text-slate-300"><?= $activeFilters ? '没有匹配的商家' : '商家资料库等待数据接入' ?></div>
            <p class="mt-2 max-w-md text-[14px] leading-5 text-slate-600"><?= $activeFilters ? '尝试减少筛选条件，或检查国家、行业与联系方式过滤。' : '在采集任务中导入采集引擎 CSV，系统会自动清洗、去重并建立来源关联。' ?></p>
            <div class="mt-5 flex gap-2">
                <?php if ($activeFilters): ?><a href="/businesses" class="btn btn-secondary btn-sm">清除筛选</a><?php else: ?><a href="/jobs" class="btn btn-primary btn-sm">前往采集任务</a><?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr><th>商家</th><th>市场</th><th>行业</th><th>联系方式</th><th>评分</th><th>完整度</th><th>来源</th><th class="text-right">操作</th></tr>
                </thead>
                <tbody>
                <?php foreach ($businesses as $b): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-white/[0.07] bg-white/[0.03] font-mono text-[12px] text-slate-400"><?= e(initials($b['business_name'])) ?></span>
                                <span class="min-w-0">
                                    <a href="/businesses/<?= (int) $b['id'] ?>" class="block max-w-[250px] truncate text-[15px] font-medium text-slate-200 hover:text-signal"><?= e($b['business_name']) ?></a>
                                    <span class="mt-0.5 block max-w-[250px] truncate font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= e($b['website_domain'] ?: '无官网') ?></span>
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="flex items-center gap-1.5 whitespace-nowrap"><?= flag_emoji($b['iso2'] ?? '') ?> <?= e($b['city'] ? place_name_cn($b['city']) : country_name_cn($b['iso2'] ?? '', $b['country_name'] ?? '—')) ?></span>
                            <span class="mt-1 block text-[12px] text-slate-600"><?= e(country_name_cn($b['iso2'] ?? '', $b['country_name'] ?? '—')) ?></span>
                        </td>
                        <td><span class="text-[13px] text-slate-400"><?= e(industry_name_display($b['industry_id'] ? (int) $b['industry_id'] : null, $b['industry_name'] ?? null)) ?></span></td>
                        <td>
                            <div class="flex items-center gap-2">
                                <?php if ($b['phone']): ?><span class="grid h-6 w-6 place-items-center rounded-md border border-emerald-400/15 bg-emerald-400/[0.05] text-emerald-400/80" title="<?= e($b['phone']) ?>"><?= icon('phone', 'h-3 w-3') ?></span><?php endif; ?>
                                <?php if ($b['website']): ?><a href="<?= e($b['website']) ?>" target="_blank" rel="noopener noreferrer" class="grid h-6 w-6 place-items-center rounded-md border border-electric/15 bg-electric/[0.05] text-electric/80 hover:border-electric/30" title="<?= e($b['website']) ?>"><?= icon('external', 'h-3 w-3') ?></a><?php endif; ?>
                                <?php if ((int) $b['email_count'] > 0): ?><span class="grid h-6 w-6 place-items-center rounded-md border border-plasma/15 bg-plasma/[0.05] text-plasma/80" title="<?= (int) $b['email_count'] ?> emails"><?= icon('mail', 'h-3 w-3') ?></span><?php endif; ?>
                                <?php if (!$b['phone'] && !$b['website'] && (int) $b['email_count'] === 0): ?><span class="font-mono text-[12px] text-slate-700">—</span><?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($b['rating'] !== null): ?>
                                <span class="flex items-center gap-1 font-mono text-[14px] text-amber-300"><?= icon('star', 'h-3 w-3 fill-current') ?> <?= number_format((float) $b['rating'], 1) ?></span>
                                <span class="mt-0.5 block font-mono text-[11px] text-slate-600"><?= fmt_number($b['review_count']) ?> 条评论</span>
                            <?php else: ?><span class="font-mono text-[12px] text-slate-700">—</span><?php endif; ?>
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="h-1 w-14 overflow-hidden rounded-full bg-white/[0.07]"><span class="block h-full rounded-full <?= (int) $b['completeness_score'] >= 70 ? 'bg-signal' : ((int) $b['completeness_score'] >= 40 ? 'bg-hazard' : 'bg-rose-400') ?>" style="width: <?= (int) $b['completeness_score'] ?>%"></span></div>
                                <span class="font-mono text-[12px] <?= (int) $b['completeness_score'] >= 70 ? 'text-signal' : 'text-slate-500' ?>"><?= (int) $b['completeness_score'] ?>%</span>
                            </div>
                            <span class="mt-1 block font-mono text-[11px] uppercase tracking-wider text-slate-700"><?= e(email_status_meta($b['email_status'])['label']) ?></span>
                        </td>
                        <td><span class="font-mono text-[12px] text-slate-500"><?= fmt_number($b['source_count']) ?> 个来源</span><span class="mt-0.5 block font-mono text-[11px] text-slate-700"><?= e(fmt_datetime($b['updated_at'])) ?></span></td>
                        <td><a href="/businesses/<?= (int) $b['id'] ?>" class="inline-grid h-7 w-7 place-items-center rounded-md text-slate-600 transition hover:bg-white/[0.05] hover:text-signal"><?= icon('chevron-right', 'h-3.5 w-3.5') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <div class="flex items-center justify-between border-t border-white/[0.06] px-4 py-3">
                <span class="font-mono text-[12px] uppercase tracking-wider text-slate-600">第 <?= $page ?> / <?= $pages ?> · <?= fmt_number($total) ?> 条记录</span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?><a href="/businesses?<?= e(http_build_query(array_merge($queryBase, ['page' => $page - 1]))) ?>" class="btn btn-secondary btn-sm">上一页</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a href="/businesses?<?= e(http_build_query(array_merge($queryBase, ['page' => $page + 1]))) ?>" class="btn btn-secondary btn-sm">下一页</a><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
