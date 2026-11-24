<?php
$renderTree = function (array $nodes, int $depth = 0) use (&$renderTree): void {
    foreach ($nodes as $node) {
        $indent = $depth * 18;
        ?>
        <div class="group flex items-center gap-2 border-b border-white/[0.04] px-4 py-2.5 transition hover:bg-white/[0.02]" style="padding-left: <?= 16 + $indent ?>px">
            <span class="h-1 w-1 rounded-full <?= (int) $node['is_active'] ? 'bg-signal' : 'bg-slate-600' ?>"></span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-[14px] font-medium <?= (int) $node['is_active'] ? 'text-slate-200' : 'text-slate-500' ?>"><?= e(industry_name_display((int) $node['id'], $node['name'])) ?></span>
                <span class="mt-0.5 block font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= $depth > 0 ? '子级分类' : '根级分类' ?></span>
            </span>
            <span class="hidden items-center gap-3 font-mono text-[11px] uppercase tracking-wider text-slate-600 sm:flex">
                <span><?= fmt_number($node['business_count']) ?> 商家</span>
                <span><?= fmt_number($node['keyword_count']) ?> 关键词</span>
            </span>
            <a href="/industries?edit=<?= (int) $node['id'] ?>" class="grid h-6 w-6 place-items-center rounded text-slate-700 hover:bg-white/[0.05] hover:text-signal"><?= icon('settings', 'h-3 w-3') ?></a>
        </div>
        <?php
        if (!empty($node['children'])) $renderTree($node['children'], $depth + 1);
    }
};
?>
<section class="reveal mb-7">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <div class="eyebrow-live mb-3"><span class="terminal-dot"></span> 分类体系 / 行业层级</div>
            <h1 class="page-title">B2B 外贸<span class="text-signal">行业</span></h1>
            <p class="page-subtitle">面向外贸 B2B 的行业树，覆盖工业品、消费品、建材、汽配、医疗、新能源与跨境服务。旧本地服务分类已停用，历史商家记录保留。</p>
        </div>
        <a href="/keywords" class="btn btn-secondary"><?= icon('key') ?> 关键词库</a>
    </div>
</section>

<div class="reveal reveal-delay-1 mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">行业节点</div><div class="mt-2 font-display text-2xl font-semibold text-white"><?= fmt_number(count($industries)) ?></div></div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">关联商家</div><div class="mt-2 font-display text-2xl font-semibold text-signal"><?= fmt_number($totalBusinesses) ?></div></div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">关键词</div><div class="mt-2 font-display text-2xl font-semibold text-plasma"><?= fmt_number($totalKeywords) ?></div></div>
    <div class="rounded-xl border border-white/[0.06] bg-white/[0.02] px-4 py-3"><div class="font-mono text-[11px] uppercase tracking-[0.16em] text-slate-600">翻译</div><div class="mt-2 font-display text-2xl font-semibold text-electric"><?= fmt_number(count($translations)) ?></div></div>
</div>

