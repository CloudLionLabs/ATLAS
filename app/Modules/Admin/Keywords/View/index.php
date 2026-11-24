<?php
$filterQuery = array_filter([
    'country' => $filters['countryId'] ?: null,
    'industry' => $filters['industryId'] ?: null,
    'lang' => $filters['lang'],
    'status' => $filters['status'],
    'q' => $filters['q'],
], static fn ($v) => $v !== '' && $v !== null);
$total = count($keywords);
$activeKeywords = count(array_filter($keywords, static fn ($k) => (int) $k['is_active'] === 1));
$languageCount = count($languages);
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 关键词库 / 多语言搜索词</div>
            <h1 class="page-title">B2B <span class="text-signal">关键词库</span></h1>
            <p class="page-subtitle">当前账号的独立关键词库。按国家、B2B 行业与语言维护采购商搜索词，其它账号看不到、也不会共用这些记录。</p>
        </div>
        <a href="/jobs/batch" class="btn btn-primary"><?= icon('layers') ?> 批量生成任务</a>
    </div>
</section>

<div class="reveal reveal-delay-1 mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">当前结果</div><div class="mt-2 font-display text-2xl font-semibold text-white"><?= fmt_number($total) ?></div></div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">启用总数</div><div class="mt-2 font-display text-2xl font-semibold text-signal"><?= fmt_number($activeCount) ?></div></div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">语言数</div><div class="mt-2 font-display text-2xl font-semibold text-electric"><?= fmt_number($languageCount) ?></div></div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">当前启用</div><div class="mt-2 font-display text-2xl font-semibold text-plasma"><?= fmt_number($activeKeywords) ?></div></div>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="panel reveal reveal-delay-2 xl:col-span-8">
        <div class="panel-head">
            <div><div class="eyebrow mb-1">关键词清单</div><h2 class="panel-title"><?= icon('key', 'h-4 w-4 text-signal') ?> 搜索词清单</h2></div>
            <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= fmt_number($total) ?> 条结果</span>
        </div>
        <div class="border-b border-white/[0.06] p-3">
            <form method="get" action="/keywords" class="space-y-2">
                <div class="flex flex-wrap gap-2">
                    <div class="relative min-w-[180px] flex-1">
                        <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-600') ?>
                        <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="搜索关键词…" class="input !pl-9">
                    </div>
                    <select name="country" class="select !w-auto min-w-[130px]">
                        <option value="">全部国家</option>
                        <?php foreach ($countries as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $filters['countryId'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?></option><?php endforeach; ?>
                    </select>
                    <select name="industry" class="select !w-auto min-w-[130px]">
                        <option value="">全部行业</option>
                        <?php foreach ($industries as $i): ?><option value="<?= (int) $i['id'] ?>" <?= (int) $filters['industryId'] === (int) $i['id'] ? 'selected' : '' ?>><?= e(industry_name_display((int) $i['id'], $i['name'])) ?></option><?php endforeach; ?>
                    </select>
                    <select name="lang" class="select !w-auto min-w-[100px]">
                        <option value="">全部语言</option>
                        <?php foreach ($languages as $l): ?><option value="<?= e($l['language_code']) ?>" <?= $filters['lang'] === $l['language_code'] ? 'selected' : '' ?>><?= e(language_name($l['language_code'])) ?></option><?php endforeach; ?>
                    </select>
                    <select name="status" class="select !w-auto min-w-[100px]" data-auto-submit>
                        <option value="">全部状态</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>已启用</option>
                        <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>已停用</option>
                    </select>
                    <button class="btn btn-secondary"><?= icon('filter', 'h-3.5 w-3.5') ?></button>
                </div>
                <?php if ($filterQuery): ?><a href="/keywords" class="inline-block font-mono text-[11px] uppercase tracking-wider text-slate-600 hover:text-signal">清除筛选</a><?php endif; ?>
            </form>
        </div>
        <?php if (!$keywords): ?>
            <div class="empty py-12"><div class="empty-icon"><?= icon('key', 'h-6 w-6') ?></div><div class="text-[15px] text-slate-400"><?= $filterQuery ? '没有匹配的关键词' : '关键词库为空' ?></div><p class="mt-1 text-[13px] text-slate-600">调整筛选条件，或在右侧添加关键词。</p></div>
        <?php else: ?>
            <div class="max-h-[620px] overflow-y-auto">
                <table class="data-table !min-w-[720px]">
                    <thead><tr><th>关键词</th><th>市场</th><th>行业</th><th>语言</th><th>状态</th><th class="text-right">操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($keywords as $k): ?>
                        <tr>
                            <td><span class="block max-w-[260px] truncate text-[14px] font-medium <?= (int) $k['is_active'] ? 'text-slate-200' : 'text-slate-500' ?>"><?= e($k['keyword']) ?></span><span class="mt-0.5 block font-mono text-[11px] uppercase tracking-wider text-slate-700"><?= e($k['notes'] ?: '无备注') ?></span></td>
                            <td><span class="flex items-center gap-1.5 whitespace-nowrap text-[13px]"><?= flag_emoji($k['iso2'] ?? '') ?> <?= e($k['iso2'] ? country_name_cn($k['iso2'], $k['country_name']) : '全球') ?></span></td>
                            <td><span class="text-[13px] text-slate-400"><?= e(industry_name_display($k['industry_id'] ? (int) $k['industry_id'] : null, $k['industry_name'] ?? null)) ?></span></td>
                            <td><span class="tag font-mono"><?= e(language_name($k['language_code'])) ?></span></td>
                            <td><span class="status-badge <?= (int) $k['is_active'] ? 'border-signal/20 bg-signal/[0.05] text-signal' : 'border-white/10 bg-white/[0.03] text-slate-500' ?>"><span class="h-1.5 w-1.5 rounded-full <?= (int) $k['is_active'] ? 'bg-signal' : 'bg-slate-600' ?>"></span><?= (int) $k['is_active'] ? '已启用' : '已停用' ?></span></td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    <a href="/jobs/create?keyword=<?= (int) $k['id'] ?>" class="grid h-7 w-7 place-items-center rounded-md text-slate-600 hover:bg-white/[0.05] hover:text-signal" title="用此词创建任务"><?= icon('crosshair', 'h-3.5 w-3.5') ?></a>
                                    <form method="post" action="/keywords/<?= (int) $k['id'] ?>/toggle"><?= csrf_field() ?><button class="grid h-7 w-7 place-items-center rounded-md text-slate-600 hover:bg-white/[0.05] hover:text-amber-300" title="切换状态"><?= icon('sync', 'h-3.5 w-3.5') ?></button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="space-y-4 xl:col-span-4">
        <section class="panel reveal reveal-delay-2">
            <div class="panel-head"><div><div class="eyebrow mb-1">新增关键词</div><h2 class="panel-title"><?= icon('plus', 'h-4 w-4 text-signal') ?> 添加关键词</h2></div></div>
            <form method="post" action="/keywords" class="space-y-3 p-5">
                <?= csrf_field() ?>
                <div><label class="label">关键词 *</label><input name="keyword" class="input" placeholder="industrial machinery importers" maxlength="300" required><p class="field-hint">关键词只填采购意图，国家和城市在任务选点或关键词国家字段中指定，不要重复写城市名。</p></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">国家</label><select name="country_id" class="select"><option value="">全球</option><?php foreach ($countries as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?></option><?php endforeach; ?></select></div>
                    <div><label class="label">行业</label><select name="industry_id" class="select"><option value="">未分类</option><?php foreach ($industries as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e(industry_name_display((int) $i['id'], $i['name'])) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">语言</label><input name="language_code" value="en" maxlength="10" class="input font-mono"></div>
                    <div><label class="label">状态</label><label class="flex h-[42px] items-center gap-2 rounded-xl border border-white/[0.09] bg-ink-950/60 px-3 text-[13px] text-slate-400"><input type="checkbox" name="is_active" value="1" checked class="accent-[#20e3bd]"> 启用</label></div>
                </div>
                <div><label class="label">备注</label><input name="notes" class="input" placeholder="可选"></div>
                <button class="btn btn-primary w-full"><?= icon('key', 'h-4 w-4') ?> 加入词库</button>
            </form>
        </section>

        <section class="panel reveal reveal-delay-3">
            <details>
                <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4"><span><span class="eyebrow mb-1 block">批量导入</span><span class="panel-title text-[15px]"><?= icon('upload', 'h-4 w-4 text-plasma') ?> 批量导入</span></span><span class="font-mono text-[11px] text-slate-600">展开 +</span></summary>
                <form method="post" action="/keywords/bulk" class="space-y-3 border-t border-white/[0.06] p-5">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="country_id" class="select"><option value="">全球</option><?php foreach ($countries as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?></option><?php endforeach; ?></select>
                        <select name="industry_id" class="select"><option value="">未分类</option><?php foreach ($industries as $i): ?><option value="<?= (int) $i['id'] ?>"><?= e(industry_name_display((int) $i['id'], $i['name'])) ?></option><?php endforeach; ?></select>
                    </div>
                    <input name="language_code" value="en" maxlength="10" class="input font-mono" aria-label="语言">
                    <textarea name="keywords" class="textarea !min-h-[150px] font-mono !text-[14px]" placeholder="每行一个关键词&#10;emergency plumber London&#10;bathroom plumber London"></textarea>
                    <button class="btn btn-secondary w-full"><?= icon('upload', 'h-4 w-4') ?> 批量导入</button>
                    <p class="font-mono text-[10px] uppercase tracking-wider text-slate-700">按国家 / 行业 / 语言自动去重</p>
                </form>
            </details>
        </section>
    </div>
</div>
