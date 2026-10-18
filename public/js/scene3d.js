/* ============================================================================
   Campus Chronicle — 3D animated background (user-facing pages)
   ----------------------------------------------------------------------------
   Raw WebGL, no libraries and no network requests: a slowly turning "campus
   network" constellation (glowing nodes joined by hairline edges), a layer of
   depth dust and two wireframe crystals. The aurora gradient behind it is plain
   CSS, so the canvas is transparent and the GPU only draws small points/lines.

   Built so it can never become the thing that makes the page feel slow:
     * Draws only after the page's first paint (two animation frames), and
       compiles shaders without blocking the main thread where the browser
       supports KHR_parallel_shader_compile.
     * Pauses while the tab is hidden; resumes on return.
     * Adaptive quality: if the measured frame time is poor over a 2.5 s window
       it steps down (lower resolution -> fewer particles -> 30 fps -> still).
     * prefers-reduced-motion and Save-Data render one still frame.
     * No WebGL / context loss -> the CSS gradient on .cc-3d-background stays.
   Admin pages keep the ambient video and do not load this file.
============================================================================ */
(() => {
  'use strict';

  const host = document.querySelector('.cc-3d-background');
  const canvas = host && host.querySelector('canvas');
  if (!canvas) return;

  const reducedMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  const saveData = !!(navigator.connection && navigator.connection.saveData);
  const isSmallScreen = () => Math.min(window.innerWidth, window.innerHeight) < 600;

  /* ---------------------------------------------------------------- tiers */
  // frameMs: minimum time between frames. dpr: device-pixel-ratio cap.
  const TIERS = [
    { name: 'low',    dpr: 0.75, dust: 0,   nodes: 60,  frameMs: 33 },
    { name: 'medium', dpr: 1.0,  dust: 320, nodes: 90,  frameMs: 0 },
    { name: 'high',   dpr: 1.5,  dust: 650, nodes: 110, frameMs: 0 },
  ];
  let tier = (isSmallScreen() || (navigator.hardwareConcurrency || 4) <= 2) ? 1 : 2;
  const MAX_NODES = 110, MAX_DUST = 650;

  /* ------------------------------------------------------------- tiny math */
  const mat = {
    perspective(fovy, aspect, near, far) {
      const f = 1 / Math.tan(fovy / 2), nf = 1 / (near - far), o = new Float32Array(16);
      o[0] = f / aspect; o[5] = f; o[10] = (far + near) * nf; o[11] = -1; o[14] = 2 * far * near * nf;
      return o;
    },
    mul(a, b) { // a * b, column-major
      const o = new Float32Array(16);
      for (let c = 0; c < 4; c++) for (let r = 0; r < 4; r++) {
        o[c * 4 + r] = a[r] * b[c * 4] + a[4 + r] * b[c * 4 + 1] + a[8 + r] * b[c * 4 + 2] + a[12 + r] * b[c * 4 + 3];
      }
      return o;
    },
    translate(x, y, z) { const o = mat.identity(); o[12] = x; o[13] = y; o[14] = z; return o; },
    rotX(a) { const o = mat.identity(), c = Math.cos(a), s = Math.sin(a); o[5] = c; o[6] = s; o[9] = -s; o[10] = c; return o; },
    rotY(a) { const o = mat.identity(), c = Math.cos(a), s = Math.sin(a); o[0] = c; o[2] = -s; o[8] = s; o[10] = c; return o; },
    scale(k) { const o = mat.identity(); o[0] = o[5] = o[10] = k; return o; },
    identity() { const o = new Float32Array(16); o[0] = o[5] = o[10] = o[15] = 1; return o; },
  };

  // Deterministic PRNG so the layout is identical on every page and reload.
  function rng(seed) {
    return () => {
      seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
      let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  /* ------------------------------------------------------------- geometry */
  // Interleaved: x, y, z, pointSize, hue
  function buildScene() {
    const rand = rng(20260);
    const R = 9;

    const nodes = new Float32Array(MAX_NODES * 5);
    const pts = [];
    for (let i = 0; i < MAX_NODES; i++) {
      // uniform point in a sphere, slightly flattened so it reads as a "disc"
      const u = rand(), v = rand(), w = rand();
      const th = 2 * Math.PI * u, ph = Math.acos(2 * v - 1), r = R * Math.cbrt(w);
      const p = [r * Math.sin(ph) * Math.cos(th), r * Math.sin(ph) * Math.sin(th) * 0.72, r * Math.cos(ph)];
      pts.push(p);
      nodes.set([p[0], p[1], p[2], 5 + rand() * 6, rand()], i * 5);
    }

    // Static neighbour graph: each node joins its 2 nearest neighbours.
    const seen = new Set(), edgeVerts = [];
    for (let i = 0; i < MAX_NODES; i++) {
      const d = [];
      for (let j = 0; j < MAX_NODES; j++) if (j !== i) {
        const dx = pts[i][0] - pts[j][0], dy = pts[i][1] - pts[j][1], dz = pts[i][2] - pts[j][2];
        d.push([dx * dx + dy * dy + dz * dz, j]);
      }
      d.sort((a, b) => a[0] - b[0]);
      for (let k = 0; k < 2; k++) {
        const j = d[k][1];
        if (d[k][0] > 20) continue; // skip long edges (~4.5 units)
        const key = i < j ? i + '_' + j : j + '_' + i;
        if (seen.has(key)) continue;
        seen.add(key);
        edgeVerts.push(pts[i][0], pts[i][1], pts[i][2], 0, nodes[i * 5 + 4]);
        edgeVerts.push(pts[j][0], pts[j][1], pts[j][2], 0, nodes[j * 5 + 4]);
      }
    }

    const dust = new Float32Array(MAX_DUST * 5);
    for (let i = 0; i < MAX_DUST; i++) {
      const u = rand(), v = rand(), w = rand();
      const th = 2 * Math.PI * u, ph = Math.acos(2 * v - 1), r = 6 + 13 * Math.cbrt(w);
      dust.set([r * Math.sin(ph) * Math.cos(th), r * Math.sin(ph) * Math.sin(th) * 0.8, r * Math.cos(ph), 1.2 + rand() * 1.8, rand()], i * 5);
    }

    // Wireframe crystals: icosahedron then octahedron, as GL_LINES vertex pairs.
    const t = (1 + Math.sqrt(5)) / 2, n = Math.hypot(1, t);
    const iv = [[-1, t, 0], [1, t, 0], [-1, -t, 0], [1, -t, 0], [0, -1, t], [0, 1, t], [0, -1, -t], [0, 1, -t], [t, 0, -1], [t, 0, 1], [-t, 0, -1], [-t, 0, 1]].map(p => p.map(c => c / n));
    const ie = [];
    for (let i = 0; i < 12; i++) for (let j = i + 1; j < 12; j++) {
      const dx = iv[i][0] - iv[j][0], dy = iv[i][1] - iv[j][1], dz = iv[i][2] - iv[j][2];
      if (Math.abs(dx * dx + dy * dy + dz * dz - 4 / (n * n)) < 1e-4) ie.push([i, j]);
    }
    const crystal = [];
    ie.forEach(([a, b]) => crystal.push(...iv[a], 0, 0.15, ...iv[b], 0, 0.15));
    const icoCount = crystal.length / 5;
    const ov = [[1, 0, 0], [-1, 0, 0], [0, 1, 0], [0, -1, 0], [0, 0, 1], [0, 0, -1]];
    for (let i = 0; i < 6; i++) for (let j = i + 1; j < 6; j++) {
      if (ov[i][0] + ov[j][0] === 0 && ov[i][1] + ov[j][1] === 0 && ov[i][2] + ov[j][2] === 0) continue; // opposite poles
      crystal.push(...ov[i], 0, 0.8, ...ov[j], 0, 0.8);
    }
    const octCount = crystal.length / 5 - icoCount;

    return {
      nodes, dust,
      edges: new Float32Array(edgeVerts), edgeCount: edgeVerts.length / 5,
      crystal: new Float32Array(crystal), icoCount, octCount,
    };
  }

  /* -------------------------------------------------------------- shaders */
  const VS_SCENE = `
attribute vec3 a_pos;
attribute vec2 a_data;
uniform mat4 u_mvp;
uniform float u_pxScale;
uniform float u_time;
varying vec3 v_color;
varying float v_alpha;
vec3 palette(float h){
  vec3 a = vec3(0.36, 0.86, 1.00);
  vec3 b = vec3(0.50, 0.55, 1.00);
  vec3 c = vec3(0.86, 0.56, 1.00);
  return h < 0.5 ? mix(a, b, h * 2.0) : mix(b, c, (h - 0.5) * 2.0);
}
void main(){
  vec4 p = u_mvp * vec4(a_pos, 1.0);
  gl_Position = p;
  float depth = clamp((p.w - 4.0) / 24.0, 0.0, 1.0);
  float twinkle = 0.78 + 0.22 * sin(u_time * 1.4 + a_pos.x * 3.1 + a_pos.y * 1.7);
  gl_PointSize = max(1.0, a_data.x * u_pxScale * (14.0 / max(p.w, 1.0)));
  v_alpha = (1.0 - depth * 0.78) * twinkle;
  v_color = palette(a_data.y);
}`;
  const FS_SCENE = `
precision mediump float;
uniform float u_points;
uniform float u_alpha;
varying vec3 v_color;
varying float v_alpha;
void main(){
  float a = v_alpha * u_alpha;
  if (u_points > 0.5) {
    float d = length(gl_PointCoord - 0.5) * 2.0;
    float f = 1.0 - smoothstep(0.0, 1.0, d);
    a *= f * f;
  }
  gl_FragColor = vec4(v_color * a, a);
}`;
  /* ---------------------------------------------------------------- state */
  let gl = null, sceneProg = null, geo = null;
  const buf = {};
  const loc = {};
  let raf = 0, running = false, staticMode = false, lost = false;
  let lastFrame = 0, startedAt = 0, framesDrawn = 0, lastStatTime = 0, fps = 0, meanMs = 16.7;
  let winStart = 0, winFrames = 0, winSum = 0, winMax = 0, warmUntil = 0, ready = false;
  let dpr = 1, width = 0, height = 0, resizePending = false;
  let mx = 0, my = 0, cx = 0, cy = 0; // parallax target / current (-1..1)

  /* -------------------------------------------------------------- gl setup */
  function compile(type, src) {
    const sh = gl.createShader(type);
    gl.shaderSource(sh, src); gl.compileShader(sh);
    return sh; // status is read after linking, so a parallel compile never blocks here
  }
  function upload(name, data) {
    buf[name] = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf[name]);
    gl.bufferData(gl.ARRAY_BUFFER, data, gl.STATIC_DRAW);
  }

  function createContext() {
    gl = canvas.getContext('webgl', { alpha: true, premultipliedAlpha: true, antialias: false, depth: false, stencil: false, powerPreference: 'low-power', preserveDrawingBuffer: false })
      || canvas.getContext('experimental-webgl');
    if (!gl) throw new Error('WebGL unavailable');

    const vs = compile(gl.VERTEX_SHADER, VS_SCENE), fs = compile(gl.FRAGMENT_SHADER, FS_SCENE);
    sceneProg = gl.createProgram();
    gl.attachShader(sceneProg, vs); gl.attachShader(sceneProg, fs);
    gl.linkProgram(sceneProg);

    geo = geo || buildScene();
    upload('nodes', geo.nodes); upload('dust', geo.dust); upload('edges', geo.edges); upload('crystal', geo.crystal);

    gl.disable(gl.DEPTH_TEST); gl.disable(gl.CULL_FACE);
    gl.clearColor(0, 0, 0, 0);
  }

  // Resolves once the program is linked. With KHR_parallel_shader_compile the
  // browser compiles off-thread and we poll once per frame instead of blocking.
  function whenLinked(done, fail) {
    const ext = gl.getExtension('KHR_parallel_shader_compile');
    const t0 = performance.now();
    const check = () => {
      if (lost) return;
      if (ext && !gl.getProgramParameter(sceneProg, ext.COMPLETION_STATUS_KHR) && performance.now() - t0 < 4000) {
        requestAnimationFrame(check); return;
      }
      if (!gl.getProgramParameter(sceneProg, gl.LINK_STATUS)) {
        fail(new Error(gl.getProgramInfoLog(sceneProg) || 'program link failed')); return;
      }
      loc.scene = {
        pos: gl.getAttribLocation(sceneProg, 'a_pos'), data: gl.getAttribLocation(sceneProg, 'a_data'),
        mvp: gl.getUniformLocation(sceneProg, 'u_mvp'), px: gl.getUniformLocation(sceneProg, 'u_pxScale'),
        time: gl.getUniformLocation(sceneProg, 'u_time'), points: gl.getUniformLocation(sceneProg, 'u_points'),
        alpha: gl.getUniformLocation(sceneProg, 'u_alpha'),
      };
      done();
    };
    check();
  }

  /* --------------------------------------------------------------- sizing */
  function applySize() {
    resizePending = false;
    const cfg = TIERS[Math.max(tier, 0)];
    const cssW = Math.max(1, host.clientWidth || window.innerWidth);
    const cssH = Math.max(1, host.clientHeight || window.innerHeight);
    dpr = Math.min(window.devicePixelRatio || 1, cfg.dpr);
    const w = Math.max(1, Math.round(cssW * dpr)), h = Math.max(1, Math.round(cssH * dpr));
    if (w !== canvas.width || h !== canvas.height) { canvas.width = w; canvas.height = h; }
    width = w; height = h;
    if (gl) gl.viewport(0, 0, w, h);
  }
  function requestResize() {
    if (resizePending) return;
    resizePending = true;
    requestAnimationFrame(() => { applySize(); if (ready && !running) drawStill(); });
  }

  /* ----------------------------------------------------------------- draw */
  function bindAttribs(buffer, posLoc, dataLoc) {
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.enableVertexAttribArray(posLoc);
    gl.vertexAttribPointer(posLoc, 3, gl.FLOAT, false, 20, 0);
    gl.enableVertexAttribArray(dataLoc);
    gl.vertexAttribPointer(dataLoc, 2, gl.FLOAT, false, 20, 12);
  }

  function draw(t) {
    const cfg = TIERS[Math.max(tier, 0)];

    gl.clear(gl.COLOR_BUFFER_BIT);
    gl.useProgram(sceneProg);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA); // shader output is premultiplied
    const aspect = width / height;
    const proj = mat.perspective(aspect < 1 ? 1.22 : 0.92, aspect, 0.5, 80);
    const pxScale = Math.min(1.7, Math.max(0.6, dpr * (height / dpr / 900)));
    gl.uniform1f(loc.scene.px, pxScale);
    gl.uniform1f(loc.scene.time, t);

    cx += (mx * 0.9 - cx) * 0.045;
    cy += (my * 0.6 - cy) * 0.045;

    // constellation (nodes, edges) + dust share one rigid rotation
    const world = mat.mul(mat.translate(-cx, -cy, -13), mat.mul(mat.rotX(0.30 + cy * 0.04), mat.rotY(t * 0.055)));
    gl.uniformMatrix4fv(loc.scene.mvp, false, mat.mul(proj, world));

    if (cfg.dust > 0) {
      gl.uniform1f(loc.scene.points, 1); gl.uniform1f(loc.scene.alpha, 0.70);
      bindAttribs(buf.dust, loc.scene.pos, loc.scene.data);
      gl.drawArrays(gl.POINTS, 0, cfg.dust);
    }
    gl.uniform1f(loc.scene.points, 0); gl.uniform1f(loc.scene.alpha, 0.40);
    bindAttribs(buf.edges, loc.scene.pos, loc.scene.data);
    gl.drawArrays(gl.LINES, 0, geo.edgeCount);

    gl.uniform1f(loc.scene.points, 1); gl.uniform1f(loc.scene.alpha, 1.0);
    bindAttribs(buf.nodes, loc.scene.pos, loc.scene.data);
    gl.drawArrays(gl.POINTS, 0, cfg.nodes);

    // wireframe crystals, nearer the camera so they parallax more
    const narrow = Math.min(1, aspect / 1.4);
    gl.uniform1f(loc.scene.points, 0); gl.uniform1f(loc.scene.alpha, 0.46);
    bindAttribs(buf.crystal, loc.scene.pos, loc.scene.data);
    const ico = mat.mul(mat.translate(-6.6 * narrow - cx * 1.3, 2.7 - cy * 1.3, -10),
      mat.mul(mat.rotY(t * 0.16), mat.mul(mat.rotX(t * 0.11), mat.scale(2.3))));
    gl.uniformMatrix4fv(loc.scene.mvp, false, mat.mul(proj, ico));
    gl.drawArrays(gl.LINES, 0, geo.icoCount);
    const oct = mat.mul(mat.translate(6.8 * narrow - cx * 1.3, -3.1 - cy * 1.3, -9),
      mat.mul(mat.rotY(-t * 0.13), mat.mul(mat.rotX(t * 0.09 + 0.5), mat.scale(1.8))));
    gl.uniformMatrix4fv(loc.scene.mvp, false, mat.mul(proj, oct));
    gl.drawArrays(gl.LINES, geo.icoCount, geo.octCount);
  }

  function drawStill() {
    if (!ready || lost) return;
    draw(8.0);
    markReady();
  }

  function markReady() { if (!host.classList.contains('is-ready')) host.classList.add('is-ready'); }

  /* ------------------------------------------------- loop + quality guard */
  function resetWindow(now) { winStart = 0; winFrames = 0; winSum = 0; winMax = 0; warmUntil = now + 1200; }

  function setStill(on) { host.classList.toggle('cc-3d-still', on); } // also freezes the CSS aurora drift

  function degrade() {
    if (tier > 0) {
      tier -= 1; applySize();
      if (tier === 0) setStill(false);
    } else {
      // Already at the lowest tier and still slow: stop animating, keep a still frame.
      staticMode = true; stop(); applySize(); setStill(true); drawStill();
    }
    host.dataset.tier = staticMode ? 'static' : TIERS[tier].name;
    resetWindow(performance.now());
  }

  // Judge quality over a 2.5 s wall-clock window so a handful of slow frames
  // (very slow devices render <2 fps) is still enough to react.
  function monitor(now, dt) {
    if (now < warmUntil) return; // shader compile / first-frame cost
    winFrames++; winSum += dt; if (dt > winMax) winMax = dt;
    if (!winStart) { winStart = now; return; }
    if (now - winStart < 2500) return;
    meanMs = winFrames >= 4 ? (winSum - winMax) / (winFrames - 1) : winSum / winFrames; // drop one outlier when we can
    const limit = TIERS[tier].frameMs ? 52 : 26; // ms: ~19 fps floor at the 30 fps tier, ~38 fps otherwise
    winStart = 0; winFrames = 0; winSum = 0; winMax = 0;
    if (meanMs > limit) degrade();
  }

  function frame(now) {
    raf = requestAnimationFrame(frame);
    const cfg = TIERS[Math.max(tier, 0)];
    if (lastFrame && now - lastFrame < cfg.frameMs - 2) return;
    const dt = lastFrame ? now - lastFrame : 16.7;
    lastFrame = now;

    monitor(now, dt);
    if (!running) return; // monitor() may have switched to the still frame

    draw((now - startedAt) / 1000);
    markReady();

    framesDrawn++;
    if (now - lastStatTime >= 1000) { fps = Math.round(framesDrawn * 1000 / (now - lastStatTime)); framesDrawn = 0; lastStatTime = now; }
  }

  function start() {
    if (running || staticMode || lost || !ready) return;
    running = true; lastFrame = 0; lastStatTime = performance.now(); framesDrawn = 0;
    if (!startedAt) startedAt = performance.now();
    resetWindow(performance.now());
    raf = requestAnimationFrame(frame);
  }
  function stop() { running = false; if (raf) cancelAnimationFrame(raf); raf = 0; }

  function shouldAnimate() {
    return !document.hidden && !reducedMotion.matches && !saveData && !staticMode;
  }
  function sync() {
    if (!ready || lost) return;
    if (shouldAnimate()) { setStill(false); start(); }
    else { stop(); setStill(true); if (!document.hidden) drawStill(); }
  }

  /* ----------------------------------------------------------------- init */
  function fallback(reason) {
    host.classList.add('cc-3d-fallback', 'cc-3d-still');
    host.dataset.tier = 'fallback';
    if (window.console && console.warn) console.warn('[3D background] using CSS fallback:', reason);
  }

  function init() {
    try {
      createContext();
    } catch (err) { fallback(err && err.message); return; }

    host.dataset.tier = TIERS[tier].name;

    window.addEventListener('resize', requestResize, { passive: true });
    if (window.ResizeObserver) new ResizeObserver(requestResize).observe(host);
    window.addEventListener('pointermove', (e) => {
      if (e.pointerType === 'touch') return;
      mx = (e.clientX / window.innerWidth) * 2 - 1;
      my = (e.clientY / window.innerHeight) * 2 - 1;
    }, { passive: true });
    document.addEventListener('visibilitychange', sync);
    window.addEventListener('pagehide', stop);
    window.addEventListener('pageshow', sync);
    if (reducedMotion.addEventListener) reducedMotion.addEventListener('change', sync);

    canvas.addEventListener('webglcontextlost', (e) => { e.preventDefault(); lost = true; ready = false; stop(); host.classList.remove('is-ready'); }, false);
    canvas.addEventListener('webglcontextrestored', () => {
      lost = false; ready = false;
      try { createContext(); whenLinked(() => { ready = true; applySize(); sync(); }, (err) => fallback(err && err.message)); }
      catch (err) { fallback(err && err.message); }
    }, false);

    whenLinked(() => { ready = true; applySize(); sync(); }, (err) => fallback(err && err.message));
  }

  // Let the page paint first; the scene fades in afterwards.
  const begin = () => requestAnimationFrame(() => requestAnimationFrame(init));
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', begin, { once: true });
  else begin();

  // Small debug surface (used by the automated checks; harmless in production).
  window.ccScene3D = {
    stats: () => ({ tier: staticMode ? 'static' : (TIERS[tier] || {}).name, fps, meanMs: Math.round(meanMs * 10) / 10, running, width, height, dpr }),
    forceTier: (n) => { tier = Math.max(0, Math.min(2, n | 0)); staticMode = false; applySize(); host.dataset.tier = TIERS[tier].name; sync(); },
  };
})();
