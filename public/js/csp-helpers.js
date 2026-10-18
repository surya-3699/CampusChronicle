/* ============================================================================
   Declarative replacements for inline event handlers.
   The Content-Security-Policy no longer allows inline JavaScript, so markup uses
   data attributes and this one small script (loaded in <head>) provides the behaviour:

     data-fallback="URL"        <img>: swap to URL once if the image fails to load
     data-confirm="Question?"   link/button/form: ask before continuing
     data-prevent-default       link: do nothing when clicked (preview-only links)
     data-print                 any element: opens the print dialog
     data-post-action="URL"     any element: POST to URL with the CSRF token

   Confirm handling runs in the CAPTURE phase and stops the event when the user
   says no, so it always runs before security.js turns action links into POSTs.
============================================================================ */
(function () {
  'use strict';

  /* ---- image fallbacks ---- */
  function useFallback(img) {
    var url = img.getAttribute('data-fallback');
    if (!url || img.getAttribute('data-fallback-used')) return;
    img.setAttribute('data-fallback-used', '1');
    img.src = url;
  }
  // 'error' does not bubble, but a capture listener on document sees it.
  document.addEventListener('error', function (e) {
    var t = e.target;
    if (t && t.tagName === 'IMG' && t.hasAttribute('data-fallback')) useFallback(t);
  }, true);
  // Images that already failed before this listener ran.
  document.addEventListener('DOMContentLoaded', function () {
    var imgs = document.querySelectorAll('img[data-fallback]');
    for (var i = 0; i < imgs.length; i++) {
      if (imgs[i].complete && imgs[i].naturalWidth === 0 && imgs[i].getAttribute('src')) useFallback(imgs[i]);
    }
  });

  /* ---- confirm dialogs ---- */
  function cancel(e) { e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation(); }
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('[data-confirm]') : null;
    if (!el || el.tagName === 'FORM') return;
    if (!window.confirm(el.getAttribute('data-confirm'))) cancel(e);
  }, true);
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form && form.hasAttribute && form.hasAttribute('data-confirm') && !window.confirm(form.getAttribute('data-confirm'))) cancel(e);
  }, true);

  /* ---- small actions ---- */
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target : null;
    if (!t) return;
    var el;
    if ((el = t.closest('[data-prevent-default]'))) { e.preventDefault(); return; }
    if ((el = t.closest('[data-print]'))) { e.preventDefault(); window.print(); return; }
    if ((el = t.closest('[data-post-action]'))) {
      e.preventDefault();
      if (typeof window.ccPostAction === 'function') window.ccPostAction(el.getAttribute('data-post-action'));
    }
  });
})();
