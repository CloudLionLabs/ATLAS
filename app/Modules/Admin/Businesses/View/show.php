<?php
$score = (int) $business['completeness_score'];
$emailContacts = array_values(array_filter($contacts, static fn ($c) => $c['contact_type'] === 'email'));
$phoneContacts = array_values(array_filter($contacts, static fn ($c) => $c['contact_type'] === 'phone'));
$rawArr = $rawRecord ? json_decode($rawRecord['raw_json'], true) : null;
?>
<section class="reveal mb-6">
    <div class="mb-4 flex items-center gap-2 font-mono text-[12px] uppercase tracking-[0.14em] text-slate-600">
        <a href="/businesses" class="hover:text-slate-300">商家资料库</a><span class="text-slate-700">/</span><span class="text-slate-500">#<?= (int) $business['id'] ?></span>
    </div>
    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
        <div class="flex min-w-0 items-start gap-4">
            <div class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-signal/20 bg-signal/[0.05] font-mono text-sm text-signal"><?= e(initials($business['business_name'])) ?></div>
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="status-badge border-signal/20 bg-signal/[0.05] text-signal"><span class="h-1.5 w-1.5 rounded-full bg-signal"></span> 标准化记录</span>
                    <?php if ($business['google_data_id']): ?><span class="tag">Data ID 已保存</span><?php endif; ?>
                    <?php if ($emailContacts): ?><span class="tag tag-signal"><?= count($emailContacts) ?> 个邮箱</span><?php endif; ?>
                </div>
                <h1 class="page-title truncate"><?= e($business['business_name']) ?></h1>
                <p class="mt-2 flex flex-wrap items-center gap-2 text-[15px] text-slate-500">
                    <span class="flex items-center gap-1.5"><?= flag_emoji($business['iso2'] ?? '') ?> <?= e($business['city'] ? place_name_cn($business['city']) : '—') ?><?= $business['region_name'] ? ', ' . e(place_name_cn($business['region_name'])) : '' ?></span>
                    <span class="text-slate-700">·</span>
                    <span><?= e(industry_name_display($business['industry_id'] ? (int) $business['industry_id'] : null, $business['industry_name'] ?? null)) ?></span>
                    <span class="text-slate-700">·</span>
                    <span class="font-mono text-[13px]"><?= e(country_name_cn($business['iso2'] ?? '', $business['country_name'] ?? '—')) ?></span>
                </p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($business['website']): ?>
                <form method="post" action="/businesses/<?= (int) $business['id'] ?>/enrich-email" data-confirm="从官网公开页面查找邮箱？系统只访问公开页面并遵守 robots.txt。">
                    <?= csrf_field() ?>
                    <button class="btn btn-secondary" title="<?= $emailFinderEnabled ? '检查官网首页和联系页面' : '请先在系统设置中开启邮箱补全' ?>" <?= $emailFinderEnabled ? '' : 'disabled' ?>>
                        <?= icon('mail', 'h-4 w-4') ?> <?= $emailContacts ? '重新检查邮箱' : '从官网补全邮箱' ?>
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($business['website']): ?><a href="<?= e($business['website']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary"><?= icon('external', 'h-4 w-4') ?> 访问官网</a><?php endif; ?>
            <?php if ($business['latitude'] !== null && $business['longitude'] !== null): ?>
                <a href="https://www.google.com/maps/search/?api=1&query=<?= e($business['latitude']) ?>,<?= e($business['longitude']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary"><?= icon('map-pin', 'h-4 w-4') ?> Google Maps</a>
            <?php endif; ?>
            <a href="/businesses?country=<?= (int) $business['country_id'] ?>" class="btn btn-primary"><?= icon('globe', 'h-4 w-4') ?> 同市场商家</a>
        </div>
    </div>
</section>