<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
    <section class="panel reveal reveal-delay-2 xl:col-span-7">
        <div class="panel-head">
            <div><div class="eyebrow mb-1">行业树</div><h2 class="panel-title"><?= icon('layers', 'h-4 w-4 text-signal') ?> 分类层级</h2></div>
            <form method="get" action="/industries" class="flex gap-2">
                <div class="relative">
                    <?= icon('search', 'pointer-events-none absolute left-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-slate-600') ?>
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="搜索行业…" class="input !w-[150px] !py-1.5 !pl-8 !text-[13px]">
                </div>
                <button class="btn btn-secondary btn-sm"><?= icon('filter', 'h-3 w-3') ?></button>
            </form>
        </div>
        <div class="max-h-[620px] overflow-y-auto py-1">
            <?php if (!$tree): ?>
                <div class="empty py-12"><div class="empty-icon"><?= icon('layers', 'h-6 w-6') ?></div><div class="text-[15px] text-slate-400">没有匹配的行业节点</div></div>
            <?php else: ?>
                <?php $renderTree($tree); ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="space-y-4 xl:col-span-5">
        <?php if (is_admin()): ?>
        <section class="panel reveal reveal-delay-2">
            <details <?= $editing ? 'open' : '' ?>>
                <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4">
                    <span><span class="eyebrow mb-1 block"><?= $editing ? '编辑 B2B 行业' : '新增 B2B 行业' ?></span><span class="panel-title text-[15px]"><?= icon($editing ? 'settings' : 'plus', 'h-4 w-4 text-signal') ?> <?= $editing ? '编辑 B2B 行业节点' : '添加 B2B 行业' ?></span></span>
                    <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600">展开 +</span>
                </summary>
                <form method="post" action="<?= $editing ? '/industries/' . (int) $editing['id'] : '/industries' ?>" class="space-y-3 border-t border-white/[0.06] p-5">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="label">编码 *</label><input name="code" value="<?= e($editing['code'] ?? '') ?>" class="input" placeholder="plumbing" <?= $editing ? 'readonly' : 'required' ?>></div>
                        <div><label class="label">排序</label><input type="number" name="sort_order" value="<?= e($editing['sort_order'] ?? 100) ?>" class="input font-mono"></div>
                    </div>
                    <div><label class="label">英文名称 *</label><input name="name" value="<?= e($editing['name'] ?? '') ?>" class="input" required></div>
                    <div><label class="label">中文名称</label><input name="name_zh" value="<?php $tr = null; foreach ($translations as $t) { if ($editing && (int) $t['industry_id'] === (int) $editing['id'] && $t['language_code'] === 'zh') $tr = $t; } ?><?= e($tr['name'] ?? '') ?>" class="input" placeholder="可选"></div>
                    <div><label class="label">父级分类</label><select name="parent_id" class="select"><option value="">作为根节点</option><?php foreach ($industries as $i): ?><?php if ($editing && (int) $i['id'] === (int) $editing['id']) continue; ?><option value="<?= (int) $i['id'] ?>" <?= $editing && (int) $editing['parent_id'] === (int) $i['id'] ? 'selected' : '' ?>><?= e(industry_name_display((int) $i['id'], $i['name'])) ?></option><?php endforeach; ?></select></div>
                    <div><label class="label">描述</label><textarea name="description" class="textarea !min-h-[70px]"><?= e($editing['description'] ?? '') ?></textarea></div>
                    <label class="flex items-center gap-2 text-[13px] text-slate-400"><input type="checkbox" name="is_active" value="1" <?= !$editing || (int) $editing['is_active'] ? 'checked' : '' ?> class="accent-[#20e3bd]"> 启用行业</label>
                    <div class="flex gap-2">
                        <?php if ($editing): ?><a href="/industries" class="btn btn-ghost btn-sm flex-1">取消</a><?php endif; ?>
                        <button class="btn btn-primary btn-sm flex-1"><?= icon('check', 'h-3.5 w-3.5') ?> <?= $editing ? '保存修改' : '创建分类' ?></button>
                    </div>
                </form>
            </details>
        </section>

        <section class="panel reveal reveal-delay-3">
            <div class="panel-head">
                <div><div class="eyebrow mb-1">多语言层</div><h2 class="panel-title"><?= icon('globe', 'h-4 w-4 text-electric') ?> 多语言名称</h2></div>
                <span class="font-mono text-[11px] uppercase tracking-wider text-slate-600"><?= count($translations) ?> 条</span>
            </div>
            <div class="max-h-[250px] overflow-y-auto divide-y divide-white/[0.05]">
                <?php if (!$translations): ?><div class="px-5 py-8 text-center text-[13px] text-slate-600">暂无翻译</div><?php endif; ?>
                <?php foreach ($translations as $t): ?>
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <span class="rounded border border-white/[0.08] px-1.5 py-0.5 font-mono text-[11px] uppercase text-slate-500"><?= e(language_name($t['language_code'])) ?></span>
                        <span class="min-w-0 flex-1"><span class="block truncate text-[13px] text-slate-300"><?= e($t['name']) ?></span><span class="mt-0.5 block truncate font-mono text-[11px] text-slate-600"><?= e(industry_name_display((int) $t['industry_id'], $t['industry_name'])) ?></span></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="border-t border-white/[0.06] p-4">
                <form method="post" action="<?= $editing ? '/industries/' . (int) $editing['id'] . '/translation' : '#' ?>" class="space-y-2">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-[80px_1fr_auto] gap-2">
                        <input name="language_code" value="zh" class="input !py-2 font-mono text-[13px]" aria-label="语言代码">
                        <input name="name" placeholder="翻译名称" class="input !py-2" required>
                        <button class="btn btn-secondary btn-sm" <?= !$editing ? 'disabled title="先选择一个行业"' : '' ?>><?= icon('plus', 'h-3 w-3') ?></button>
                    </div>
                    <p class="font-mono text-[10px] uppercase tracking-wider text-slate-700"><?= $editing ? '目标：' . e($editing['name']) : '请先选择行业再添加翻译' ?></p>
                </form>
            </div>
        </section>
        <?php else: ?>
        <section class="panel reveal reveal-delay-2 p-5">
            <div class="eyebrow mb-2">只读分类</div>
            <h2 class="text-[17px] font-semibold text-white">B2B 行业由管理员维护</h2>
            <p class="mt-2 text-[13px] leading-5 text-slate-500">行业分类是共享基础字典。成员可以用于筛选和创建自己的关键词，但不能新增或修改公共分类。</p>
        </section>
        <?php endif; ?>
    </div>
</div>
