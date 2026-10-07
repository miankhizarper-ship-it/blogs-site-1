/* ==========================================================================
   BlogSite — main.js  (Phase 2)
   Vanilla ES6+, no dependencies. Modules: theme, menu, header, lazyload,
   toasts, scroll-top, search, reading progress, TOC, like/bookmark.
   All features degrade gracefully without JS.
   ========================================================================== */
'use strict';

/* ---------- tiny DOM helpers ---------- */
const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

/* ==========================================================================
   1. THEME (dark/light) — persisted in localStorage, OS preference default
   ========================================================================== */
const ThemeManager = {
  KEY: 'blogsite_theme',

  init() {
    // The inline script in <head> already set data-theme before paint;
    // here we only wire the toggle buttons.
    $$('[data-theme-toggle]').forEach(btn =>
      btn.addEventListener('click', () => ThemeManager.toggle())
    );
  },

  current() {
    return document.documentElement.dataset.theme || 'light';
  },

  set(theme) {
    document.documentElement.dataset.theme = theme;
    try { localStorage.setItem(ThemeManager.KEY, theme); } catch (_) { /* private mode */ }
    // Keep <meta name="theme-color"> in sync for mobile browsers.
    const meta = $('meta[name="theme-color"]');
    if (meta) meta.content = theme === 'dark' ? '#0b1120' : '#ffffff';
    document.dispatchEvent(new CustomEvent('theme:change', { detail: { theme } }));
  },

  toggle() {
    ThemeManager.set(ThemeManager.current() === 'dark' ? 'light' : 'dark');
  },
};

/* ==========================================================================
   2. MOBILE MENU — drawer with focus trap + Esc/backdrop close
   ========================================================================== */
const MobileMenu = {
  init() {
    const toggle = $('#menuToggle');
    const nav = $('#mobileNav');
    if (!toggle || !nav) return;

    this.toggle = toggle;
    this.nav = nav;
    this.panel = $('.mobile-nav-panel', nav);

    toggle.addEventListener('click', () => this.isOpen() ? this.close() : this.open());
    $('.mobile-nav-backdrop', nav)?.addEventListener('click', () => this.close());
    $('[data-close-menu]', nav)?.addEventListener('click', () => this.close());

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && this.isOpen()) this.close();
    });

    // Close drawer when a link inside is clicked
    $$('a', this.panel).forEach(a => a.addEventListener('click', () => this.close()));
  },

  isOpen() { return this.nav.classList.contains('is-open'); },

  open() {
    this.nav.classList.add('is-open');
    this.nav.removeAttribute('hidden');
    this.toggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    // Move focus into the panel for keyboard users
    const first = $('a, button', this.panel);
    first?.focus();
    this.nav.addEventListener('keydown', this.trap);
  },

  close() {
    this.nav.classList.remove('is-open');
    this.toggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    this.nav.removeEventListener('keydown', this.trap);
    this.toggle.focus();
  },

  /** Simple focus trap: Tab cycles within the drawer while open. */
  trap(e) {
    if (e.key !== 'Tab') return;
    const focusables = $$('a[href], button:not([disabled])', MobileMenu.panel);
    if (!focusables.length) return;
    const first = focusables[0], last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  },
};

/* ==========================================================================
   3. STICKY HEADER — shadow after scrolling past threshold
   ========================================================================== */
const StickyHeader = {
  init() {
    const header = $('.site-header');
    if (!header) return;
    let ticking = false;
    const onScroll = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        header.classList.toggle('is-scrolled', window.scrollY > 8);
        ticking = false;
      });
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  },
};

/* ==========================================================================
   4. LAZY LOADING — IntersectionObserver for images with data-src
   ========================================================================== */
