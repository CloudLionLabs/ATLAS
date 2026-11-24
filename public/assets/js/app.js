(function () {
  'use strict';

  document.documentElement.classList.add('js');

  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  // Live UTC + Shanghai clock in the top bar.
  const clock = $('[data-utc-clock]');
  const shanghai = $('[data-shanghai-clock]');
  const tick = () => {
    const now = new Date();
    if (clock) clock.textContent = now.toISOString().slice(11, 19) + ' UTC';
    if (shanghai) {
      try {
        shanghai.textContent = new Intl.DateTimeFormat('zh-CN', {
          timeZone: 'Asia/Shanghai', hour: '2-digit', minute: '2-digit', hour12: false
        }).format(now) + ' 北京';
      } catch (e) { /* ignore */ }
    }
  };
  tick();
  setInterval(tick, 1000);

  // Mobile navigation.
  const sidebar = $('#sidebar');
  const backdrop = $('#nav-backdrop');
  const openNav = () => {
    if (sidebar) sidebar.classList.add('is-open');
    if (backdrop) backdrop.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  };
  const closeNav = () => {
    if (sidebar) sidebar.classList.remove('is-open');
    if (backdrop) backdrop.classList.remove('is-open');
    document.body.style.overflow = '';
  };
  $$('[data-nav-open]').forEach((el) => el.addEventListener('click', openNav));
  $$('[data-nav-close]').forEach((el) => el.addEventListener('click', closeNav));
  if (backdrop) backdrop.addEventListener('click', closeNav);

  // Toasts.
  $$('[data-toast]').forEach((toast, index) => {
    const close = $('[data-toast-close]', toast);
    const dismiss = () => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-6px)';
      toast.style.transition = 'all .25s ease';
      setTimeout(() => toast.remove(), 260);
    };
    if (close) close.addEventListener('click', dismiss);
    setTimeout(dismiss, 6500 + index * 700);
  });

  // Command palette.
  const palette = $('#command-palette');
  const input = $('#command-input');
  const list = $('#command-list');
  let selected = 0;

  const items = () => $$('[data-command]', list || document);
  const select = (index) => {
    const all = items();
    if (!all.length) return;
    selected = (index + all.length) % all.length;
    all.forEach((el, i) => el.classList.toggle('is-selected', i === selected));
    all[selected].scrollIntoView({ block: 'nearest' });
  };
  const filter = () => {
    if (!input) return;
    const q = input.value.trim().toLowerCase();
    items().forEach((el) => {
      const hay = (el.dataset.label || el.textContent || '').toLowerCase();
      el.style.display = !q || hay.includes(q) ? '' : 'none';
    });
    selected = 0;
    select(0);
  };
  const openPalette = () => {
    if (!palette) return;
    palette.classList.add('is-open');
    palette.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (input) { input.value = ''; filter(); setTimeout(() => input.focus(), 30); }
    select(0);
  };
  const closePalette = () => {
    if (!palette) return;
    palette.classList.remove('is-open');
    palette.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };
  $$('[data-command-open]').forEach((el) => el.addEventListener('click', openPalette));
  $$('[data-command-close]').forEach((el) => {
    el.addEventListener('click', (event) => {
      if (event.target === el) closePalette();
    });
  });
  if (input) {
    input.addEventListener('input', filter);
    input.addEventListener('keydown', (event) => {
      const all = items().filter((el) => el.style.display !== 'none');
      if (!all.length) return;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        selected = (selected + 1) % all.length;
        all.forEach((el, i) => el.classList.toggle('is-selected', i === selected));
        all[selected].scrollIntoView({ block: 'nearest' });
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        selected = (selected - 1 + all.length) % all.length;
        all.forEach((el, i) => el.classList.toggle('is-selected', i === selected));
        all[selected].scrollIntoView({ block: 'nearest' });
      } else if (event.key === 'Enter' && all[selected]) {
        event.preventDefault();
        all[selected].click();
      }
    });
  }
  if (palette) {
    palette.addEventListener('click', (event) => {
      if (event.target === palette) closePalette();
    });
  }
  if (list) {
    list.addEventListener('mouseover', (event) => {
      const item = event.target.closest('[data-command]');
      if (!item) return;
      const all = items();
      selected = all.indexOf(item);
      all.forEach((el) => el.classList.toggle('is-selected', el === item));
    });
  }
  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      openPalette();
    }
    if (event.key === 'Escape') {
      closePalette();
      closeNav();
    }
    if (event.key === '/' && !/input|textarea|select/i.test((event.target.tagName || ''))) {
      event.preventDefault();
      openPalette();
    }
  });

  // Scraper status polling.
  const dot = $('[data-scraper-dot]');
  const statusText = $('[data-scraper-text]');
  const statusLatency = $('[data-scraper-latency]');
  const paintStatus = (state) => {
    if (dot) {
      dot.className = 'terminal-dot ' + (state === 'online'
        ? 'bg-signal'
        : state === 'offline' ? 'bg-rose-400' : 'bg-amber-400');
    }
    if (statusText) statusText.textContent = state === 'online' ? '已连接' : state === 'offline' ? '离线' : '连接中';
    $$('[data-scraper-node-state]').forEach((el) => { el.textContent = state === 'online' ? '在线' : state === 'offline' ? '离线' : '未知'; });
  };
  const pollStatus = async () => {
    try {
      const res = await fetch('/api/system/status', { headers: { Accept: 'application/json' } });
      const json = await res.json();
      if (json.success) {
        paintStatus(json.data.state);
        if (statusLatency) statusLatency.textContent = json.data.latency_ms != null ? json.data.latency_ms + 'ms' : '—';
      } else paintStatus('unknown');
    } catch (e) {
      paintStatus('unknown');
    }
  };
  if (dot || statusText) pollStatus();

  // Confirm guarded POST forms. Delegate so forms inserted by the task-list
  // live refresh keep working without rebinding.
  document.addEventListener('submit', (event) => {
    const form = event.target.closest && event.target.closest('form[data-confirm]');
    if (!form) return;
    const message = form.dataset.confirm || '确认执行此操作？';
    if (!window.confirm(message)) event.preventDefault();
  });

  // Copy to clipboard.
  $$('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.getAttribute('data-copy');
      try {
        await navigator.clipboard.writeText(value || '');
        const previous = button.textContent;
        button.textContent = '已复制';
        setTimeout(() => { button.textContent = previous; }, 1500);
      } catch (e) { /* ignore */ }
    });
  });

  // Subtle parallax on decorative hero glows.
  const glow = $('[data-parallax-glow]');
  if (glow && window.matchMedia('(pointer:fine)').matches) {
    window.addEventListener('mousemove', (event) => {
      const x = (event.clientX / window.innerWidth - 0.5) * 18;
      const y = (event.clientY / window.innerHeight - 0.5) * 18;
      glow.style.transform = 'translate(' + x + 'px,' + y + 'px)';
    }, { passive: true });
  }

  // Auto-submit selects tagged for instant filtering.
  $$('[data-auto-submit]').forEach((el) => {
    el.addEventListener('change', () => el.form && el.form.submit());
  });
})();
