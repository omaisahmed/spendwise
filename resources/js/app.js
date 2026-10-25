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

window.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('spendwise_hide_amounts') === '1') {
        document.body.classList.add('hide-amounts');
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
