#!/usr/bin/env python3
"""One-shot broken-link crawler for ric.theahg.co.za + openric.org.

Crawls each site's internal pages, extracts every <a href>, tests each unique
URL (HEAD, fall back to GET on 405/403), prints a report. Stdlib only.

On broken links, sends a digest email to NOTIFY_TO via /usr/sbin/sendmail.
"""
from __future__ import annotations

import os
import socket
import subprocess
import sys
import time
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
from email.message import EmailMessage
from html.parser import HTMLParser
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urldefrag, urlparse
from urllib.request import Request, urlopen

SEEDS = ["https://ric.theahg.co.za/", "https://openric.org/"]
INTERNAL_HOSTS = {"ric.theahg.co.za", "openric.org"}
TIMEOUT = 15
UA = "OpenRiC-LinkCrawler/1.0 (+mailto:johan@theahg.co.za)"
MAX_WORKERS = 16

NOTIFY_TO = "johan@theahg.co.za"
NOTIFY_FROM = "OpenRiC Link Crawler <pieterse.johan3@gmail.com>"
SENDMAIL = "/usr/sbin/sendmail"


class LinkExtractor(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.links: list[tuple[str, str]] = []
        self._in_a = False
        self._cur_href: str | None = None
        self._cur_text: list[str] = []

    def handle_starttag(self, tag, attrs):
        if tag == "a":
            href = dict(attrs).get("href")
            if href:
                self._in_a = True
                self._cur_href = href
                self._cur_text = []

    def handle_endtag(self, tag):
        if tag == "a" and self._in_a:
            self.links.append((self._cur_href or "", "".join(self._cur_text).strip()))
            self._in_a = False
            self._cur_href = None
            self._cur_text = []

    def handle_data(self, data):
        if self._in_a:
            self._cur_text.append(data)


def fetch(url: str, method: str = "GET") -> tuple[int, str, str]:
    """Return (status, final_url, body_or_err). body='' for HEAD."""
    req = Request(url, method=method, headers={"User-Agent": UA, "Accept": "*/*"})
    try:
        with urlopen(req, timeout=TIMEOUT) as resp:
            body = resp.read().decode("utf-8", errors="replace") if method == "GET" else ""
            return resp.status, resp.geturl(), body
    except HTTPError as e:
        return e.code, url, ""
    except (URLError, TimeoutError, ConnectionError, socket.timeout) as e:
        return 0, url, f"{type(e).__name__}: {e}"


def normalize(url: str) -> str:
    url, _ = urldefrag(url)
    return url.rstrip("/") if urlparse(url).path in ("/", "") else url


def is_testable(url: str) -> bool:
    return urlparse(url).scheme.lower() in ("http", "https")


def crawl_internal(seed: str, discovered_links: dict[str, set[str]]) -> set[str]:
    host = urlparse(seed).netloc
    seen_pages: set[str] = set()
    queue: list[str] = [seed]

    while queue:
        page = queue.pop(0)
        page_n = normalize(page)
        if page_n in seen_pages:
            continue
        seen_pages.add(page_n)

        status, final_url, body = fetch(page, method="GET")
        if status != 200 or not body:
            continue

        parser = LinkExtractor()
        try:
            parser.feed(body)
        except Exception:
            continue

        for href, _text in parser.links:
            absolute = urljoin(final_url, href)
            if not is_testable(absolute):
                continue
            absolute_n = normalize(absolute)
            discovered_links[absolute_n].add(page_n)
            if urlparse(absolute_n).netloc == host and absolute_n not in seen_pages:
                queue.append(absolute_n)

    return seen_pages


def test_link(url: str) -> tuple[str, int, str]:
    status, _final, err = fetch(url, method="HEAD")
    if status in (0, 403, 405, 501):
        status, _final, err = fetch(url, method="GET")
    return url, status, err


def format_report(
    broken: list[tuple[str, int, str, list[str]]],
    elapsed: float,
    total_links: int,
    total_pages: int,
) -> str:
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")
    lines = [
        f"OpenRiC link crawl — {now}",
        f"Seeds:   {', '.join(SEEDS)}",
        f"Summary: {total_pages} pages walked, {total_links} unique links tested,",
        f"         {len(broken)} broken, {elapsed:.1f}s elapsed.",
        "",
    ]
    if not broken:
        lines.append("No broken links.")
        return "\n".join(lines)

    lines.append("Broken links:")
    lines.append("")
    for url, status, err, refs in sorted(broken, key=lambda x: (x[1], x[0])):
        label = f"HTTP {status}" if status else "NETWORK"
        lines.append(f"  [{label}] {url}")
        if err:
            lines.append(f"          {err}")
        for r in refs[:5]:
            lines.append(f"          referred from: {r}")
        if len(refs) > 5:
            lines.append(f"          (+{len(refs) - 5} more referrers)")
        lines.append("")
    return "\n".join(lines)


def send_email_report(subject: str, body: str) -> bool:
    if not os.path.exists(SENDMAIL):
        print(f"[mail] {SENDMAIL} not found — skipping email", flush=True)
        return False
    msg = EmailMessage()
    msg["Subject"] = subject
    msg["From"] = NOTIFY_FROM
    msg["To"] = NOTIFY_TO
    msg.set_content(body)
    try:
        proc = subprocess.run(
            [SENDMAIL, "-t", "-i"],
            input=msg.as_bytes(),
            timeout=30,
            capture_output=True,
        )
        if proc.returncode != 0:
            stderr = proc.stderr.decode("utf-8", errors="replace").strip()
            print(f"[mail] sendmail exit {proc.returncode}: {stderr}", flush=True)
            return False
        print(f"[mail] sent to {NOTIFY_TO}", flush=True)
        return True
    except (OSError, subprocess.TimeoutExpired) as e:
        print(f"[mail] failed: {type(e).__name__}: {e}", flush=True)
        return False


def main() -> int:
    print(f"[crawl] seeds: {SEEDS}", flush=True)
    discovered: dict[str, set[str]] = defaultdict(set)
    total_pages = 0

    t0 = time.time()
    for seed in SEEDS:
        print(f"[crawl] walking {seed} ...", flush=True)
        pages = crawl_internal(seed, discovered)
        total_pages += len(pages)
        print(f"[crawl]   {len(pages)} internal pages fetched from {seed}", flush=True)

    total_links = len(discovered)
    print(f"[crawl] {total_links} unique links discovered; testing ...", flush=True)

    broken: list[tuple[str, int, str, list[str]]] = []
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as pool:
        futures = {pool.submit(test_link, u): u for u in discovered}
        done = 0
        for fut in as_completed(futures):
            url, status, err = fut.result()
            done += 1
            if done % 25 == 0:
                print(f"[crawl]   tested {done}/{total_links}", flush=True)
            if status < 200 or status >= 400:
                broken.append((url, status, err, sorted(discovered[url])))

    elapsed = time.time() - t0
    print(f"[crawl] done in {elapsed:.1f}s — {len(broken)} broken\n", flush=True)

    report = format_report(broken, elapsed, total_links, total_pages)
    print(report)

    if broken:
        subject = f"[OpenRiC link crawl] {len(broken)} broken link(s)"
        send_email_report(subject, report)
        return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
