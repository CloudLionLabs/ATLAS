<?php
$code = $code ?? '500';
$message = $message ?? '发生未知错误。';
?>
<section class="flex min-h-[62vh] items-center justify-center py-16">
    <div class="w-full max-w-2xl text-center">
        <div class="mx-auto mb-8 relative grid h-28 w-28 place-items-center rounded-[2rem] border border-danger/20 bg-danger/[0.04]">
            <div class="absolute inset-3 rounded-[1.5rem] border border-dashed border-danger/20 animate-[spin_16s_linear_infinite]"></div>
            <span class="font-mono text-3xl font-medium text-danger/80"><?= e($code) ?></span>
        </div>
        <p class="eyebrow mb-3">信号中断 / 错误 <?= e($code) ?></p>
        <h1 class="page-title"><?= e($title ?? '系统异常') ?></h1>
        <p class="mx-auto mt-4 max-w-lg whitespace-pre-wrap text-[16px] leading-6 text-slate-500"><?= e($message) ?></p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="/" class="btn btn-primary"><?= icon('grid') ?> 返回指挥中心</a>
            <button type="button" onclick="history.back()" class="btn btn-secondary"><?= icon('arrow-right', 'rotate-180') ?> 返回上一页</button>
        </div>
        <div class="mt-12 font-mono text-[12px] uppercase tracking-[0.2em] text-slate-700">ATLAS / 本地节点 / <?= e(gmdate('Y-m-d H:i:s')) ?> UTC</div>
    </div>
</section>
