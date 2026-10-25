import './bootstrap';
import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';

window.Alpine = Alpine;

const renderIcons = (root = document) => {
    createIcons({ icons, root });
};

window.refreshIcons = () => renderIcons(document);

window.togglePrivacy = () => {
    const hidden = document.body.classList.toggle('hide-amounts');
    localStorage.setItem('spendwise_hide_amounts', hidden ? '1' : '0');

    const button = document.querySelector('[data-privacy-toggle]');
    button?.setAttribute('aria-label', hidden ? 'Show amounts' : 'Hide amounts');
};

const themeMedia = window.matchMedia('(prefers-color-scheme: dark)');
const themeStorageKey = 'spendwise_theme_setting';

const storedTheme = () => {
    try {
        const setting = localStorage.getItem(themeStorageKey);

        return ['system', 'light', 'dark'].includes(setting) ? setting : null;
    } catch {
        return null;
    }
};

const rememberTheme = (setting) => {
    try {
        localStorage.setItem(themeStorageKey, setting);
    } catch {
        // Storage can be unavailable in restricted webviews.
    }
};

const resolveTheme = (setting) => {
    if (setting === 'system') {
        return themeMedia.matches ? 'dark' : 'light';
    }

    return setting === 'dark' ? 'dark' : 'light';
};

const persistTheme = (setting) => {
    const endpoint = document.documentElement.dataset.themeEndpoint;

    if (!endpoint) return;

    fetch(endpoint, {
        method: 'PATCH',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ theme: setting }),
    }).catch(() => {});
};

window.setTheme = (setting, persist = true) => {
    if (!['system', 'light', 'dark'].includes(setting)) return;

    const root = document.documentElement;
    const resolved = resolveTheme(setting);

    rememberTheme(setting);

    root.dataset.themeSetting = setting;
    root.dataset.theme = resolved;
    root.style.colorScheme = resolved;

    const meta = document.getElementById('theme-color');
    meta?.setAttribute('content', resolved === 'dark' ? '#10131c' : '#f6f7fb');

    document.querySelectorAll('[data-theme-option]').forEach((input) => {
        input.checked = input.value === setting;
    });

    const toggle = document.querySelector('[data-theme-toggle]');
    toggle?.setAttribute('aria-label', resolved === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');

    if (persist) persistTheme(setting);
};

window.toggleTheme = () => {
    const root = document.documentElement;
    setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
};

themeMedia.addEventListener('change', () => {
    if (document.documentElement.dataset.themeSetting === 'system') {
        setTheme('system', false);
    }
});

const hiddenAtKey = 'spendwise_hidden_at';
const lockGraceMs = 2500;
let ignoreNextBackgroundLock = false;

const lockApp = () => {
    if (document.body.dataset.appLockEnabled !== '1' || document.documentElement.dataset.locking === '1') {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const endpoint = document.body.dataset.lockEndpoint;

    if (!endpoint) return;

    document.documentElement.dataset.locking = '1';

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = endpoint;
    form.style.display = 'none';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrf;

    form.appendChild(token);
    document.body.appendChild(form);
    form.submit();
};

document.addEventListener('click', (event) => {
    if (event.target.closest?.('[data-background-lock-ignore], input[type="file"]')) {
        ignoreNextBackgroundLock = true;
    }
}, true);

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        if (!ignoreNextBackgroundLock) {
            localStorage.setItem(hiddenAtKey, String(Date.now()));
        }

        ignoreNextBackgroundLock = false;
        return;
    }

    const hiddenAt = Number(localStorage.getItem(hiddenAtKey));
    localStorage.removeItem(hiddenAtKey);

    if (hiddenAt > 0 && Date.now() - hiddenAt > lockGraceMs) {
        lockApp();
    }
});

const resetSwipe = (row) => {
    const content = row.querySelector('[data-swipe-content]');

    if (!content) return;

    row.classList.remove('is-swiping', 'is-dismissing');
    content.style.transform = '';
};

const setupNotificationSwipes = () => {
    const rows = Array.from(document.querySelectorAll('[data-swipe-row]'));

    rows.forEach((row) => {
        const content = row.querySelector('[data-swipe-content]');
        const form = row.querySelector('[data-swipe-form]');

        if (!content || !form) return;

        let startX = 0;
        let startY = 0;
        let offset = 0;
        let tracking = false;
        let horizontal = false;

        const stopTracking = () => {
            tracking = false;
            horizontal = false;
        };

        content.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            if (event.target.closest?.('a, button, input, select, textarea, label')) return;

            rows.forEach((other) => {
                if (other !== row) resetSwipe(other);
            });

            startX = event.clientX;
            startY = event.clientY;
            offset = 0;
            tracking = true;
            horizontal = false;

            content.setPointerCapture?.(event.pointerId);
        });

        content.addEventListener('pointermove', (event) => {
            if (!tracking) return;

            const deltaX = event.clientX - startX;
            const deltaY = event.clientY - startY;

            if (!horizontal) {
                if (Math.abs(deltaX) < 8 && Math.abs(deltaY) < 8) return;

                if (Math.abs(deltaY) >= Math.abs(deltaX)) {
                    stopTracking();
                    return;
                }

                horizontal = true;
                row.classList.add('is-swiping');
            }

            event.preventDefault();
            offset = Math.min(0, Math.max(deltaX, -120));
            content.style.transform = `translateX(${offset}px)`;
        }, { passive: false });

        const finishSwipe = () => {
            if (!tracking && !horizontal) return;

            const shouldDelete = horizontal && offset <= -72;

            stopTracking();

            if (shouldDelete) {
                row.classList.remove('is-swiping');
                row.classList.add('is-dismissing');
                content.style.transform = '';

                window.setTimeout(() => form.submit(), 180);

                return;
            }

            resetSwipe(row);
        };

        content.addEventListener('pointerup', finishSwipe);
        content.addEventListener('pointercancel', () => {
            stopTracking();
            resetSwipe(row);
        });
    });
};

window.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('spendwise_hide_amounts') === '1') {
        document.body.classList.add('hide-amounts');
    }

    setTheme(storedTheme() || document.documentElement.dataset.themeSetting || 'system', false);
    setupNotificationSwipes();

    const hiddenAt = Number(localStorage.getItem(hiddenAtKey));
    localStorage.removeItem(hiddenAtKey);

    if (hiddenAt > 0 && Date.now() - hiddenAt > lockGraceMs) {
        lockApp();
    }

    renderIcons();

    const observer = new MutationObserver((mutations) => {
        const hasNewIcon = mutations.some((mutation) =>
            Array.from(mutation.addedNodes).some((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) return false;
                return node.matches?.('i[data-lucide]') || node.querySelector?.('i[data-lucide]');
            })
        );

        if (hasNewIcon) renderIcons();
    });

    observer.observe(document.body, { childList: true, subtree: true });
});

Alpine.start();
