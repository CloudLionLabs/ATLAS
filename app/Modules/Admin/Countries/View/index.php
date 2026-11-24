<?php
$totalCountries = count($countries);
$activeCountries = count(array_filter($countries, static fn ($c) => (int) $c['is_active'] === 1));
$totalRegions = array_sum(array_map(static fn ($c) => (int) $c['region_count'], $countries));
$totalBusinesses = array_sum(array_map(static fn ($c) => (int) $c['business_count'], $countries));
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 覆盖网络 / 市场注册表</div>
            <h1 class="page-title">国家与<span class="text-signal">地区</span></h1>
            <p class="page-subtitle">维护 ISO 国家代码、默认搜索语言、电话区号与城市坐标。地区树为全球选点和批量任务提供地理中心。城市节点为常用城市快捷列表；全球城市库已接入 <?= fmt_number($geoCitiesCount ?? 0) ?> 个城市，可在“全球选点”中搜索。</p>
        </div>
        <a href="/jobs/create" class="btn btn-primary"><?= icon('crosshair') ?> 用地图选点</a>
    </div>
</section>

<div class="reveal reveal-delay-1 mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">国家</div><div class="mt-2 font-display text-2xl font-semibold text-white"><?= fmt_number($totalCountries) ?></div></div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">已启用</div><div class="mt-2 font-display text-2xl font-semibold text-signal"><?= fmt_number($activeCountries) ?></div></div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">城市节点</div><div class="mt-2 font-display text-2xl font-semibold text-electric"><?= fmt_number($totalRegions) ?></div></div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">商家</div><div class="mt-2 font-display text-2xl font-semibold text-plasma"><?= fmt_number($totalBusinesses) ?></div></div>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="panel reveal reveal-delay-2 xl:col-span-7">
        <div class="panel-head">
            <div><div class="eyebrow mb-1">国家索引</div><h2 class="panel-title"><?= icon('globe', 'h-4 w-4 text-signal') ?> 国家注册表</h2></div>
            <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= fmt_number($totalCountries) ?> 条记录</span>
        </div>
        <div class="border-b border-white/[0.06] p-3">
            <form method="get" action="/countries" class="flex gap-2">
                <div class="relative flex-1">
                    <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-600') ?>
                    <input type="text" name="q" value="<?= e($q) ?>" class="input !pl-9" placeholder="搜索国家名称或 ISO 代码…">
                </div>
                <?php if ($selectedCountryId): ?><input type="hidden" name="country" value="<?= (int) $selectedCountryId ?>"><?php endif; ?>
                <button class="btn btn-secondary"><?= icon('filter', 'h-3.5 w-3.5') ?></button>
            </form>
        </div>
        <div class="max-h-[560px] overflow-y-auto">
            <table class="data-table">
                <thead><tr><th>市场</th><th>语言 / 区号</th><th>城市节点</th><th>商家</th><th>状态</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($countries as $c): ?>
                    <tr class="<?= $selectedCountryId === (int) $c['id'] ? 'bg-signal/[0.035]' : '' ?>">
                        <td>
                            <a href="/countries?country=<?= (int) $c['id'] ?>" class="flex items-center gap-2.5">
                                <span class="text-base"><?= flag_emoji($c['iso2']) ?></span>
                                <span><span class="block text-[14px] font-medium text-slate-200"><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?></span><span class="mt-0.5 block font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= e($c['name_en']) ?> · <?= e($c['iso2']) ?></span></span>
                            </a>
                        </td>
                        <td><span class="font-mono text-[12px] uppercase text-slate-400"><?= e(language_name($c['default_language'])) ?></span><span class="mt-0.5 block font-mono text-[11px] text-slate-600"><?= e($c['phone_code'] ?: '—') ?></span></td>
                        <td><span class="font-mono text-[13px] text-slate-400"><?= fmt_number($c['region_count']) ?></span></td>
                        <td><span class="font-mono text-[13px] text-slate-300"><?= fmt_number($c['business_count']) ?></span></td>
                        <td><span class="status-badge <?= (int) $c['is_active'] ? 'border-signal/20 bg-signal/[0.05] text-signal' : 'border-white/10 bg-white/[0.03] text-slate-500' ?>"><span class="h-1.5 w-1.5 rounded-full <?= (int) $c['is_active'] ? 'bg-signal' : 'bg-slate-600' ?>"></span><?= (int) $c['is_active'] ? '已启用' : 'OFF' ?></span></td>
                        <td class="text-right"><?php if (is_admin()): ?><a href="/countries?edit=<?= (int) $c['id'] ?>&country=<?= (int) $c['id'] ?>" class="inline-grid h-7 w-7 place-items-center rounded-md text-slate-600 hover:bg-white/[0.05] hover:text-signal" title="编辑"><?= icon('settings', 'h-3.5 w-3.5') ?></a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="space-y-4 xl:col-span-5">
        <?php if ($selectedCountry): ?>
            <section class="panel reveal reveal-delay-2">
                <div class="panel-head">
                    <div><div class="eyebrow mb-1">国家节点</div><h2 class="panel-title"><span class="text-base"><?= flag_emoji($selectedCountry['iso2']) ?></span> <?= e(country_name_cn($selectedCountry['iso2'], $selectedCountry['name_en'])) ?></h2></div>
                    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= e($selectedCountry['iso2']) ?></span>
                </div>
                <div class="grid grid-cols-3 gap-px bg-white/[0.04]">
                    <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">语言</div><div class="mt-1 font-mono text-[13px] text-slate-300"><?= e(language_name($selectedCountry['default_language'])) ?></div></div>
                    <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">电话</div><div class="mt-1 font-mono text-[13px] text-slate-300"><?= e($selectedCountry['phone_code'] ?: '—') ?></div></div>
                    <div class="bg-ink-900/90 px-4 py-3"><div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">缩放</div><div class="mt-1 font-mono text-[13px] text-slate-300">Z<?= (int) $selectedCountry['default_zoom'] ?></div></div>
                </div>
                <div class="border-b border-white/[0.06] px-4 py-3">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">城市节点 / <?= count($regions) ?></span>
                        <span class="font-mono text-[11px] text-slate-600">经纬度</span>
                    </div>
                    <div class="mt-2 max-h-[240px] space-y-1 overflow-y-auto">
                        <?php if (!$regions): ?><div class="py-4 text-center text-[13px] text-slate-600">暂无城市节点</div><?php endif; ?>
                        <?php foreach ($regions as $r): ?>
                            <div class="flex items-center gap-2 rounded-lg border border-transparent px-2 py-1.5 hover:border-white/[0.06] hover:bg-white/[0.02]">
                                <span class="h-1 w-1 rounded-full <?= (int) $r['is_active'] ? 'bg-signal' : 'bg-slate-600' ?>"></span>
                                <span class="min-w-0 flex-1 truncate text-[13px] text-slate-300"><?= e(place_name_cn($r['name'])) ?> <span class="font-mono text-[11px] text-slate-600"><?= e(region_type_label($r['region_type'])) ?></span></span>
                                <span class="font-mono text-[11px] text-slate-600"><?= $r['latitude'] !== null ? e(number_format((float) $r['latitude'], 2) . ', ' . number_format((float) $r['longitude'], 2)) : '—' ?></span>
                                <a href="/jobs/create?region=<?= (int) $r['id'] ?>" class="text-slate-700 hover:text-signal" title="用此节点创建任务"><?= icon('crosshair', 'h-3 w-3') ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (is_admin()): ?>
                <div class="p-4">
                    <details>
                        <summary class="flex cursor-pointer list-none items-center justify-between font-mono text-[11px] uppercase tracking-wider text-signal/80">添加城市节点 <span>+</span></summary>
                        <form method="post" action="/regions" class="mt-3 space-y-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="country_id" value="<?= (int) $selectedCountry['id'] ?>">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" name="name" placeholder="城市名称 *" class="input !py-2" required>
                                <input type="text" name="name_local" placeholder="本地名称" class="input !py-2">
                                <input type="number" step="any" name="latitude" placeholder="纬度" class="input !py-2 font-mono" required>
                                <input type="number" step="any" name="longitude" placeholder="经度" class="input !py-2 font-mono" required>
                            </div>
                            <div class="flex gap-2">
                                <select name="region_type" class="select !py-2"><option value="city">city</option><option value="state">state</option><option value="district">district</option><option value="county">county</option></select>
                                <input type="number" name="default_zoom" value="11" min="1" max="20" class="input !py-2 font-mono" title="默认缩放">
                                <label class="flex items-center gap-2 px-2 text-[12px] text-slate-500"><input type="checkbox" name="is_active" value="1" checked class="accent-[#20e3bd]"> 启用</label>
                            </div>
                            <button class="btn btn-secondary btn-sm w-full"><?= icon('plus', 'h-3.5 w-3.5') ?> 添加节点</button>
                        </form>
                    </details>
                </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="panel reveal reveal-delay-2 p-5">
                <div class="eyebrow mb-2">节点选择</div>
                <h2 class="text-[17px] font-semibold text-white">选择一个市场节点</h2>
                <p class="mt-2 text-[14px] leading-5 text-slate-500">从左侧列表打开国家详情，查看城市坐标、语言和商家覆盖，或添加新的城市节点。</p>
                <div class="mt-5 rounded-xl border border-dashed border-white/[0.1] p-5 text-center">
                    <div class="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-xl border border-white/[0.07] bg-white/[0.03] text-slate-600"><?= icon('globe', 'h-5 w-5') ?></div>
                    <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">等待选择</div>
                </div>
            </section>
        <?php endif; ?>

        <?php if (is_admin()): ?>
        <section class="panel reveal reveal-delay-3">
            <details <?= $editing ? 'open' : '' ?>>
                <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4">
                    <span><span class="eyebrow mb-1 block"><?= $editing ? '编辑国家' : '新国家' ?></span><span class="panel-title text-[15px]"><?= icon($editing ? 'settings' : 'plus', 'h-4 w-4 text-signal') ?> <?= $editing ? '编辑国家节点' : '接入新国家' ?></span></span>
                    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= $editing ? '展开 ↓' : '展开 +' ?></span>
                </summary>
                <form method="post" action="<?= $editing ? '/countries/' . (int) $editing['id'] : '/countries' ?>" class="space-y-3 border-t border-white/[0.06] p-5">
                    <?= csrf_field() ?>
                    <?php if ($editing): ?>
                        <div class="rounded-lg border border-amber-400/15 bg-amber-400/[0.04] px-3 py-2 font-mono text-[11px] uppercase tracking-wider text-amber-200/70">正在编辑 <?= e($editing['iso2']) ?> · 修改将应用到新任务</div>
                    <?php endif; ?>
                    <div class="grid grid-cols-3 gap-2">
                        <div><label class="label">ISO2 *</label><input name="iso2" maxlength="2" value="<?= e($editing['iso2'] ?? '') ?>" class="input uppercase" <?= $editing ? 'readonly' : 'required' ?>></div>
                        <div><label class="label">ISO3 代码</label><input name="iso3" maxlength="3" value="<?= e($editing['iso3'] ?? '') ?>" class="input uppercase"></div>
                        <div><label class="label">区号</label><input name="phone_code" value="<?= e($editing['phone_code'] ?? '') ?>" class="input" placeholder="+44"></div>
                    </div>
                    <div><label class="label">英文名称 *</label><input name="name_en" value="<?= e($editing['name_en'] ?? '') ?>" class="input" required></div>
                    <div><label class="label">中文名称</label><input name="name_zh" value="<?= e($editing['name_zh'] ?? '') ?>" class="input" placeholder="例如：英国"></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="label">本地名称</label><input name="name_local" value="<?= e($editing['name_local'] ?? '') ?>" class="input"></div>
                        <div><label class="label">默认语言</label><input name="default_language" value="<?= e($editing['default_language'] ?? 'en') ?>" class="input" maxlength="10"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div><label class="label">中心纬度</label><input name="center_lat" value="<?= e($editing['center_lat'] ?? '') ?>" class="input font-mono"></div>
                        <div><label class="label">中心经度</label><input name="center_lng" value="<?= e($editing['center_lng'] ?? '') ?>" class="input font-mono"></div>
                        <div><label class="label">默认缩放</label><input name="default_zoom" value="<?= e($editing['default_zoom'] ?? 5) ?>" class="input font-mono"></div>
                    </div>
                    <label class="flex items-center gap-2 text-[13px] text-slate-400"><input type="checkbox" name="is_active" value="1" <?= !$editing || (int) $editing['is_active'] ? 'checked' : '' ?> class="accent-[#20e3bd]"> 在国家选择器中启用</label>
                    <div class="flex gap-2">
                        <?php if ($editing): ?><a href="/countries" class="btn btn-ghost btn-sm flex-1">取消</a><?php endif; ?>
                        <button class="btn btn-primary btn-sm flex-1"><?= icon('check', 'h-3.5 w-3.5') ?> <?= $editing ? '保存国家配置' : '创建国家节点' ?></button>
                    </div>
                </form>
            </details>
        </section>
        <?php else: ?>
        <section class="panel reveal reveal-delay-3 p-5">
            <div class="eyebrow mb-2">只读字典</div>
            <h2 class="text-[17px] font-semibold text-white">由管理员维护</h2>
            <p class="mt-2 text-[13px] leading-5 text-slate-500">国家与城市是共享基础字典。成员可以使用和搜索，但不能修改；如需新增市场，请联系管理员。</p>
        </section>
        <?php endif; ?>
    </div>
</div>
