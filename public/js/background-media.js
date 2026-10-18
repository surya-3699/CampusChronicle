/* Ambient video must not compete with the first usable page or hidden tabs. */
(() => {
  const video = document.querySelector('.cc-video-background video');
  if (!video) return;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  let started = false;
  function sync() {
    if (document.hidden || reduced.matches || navigator.connection?.saveData) { video.pause(); return; }
    if (!started) {
      const source = video.querySelector('source[data-src]');
      if (source) { source.src = source.dataset.src; video.load(); }
      started = true;
    }
    video.play().catch(() => { /* The poster stays visible if autoplay is unavailable. */ });
  }
  // Paint the page and poster first, then decode the ambient video.
  const begin = () => requestAnimationFrame(() => requestAnimationFrame(sync));
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', begin, {once:true});
  else begin();
  document.addEventListener('visibilitychange', sync);
  reduced.addEventListener?.('change', sync);
  window.addEventListener('pagehide', () => video.pause());
  window.addEventListener('pageshow', sync);
})();
