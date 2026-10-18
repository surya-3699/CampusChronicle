/* Preserve existing controls while submitting mutations with POST and CSRF. */
(() => {
    'use strict';
    const token = () => document.querySelector('meta[name="cc-csrf"]')?.content || '';
    const actions = new Set(['logout','delete-event','approve-registration','reject-registration','mark-attendance','promote-user','demote-user','delete-user','delete-gallery','delete-media']);
    window.ccPostAction = (href) => {
        const url = new URL(href, location.href);
        if (url.origin !== location.origin) return;
        const form = document.createElement('form');
        form.method = 'POST'; form.action = url.href;
        const field = document.createElement('input');
        field.type = 'hidden'; field.name = '_csrf'; field.value = token();
        form.append(field); document.body.append(form); form.submit();
    };
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented) return;
        const url = new URL(link.href, location.href);
        if (url.origin === location.origin && actions.has(url.searchParams.get('page'))) {
            event.preventDefault(); window.ccPostAction(url.href);
        }
    });
    const originalFetch = window.fetch.bind(window);
    window.fetch = (input, options = {}) => {
        const url = new URL(input instanceof Request ? input.url : input, location.href);
        const method = (options.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
        if (url.origin === location.origin && method === 'POST') {
            options = {...options, headers: new Headers(options.headers || (input instanceof Request ? input.headers : undefined))};
            options.headers.set('X-CSRF-Token', token());
        }
        return originalFetch(input, options);
    };
})();
