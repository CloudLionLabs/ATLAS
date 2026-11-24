<?php
$isPreselected = !empty($selectedCountryData);
$lat = (float) $defaults['latitude'];
$lng = (float) $defaults['longitude'];
$zoom = (int) $defaults['zoom'];
$radius = (int) $defaults['radius'];
$selectedCountryId = (int) ($defaults['country_id'] ?? 0);
$selectedRegionId = (int) ($defaults['region_id'] ?? 0);
$regionOptions = array_values(array_filter($regions, static fn ($r) => (int) $r['country_id'] === $selectedCountryId));
$languages = [
    'en' => '英语', 'zh' => '中文', 'ja' => '日语', 'de' => '德语',
    'fr' => '法语', 'es' => '西班牙语', 'pt' => '葡萄牙语', 'it' => '意大利语',
    'nl' => '荷兰语', 'ar' => '阿拉伯语', 'ko' => '韩语', 'ru' => '俄语',
    'pl' => '波兰语', 'tr' => '土耳其语', 'id' => '印尼语', 'th' => '泰语',
    'vi' => '越南语', 'hi' => '印地语', 'uk' => '乌克兰语', 'ro' => '罗马尼亚语',
    'cs' => '捷克语', 'el' => '希腊语', 'he' => '希伯来语', 'sv' => '瑞典语',
    'da' => '丹麦语', 'fi' => '芬兰语', 'no' => '挪威语', 'ms' => '马来语',
];
?>
<style>
    .map-shell:fullscreen { border-radius: 0; }
    .map-shell:fullscreen .map-canvas { height: 100vh; min-height: 100vh; }
    .target-map-loading { position:absolute; inset:0; z-index:800; display:none; place-items:center; background:rgba(4,7,13,.38); backdrop-filter: blur(1px); }
    .target-map-loading.is-active { display:grid; }
    .target-map-loading .spinner { width:34px; height:34px; border-radius:999px; border:1px solid rgba(32,227,189,.2); border-top-color:#20e3bd; animation:spin 1s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .target-kicker { letter-spacing:.18em; }
    .pin-readout { font-variant-numeric: tabular-nums; }
</style>

<section class="reveal mb-6">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 全球选点 / 地理定位</div>
            <h1 class="page-title">全球地址 <span class="text-signal">选点锁定</span></h1>
            <p class="page-subtitle">搜索全球任意地址，或在地图上点击、缩放、拖拽定位。系统自动解析行政区域，并以可视化扫描半径生成采集任务。</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/jobs" class="btn btn-secondary"><?= icon('arrow-right', 'rotate-180') ?> 返回任务</a>
            <button type="submit" form="target-form" name="submit_now" value="0" class="btn btn-secondary"><?= icon('file') ?> <span data-label-single="保存草稿" data-label-coverage="保存覆盖草稿">保存草稿</span></button>
            <button type="submit" form="target-form" name="submit_now" value="1" class="btn btn-primary"><?= icon('zap') ?> <span data-label-single="创建并提交" data-label-coverage="生成覆盖任务">创建并提交</span></button>
        </div>
    </div>
</section>

<form id="target-form" method="post" action="/jobs" class="reveal reveal-delay-1">
    <?= csrf_field() ?>
    <input type="hidden" name="latitude" id="latitude" value="<?= e($lat) ?>">
    <input type="hidden" name="longitude" id="longitude" value="<?= e($lng) ?>">
    <input type="hidden" name="target_address" id="target_address" value="">
    <input type="hidden" name="zoom" id="zoom_input" value="<?= $zoom ?>">
    <input type="hidden" name="radius" id="radius_input" value="<?= $radius ?>">

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <div class="xl:col-span-8">
            <section class="map-shell scan-panel flex h-full flex-col">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.07] px-4 py-3">
                    <div class="flex items-center gap-3">
                        <span class="grid h-7 w-7 place-items-center rounded-lg border border-signal/25 bg-signal/[0.07] text-signal"><?= icon('crosshair', 'h-3.5 w-3.5') ?></span>
                        <div>
                            <div class="font-mono text-[12px] uppercase tracking-[0.17em] text-slate-300">目标获取</div>
                            <div class="font-mono text-[10px] uppercase tracking-[0.14em] text-slate-600">点击 · 缩放 · 拖拽锁定</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="hidden items-center gap-2 rounded-md border border-white/[0.07] bg-white/[0.02] px-2 py-1.5 font-mono text-[11px] uppercase tracking-wider text-slate-500 sm:flex">
                            <span class="h-1.5 w-1.5 rounded-full bg-signal"></span> ESRI 深色底图
                        </span>
                        <button type="button" id="layer-toggle" class="btn btn-secondary btn-sm"><?= icon('layers', 'h-3.5 w-3.5') ?> <span>卫星</span></button>
                        <button type="button" id="map-expand" class="icon-button !h-8 !w-8" title="全屏地图"><?= icon('expand', 'h-3.5 w-3.5') ?></button>
                    </div>
                </div>
                <div class="relative min-h-[560px] flex-1">
                    <div id="target-map" class="absolute inset-0"></div>
                    <div class="map-overlay"></div>
                    <div class="map-scan"></div>

                    <div class="map-search">
                        <div class="map-search-box">
                            <?= icon('search', 'h-4 w-4 shrink-0 text-signal/80') ?>
                            <input type="text" id="address-search" placeholder="搜索全球地址、城市或国家…" autocomplete="off" spellcheck="false">
                            <span class="hidden shrink-0 rounded border border-white/[0.1] px-1.5 py-0.5 font-mono text-[10px] text-slate-600 sm:inline">全球</span>
                        </div>
                        <div id="search-results" class="map-results hidden"></div>
                        <div class="mt-2 flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.14em] text-slate-600 drop-shadow">
                            <span class="h-1 w-1 rounded-full bg-signal/70"></span> 全球地址解析 · NOMINATIM
                        </div>
                    </div>

                    <div class="map-hud right-4 top-4 !px-3 !py-2 text-right hidden sm:block">
                        <div class="text-[10px] text-slate-600">扫描中心</div>
                        <div class="mt-0.5 pin-readout text-[13px] normal-case tracking-normal text-signal" id="hud-coords"><?= number_format($lat, 5) ?>, <?= number_format($lng, 5) ?></div>
                        <div class="mt-1 flex items-center justify-end gap-2 text-[10px] text-slate-600"><span>缩放</span><span class="text-slate-300" id="hud-zoom"><?= $zoom ?></span><span class="text-slate-700">/</span><span>半径</span><span class="text-slate-300" id="hud-radius"><?= number_format($radius / 1000, 1) ?> 公里</span></div>
                    </div>

                    <div class="absolute bottom-4 left-4 right-4 z-[650] flex flex-wrap items-end justify-between gap-2">
                        <div class="max-w-[calc(100%-2rem)] rounded-xl border border-white/[0.1] bg-ink-950/85 px-3.5 py-2.5 shadow-[0_18px_45px_rgba(0,0,0,.45)] backdrop-blur-xl sm:max-w-md">
                            <div class="flex items-center gap-2 font-mono text-[11px] uppercase tracking-[0.16em] text-signal"><span class="h-1.5 w-1.5 rounded-full bg-signal animate-pulse-soft"></span> <span id="address-status">信号已锁定</span></div>
                            <div class="mt-1.5 truncate text-[14px] font-medium text-slate-200" id="address-display"><?= e($selectedCountryData ? country_name_cn($selectedCountryData['iso2'], $selectedCountryData['name_en']) : '全球网络 · 点击地图选择目标区域') ?></div>
                            <div class="mt-1 flex flex-wrap items-center gap-2 font-mono text-[11px] uppercase tracking-wider text-slate-600">
                                <span id="address-country"><?= e($selectedCountryData ? country_name_cn($selectedCountryData['iso2'], $selectedCountryData['name_en']) : '未识别区域') ?></span>
                                <span class="text-slate-700">/</span>
                                <span id="address-city"><?= e($selectedCountryData ? '国家节点' : '全球视图') ?></span>
                                <span class="text-slate-700">/</span>
                                <span id="address-postcode">—</span>
                            </div>
                        </div>
                        <div class="hidden rounded-xl border border-white/[0.1] bg-ink-950/85 px-3 py-2 font-mono text-[11px] uppercase tracking-[0.14em] text-slate-500 backdrop-blur-xl sm:block">
                            <div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-signal"></span> <span id="layers-label">底图 / 深色</span></div>
                            <div class="mt-1 text-[10px] text-slate-700">滚轮缩放 · 拖拽平移</div>
                        </div>
                    </div>

                    <div class="map-hud left-4 top-[104px] !px-2.5 !py-1.5 hidden sm:block">
                        <div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-signal"></span><span id="map-mode">点击地图选点</span></div>
                    </div>

                    <div id="map-loading" class="target-map-loading"><div class="text-center"><div class="spinner mx-auto"></div><div class="mt-3 font-mono text-[11px] uppercase tracking-[0.18em] text-signal/70">正在解析位置</div></div></div>
                </div>
            </section>
        </div>

        <aside class="xl:col-span-4">
            <div class="flex h-full flex-col gap-4">
                <section class="panel scan-panel">
                    <div class="panel-head">
                        <div>
                            <div class="eyebrow mb-1">目标读数</div>
                            <h2 class="panel-title"><?= icon('map-pin', 'h-4 w-4 text-signal') ?> 位置锁定</h2>
                        </div>
                        <span class="map-badge">实时</span>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="rounded-xl border border-signal/15 bg-gradient-to-br from-signal/[0.07] to-transparent p-3.5">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-[11px] uppercase tracking-[0.18em] text-signal/70">识别区域</span>
                                <button type="button" class="font-mono text-[11px] uppercase tracking-wider text-slate-600 hover:text-signal" data-copy-target="address">复制地址</button>
                            </div>
                            <div class="mt-2 min-h-[38px] text-[15px] font-medium leading-5 text-slate-200" id="detected-address"><?= e($selectedCountryData ? country_name_cn($selectedCountryData['iso2'], $selectedCountryData['name_en']) : '在地图上点击任意位置，或搜索全球地址') ?></div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span class="tag tag-signal" id="chip-country"><?= e($selectedCountryData ? country_name_cn($selectedCountryData['iso2'], $selectedCountryData['name_en']) : '全球') ?></span>
                                <span class="tag" id="chip-city"><?= e($selectedCountryData ? country_name_cn($selectedCountryData['iso2'], $selectedCountryData['name_en']) : '全球') ?></span>
                                <span class="tag" id="chip-postcode">—</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">纬度</label>
                                <input type="number" step="any" min="-90" max="90" id="lat_display" value="<?= e($lat) ?>" class="input font-mono !text-[14px]">
                            </div>
                            <div>
                                <label class="label">经度</label>
                                <input type="number" step="any" min="-180" max="180" id="lng_display" value="<?= e($lng) ?>" class="input font-mono !text-[14px]">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label" for="country_id">国家</label>
                                <select name="country_id" id="country_id" class="select">
                                    <option value="">未指定</option>
                                    <?php foreach ($countries as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>" data-lat="<?= e($c['center_lat']) ?>" data-lng="<?= e($c['center_lng']) ?>" data-zoom="<?= (int) $c['default_zoom'] ?>" data-iso="<?= e($c['iso2']) ?>" <?= $selectedCountryId === (int) $c['id'] ? 'selected' : '' ?>><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="label" for="region_id">城市节点 <span class="font-normal normal-case tracking-normal text-slate-600">（该国城市）</span></label>
                                <select name="region_id" id="region_id" class="select">
                                    <option value="">国家中心点</option>
                                    <?php foreach ($regionOptions as $r): ?>
                                        <option value="<?= (int) $r['id'] ?>" data-lat="<?= e($r['latitude']) ?>" data-lng="<?= e($r['longitude']) ?>" data-zoom="<?= (int) $r['default_zoom'] ?>" <?= $selectedRegionId === (int) $r['id'] ? 'selected' : '' ?>><?= e(place_name_cn($r['name'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <p class="field-hint" id="region-city-hint">全球城市库共 <?= number_format($geoCitiesCount) ?> 个城市；选择国家后自动加载该国城市。</p>

                        <div class="rounded-xl border border-white/[0.07] bg-ink-950/40 p-3.5">
                            <div class="flex items-center justify-between">
                                <span class="label !mb-0" id="radius-title">扫描半径</span>
                                <span class="font-mono text-[14px] text-signal" id="radius-label"><?= number_format($radius / 1000, 1) ?> 公里</span>
                            </div>
                            <input type="range" id="radius_range" min="1000" max="10000" step="500" value="<?= $radius ?>" class="range mt-3">
                            <div id="radius-presets" class="mt-2.5 flex justify-between font-mono text-[10px] uppercase tracking-wider text-slate-700">
                                <button type="button" class="hover:text-signal" data-radius="1000">1 公里</button>
                                <button type="button" class="hover:text-signal" data-radius="5000">5 公里</button>
                                <button type="button" class="hover:text-signal" data-radius="10000">10 公里</button>
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/[0.07] bg-ink-950/40 p-3.5">
                            <label class="flex cursor-pointer items-center justify-between gap-3">
                                <span>
                                    <span class="block text-[14px] font-medium text-slate-200">全市网格覆盖</span>
                                    <span class="mt-0.5 block text-[12px] leading-5 text-slate-600">把城市覆盖半径拆成多个小任务，避开 Google 单次结果上限</span>
                                </span>
                                <span class="switch"><input type="checkbox" name="coverage_grid" id="coverage_grid" value="1"></span>
                            </label>
                            <div id="grid-settings" class="mt-3 hidden rounded-lg border border-white/[0.06] bg-white/[0.02] p-3">
                                <div class="mt-3 flex items-end gap-3">
                                    <div class="flex-1"><label class="label" for="grid_cell_km">单格半径（公里，最大 10）</label><input type="number" name="grid_cell_km" id="grid_cell_km" value="5" min="1" max="10" step="0.5" class="input !py-2 font-mono"></div>
                                    <div class="pb-2 font-mono text-[11px] uppercase tracking-wider text-slate-600">去重后预计 <span id="grid-count" class="text-signal">—</span> 个子任务</div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <button type="button" data-grid-cell="5" class="rounded-md border border-white/[0.06] px-2 py-1 font-mono text-[10px] text-slate-600 hover:border-signal/20 hover:text-signal">5 km</button>
                                    <button type="button" data-grid-cell="8" class="rounded-md border border-white/[0.06] px-2 py-1 font-mono text-[10px] text-slate-600 hover:border-signal/20 hover:text-signal">8 km</button>
                                    <button type="button" data-grid-cell="10" class="rounded-md border border-white/[0.06] px-2 py-1 font-mono text-[10px] text-slate-600 hover:border-signal/20 hover:text-signal">10 km</button>
                                </div>
                                <p id="grid-boundary-info" class="field-hint">选择城市节点后自动读取行政区面积，并按边界覆盖半径生成网格。未选择城市时使用手动扫描半径。</p>
                                <p class="field-hint">格子中心自动去重，相邻扫描区域保留重叠以覆盖边界。重叠区域采集到的同一商家会按 Google ID、名称+电话或名称+官网自动合并，不会重复入库。生成后进入等待提交状态；手动提交第一个后，完成一个会自动提交下一个，请保持当前任务页打开。</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/[0.07] bg-ink-950/40 p-3.5">
                            <div class="flex items-center justify-between">
                                <span class="label !mb-0">地图缩放</span>
                                <span class="font-mono text-[14px] text-electric" id="zoom-label">Z<?= $zoom ?></span>
                            </div>
                            <input type="range" id="zoom_range" min="2" max="19" step="1" value="<?= $zoom ?>" class="range mt-3">
                            <div class="mt-2 grid grid-cols-3 gap-1.5">
                                <button type="button" data-zoom="11" class="rounded-md border border-white/[0.06] py-1 font-mono text-[10px] uppercase tracking-wider text-slate-600 hover:border-signal/20 hover:text-signal">城市</button>
                                <button type="button" data-zoom="14" class="rounded-md border border-white/[0.06] py-1 font-mono text-[10px] uppercase tracking-wider text-slate-600 hover:border-signal/20 hover:text-signal">区域</button>
                                <button type="button" data-zoom="17" class="rounded-md border border-white/[0.06] py-1 font-mono text-[10px] uppercase tracking-wider text-slate-600 hover:border-signal/20 hover:text-signal">街道</button>
                            </div>
                            <p id="zoom-warning" class="mt-2 hidden rounded-md border border-amber-400/15 bg-amber-400/[0.04] px-2 py-1.5 text-[11px] leading-4 text-amber-200/60">当前缩放过低，采集范围会过大，建议使用 11–15。</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] p-2.5">
                                <div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">扫描面积</div>
                                <div class="mt-1 font-display text-lg font-semibold text-white" id="area-label"><?= number_format(M_PI * (($radius / 1000) ** 2), 1) ?><span class="text-[13px] text-slate-500"> 平方公里</span></div>
                            </div>
                            <div class="rounded-lg border border-white/[0.06] bg-white/[0.02] p-2.5">
                                <div class="font-mono text-[10px] uppercase tracking-wider text-slate-600">覆盖级别</div>
                                <div class="mt-1 font-display text-lg font-semibold text-signal" id="area-class"><?= $radius <= 5000 ? '本地' : ($radius <= 15000 ? '都市' : '区域') ?></div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="locate-me" class="btn btn-secondary btn-sm flex-1"><?= icon('crosshair', 'h-3.5 w-3.5') ?> 我的位置</button>
                            <button type="button" id="reset-view" class="btn btn-ghost btn-sm">重置视角</button>
                        </div>
                    </div>
                </section>
            </div>
        </aside>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-12">
        <section class="panel xl:col-span-8">
            <div class="panel-head">
                <div>
                    <div class="eyebrow mb-1">任务参数</div>
                    <h2 class="panel-title"><?= icon('settings', 'h-4 w-4 text-signal') ?> 任务参数</h2>
                </div>
                <span class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">地图目标 → 任务参数</span>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="label" for="task_name">任务名称</label>
                    <input type="text" name="task_name" id="task_name" value="<?= e($defaults['task_name']) ?>" maxlength="200" class="input" placeholder="例如：B2B 采购商 — 11月22日">
                    <p class="field-hint">建议包含市场、行业与日期，方便在任务列表中定位。</p>
                </div>
                <div class="md:col-span-2">
                    <label class="label" for="keyword_text">搜索关键词</label>
                    <div class="flex flex-col gap-2">
                        <input type="text" name="keyword_text" id="keyword_text" required value="<?= e($defaults['keyword_text']) ?>" maxlength="300" class="input flex-1" placeholder="填写关键词，或从下拉选择预设">
                        <select id="keyword_picker" class="select">
                            <option value="">选择预设关键词…</option>
                            <?php foreach ($keywords as $kw): $kwCountryId = (int) $kw['country_id']; ?>
                                <option value="<?= (int) $kw['id'] ?>" data-keyword="<?= e($kw['keyword']) ?>" data-industry="<?= (int) $kw['industry_id'] ?>" <?= $kwCountryId > 0 ? 'data-country="' . $kwCountryId . '"' : '' ?> data-lang="<?= e($kw['language_code']) ?>"><?= e(($kw['iso2'] ? country_name_cn($kw['iso2'], '') : '全局') . ' · ' . $kw['keyword']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="field-hint">位置由上方国家、城市和地图坐标决定；这里只填写采购关键词，不要写“in Berlin”等位置后缀。带国家的词会同步切换国家，全局词保留你当前选择的国家。</p>
                    <input type="hidden" name="keyword_id" id="keyword_id" value="<?= (int) ($defaults['keyword_id'] ?? 0) ?>">
                </div>
                <div>
                    <label class="label" for="language_code">搜索语言</label>
                    <select name="language_code" id="language_code" class="select">
                        <?php foreach ($languages as $code => $name): ?>
                            <option value="<?= e($code) ?>" <?= $defaults['language_code'] === $code ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="industry_id">行业分类</label>
                    <select name="industry_id" id="industry_id" class="select">
                        <option value="">未分类</option>
                        <?php foreach ($industries as $industry): ?>
                            <option value="<?= (int) $industry['id'] ?>" <?= (int) ($defaults['industry_id'] ?? 0) === (int) $industry['id'] ? 'selected' : '' ?>><?= e(industry_name_display((int) $industry['id'], $industry['name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="depth">采集深度</label>
                    <input type="number" name="depth" id="depth" min="1" max="50" value="<?= (int) $defaults['depth'] ?>" class="input">
                </div>
                <div>
                    <label class="label" for="max_time">最大时长</label>
                    <input type="text" name="max_time" id="max_time" value="<?= e($defaults['max_time']) ?>" class="input font-mono" placeholder="10m">
                    <p class="field-hint">当前采集引擎的合法格式仍需实测确认，默认沿用表单示例。</p>
                </div>
                <div class="md:col-span-2">
                    <label class="flex cursor-pointer items-center justify-between rounded-xl border border-white/[0.08] bg-ink-950/40 px-3.5 py-3">
                        <span>
                            <span class="block text-[14px] font-medium text-slate-200">提取邮箱</span>
                            <span class="mt-0.5 block text-[12px] text-slate-600">仅采集公开商务联系方式</span>
                        </span>
                        <span class="switch"><input type="checkbox" name="extract_email" value="1" <?= !empty($defaults['extract_email']) ? 'checked' : '' ?>></span>
                    </label>
                </div>
                <div class="md:col-span-2 rounded-xl border border-amber-400/15 bg-amber-400/[0.035] px-4 py-3">
                    <div class="flex gap-2.5">
                        <span class="mt-0.5 text-amber-400/80"><?= icon('alert', 'h-3.5 w-3.5') ?></span>
                        <p class="text-[13px] leading-5 text-amber-200/60">提交后会直接调用本地采集引擎。若请求超时，系统将标记为「提交未知」并禁止自动重试，避免产生重复任务。布尔字段与最大时长的正式约定仍待接口实测。</p>
                    </div>
                </div>
            </div>
        </section>

        <aside class="flex flex-col gap-4 xl:col-span-4">
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <div class="eyebrow mb-1">提交预览</div>
                        <h2 class="panel-title"><?= icon('zap', 'h-4 w-4 text-hazard') ?> 提交预览</h2>
                    </div>
                </div>
                <div class="space-y-2 p-5 font-mono text-[13px]">
                    <div class="flex justify-between border-b border-white/[0.05] pb-2"><span class="text-slate-600">接口</span><span class="text-slate-300">POST /scrape</span></div>
                    <div class="flex justify-between border-b border-white/[0.05] pb-2"><span class="text-slate-600">语言</span><span class="text-slate-300" id="preview-lang"><?= e(language_name($defaults['language_code'])) ?></span></div>
                    <div class="flex justify-between border-b border-white/[0.05] pb-2"><span class="text-slate-600">缩放</span><span class="text-slate-300" id="preview-zoom"><?= $zoom ?></span></div>
                    <div class="flex justify-between border-b border-white/[0.05] pb-2"><span class="text-slate-600">半径</span><span class="text-slate-300" id="preview-radius"><?= $radius ?></span></div>
                    <div class="flex justify-between border-b border-white/[0.05] pb-2"><span class="text-slate-600">经纬度</span><span class="text-right text-slate-300" id="preview-coords"><?= number_format($lat, 4) ?> / <?= number_format($lng, 4) ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-600">最大时长</span><span class="text-slate-300" id="preview-maxtime"><?= e($defaults['max_time']) ?></span></div>
                </div>
                <div class="border-t border-white/[0.06] p-4">
                    <button type="submit" name="submit_now" value="1" class="btn btn-primary w-full"><?= icon('zap', 'h-4 w-4') ?> <span data-label-single="创建并提交采集任务" data-label-coverage="生成全市覆盖任务">创建并提交采集任务</span></button>
                    <button type="submit" name="submit_now" value="0" class="btn btn-ghost mt-2 w-full"><?= icon('file', 'h-4 w-4') ?> <span data-label-single="仅保存为草稿" data-label-coverage="保存覆盖草稿">仅保存为草稿</span></button>
                    <p class="mt-3 text-center font-mono text-[10px] uppercase tracking-[0.15em] text-slate-700">外部提交需手动确认</p>
                </div>
            </section>

            <section class="panel p-4">
                <div class="flex items-center gap-2">
                    <span class="grid h-7 w-7 place-items-center rounded-lg border border-white/[0.07] bg-white/[0.03] text-slate-500"><?= icon('clock', 'h-3.5 w-3.5') ?></span>
                    <div class="min-w-0">
                        <div class="font-mono text-[11px] uppercase tracking-wider text-slate-600">最近选点</div>
                        <div class="truncate text-[14px] text-slate-300">最近选点任务</div>
                    </div>
                </div>
                <div class="mt-3 divide-y divide-white/[0.05]">
                    <?php if (!$recentJobs): ?>
                        <p class="py-3 text-[13px] leading-5 text-slate-600">还没有任务。地图上的第一次点击会开启一条新的采集链路。</p>
                    <?php endif; ?>
                    <?php foreach ($recentJobs as $rj): ?>
                        <a href="/jobs/<?= e($rj['id']) ?>" class="flex items-center gap-2 py-2.5 text-[13px] text-slate-400 hover:text-signal">
                            <span class="h-1 w-1 rounded-full <?= status_meta($rj['status'])['dot'] ?>"></span>
                            <span class="min-w-0 flex-1 truncate"><?= e($rj['task_name']) ?></span>
                            <span class="font-mono text-[11px] text-slate-700"><?= e(fmt_datetime($rj['created_at'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        </aside>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('target-form');
    const mapEl = document.getElementById('target-map');
    if (!mapEl || typeof L === 'undefined') {
        if (mapEl) mapEl.innerHTML = '<div class="grid h-full place-items-center font-mono text-[13px] uppercase tracking-wider text-slate-600">地图引擎离线 · CHECK VENDOR ASSETS</div>';
        return;
    }

    const countries = <?= $countriesJson ?: '[]' ?>;
    const regions = <?= $regionsJson ?: '[]' ?>;
    const initial = {
        lat: <?= $lat ?>,
        lng: <?= $lng ?>,
        zoom: <?= $zoom ?>,
        radius: <?= $radius ?>,
        countryId: <?= $selectedCountryId ?>,
        regionId: <?= $selectedRegionId ?>
    };
    // The target zoom must not be read from Leaflet mid fly-to animation:
    // Leaflet may temporarily zoom out to z3 during a long flight.
    let desiredZoom = initial.zoom;
    let regionCountryLoaded = String(initial.countryId || '');
    let cityOptionsToken = 0;

    const fields = {
        lat: document.getElementById('latitude'),
        lng: document.getElementById('longitude'),
        latDisplay: document.getElementById('lat_display'),
        lngDisplay: document.getElementById('lng_display'),
        address: document.getElementById('target_address'),
        zoom: document.getElementById('zoom_input'),
        radius: document.getElementById('radius_input'),
        country: document.getElementById('country_id'),
        region: document.getElementById('region_id'),
        radiusRange: document.getElementById('radius_range'),
        coverageGrid: document.getElementById('coverage_grid'),
        gridSettings: document.getElementById('grid-settings'),
        gridCellKm: document.getElementById('grid_cell_km'),
        gridCount: document.getElementById('grid-count'),
        gridBoundaryInfo: document.getElementById('grid-boundary-info'),
        zoomRange: document.getElementById('zoom_range'),
        search: document.getElementById('address-search'),
        results: document.getElementById('search-results'),
        detectedAddress: document.getElementById('detected-address'),
        addressDisplay: document.getElementById('address-display'),
        addressStatus: document.getElementById('address-status'),
        addressCountry: document.getElementById('address-country'),
        addressCity: document.getElementById('address-city'),
        addressPostcode: document.getElementById('address-postcode'),
        chipCountry: document.getElementById('chip-country'),
        chipCity: document.getElementById('chip-city'),
        chipPostcode: document.getElementById('chip-postcode'),
        hudCoords: document.getElementById('hud-coords'),
        hudZoom: document.getElementById('hud-zoom'),
        hudRadius: document.getElementById('hud-radius'),
        radiusLabel: document.getElementById('radius-label'),
        radiusTitle: document.getElementById('radius-title'),
        radiusPresets: document.getElementById('radius-presets'),
        zoomLabel: document.getElementById('zoom-label'),
        areaLabel: document.getElementById('area-label'),
        areaClass: document.getElementById('area-class'),
        loading: document.getElementById('map-loading'),
        keywordText: document.getElementById('keyword_text'),
        keywordId: document.getElementById('keyword_id'),
        keywordPicker: document.getElementById('keyword_picker'),
        industry: document.getElementById('industry_id'),
        language: document.getElementById('language_code'),
        taskName: document.getElementById('task_name'),
        maxTime: document.getElementById('max_time'),
        previewLang: document.getElementById('preview-lang'),
        previewZoom: document.getElementById('preview-zoom'),
        previewRadius: document.getElementById('preview-radius'),
        previewCoords: document.getElementById('preview-coords'),
        previewMaxtime: document.getElementById('preview-maxtime'),
        layersLabel: document.getElementById('layers-label'),
        layerToggle: document.getElementById('layer-toggle'),
        mapMode: document.getElementById('map-mode')
    };

    const map = L.map('target-map', {
        zoomControl: false,
        scrollWheelZoom: true,
        worldCopyJump: true,
        minZoom: 2,
        maxZoom: 19,
        zoomSnap: 1
    }).setView([initial.lat, initial.lng], initial.zoom);

    const darkLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, maxNativeZoom: 16,
        attribution: '&copy; Esri, HERE, Garmin, OpenStreetMap contributors'
    }).addTo(map);
    const darkLabels = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Reference/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, maxNativeZoom: 16
    }).addTo(map);
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, maxNativeZoom: 19, attribution: 'Tiles &copy; Esri'
    });
    let activeLayer = 'dark';

    const pulseIcon = L.divIcon({
        className: '',
        html: '<div class="atlas-marker"><span class="ring"></span><span class="ring"></span><span class="crosshair-x"></span><span class="crosshair-y"></span><span class="core"></span></div>',
        iconSize: [28, 28],
        iconAnchor: [14, 14]
    });

    const marker = L.marker([initial.lat, initial.lng], { icon: pulseIcon, draggable: true, keyboard: false }).addTo(map);
    const circle = L.circle([initial.lat, initial.lng], {
        radius: initial.radius,
        color: '#20e3bd',
        weight: 1.2,
        opacity: 0.75,
        fillColor: '#20e3bd',
        fillOpacity: 0.075,
        dashArray: '4 6'
    }).addTo(map);

    L.control.scale({ position: 'bottomright', imperial: false }).addTo(map);

    let reverseController = null;
    let reverseTimer = null;

    function setLoading(active) {
        if (fields.loading) fields.loading.classList.toggle('is-active', active);
    }

    function fmtCoord(value) {
        return Number(value).toFixed(6);
    }

    function areaStats(radius) {
        const km = radius / 1000;
        const area = Math.PI * km * km;
        const cls = radius <= 5000 ? '本地' : radius <= 15000 ? '都市' : '区域';
        if (fields.areaLabel) fields.areaLabel.innerHTML = area.toLocaleString(undefined, { maximumFractionDigits: 1 }) + '<span class="text-[13px] text-slate-500"> 平方公里</span>';
        if (fields.areaClass) fields.areaClass.textContent = cls;
    }

    function paintGeometry(lat, lng, radius) {
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
        circle.setRadius(radius);
        fields.lat.value = fmtCoord(lat);
        fields.lng.value = fmtCoord(lng);
        fields.latDisplay.value = fmtCoord(lat);
        fields.lngDisplay.value = fmtCoord(lng);
        fields.radius.value = radius;
        fields.radiusRange.value = radius;
        fields.hudCoords.textContent = Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5);
        fields.hudRadius.textContent = (radius / 1000).toFixed(1) + ' 公里';
        fields.radiusLabel.textContent = (radius / 1000).toFixed(1) + ' 公里';
        fields.previewRadius.textContent = radius;
        fields.previewCoords.textContent = Number(lat).toFixed(4) + ' / ' + Number(lng).toFixed(4);
        areaStats(radius);
    }

    function setZoomLabel(zoom) {
        zoom = Math.max(2, Math.min(19, Number(zoom)));
        desiredZoom = zoom;
        fields.zoom.value = zoom;
        fields.zoomRange.value = zoom;
        fields.hudZoom.textContent = zoom;
        fields.zoomLabel.textContent = 'Z' + zoom;
        fields.previewZoom.textContent = zoom;
        const warning = document.getElementById('zoom-warning');
        if (warning) warning.classList.toggle('hidden', zoom >= 11);
    }

    function updatePreview() {
        if (fields.previewLang && fields.language) fields.previewLang.textContent = fields.language.options[fields.language.selectedIndex]?.textContent || fields.language.value;
        if (fields.previewMaxtime && fields.maxTime) fields.previewMaxtime.textContent = fields.maxTime.value || '—';
    }

    function fillCountry(countryId) {
        fields.country.value = countryId || '';
        regionCountryLoaded = String(countryId || '');
        const list = regions.filter(function (r) { return String(r.country_id) === String(countryId); });
        fields.region.innerHTML = '<option value="">国家中心点</option>';
        list.forEach(function (r) {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.name_cn || r.name;
            opt.dataset.lat = r.latitude;
            opt.dataset.lng = r.longitude;
            opt.dataset.zoom = r.default_zoom;
            fields.region.appendChild(opt);
        });
    }

    function syncCountrySelectFromCode(code) {
        if (!code) return;
        const country = countries.find(function (c) { return String(c.iso2).toUpperCase() === String(code).toUpperCase(); });
        if (country) fields.country.value = country.id;
    }

    function selectRegionByCity(city, countryIso) {
        if (!city) return;
        const country = countries.find(function (c) { return String(c.iso2).toUpperCase() === String(countryIso || '').toUpperCase(); });
        if (country) {
            const countryChanged = String(regionCountryLoaded) !== String(country.id);
            fields.country.value = country.id;
            if (countryChanged) fillCountry(country.id);
            const cityKey = String(city).toLowerCase();
            const match = regions.find(function (r) {
                return String(r.country_id) === String(country.id) && (
                    String(r.name).toLowerCase() === cityKey ||
                    String(r.name_local || '').toLowerCase() === cityKey ||
                    String(r.name_cn || '').toLowerCase() === cityKey
                );
            });
            if (match) {
                fields.region.value = match.id;
            }
            if (countryChanged) loadCountryCities(country.id, country.iso2);
        }
    }

    function renderAddress(data) {
        const label = data.display_name || [data.city, data.state, data.country].filter(Boolean).join(', ') || '自定义坐标';
        fields.address.value = label;
        fields.detectedAddress.textContent = label;
        fields.addressDisplay.textContent = label;
        fields.addressStatus.textContent = '信号已锁定 / 地址已解析';
        fields.addressCountry.textContent = (data.country || '').split(';')[0] || data.country_code || '—';
        fields.addressCity.textContent = data.city || data.suburb || '—';
        fields.addressPostcode.textContent = data.postcode || '—';
        fields.chipCountry.textContent = (data.country || '').split(';')[0] || data.country_code || '全球';
        fields.chipCity.textContent = data.city || data.state || '—';
        fields.chipPostcode.textContent = data.postcode || '—';
        if (data.country_code) syncCountrySelectFromCode(data.country_code);
        selectRegionByCity(data.city, data.country_code);
    }

    function reverseGeocode(lat, lng) {
        if (reverseController) reverseController.abort();
        reverseController = new AbortController();
        clearTimeout(reverseTimer);
        fields.addressStatus.textContent = '正在解析区域…';
        setLoading(true);
        reverseTimer = setTimeout(function () {
            fetch('/api/reverse?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng), {
                signal: reverseController.signal,
                headers: { Accept: 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (json) {
                setLoading(false);
                if (json.success) renderAddress(json.data);
                else {
                    fields.addressStatus.textContent = '坐标已锁定';
                    fields.detectedAddress.textContent = '坐标已锁定 · 地址解析暂时不可用';
                    fields.addressDisplay.textContent = 'LAT ' + Number(lat).toFixed(5) + ' · LNG ' + Number(lng).toFixed(5);
                }
            }).catch(function (err) {
                setLoading(false);
                if (err.name === 'AbortError') return;
                fields.addressStatus.textContent = '坐标已锁定';
                fields.detectedAddress.textContent = '坐标已锁定 · 地址解析暂时不可用';
            });
        }, 280);
    }

    function setTarget(lat, lng, options) {
        options = options || {};
        const radius = options.radius || Number(fields.radiusRange.value);
        paintGeometry(lat, lng, radius);
        if (options.fly) {
            map.flyTo([lat, lng], options.zoom || Math.max(map.getZoom(), 10), { duration: 0.85 });
        }
        if (!options.skipReverse) reverseGeocode(Number(lat), Number(lng));
    }

    map.on('click', function (e) {
        const shouldZoom = map.getZoom() <= 5;
        if (shouldZoom) setZoomLabel(11);
        fields.region.value = '';
        setTarget(e.latlng.lat, e.latlng.lng, {
            fly: shouldZoom,
            zoom: shouldZoom ? 11 : map.getZoom()
        });
    });
    marker.on('dragend', function () {
        const pos = marker.getLatLng();
        setTarget(pos.lat, pos.lng, { skipReverse: false });
    });
    map.on('zoomend', function () {
        if (!map._animatingZoom) setZoomLabel(map.getZoom());
        if (fields.layersLabel) fields.layersLabel.textContent = activeLayer === 'dark' ? '底图 / 深色' : '底图 / 卫星';
    });
    map.on('moveend', function () {
        if (fields.mapMode) fields.mapMode.textContent = '中心 ' + map.getCenter().lat.toFixed(3) + ', ' + map.getCenter().lng.toFixed(3);
    });

    // Radius + zoom controls.
    fields.radiusRange.addEventListener('input', function () {
        paintGeometry(Number(fields.lat.value), Number(fields.lng.value), Number(this.value));
    });
    fields.zoomRange.addEventListener('input', function () {
        setZoomLabel(Number(this.value));
        map.setZoom(Number(this.value));
    });
    document.querySelectorAll('[data-radius]').forEach(function (button) {
        button.addEventListener('click', function () {
            paintGeometry(Number(fields.lat.value), Number(fields.lng.value), Number(button.dataset.radius));
        });
    });
    document.querySelectorAll('[data-zoom]').forEach(function (button) {
        button.addEventListener('click', function () {
            const z = Number(button.dataset.zoom);
            setZoomLabel(z);
            map.flyTo([Number(fields.lat.value), Number(fields.lng.value)], z, { duration: 0.7 });
        });
    });
    [fields.latDisplay, fields.lngDisplay].forEach(function (input) {
        input.addEventListener('change', function () {
            let lat = Number(fields.latDisplay.value);
            let lng = Number(fields.lngDisplay.value);
            if (!isFinite(lat) || lat < -90 || lat > 90 || !isFinite(lng) || lng < -180 || lng > 180) {
                fields.latDisplay.value = fields.lat.value;
                fields.lngDisplay.value = fields.lng.value;
                return;
            }
            setTarget(lat, lng, { fly: true, zoom: map.getZoom() });
        });
    });

    function setRegionHint(text) {
        const hint = document.getElementById('region-city-hint');
        if (hint) hint.textContent = text;
    }

    function applyDictionaryCity(opt) {
        const lat = Number(opt.dataset.lat);
        const lng = Number(opt.dataset.lng);
        if (!isFinite(lat) || !isFinite(lng)) return;
        const cityName = opt.dataset.cityName || opt.textContent || '';
        const admin = opt.dataset.cityAdmin || '';
        const countryOpt = fields.country.options[fields.country.selectedIndex];
        const countryName = countryOpt ? countryOpt.textContent : '';
        const label = [cityName, admin, countryName].filter(Boolean).join(', ');
        fields.latDisplay.value = lat.toFixed(6);
        fields.lngDisplay.value = lng.toFixed(6);
        fields.address.value = label;
        fields.detectedAddress.textContent = label;
        fields.addressDisplay.textContent = label;
        fields.addressStatus.textContent = '已从全球城市库选择';
        fields.addressCountry.textContent = countryName;
        fields.addressCity.textContent = cityName;
        fields.addressPostcode.textContent = opt.dataset.cityPopulation ? ('人口 ' + Number(opt.dataset.cityPopulation).toLocaleString('zh-CN')) : '—';
        fields.chipCountry.textContent = countryName;
        fields.chipCity.textContent = cityName;
        fields.chipPostcode.textContent = admin || '—';
        setZoomLabel(12);
        map.flyTo([lat, lng], 12, { duration: 0.85 });
        setTarget(lat, lng, { radius: Number(fields.radiusRange.value), skipReverse: true });
    }

    function loadCountryCities(countryId, countryIso) {
        if (!fields.region) return;
        const token = ++cityOptionsToken;
        document.querySelectorAll('#region_id optgroup[data-geo-cities], #region_id option[data-geo-city]').forEach(function (el) { el.remove(); });
        if (!countryId) {
            setRegionHint('选择国家后自动加载该国城市。');
            return;
        }
        if (!countryIso) {
            const opt = fields.country.options[fields.country.selectedIndex];
            countryIso = opt && opt.dataset ? (opt.dataset.iso || '') : '';
        }
        if (!countryIso) return;
        setRegionHint('正在加载该国城市…');
        fetch('/api/cities?country=' + encodeURIComponent(countryIso) + '&limit=5000', { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (token !== cityOptionsToken) return;
                if (!json.success || !json.meta || json.meta.available === false) {
                    setRegionHint('城市库暂时不可用；仍可使用地图地址搜索。');
                    return;
                }
                const cities = json.data || [];
                const curated = new Set();
                regions.filter(function (r) { return String(r.country_id) === String(countryId); }).forEach(function (r) {
                    [r.name, r.name_local, r.name_cn].forEach(function (name) { if (name) curated.add(String(name).toLowerCase()); });
                });
                const group = document.createElement('optgroup');
                group.label = '全球城市库 · ' + cities.length + ' 个城市';
                group.dataset.geoCities = '1';
                const fragment = document.createDocumentFragment();
                let added = 0;
                cities.forEach(function (city) {
                    const nameZh = city.name_zh || '';
                    if (curated.has(String(city.name).toLowerCase()) || (nameZh && curated.has(String(nameZh).toLowerCase()))) return;
                    const option = document.createElement('option');
                    option.value = 'geo:' + city.id;
                    option.dataset.geoCity = String(city.id);
                    option.dataset.lat = city.latitude;
                    option.dataset.lng = city.longitude;
                    option.dataset.cityName = nameZh || city.name;
                    option.dataset.cityAdmin = city.admin1_name || city.admin1 || '';
                    option.dataset.cityPopulation = city.population || 0;
                    option.textContent = (nameZh || city.name) + (city.admin1_name ? ' · ' + city.admin1_name : '');
                    option.title = [city.name, city.admin1_name, city.population ? ('人口 ' + Number(city.population).toLocaleString('zh-CN')) : ''].filter(Boolean).join(' · ');
                    fragment.appendChild(option);
                    added++;
                });
                group.appendChild(fragment);
                fields.region.appendChild(group);
                setRegionHint('已加载 ' + added + ' 个城市，按人口排序；选择后自动定位。');
            })
            .catch(function () {
                if (token !== cityOptionsToken) return;
                setRegionHint('城市库加载失败；仍可使用地图地址搜索。');
            });
    }

    // Country / region movement.
    fields.country.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        fillCountry(this.value);
        if (!this.value) { setRegionHint('全球城市库已就绪；选择国家后加载该国城市。'); return; }
        const lat = Number(opt.dataset.lat);
        const lng = Number(opt.dataset.lng);
        const z = Number(opt.dataset.zoom || 5);
        if (isFinite(lat) && isFinite(lng)) {
            setZoomLabel(z);
            map.flyTo([lat, lng], z, { duration: 0.9 });
            setTarget(lat, lng, { radius: Number(fields.radiusRange.value), skipReverse: false });
        }
        loadCountryCities(this.value, opt.dataset.iso || '');
    });
    fields.region.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!opt) return;
        if (opt.dataset.geoCity) {
            applyDictionaryCity(opt);
            return;
        }
        if (!this.value) {
            const countryOpt = fields.country.options[fields.country.selectedIndex];
            const lat = Number(countryOpt && countryOpt.dataset.lat);
            const lng = Number(countryOpt && countryOpt.dataset.lng);
            if (isFinite(lat) && isFinite(lng)) {
                setZoomLabel(Number(countryOpt.dataset.zoom || 5));
                map.flyTo([lat, lng], Number(countryOpt.dataset.zoom || 5), { duration: 0.8 });
                setTarget(lat, lng, { radius: Number(fields.radiusRange.value) });
            }
            return;
        }
        const lat = Number(opt.dataset.lat);
        const lng = Number(opt.dataset.lng);
        const z = Number(opt.dataset.zoom || 11);
        if (isFinite(lat) && isFinite(lng)) {
            const countryOpt = fields.country.options[fields.country.selectedIndex];
            const countryName = countryOpt ? countryOpt.textContent : '';
            const cityName = opt.textContent || '';
            const label = [cityName, countryName].filter(Boolean).join(', ');
            fields.latDisplay.value = lat.toFixed(6);
            fields.lngDisplay.value = lng.toFixed(6);
            fields.address.value = label;
            fields.detectedAddress.textContent = label;
            fields.addressDisplay.textContent = label;
            fields.addressStatus.textContent = '已选择城市节点';
            fields.addressCountry.textContent = countryName;
            fields.addressCity.textContent = cityName;
            fields.addressPostcode.textContent = '—';
            fields.chipCountry.textContent = countryName;
            fields.chipCity.textContent = cityName;
            fields.chipPostcode.textContent = '—';
            setZoomLabel(z);
            map.flyTo([lat, lng], z, { duration: 0.9 });
            setTarget(lat, lng, { radius: Number(fields.radiusRange.value), skipReverse: true });
        }
    });

    // Worldwide geocoder search.
    let searchController = null;
    let searchTimer = null;
    function hideResults() { fields.results.classList.add('hidden'); fields.results.innerHTML = ''; }
    function doSearch() {
        const q = fields.search.value.trim();
        if (q.length < 2) { hideResults(); return; }
        if (searchController) searchController.abort();
        searchController = new AbortController();
        fields.results.classList.remove('hidden');
        fields.results.innerHTML = '<div class="px-3 py-3 font-mono text-[12px] uppercase tracking-wider text-slate-600">正在搜索全球地址…</div>';
        fetch('/api/geocode?q=' + encodeURIComponent(q), { signal: searchController.signal, headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.success) {
                    fields.results.innerHTML = '<div class="px-3 py-3 text-[13px] text-rose-300">' + (json.error && json.error.message || '地址搜索失败') + '</div>';
                    return;
                }
                if (!json.data.length) {
                    fields.results.innerHTML = '<div class="px-3 py-3 text-[13px] text-slate-500">没有找到匹配位置</div>';
                    return;
                }
                fields.results.innerHTML = '';
                json.data.forEach(function (item) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'map-result';
                    btn.innerHTML = '<span class="mt-0.5 text-signal/80">' + '<svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>' + '</span><span class="min-w-0 flex-1"><strong>' + escapeHtml(item.name || item.display_name) + '</strong><span>' + escapeHtml(item.display_name) + '</span></span><span class="mt-0.5 shrink-0 font-mono text-[10px] uppercase text-slate-600">' + escapeHtml(item.type) + '</span>';
                    btn.addEventListener('click', function () {
                        hideResults();
                        fields.search.value = item.name || item.display_name;
                        const lat = Number(item.lat);
                        const lng = Number(item.lng);
                        const bbox = item.boundingbox;
                        if (bbox && bbox.length === 4) {
                            map.fitBounds([[bbox[0], bbox[2]], [bbox[1], bbox[3]]], { padding: [30, 30], maxZoom: 14 });
                            setZoomLabel(map.getZoom());
                        } else {
                            map.flyTo([lat, lng], 13, { duration: 0.8 });
                            setZoomLabel(13);
                        }
                        setTarget(lat, lng, { radius: Number(fields.radiusRange.value) });
                    });
                    fields.results.appendChild(btn);
                });
            })
            .catch(function (err) {
                if (err.name !== 'AbortError') fields.results.innerHTML = '<div class="px-3 py-3 text-[13px] text-rose-300">地址服务暂时不可用</div>';
            });
    }
    fields.search.addEventListener('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(doSearch, 480); });
    fields.search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); clearTimeout(searchTimer); doSearch(); }
        if (e.key === 'Escape') hideResults();
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.map-search')) hideResults();
    });

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Keyword picker.
    if (fields.keywordPicker) {
        fields.keywordPicker.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!this.value) return;
            fields.keywordText.value = opt.dataset.keyword || '';
            fields.keywordId.value = this.value;
            if (autoTaskName && fields.taskName) {
                const now = new Date();
                const today = (now.getMonth() + 1) + '月' + now.getDate() + '日';
                fields.taskName.value = (opt.dataset.keyword || '').slice(0, 60) + ' — ' + today;
            }
            if (opt.dataset.industry) fields.industry.value = opt.dataset.industry;
            if (opt.dataset.lang) { fields.language.value = opt.dataset.lang; updatePreview(); }

            const pickedCountryId = opt.dataset.country || '';
            let countryId = pickedCountryId;
            if (!countryId) {
                // Global keyword: keep the country the operator already chose.
                countryId = fields.country.value;
            } else {
                fields.country.value = countryId;
                fillCountry(countryId);
            }
            const country = countries.find(function (c) { return String(c.id) === String(countryId); });
            if (!pickedCountryId) {
                // Do not reset the selected city or map target for a global keyword.
                return;
            }

            // Pick the most specific city node mentioned in the keyword.
            const text = (fields.keywordText.value || '').toLowerCase();
            let region = null;
            let best = 0;
            regions.filter(function (r) { return String(r.country_id) === String(countryId); }).forEach(function (r) {
                [r.name, r.name_local, r.name_cn].forEach(function (label) {
                    if (!label) return;
                    const lower = String(label).toLowerCase();
                    if (text.includes(lower) && lower.length > best) {
                        region = r;
                        best = lower.length;
                    }
                });
            });

            let lat;
            let lng;
            let zoom;
            if (region) {
                fields.region.value = region.id;
                lat = Number(region.latitude);
                lng = Number(region.longitude);
                zoom = Number(region.default_zoom || 12);
            } else {
                fields.region.value = '';
                if (!country || country.center_lat == null) return;
                lat = Number(country.center_lat);
                lng = Number(country.center_lng);
                zoom = Number(country.default_zoom || 5);
            }
            setZoomLabel(zoom);
            map.flyTo([lat, lng], zoom, { duration: 0.9 });
            setTarget(lat, lng, { radius: Number(fields.radiusRange.value), skipReverse: true });
            if (region) {
                fields.addressStatus.textContent = '按关键词匹配城市节点';
                fields.detectedAddress.textContent = (region.name_cn || region.name) + ' · ' + (country ? country_name_for_js(country) : '');
            }
            loadCountryCities(countryId, country ? country.iso2 : '');
        });
    }
    if (fields.keywordText) fields.keywordText.addEventListener('input', function () { if (fields.keywordId) fields.keywordId.value = ''; });
    let autoTaskName = /^(B2B 采购商|伦敦水管工)/.test((fields.taskName && fields.taskName.value) || '');
    if (fields.taskName) fields.taskName.addEventListener('input', function () { autoTaskName = false; });
    if (fields.language) fields.language.addEventListener('change', updatePreview);
    if (fields.maxTime) fields.maxTime.addEventListener('input', updatePreview);

    // Layer + fullscreen + geolocate + reset.
    if (fields.layerToggle) {
        fields.layerToggle.addEventListener('click', function () {
            if (activeLayer === 'dark') {
                map.removeLayer(darkLayer);
                map.removeLayer(darkLabels);
                satelliteLayer.addTo(map);
                activeLayer = 'satellite';
                fields.layerToggle.querySelector('span').textContent = '深色';
                fields.layersLabel.textContent = '底图 / 卫星';
            } else {
                map.removeLayer(satelliteLayer);
                darkLayer.addTo(map);
                darkLabels.addTo(map);
                activeLayer = 'dark';
                fields.layerToggle.querySelector('span').textContent = '卫星';
                fields.layersLabel.textContent = '底图 / 深色';
            }
        });
    }
    document.getElementById('map-expand').addEventListener('click', function () {
        const shell = mapEl.closest('.map-shell');
        if (!document.fullscreenElement) {
            if (shell.requestFullscreen) shell.requestFullscreen();
        } else {
            document.exitFullscreen();
        }
    });
    document.addEventListener('fullscreenchange', function () { setTimeout(function () { map.invalidateSize(); }, 200); });
    document.getElementById('locate-me').addEventListener('click', function () {
        if (!navigator.geolocation) return;
        this.disabled = true;
        const btn = this;
        navigator.geolocation.getCurrentPosition(function (pos) {
            map.flyTo([pos.coords.latitude, pos.coords.longitude], 14, { duration: 0.9 });
            setZoomLabel(14);
            setTarget(pos.coords.latitude, pos.coords.longitude, { radius: Number(fields.radiusRange.value) });
            btn.disabled = false;
        }, function () { btn.disabled = false; }, { timeout: 8000, maximumAge: 60000 });
    });
    document.getElementById('reset-view').addEventListener('click', function () {
        map.flyTo([<?= (float) $mapConfig['default_lat'] ?>, <?= (float) $mapConfig['default_lng'] ?>], <?= (int) $mapConfig['default_zoom'] ?>, { duration: 0.8 });
        setZoomLabel(<?= (int) $mapConfig['default_zoom'] ?>);
    });
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            const text = fields.address.value || fields.detectedAddress.textContent;
            if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () {
                button.textContent = '已复制';
                setTimeout(function () { button.textContent = '复制地址'; }, 1500);
            });
        });
    });

    // City-wide coverage: split the coverage radius into overlapping cells.
    function countCoverageCells(radiusMeters, cellMeters) {
        const spacing = cellMeters * 1.4;
        if (spacing <= 0) return 0;
        const steps = Math.ceil(radiusMeters / spacing);
        let count = 0;
        for (let x = -steps; x <= steps; x++) {
            for (let y = -steps; y <= steps; y++) {
                if (Math.hypot(x * spacing, y * spacing) <= radiusMeters) count++;
            }
        }
        return count;
    }
    function updateCoverageUI() {
        if (!fields.coverageGrid) return;
        const enabled = fields.coverageGrid.checked;
        if (fields.gridSettings) fields.gridSettings.classList.toggle('hidden', !enabled);
        document.querySelectorAll('[data-label-single]').forEach(function (el) {
            el.textContent = enabled ? (el.dataset.labelCoverage || el.dataset.labelSingle) : el.dataset.labelSingle;
        });
        if (!enabled) return;
        const radius = Number(fields.radiusRange.value);
        const cellKm = Math.max(1, Math.min(10, Number(fields.gridCellKm ? fields.gridCellKm.value : 5) || 5));
        const count = countCoverageCells(radius, cellKm * 1000);
        if (fields.gridCount) fields.gridCount.textContent = count > 200 ? count + '（超过上限）' : String(count);
    }
    let boundaryRequest = 0;
    function fitCoverageCell(radiusMeters) {
        if (!fields.gridCellKm) return;
        let cell = Math.max(1, Number(fields.gridCellKm.value) || 5);
        fields.gridCellKm.value = String(cell);
    }
    function loadCoverageBoundary() {
        if (!fields.coverageGrid || !fields.coverageGrid.checked) return;
        const token = ++boundaryRequest;
        const regionId = fields.region.value;
        if (!regionId) {
            if (fields.gridBoundaryInfo) fields.gridBoundaryInfo.textContent = '未选择城市节点，当前使用手动扫描半径；选择城市后可读取行政区面积。';
            updateCoverageUI();
            return;
        }
        if (fields.gridBoundaryInfo) fields.gridBoundaryInfo.textContent = '正在读取城市行政区边界…';
        fetch('/api/region-boundary?region_id=' + encodeURIComponent(regionId), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (token !== boundaryRequest) return;
                const data = json && json.data ? json.data : null;
                if (!json.success || !data || !data.available) {
                    if (fields.gridBoundaryInfo) fields.gridBoundaryInfo.textContent = '未找到该节点的行政区边界，使用手动扫描半径。';
                    updateCoverageUI();
                    return;
                }
                const boundaryRadius = Math.min(100, Math.max(1, Number(data.radius_km) || 0));
                const radiusMeters = Math.round(boundaryRadius * 1000);
                paintGeometry(Number(fields.lat.value), Number(fields.lng.value), radiusMeters);
                fitCoverageCell(radiusMeters);
                if (fields.gridBoundaryInfo) {
                    fields.gridBoundaryInfo.textContent = '行政区：' + data.region_name + ' · 面积 ' + Number(data.area_km2).toLocaleString('zh-CN') + ' km² · 边界覆盖半径 ' + boundaryRadius.toFixed(1) + ' km（已自动设置）';
                }
                updateCoverageUI();
            })
            .catch(function () {
                if (token !== boundaryRequest) return;
                if (fields.gridBoundaryInfo) fields.gridBoundaryInfo.textContent = '行政区边界服务暂时不可用，使用手动扫描半径。';
                updateCoverageUI();
            });
    }
    if (fields.coverageGrid) {
        fields.coverageGrid.addEventListener('change', function () {
            if (this.checked) {
                // Coverage radius may be larger than a single scan because it
                // follows the administrative boundary.
                fields.radiusRange.max = '100000';
                if (fields.radiusTitle) fields.radiusTitle.textContent = '城市覆盖半径';
                if (fields.radiusPresets) fields.radiusPresets.classList.add('hidden');
                if (Number(fields.radiusRange.value) <= 10000) {
                    paintGeometry(Number(fields.lat.value), Number(fields.lng.value), 10000);
                }
                loadCoverageBoundary();
            } else {
                // A normal single task is capped at 10 km. Read the value
                // before lowering `max`, otherwise the range input clamps
                // silently and the hidden radius would stay stale.
                const previousRadius = Number(fields.radiusRange.value);
                const singleRadius = Math.min(previousRadius || 10000, 10000);
                fields.radiusRange.max = '10000';
                if (fields.radiusTitle) fields.radiusTitle.textContent = '扫描半径';
                if (fields.radiusPresets) fields.radiusPresets.classList.remove('hidden');
                paintGeometry(Number(fields.lat.value), Number(fields.lng.value), singleRadius);
                updateCoverageUI();
            }
        });
        if (fields.gridCellKm) fields.gridCellKm.addEventListener('input', updateCoverageUI);
        fields.radiusRange.addEventListener('input', updateCoverageUI);
        document.querySelectorAll('[data-radius]').forEach(function (button) {
            button.addEventListener('click', updateCoverageUI);
        });
        if (fields.region) fields.region.addEventListener('change', loadCoverageBoundary);
        document.querySelectorAll('[data-grid-cell]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!fields.gridCellKm) return;
                fields.gridCellKm.value = button.dataset.gridCell;
                updateCoverageUI();
            });
        });
        updateCoverageUI();
    }

    // Always submit the geometry that is actually visible on the map.
    form.addEventListener('submit', function () {
        const pos = marker.getLatLng();
        fields.lat.value = fmtCoord(pos.lat);
        fields.lng.value = fmtCoord(pos.lng);
        fields.zoom.value = desiredZoom;
        fields.radius.value = Number(fields.radiusRange.value);
    });

    function country_name_for_js(country) {
        const option = Array.from(fields.country.options).find(function (o) { return String(o.value) === String(country.id); });
        return option ? option.textContent : (country.name_en || '');
    }

    // Initial visual pass (without reverse lookup on first render).
    paintGeometry(initial.lat, initial.lng, initial.radius);
    setZoomLabel(initial.zoom);
    updatePreview();
    if (initial.countryId) {
        const countryOpt = fields.country.options[fields.country.selectedIndex];
        loadCountryCities(initial.countryId, countryOpt && countryOpt.dataset ? (countryOpt.dataset.iso || '') : '');
    }
    if (initial.lat !== 20 || initial.lng !== 10) {
        // A country node was preselected: resolve address after the map settles.
        setTimeout(function () { reverseGeocode(initial.lat, initial.lng); }, 300);
    }
});
</script>
