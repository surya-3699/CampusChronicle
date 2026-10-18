# Scaling: handling 500+ simultaneous requests

## The layers (outside in)

```
Browsers ──► Apache 2.4, event MPM ──► Request Gateway ──► PHP-FPM pool ──► MySQL
 (500+)      holds 700+ connections    rate limit,          fixed number     never sees more
             on a few threads;         admission queue,     of PHP workers   connections than
             serves static files       priority lanes,                       the pool size
             directly (cached)         503 + Retry-After
```

| Layer | File | What it protects against |
|---|---|---|
| Apache event MPM | `deploy/mpm_event.conf`, `deploy/campus-tuning.conf` | Thousands of idle/slow connections each pinning a 30 MB process. 12 processes x 64 threads = **768 request workers**, plus async keep-alive. Slow-client (slowloris) timeouts. |
| Static caching | `deploy/apache.conf` | CSS/JS/images/uploads never reach PHP and are cached by browsers (CSS/JS revalidate after 5 min). |
| Real client IP | `deploy/apache.conf` (`mod_remoteip`) | Behind Render's proxy every visitor would share one IP, making per-IP limits global. `X-Forwarded-For` is trusted **only** from private proxy addresses. |
| PHP-FPM pool | `deploy/php-fpm-pool.conf` | Caps concurrent PHP/DB work. Fixed-size (`pm = static`) so full capacity exists the instant a burst lands. Backlog of 2048 queues the overflow cheaply. |
| **Request Gateway** (the "middle manager") | `config/gateway.php`, called first in `index.php` | Runs before session/DB. Health probe, per-IP rate limits, admission control (bounded concurrent executions + short queue), reserved priority lane for admin/login/POST, friendly 503/429 with `Retry-After`. |
| Database layer | `config/database.php` | 5 s connect timeout, jittered retry on "too many connections", 503 + `Retry-After` instead of a bare 500. Optional `DB_PERSISTENT=1`. |
| Hot paths | `Notification::broadcast`, `Registration::register` | Broadcast is one `INSERT ... SELECT` (was one INSERT per user). Registration retries deadlock/lock-timeout victims (a rush on one popular event). |

## What happens at 500+ at once

1. All 500 connections are accepted by Apache (event MPM) without 500 processes.
2. Static assets are served directly; only page requests continue to PHP.
3. Requests queue in the FPM backlog and are served FIFO by the fixed pool.
4. The gateway lets `GATEWAY_MAX_ACTIVE` requests run the app; others wait up to `GATEWAY_QUEUE_MS`, then receive a 503 page that retries automatically. Admin, sign-in and POST requests use a reserved slice (`GATEWAY_RESERVED`, default 25 %) and wait twice as long, so a public burst cannot lock out staff.
5. MySQL sees at most `FPM_MAX_CHILDREN` connections. Keep `max_connections` above that.

## Sizing (set automatically at start-up from container memory)

`deploy/start.sh` computes `FPM_MAX_CHILDREN = (memory_MB - 200) / 40` (min 4, max 64) and
`GATEWAY_MAX_ACTIVE = children - children/8`. Override both in the Render dashboard if needed.

| Instance memory | FPM workers | Rough sustained pages/s* |
|---|---|---|
| 512 MB (Render Starter) | 7 | ~100-150 |
| 1 GB | 20 | ~300-400 |
| 2 GB | 45 | ~700-900 |

\*Planning estimate = workers / average page time (about 50 ms). Measure your real number with the load test below.

**Important:** the architecture accepts 500+ simultaneous connections on any plan, but a small instance *executes* only a handful of pages at once. On 512 MB a 500-request burst is absorbed and served within a few seconds; a *sustained* 500 simultaneous page loads needs a larger instance. The gateway makes the overload graceful (queue, then retry) instead of a crash.

## Settings

| Variable | Default | Meaning |
|---|---|---|
| `GATEWAY_ENABLED` | 1 | 0 switches the gateway off |
| `GATEWAY_MAX_ACTIVE` | auto | Concurrent application executions |
| `GATEWAY_RESERVED` | 25 % | Slots held back for priority traffic |
| `GATEWAY_QUEUE_MS` | 1500 | Max queue wait for public requests (priority: x2) |
| `GATEWAY_RATE_GET` / `GATEWAY_RATE_POST` | 600 / 120 | Per-IP requests per 10 s. Generous on purpose: a campus can share one public IP |
| `FPM_MAX_CHILDREN` | auto | PHP-FPM pool size |
| `DB_PERSISTENT` | 0 | 1 = reuse MySQL connections per worker |

## Verify on the real deployment

```bash
# health: answered by the gateway with no session/DB. Shows busy/capacity.
curl https://YOUR-APP/index.php?page=healthz

# 600 simultaneous clients
python3 tools/loadtest.py https://YOUR-APP --concurrency 600 --requests 3000
python3 tools/loadtest.py https://YOUR-APP -c 600 -n 3000 --paths "/index.php?page=home,/index.php?page=events"
```

Goal: **errors < 1 %** with >= 500 in flight. `429`/`503` are counted separately: they are deliberate, fast, retryable answers.
Your own machine sends from one IP, so the per-IP limit (`GATEWAY_RATE_GET`) can answer `429` during a test; raise it temporarily (e.g. 100000) to test raw capacity.
Responses that waited in the queue carry `X-Gateway-Wait: <ms>`.

After first deploy, confirm the client-IP setting: sign in from two different networks and check the Render logs show different client addresses (not one private address).

## Rollback

Set `dockerfilePath: ./Dockerfile.prefork` in `render.yaml` to return to the previous Apache + mod_php image.
`GATEWAY_ENABLED=0` disables only the gateway.

## Notes

* Keep `CC_AUTO_MIGRATE=0` in production: when `1`, every request runs the schema checker.
* Apache streams request bodies, so a very slow 50 MB admin upload occupies a PHP worker while it arrives. The reserved priority lane keeps admins working during public bursts.
* Cache-friendly pages: dynamic HTML embeds a per-visitor CSRF token, so it is deliberately **not** cached at the proxy.