const LazyLoad = {
  init() {
    const imgs = $$('img[data-src]');
    if (!imgs.length) return;

    if (!('IntersectionObserver' in window)) {
      imgs.forEach(img => LazyLoad.load(img));
      return;
    }
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => {
        if (en.isIntersecting) { LazyLoad.load(en.target); io.unobserve(en.target); }
      });
    }, { rootMargin: '200px 0px' });
    imgs.forEach(img => io.observe(img));
  },

  load(img) {
    img.addEventListener('load', () => img.classList.add('is-loaded'), { once: true });
    img.src = img.dataset.src;
    if (img.dataset.srcset) img.srcset = img.dataset.srcset;
    delete img.dataset.src;
  },
};

/* ==========================================================================
   5. TOASTS — global helper: Toast.show('Saved!', 'success')
   ========================================================================== */
const Toast = {
  container: null,

  ensure() {
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.className = 'toast-container';
      this.container.setAttribute('role', 'status');
      this.container.setAttribute('aria-live', 'polite');
      document.body.appendChild(this.container);
    }
    return this.container;
  },

  /** type: success | error | warning | info */
  show(message, type = 'info', timeout = 3800) {
    const el = document.createElement('div');
    el.className = `toast toast--${type}`;
    el.innerHTML = `<span class="toast-dot" aria-hidden="true"></span><span></span>`;
    el.lastElementChild.textContent = message; // textContent = XSS-safe
    this.ensure().appendChild(el);
    setTimeout(() => {
      el.classList.add('is-hide');
      el.addEventListener('animationend', () => el.remove(), { once: true });
    }, timeout);
  },
};
window.Toast = Toast; // expose for other scripts / fetch handlers

/* Replay server-side flashes as toasts (optional nicety) */
const FlashToasts = {
  init() {
    $$('[data-flash]').forEach(n => {
      Toast.show(n.dataset.flashMessage || n.textContent.trim(), n.dataset.flash || 'info');
      n.remove();
    });
  },
};

/* ==========================================================================
   6. SCROLL-TO-TOP BUTTON
   ========================================================================== */
const ScrollTop = {
  init() {
    let btn = $('.scroll-top');
    if (!btn) {
      btn = document.createElement('button');
      btn.className = 'scroll-top';
      btn.setAttribute('aria-label', 'Scroll to top');
      btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
      document.body.appendChild(btn);
    }
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    let ticking = false;
    window.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        btn.classList.toggle('is-visible', window.scrollY > 500);
        ticking = false;
      });
    }, { passive: true });
  },
};

/* ==========================================================================
   7. READING PROGRESS BAR (single post pages)
   ========================================================================== */
const ReadingProgress = {
  init() {
    const bar = $('.reading-progress');
    const article = $('#article-content');
    if (!bar || !article) return;
    let ticking = false;
    window.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        const rect = article.getBoundingClientRect();
        const total = rect.height - window.innerHeight;
        const done = Math.min(Math.max(-rect.top, 0), Math.max(total, 1));
        bar.style.width = `${(done / Math.max(total, 1)) * 100}%`;
        ticking = false;
      });
    }, { passive: true });
  },
};

/* ==========================================================================
   8. TABLE OF CONTENTS — auto-generate from h2/h3 inside the article
   ========================================================================== */
const Toc = {
  init() {
    const holder = $('#toc');
    const article = $('#article-content');
    if (!holder || !article) return;

    const headings = $$('h2, h3', article);
    if (headings.length < 2) { holder.closest('.toc-card')?.remove(); return; }

    const list = document.createElement('ol');
    list.className = 'toc-list';
    list.setAttribute('role', 'list');
    headings.forEach((h, i) => {
      if (!h.id) h.id = 'section-' + (i + 1);
      const li = document.createElement('li');
      li.className = h.tagName === 'H3' ? 'toc-sub' : '';
      const a = document.createElement('a');
      a.href = '#' + h.id;
      a.textContent = h.textContent;
      li.appendChild(a);
      list.appendChild(li);
    });
    holder.innerHTML = '';
    holder.appendChild(list);
  },
};

/* ==========================================================================
   9. LIKE / BOOKMARK — stored per-post in localStorage
   ========================================================================== */
