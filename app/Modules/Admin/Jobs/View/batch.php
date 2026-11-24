<?php
$cfg = $batchConfig ?? [];
$selectedIds = array_map('intval', $selected ?? []);
$countryId = (int) ($cfg['country_id'] ?? 0);
$regionId = (int) ($cfg['region_id'] ?? 0);
$industryId = (int) ($cfg['industry_id'] ?? 0);
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 批量编排 / 预览确认</div>
            <h1 class="page-title">批量任务 <span class="text-signal">生成器</span></h1>
            <p class="page-subtitle">按国家、城市与关键词组合生成任务清单。提交前必须预览，单批最多 200 个子任务；不会自动调用外部采集引擎。</p>
        </div>
        <div class="flex gap-2">
            <a href="/jobs" class="btn btn-secondary"><?= icon('arrow-right', 'rotate-180') ?> 返回任务</a>
            <a href="/jobs/create" class="btn btn-primary"><?= icon('crosshair') ?> 单点任务</a>
        </div>
    </div>
</section>

<form method="post" action="/jobs/batch/preview" class="reveal reveal-delay-1">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="panel xl:col-span-5">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">批次配置</div><h2 class="panel-title"><?= icon('layers', 'h-4 w-4 text-signal') ?> 批次参数</h2></div>
            </div>
            <div class="space-y-4 p-5">
                <div>
                    <label class="label">批次名称</label>
                    <input type="text" name="batch_name" value="<?= e($cfg['batch_name'] ?? 'Q4 · ' . gmdate('M d') . ' · Multi-market') ?>" class="input" maxlength="200" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">覆盖国家</label>
                        <select name="country_id" id="batch-country" class="select">
                            <option value="">各关键词所属国家</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $countryId === (int) $c['id'] ? 'selected' : '' ?>><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="label">统一城市节点</label>
                        <select name="region_id" id="batch-region" class="select">
                            <option value="">不锁定 / 国家中心点</option>
                            <?php foreach ($regions as $r): ?>
                                <option value="<?= (int) $r['id'] ?>" data-country="<?= (int) $r['country_id'] ?>" <?= $regionId === (int) $r['id'] ? 'selected' : '' ?>><?= e(place_name_cn($r['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">缩放级别</label>
                        <input type="number" name="zoom" min="1" max="20" value="<?= (int) ($cfg['zoom'] ?? 13) ?>" class="input">
                    </div>
                    <div>
                        <label class="label">扫描半径（米）</label>
                        <input type="number" name="radius" min="500" max="50000" step="500" value="<?= (int) ($cfg['radius'] ?? 10000) ?>" class="input">
                    </div>
                    <div>
                        <label class="label">采集深度</label>
                        <input type="number" name="depth" min="1" max="50" value="<?= (int) ($cfg['depth'] ?? 10) ?>" class="input">
                    </div>
                    <div>
                        <label class="label">最大时长</label>
                        <input type="text" name="max_time" value="<?= e($cfg['max_time'] ?? '10m') ?>" class="input font-mono">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex cursor-pointer items-center justify-between rounded-xl border border-white/[0.08] bg-ink-950/40 px-3 py-2.5">
                        <span class="text-[13px] text-slate-300">提取邮箱</span>
                        <span class="switch"><input type="checkbox" name="extract_email" value="1" <?= !empty($cfg['extract_email']) ? 'checked' : '' ?>></span>
                    </label>
                </div>
                <div>
                    <label class="label">备注</label>
                    <textarea name="notes" class="textarea !min-h-[76px]" placeholder="批次目标、交付要求或数据范围…"><?= e($cfg['notes'] ?? '') ?></textarea>
                </div>
                <button class="btn btn-primary w-full"><?= icon('sparkles', 'h-4 w-4') ?> 生成任务预览</button>
                <p class="text-center font-mono text-[10px] uppercase tracking-[0.16em] text-slate-700">创建前必须预览</p>
            </div>
        </section>

        <section class="panel xl:col-span-7">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">关键词池</div><h2 class="panel-title"><?= icon('key', 'h-4 w-4 text-plasma') ?> 选择关键词</h2></div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-[12px] text-slate-600" id="selected-keywords"><?= count($selectedIds) ?> 已选择</span>
                    <button type="button" id="select-all-keywords" class="btn btn-ghost btn-sm">全选</button>
                </div>
            </div>
            <div class="border-b border-white/[0.06] p-3">
                <div class="relative">
                    <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-600') ?>
                    <input type="text" id="keyword-filter" class="input !pl-9" placeholder="过滤关键词、行业或国家…">
                </div>
            </div>
            <div class="max-h-[560px] overflow-y-auto p-3">
                <div class="grid grid-cols-1 gap-1.5" id="keyword-list">
                    <?php foreach ($keywords as $kw): ?>
                        <label class="keyword-row group flex cursor-pointer items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 transition hover:border-white/[0.06] hover:bg-white/[0.025]"
                               data-search="<?= e(mb_strtolower($kw['keyword'] . ' ' . $kw['country_name'] . ' ' . $kw['industry_name'] . ' ' . $kw['iso2'])) ?>"
                               data-country="<?= (int) $kw['country_id'] ?>" data-industry="<?= (int) $kw['industry_id'] ?>">
                            <input type="checkbox" name="keyword_ids[]" value="<?= (int) $kw['id'] ?>" class="keyword-check h-4 w-4 rounded border-white/15 bg-ink-950 text-signal accent-[#20e3bd]" <?= in_array((int) $kw['id'], $selectedIds, true) ? 'checked' : '' ?>>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[14px] font-medium text-slate-300"><?= e($kw['keyword']) ?></span>
                                <span class="mt-0.5 flex items-center gap-2 font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= flag_emoji($kw['iso2'] ?? '') ?> <?= e($kw['iso2'] ? country_name_cn($kw['iso2'], $kw['country_name']) : '全球') ?> <span class="text-slate-700">/</span> <?= e(industry_name_display($kw['industry_id'] ? (int) $kw['industry_id'] : null, $kw['industry_name'] ?? null)) ?></span>
                            </span>
                            <span class="font-mono text-[10px] uppercase text-slate-700"><?= e(language_name($kw['language_code'])) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div id="no-keywords" class="hidden py-10 text-center text-[13px] text-slate-600">没有匹配的关键词</div>
            </div>
        </section>
    </div>
</form>

<?php if ($preview): ?>
    <section class="panel reveal reveal-delay-2 mt-4">
        <div class="panel-head">
            <div>
                <div class="eyebrow mb-1">预览确认</div>
                <h2 class="panel-title"><?= icon('check-circle', 'h-4 w-4 text-signal') ?> 任务清单预览</h2>
            </div>
            <div class="flex items-center gap-3">
                <span class="tag tag-signal"><?= count($preview) ?> 个任务</span>
                <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">并发数 1</span>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>关键词</th><th>市场</th><th>行业</th><th>中心点</th><th>语言</th><th>状态</th></tr></thead>
                <tbody>
                <?php foreach ($preview as $i => $p): ?>
                    <tr>
                        <td><span class="font-mono text-[12px] text-slate-600"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span></td>
                        <td><span class="block max-w-[260px] truncate text-[14px] text-slate-300"><?= e($p['keyword']) ?></span></td>
                        <td><span class="flex items-center gap-1.5 whitespace-nowrap text-[13px]"><?= flag_emoji($p['country_iso2'] ?? '') ?> <?= e(place_name_cn($p['region_name'])) ?></span><span class="mt-0.5 block text-[11px] text-slate-600"><?= e($p['country_name']) ?></span></td>
                        <td><span class="text-[13px] text-slate-400"><?= e(industry_name_display($p['industry_id'] ? (int) $p['industry_id'] : null, $p['industry_name'] ?? null)) ?></span></td>
                        <td><span class="font-mono text-[12px] text-slate-600"><?= e(number_format((float) $p['latitude'], 3)) ?>, <?= e(number_format((float) $p['longitude'], 3)) ?></span></td>
                        <td><span class="tag font-mono"><?= e(language_name($p['language_code'])) ?></span></td>
                        <td><span class="status-badge border-white/10 bg-white/[0.03] text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span> 就绪</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="flex flex-col gap-3 border-t border-white/[0.06] p-4 sm:flex-row sm:items-center">
            <div class="flex items-center gap-2 text-[13px] text-slate-500">
                <?= icon('alert', 'h-3.5 w-3.5 text-amber-400/70') ?>
                创建后为「等待提交」状态；进入任务详情点击「提交采集任务」后才会调用采集引擎。
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <a href="/jobs/batch" class="btn btn-secondary btn-sm">重新配置</a>
                <form method="post" action="/jobs/batch/create" data-confirm="确认创建 <?= count($preview) ?> 个本地任务？">
                    <?= csrf_field() ?>
                    <?php foreach ($batchConfig as $key => $value): ?>
                        <?php if ($key === 'keyword_ids') continue; ?>
                        <input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="keyword_ids" value="<?= e(json_encode(array_column($preview, 'keyword_id'))) ?>">
                    <button class="btn btn-primary btn-sm"><?= icon('layers', 'h-3.5 w-3.5') ?> 确认创建批次</button>
                </form>
            </div>
        </div>
    </section>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checks = Array.from(document.querySelectorAll('.keyword-check'));
    const counter = document.getElementById('selected-keywords');
    const all = document.getElementById('select-all-keywords');
    const filter = document.getElementById('keyword-filter');
    const rows = Array.from(document.querySelectorAll('.keyword-row'));
    const country = document.getElementById('batch-country');
    const region = document.getElementById('batch-region');
    const noKeywords = document.getElementById('no-keywords');

    function updateCount() {
        if (counter) counter.textContent = checks.filter(function (c) { return c.checked; }).length + ' 已选择';
    }
    checks.forEach(function (c) { c.addEventListener('change', updateCount); });
    if (all) all.addEventListener('click', function () {
        const visible = rows.filter(function (r) { return r.style.display !== 'none'; });
        const shouldCheck = visible.some(function (r) { const c = r.querySelector('input'); return !c.checked; });
        visible.forEach(function (r) { r.querySelector('input').checked = shouldCheck; });
        updateCount();
    });
    if (filter) filter.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        let shown = 0;
        rows.forEach(function (row) {
            const ok = !q || row.dataset.search.includes(q);
            row.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        noKeywords.classList.toggle('hidden', shown > 0);
    });
    function filterRegions() {
        const id = country.value;
        Array.from(region.options).forEach(function (opt) {
            if (!opt.value) return;
            const match = !id || opt.dataset.country === id;
            opt.hidden = !match;
            if (!match && opt.selected) region.value = '';
        });
    }
    if (country) country.addEventListener('change', filterRegions);
    filterRegions();
    updateCount();
});
</script>
