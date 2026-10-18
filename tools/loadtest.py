#!/usr/bin/env python3
"""
Campus Chronicle load test — verifies the "500+ simultaneous requests" target.

Pure standard library (Python 3.8+). All clients are released at the same
instant so the server really sees N requests in flight at once.

Examples
  # 600 simultaneous clients, 3 000 requests, against the live site
  python3 tools/loadtest.py https://your-app.onrender.com --concurrency 600 --requests 3000

  # health endpoint only (gateway, no session, no database)
  python3 tools/loadtest.py https://your-app.onrender.com --paths "/index.php?page=healthz"

  # a realistic mix of public pages
  python3 tools/loadtest.py https://your-app.onrender.com \
      --paths "/index.php?page=home,/index.php?page=events,/index.php?page=gallery"

Reading the result
  2xx/3xx   served
  503 / 429 deliberately shed or rate-limited by the request gateway (with
            Retry-After). A healthy overloaded server answers these quickly.
  errors    connection failures, timeouts, other 5xx. This is what to drive to 0.

Only test sites you own. The gateway's per-IP rate limit (GATEWAY_RATE_GET)
will, by design, start returning 429 when one machine sends a flood.
"""
import argparse
import asyncio
import ssl
import sys
import time
from urllib.parse import urlsplit


def pct(sorted_values, p):
    if not sorted_values:
        return 0.0
    k = min(len(sorted_values) - 1, max(0, int(round(p / 100.0 * len(sorted_values) + 0.5)) - 1))
    return sorted_values[k]


async def one_request(host, port, use_tls, ctx, path, timeout):
    """One HTTP/1.1 request on a fresh connection. Returns (status, seconds, kind)."""
    started = time.perf_counter()
    writer = None
    try:
        reader, writer = await asyncio.wait_for(
            asyncio.open_connection(host, port, ssl=ctx if use_tls else None, server_hostname=host if use_tls else None),
            timeout,
        )
        req = (
            f"GET {path} HTTP/1.1\r\nHost: {host}\r\n"
            "User-Agent: CampusChronicle-LoadTest/1.0\r\nAccept: text/html,application/json\r\n"
            "Connection: close\r\n\r\n"
        )
        writer.write(req.encode())
        await writer.drain()
        head = await asyncio.wait_for(reader.readuntil(b"\r\n\r\n"), timeout)
        status = int(head.split(b" ", 2)[1])
        # drain the body so the server finishes the response
        await asyncio.wait_for(reader.read(), timeout)
        return status, time.perf_counter() - started, "ok"
    except asyncio.TimeoutError:
        return 0, time.perf_counter() - started, "timeout"
    except Exception as exc:  # connection refused/reset, TLS errors, ...
        return 0, time.perf_counter() - started, type(exc).__name__
    finally:
        if writer is not None:
            try:
                writer.close()
            except Exception:
                pass


async def run(args):
    parts = urlsplit(args.url)
    use_tls = parts.scheme == "https"
    host = parts.hostname
    port = parts.port or (443 if use_tls else 80)
    base_path = parts.path.rstrip("/")
    paths = [p.strip() for p in args.paths.split(",") if p.strip()]
    paths = [(base_path + p) if p.startswith("/") else p for p in paths]

    ctx = None
    if use_tls:
        ctx = ssl.create_default_context()
        if args.insecure:
            ctx.check_hostname = False
            ctx.verify_mode = ssl.CERT_NONE

    total = args.requests
    state = {"issued": 0, "inflight": 0, "peak": 0}
    results = []
    go = asyncio.Event()
    deadline = [None]

    async def worker(n):
        await go.wait()
        while True:
            if deadline[0] is not None:
                if time.perf_counter() >= deadline[0]:
                    return
            elif state["issued"] >= total:
                return
            i = state["issued"]
            state["issued"] += 1
            state["inflight"] += 1
            state["peak"] = max(state["peak"], state["inflight"])
            try:
                results.append(await one_request(host, port, use_tls, ctx, paths[i % len(paths)], args.timeout))
            finally:
                state["inflight"] -= 1

    tasks = [asyncio.create_task(worker(n)) for n in range(args.concurrency)]
    await asyncio.sleep(0.2)  # let every worker park at the starting line
    t0 = time.perf_counter()
    if args.duration:
        deadline[0] = t0 + args.duration
    go.set()
    await asyncio.gather(*tasks)
    wall = time.perf_counter() - t0
    return results, wall, state["peak"], paths


def report(results, wall, peak, concurrency, paths):
    served = [r for r in results if 200 <= r[0] < 400]
    shed = [r for r in results if r[0] in (429, 503)]
    errors = [r for r in results if r not in served and r not in shed]
    lat = sorted(r[1] for r in served)

    by_status = {}
    for r in results:
        key = str(r[0]) if r[0] else r[2]
        by_status[key] = by_status.get(key, 0) + 1

    print(f"\nTarget concurrency      : {concurrency}")
    print(f"Peak in flight (client) : {peak}")
    print(f"Paths                   : {', '.join(paths)}")
    print(f"Requests completed      : {len(results)} in {wall:.2f}s  ({len(results) / wall:.0f} req/s)")
    print(f"  served (2xx/3xx)      : {len(served)}")
    print(f"  shed/limited (429/503): {len(shed)}")
    print(f"  errors                : {len(errors)}")
    print(f"Status breakdown        : {dict(sorted(by_status.items()))}")
    if lat:
        print(f"Latency of served (ms)  : p50 {pct(lat, 50) * 1000:.0f}   p95 {pct(lat, 95) * 1000:.0f}   p99 {pct(lat, 99) * 1000:.0f}   max {lat[-1] * 1000:.0f}")
    err_rate = len(errors) / max(1, len(results))
    verdict = "PASS" if err_rate <= 0.01 and peak >= min(concurrency, 500) else "CHECK"
    print(f"\nResult: {verdict}  (error rate {err_rate * 100:.2f}%, goal: <1% errors with >=500 in flight)")
    return 0 if verdict == "PASS" else 1


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("url", help="Site root, e.g. https://your-app.onrender.com")
    ap.add_argument("--concurrency", "-c", type=int, default=600, help="simultaneous clients (default 600)")
    ap.add_argument("--requests", "-n", type=int, default=3000, help="total requests (default 3000)")
    ap.add_argument("--duration", "-d", type=float, default=0, help="run for N seconds instead of a request count")
    ap.add_argument("--paths", default="/index.php?page=healthz", help="comma-separated paths, cycled through")
    ap.add_argument("--timeout", type=float, default=30, help="per-request timeout in seconds (default 30)")
    ap.add_argument("--insecure", action="store_true", help="skip TLS certificate verification")
    args = ap.parse_args()

    try:
        results, wall, peak, paths = asyncio.run(run(args))
    except KeyboardInterrupt:
        sys.exit(130)
    sys.exit(report(results, wall, peak, args.concurrency, paths))


if __name__ == "__main__":
    main()