<div class="reveal reveal-delay-1 mb-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">完整度</div>
        <div class="mt-2 font-display text-2xl font-semibold <?= $score >= 70 ? 'text-signal' : ($score >= 40 ? 'text-hazard' : 'text-rose-300') ?>"><?= $score ?><span class="text-sm text-slate-600">%</span></div>
        <div class="progress mt-2"><span style="width:<?= $score ?>%; background:<?= $score >= 70 ? '#20e3bd' : ($score >= 40 ? '#ffb454' : '#ff6b81') ?>"></span></div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">评分</div>
        <div class="mt-2 flex items-baseline gap-1 font-display text-2xl font-semibold text-amber-300"><?= $business['rating'] !== null ? number_format((float) $business['rating'], 1) : '—' ?><span class="text-sm text-slate-600">/5</span></div>
        <div class="mt-1 font-mono text-[11px] text-slate-700"><?= fmt_number($business['review_count']) ?> 条评论</div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">邮箱</div>
        <div class="mt-2 font-display text-2xl font-semibold text-plasma"><?= count($emailContacts) ?></div>
        <div class="mt-1 font-mono text-[11px] text-slate-700"><?= e(email_status_meta($business['email_status'])['label']) ?></div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">来源</div>
        <div class="mt-2 font-display text-2xl font-semibold text-electric"><?= count($sources) ?></div>
        <div class="mt-1 font-mono text-[11px] text-slate-700">任务关联</div>
    </div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4">
        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">最近更新</div>
        <div class="mt-2 font-display text-lg font-semibold text-white"><?= e(fmt_datetime($business['updated_at'])) ?></div>
        <div class="mt-1 font-mono text-[11px] text-slate-700"><?= e(fmt_date($business['created_at'])) ?> 入库</div>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <div class="space-y-4 xl:col-span-7">
        <section class="panel reveal reveal-delay-2">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">基本资料</div><h2 class="panel-title"><?= icon('building', 'h-4 w-4 text-signal') ?> 基本资料</h2></div>
                <button type="button" onclick="document.getElementById('edit-profile').classList.toggle('hidden')" class="btn btn-secondary btn-sm"><?= icon('settings', 'h-3.5 w-3.5') ?> 编辑</button>
            </div>
            <div class="grid grid-cols-1 gap-px bg-white/[0.04] sm:grid-cols-2">
                <div class="bg-ink-900/90 px-5 py-4"><div class="font-mono text-[11px] uppercase tracking-wider text-slate-600">地址</div><div class="mt-1.5 text-[14px] leading-5 text-slate-300"><?= e($business['address'] ?: '—') ?></div></div>
                <div class="bg-ink-900/90 px-5 py-4"><div class="font-mono text-[11px] uppercase tracking-wider text-slate-600">邮编 / 城市</div><div class="mt-1.5 text-[14px] text-slate-300"><?= e(trim(($business['postal_code'] ?: '—') . ' · ' . ($business['city'] ?: '—'))) ?></div></div>
                <div class="bg-ink-900/90 px-5 py-4"><div class="font-mono text-[11px] uppercase tracking-wider text-slate-600">电话</div><div class="mt-1.5 flex items-center gap-2 font-mono text-[14px] text-slate-300"><?= e($business['phone'] ?: '—') ?><?php if ($business['phone']): ?><button type="button" class="text-slate-600 hover:text-signal" data-copy="<?= e($business['phone']) ?>"><?= icon('copy', 'h-3 w-3') ?></button><?php endif; ?></div></div>
                <div class="bg-ink-900/90 px-5 py-4"><div class="font-mono text-[11px] uppercase tracking-wider text-slate-600">官网</div><div class="mt-1.5 truncate text-[14px] text-slate-300"><?php if ($business['website']): ?><a href="<?= e($business['website']) ?>" target="_blank" rel="noopener noreferrer" class="text-electric hover:underline"><?= e($business['website_domain'] ?: $business['website']) ?></a><?php else: ?>—<?php endif; ?></div></div>
                <div class="bg-ink-900/90 px-5 py-4"><div class="font-mono text-[11px] uppercase tracking-wider text-slate-600">Google Data ID</div><div class="mt-1.5 break-all font-mono text-[12px] text-slate-500"><?= e($business['google_data_id'] ?: '—') ?></div></div>
                <div class="bg-ink-900/90 px-5 py-4"><div class="font-mono text-[11px] uppercase tracking-wider text-slate-600">Place ID / CID / Plus Code</div><div class="mt-1.5 break-all font-mono text-[12px] text-slate-500"><?= e($business['google_place_id'] ?: ($business['google_cid'] ?: ($business['plus_code'] ?: '—'))) ?></div></div>
                <div class="bg-ink-900/90 px-5 py-4"><div class="font-mono text-[11px] uppercase tracking-wider text-slate-600">坐标</div><div class="mt-1.5 font-mono text-[13px] text-slate-300"><?= $business['latitude'] !== null ? e(number_format((float) $business['latitude'], 6) . ', ' . number_format((float) $business['longitude'], 6)) : '—' ?></div></div>
                <div class="bg-ink-900/90 px-5 py-4"><div class="font-mono text-[11px] uppercase tracking-wider text-slate-600">时区 / 状态</div><div class="mt-1.5 font-mono text-[13px] text-slate-300"><?= e($business['timezone'] ?: '—') ?> · <?= e($business['business_status'] ?: '—') ?></div></div>
            </div>
            <div id="edit-profile" class="hidden border-t border-white/[0.06] p-5">
                <form method="post" action="/businesses/<?= (int) $business['id'] ?>/update" class="space-y-3">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div><label class="label">商家名称</label><input name="business_name" value="<?= e($business['business_name']) ?>" class="input" required></div>
                        <div><label class="label">行业</label><select name="industry_id" class="select"><option value="">未分类</option><?php foreach ($industries as $i): ?><option value="<?= (int) $i['id'] ?>" <?= (int) $business['industry_id'] === (int) $i['id'] ? 'selected' : '' ?>><?= e(industry_name_display((int) $i['id'], $i['name'])) ?></option><?php endforeach; ?></select></div>
                        <div><label class="label">国家</label><select name="country_id" class="select"><option value="">—</option><?php foreach ($countries as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $business['country_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(country_name_cn($c['iso2'], $c['name_en'])) ?></option><?php endforeach; ?></select></div>
                        <div><label class="label">城市节点</label><select name="region_id" class="select"><option value="">—</option><?php foreach ($regions as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $business['region_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
                        <div><label class="label">城市</label><input name="city" value="<?= e($business['city'] ?? '') ?>" class="input"></div>
                        <div><label class="label">州/省</label><input name="state_region" value="<?= e($business['state_region'] ?? '') ?>" class="input"></div>
                        <div><label class="label">邮编</label><input name="postal_code" value="<?= e($business['postal_code'] ?? '') ?>" class="input"></div>
                        <div><label class="label">电话</label><input name="phone" value="<?= e($business['phone'] ?? '') ?>" class="input"></div>
                        <div class="sm:col-span-2"><label class="label">地址</label><input name="address" value="<?= e($business['address'] ?? '') ?>" class="input"></div>
                        <div class="sm:col-span-2"><label class="label">官网</label><input name="website" value="<?= e($business['website'] ?? '') ?>" class="input"></div>
                        <div class="sm:col-span-2"><label class="label">商家介绍</label><textarea name="description" class="textarea !min-h-[96px]" placeholder="商家简介、主营业务、服务范围…"><?= e($business['description'] ?? '') ?></textarea></div>
                        <div><label class="label">纬度</label><input name="latitude" value="<?= e($business['latitude']) ?>" class="input font-mono"></div>
                        <div><label class="label">经度</label><input name="longitude" value="<?= e($business['longitude']) ?>" class="input font-mono"></div>
                        <div><label class="label">邮箱状态</label><select name="email_status" class="select"><?php foreach (['not_checked' => '未检查', 'found' => '已发现', 'none_found' => '未发现', 'failed' => '失败', 'blocked' => '已阻止'] as $k => $v): ?><option value="<?= $k ?>" <?= $business['email_status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="flex justify-end gap-2"><button type="button" onclick="document.getElementById('edit-profile').classList.add('hidden')" class="btn btn-ghost btn-sm">取消</button><button class="btn btn-primary btn-sm"><?= icon('check', 'h-3.5 w-3.5') ?> 保存修改</button></div>
                </form>
            </div>
        </section>

        <section class="panel reveal reveal-delay-3">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">商家介绍</div><h2 class="panel-title"><?= icon('building', 'h-4 w-4 text-signal') ?> 商家简介</h2></div>
                <span class="tag">来自采集数据</span>
            </div>
            <div class="px-5 py-4">
                <?php if (!empty($business['description'])): ?>
                    <div class="whitespace-pre-line text-[14px] leading-7 text-slate-300"><?= nl2br(e($business['description'])) ?></div>
                <?php else: ?>
                    <div class="rounded-xl border border-dashed border-white/[0.08] bg-white/[0.015] px-4 py-5 text-[13px] leading-6 text-slate-600">
                        当前采集数据没有返回商家介绍。系统会读取 CSV 中的「descriptions / about」字段；补采后会在这里显示。
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel reveal reveal-delay-3">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">联系层</div><h2 class="panel-title"><?= icon('mail', 'h-4 w-4 text-plasma') ?> 联系方式与来源</h2></div>
                <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= count($contacts) ?> 个联系方式</span>
            </div>
            <?php if (!$contacts): ?>
                <div class="px-5 py-10 text-center text-[14px] text-slate-600">尚未发现公开联系方式</div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-px bg-white/[0.04] sm:grid-cols-2">
                    <?php foreach ($contacts as $contact): ?>
                        <div class="bg-ink-900/90 px-5 py-4">
                            <div class="flex items-center gap-2">
                                <span class="grid h-7 w-7 place-items-center rounded-lg border border-white/[0.07] bg-white/[0.03] text-slate-500"><?= icon($contact['contact_type'] === 'email' ? 'mail' : 'phone', 'h-3.5 w-3.5') ?></span>
                                <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= $contact['contact_type'] === 'email' ? '邮箱' : ($contact['contact_type'] === 'phone' ? '电话' : '联系表单') ?></span>
                                <span class="ml-auto status-badge border-white/[0.08] bg-white/[0.03] text-slate-500"><?= e($contact['verification_status'] === 'unverified' ? '未验证' : ($contact['verification_status'] === 'valid' ? '有效' : ($contact['verification_status'] === 'invalid' ? '无效' : '未知'))) ?></span>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="break-all font-mono text-[14px] text-slate-200"><?= e($contact['contact_value']) ?></span>
                                <button type="button" class="text-slate-600 hover:text-signal" data-copy="<?= e($contact['contact_value']) ?>"><?= icon('copy', 'h-3 w-3') ?></button>
                            </div>
                            <div class="mt-2 flex items-center gap-2 font-mono text-[11px] uppercase tracking-wider text-slate-700">
                                <span><?= e($contact['source_type'] === 'scraper' ? '采集引擎' : ($contact['source_type'] === 'manual' ? '手动' : '官网')) ?></span>
                                <?php if ($contact['source_url']): ?><span class="text-slate-800">/</span><a href="<?= e($contact['source_url']) ?>" target="_blank" rel="noopener noreferrer" class="max-w-[180px] truncate text-signal/60 hover:text-signal"><?= e(parse_url($contact['source_url'], PHP_URL_HOST) ?: $contact['source_url']) ?></a><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="border-t border-white/[0.06] p-4">
                <p class="mb-3 text-[11px] leading-5 text-slate-600">官网补全只读取公开首页与联系/关于页面，遵守 robots.txt，不猜测邮箱，也不做 SMTP 探测。发现的邮箱均标记为“未验证”。</p>
                <form method="post" action="/businesses/<?= (int) $business['id'] ?>/email-status" class="flex items-center gap-2">
                    <?= csrf_field() ?>
                    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">邮箱状态</span>
                    <select name="email_status" class="select !w-auto !py-1.5 !text-[13px]">
                        <?php foreach (['not_checked' => '未检查', 'found' => '已发现', 'none_found' => '未发现', 'failed' => '失败', 'blocked' => '已阻止'] as $k => $v): ?><option value="<?= $k ?>" <?= $business['email_status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn btn-secondary btn-sm">更新</button>
                </form>
            </div>
        </section>

        <section class="panel reveal reveal-delay-4">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">数据溯源</div><h2 class="panel-title"><?= icon('layers', 'h-4 w-4 text-electric') ?> 采集历史</h2></div>
                <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= count($sources) ?> 来源</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>任务</th><th>关键词</th><th>原始行</th><th>首次发现</th><th>最近发现</th></tr></thead>
                    <tbody>
                    <?php foreach ($sources as $source): ?>
                        <tr>
                            <td><a href="/jobs/<?= e($source['job_id']) ?>" class="block max-w-[200px] truncate text-[14px] text-slate-300 hover:text-signal"><?= e($source['task_name']) ?></a></td>
                            <td><span class="block max-w-[170px] truncate text-[13px] text-slate-500"><?= e($source['keyword_text']) ?></span></td>
                            <td><span class="font-mono text-[12px] text-slate-500">#<?= (int) $source['row_no'] ?></span></td>
                            <td><span class="font-mono text-[12px] text-slate-600"><?= e(fmt_datetime($source['first_seen_at'])) ?></span></td>
                            <td><span class="font-mono text-[12px] text-slate-600"><?= e(fmt_datetime($source['last_seen_at'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="space-y-4 xl:col-span-5">
        <section class="panel overflow-hidden reveal reveal-delay-2">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">地理位置</div><h2 class="panel-title"><?= icon('map-pin', 'h-4 w-4 text-signal') ?> 位置</h2></div>
                <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= $business['latitude'] !== null ? '已定位' : 'NO 坐标' ?></span>
            </div>
            <?php if ($business['latitude'] !== null && $business['longitude'] !== null): ?>
                <div class="relative m-3 overflow-hidden rounded-xl border border-white/[0.06]">
                    <div id="business-map" class="map-canvas h-[280px]"></div>
                    <div class="map-overlay"></div><div class="map-scan"></div>
                    <div class="map-hud left-3 top-3 !px-2 !py-1"><span class="text-signal">●</span> 地理位置ED</div>
                </div>
            <?php else: ?>
                <div class="px-5 py-12 text-center text-[13px] text-slate-600">没有坐标数据</div>
            <?php endif; ?>
            <div class="border-t border-white/[0.06] px-5 py-3 text-[13px] leading-5 text-slate-500"><?= e($business['address'] ?: '地址待补全') ?></div>
        </section>

        <section class="panel reveal reveal-delay-3">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">重复检测</div><h2 class="panel-title"><?= icon('shield', 'h-4 w-4 text-hazard') ?> 疑似重复</h2></div>
                <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= count($candidates) ?> 个候选</span>
            </div>
            <?php if (!$candidates): ?>
                <div class="px-5 py-8 text-center text-[13px] text-slate-600">未发现电话或域名相同的候选记录</div>
            <?php else: ?>
                <div class="divide-y divide-white/[0.05]">
                    <?php foreach ($candidates as $candidate): ?>
                        <a href="/businesses/<?= (int) $candidate['id'] ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-white/[0.02]">
                            <span class="grid h-7 w-7 place-items-center rounded-lg border border-white/[0.07] bg-white/[0.03] font-mono text-[11px] text-slate-500"><?= e(initials($candidate['business_name'])) ?></span>
                            <span class="min-w-0 flex-1"><span class="block truncate text-[13px] text-slate-300"><?= e($candidate['business_name']) ?></span><span class="mt-0.5 block truncate font-mono text-[11px] uppercase text-slate-600"><?= e($candidate['phone'] ?: $candidate['website_domain'] ?: '匹配') ?></span></span>
                            <span class="font-mono text-[12px] text-slate-600"><?= (int) $candidate['completeness_score'] ?>%</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="border-t border-white/[0.06] px-5 py-3 text-[12px] leading-4 text-slate-600">模糊重复不会自动合并。强匹配仅基于 place_id、data_id 或可信 CID。</div>
        </section>

        <?php if ($rawArr): ?>
            <section class="panel reveal reveal-delay-4">
                <details>
                    <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4">
                        <span><span class="eyebrow mb-1 block">原始数据</span><span class="panel-title text-[15px]"><?= icon('database', 'h-4 w-4 text-plasma') ?> 原始采集行</span></span>
                        <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">展开 ↓</span>
                    </summary>
                    <pre class="max-h-[420px] overflow-auto border-t border-white/[0.06] bg-ink-950/60 p-4 text-[12px] leading-5 text-slate-500"><?= e(json_encode($rawArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '') ?></pre>
                </details>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php if ($business['latitude'] !== null && $business['longitude'] !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof L === 'undefined') return;
    const map = L.map('business-map', { zoomControl: true, scrollWheelZoom: false }).setView([<?= (float) $business['latitude'] ?>, <?= (float) $business['longitude'] ?>], 14);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, maxNativeZoom: 16, attribution: '&copy; Esri, HERE, Garmin, OpenStreetMap contributors' }).addTo(map);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Reference/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, maxNativeZoom: 16 }).addTo(map);
    L.marker([<?= (float) $business['latitude'] ?>, <?= (float) $business['longitude'] ?>], {
        icon: L.divIcon({ className: '', html: '<div class="atlas-marker"><span class="ring"></span><span class="ring"></span><span class="crosshair-x"></span><span class="crosshair-y"></span><span class="core"></span></div>', iconSize: [28, 28], iconAnchor: [14, 14] })
    }).addTo(map);
});
</script>
<?php endif; ?>