const PostActions = {
  KEY: 'blogsite_actions',

  read() {
    try { return JSON.parse(localStorage.getItem(PostActions.KEY)) || {}; }
    catch (_) { return {}; }
  },
  write(data) {
    try { localStorage.setItem(PostActions.KEY, JSON.stringify(data)); } catch (_) {}
  },

  init() {
    $$('[data-post-id]').forEach(el => {
      const id = el.dataset.postId;
      const state = PostActions.read();
      $$(`[data-action][data-post-id="${id}"]`).forEach(btn => {
        const action = btn.dataset.action; // like | bookmark
        if (state[id]?.[action]) btn.classList.add('is-active');
        btn.addEventListener('click', e => {
          e.preventDefault();
          const s = PostActions.read();
          s[id] = s[id] || {};
          s[id][action] = !s[id][action];
          PostActions.write(s);
          btn.classList.toggle('is-active', s[id][action]);
          const countEl = $(`[data-count-for="${action}-${id}"]`);
          if (countEl) {
            const delta = s[id][action] ? 1 : -1;
            countEl.textContent = Math.max(0, parseInt(countEl.textContent || '0', 10) + delta);
          }
          Toast.show(
            s[id][action]
              ? (action === 'like' ? 'Thanks for the like!' : 'Saved to your bookmarks.')
              : (action === 'like' ? 'Like removed.' : 'Bookmark removed.'),
            'success', 2200
          );
        });
      });
    });
  },
};

/* ==========================================================================
   10. SHARE — copy-link button (WhatsApp/FB/X/LinkedIn are plain links)
   ========================================================================== */
const Share = {
  init() {
    $$('[data-copy-link]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const url = btn.dataset.copyLink || location.href;
        try {
          await navigator.clipboard.writeText(url);
          Toast.show('Link copied to clipboard.', 'success', 2000);
        } catch (_) {
          // Fallback for older/insecure contexts
          const ta = document.createElement('textarea');
          ta.value = url; document.body.appendChild(ta); ta.select();
          document.execCommand('copy'); ta.remove();
          Toast.show('Link copied.', 'success', 2000);
        }
      });
    });
  },
};

/* ==========================================================================
   11. NEWSLETTER + generic AJAX forms (fetch API, CSRF header)
   ========================================================================== */
const AjaxForms = {
  init() {
    $$('form[data-ajax]').forEach(form => {
      form.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = $('button[type="submit"]', form);
        const original = btn?.textContent;
        btn?.disabled !== undefined && (btn.disabled = true);
        if (btn) btn.textContent = 'Sending…';
        try {
          const res = await fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            body: new FormData(form),
            headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': form.querySelector('[name=csrf_token]')?.value || '' },
          });
          const data = await res.json().catch(() => ({}));
          if (res.ok && data.ok) {
            Toast.show(data.message || 'Done!', 'success');
            form.reset();
          } else {
            Toast.show(data.message || 'Something went wrong.', 'error');
            if (data.csrf) form.querySelector('[name=csrf_token]').value = data.csrf;
          }
        } catch (_) {
          Toast.show('Network error — please try again.', 'error');
        } finally {
          if (btn) { btn.disabled = false; btn.textContent = original; }
        }
      });
    });
  },
};

/* ==========================================================================
   12. SKELETON SWAP — remove skeletons once real content is present
   ========================================================================== */
const Skeletons = {
  init() {
    // If a view ships skeleton placeholders followed by [data-real],
    // hide the skeleton when real content renders. (Used in phase 3+.)
    $$('[data-skeleton-for]').forEach(sk => {
      const target = document.getElementById(sk.dataset.skeletonFor);
      if (target && target.children.length) sk.remove();
    });
  },
};

/* ==========================================================================
   BOOT
   ========================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  ThemeManager.init();
  MobileMenu.init();
  StickyHeader.init();
  LazyLoad.init();
  ScrollTop.init();
  ReadingProgress.init();
  Toc.init();
  PostActions.init();
  Share.init();
  AjaxForms.init();
  FlashToasts.init();
  Skeletons.init();
});
