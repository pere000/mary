#!/usr/bin/env python3
"""Run the Mary / thelady music app on this computer (Linux, Windows, macOS).

Usage:  python mary.py            (or python3 mary.py)
        python mary.py --port 9000 --no-browser

Needs only Python 3.8+. No PHP, Docker or extra packages. The server listens on
127.0.0.1 only, and includes a local replacement for tace-fetch.php so remote
playlists load when the page runs on localhost.
"""
import argparse
import ipaddress
import os
import re
import socket
import sys
import threading
import urllib.error
import urllib.parse
import urllib.request
import webbrowser
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer

ROOT = os.path.dirname(os.path.abspath(__file__))
APP = "thelady/music.html"
MAX_BYTES = 10 * 1024 * 1024
TIMEOUT = 20
PLAYLIST_RE = re.compile(r"\.(csv|m3u|m3u8)$", re.I)


def is_public_host(host):
    try:
        infos = socket.getaddrinfo(host, None)
    except socket.gaierror:
        return False
    for info in infos:
        ip = ipaddress.ip_address(info[4][0])
        if not ip.is_global:
            return False
    return True


def check_url(url):
    parts = urllib.parse.urlparse(url)
    if parts.scheme not in ("http", "https") or not parts.hostname:
        return "Only HTTP and HTTPS URLs are allowed."
    if not PLAYLIST_RE.search(parts.path):
        return "Only CSV, M3U and M3U8 resources may be proxied."
    if not is_public_host(parts.hostname):
        return "Private or local network targets are not allowed."
    return None


class CheckedRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        problem = check_url(newurl)
        if problem:
            raise urllib.error.URLError("Unsafe redirect: " + problem)
        return super().redirect_request(req, fp, code, msg, headers, newurl)


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=ROOT, **kwargs)

    def end_headers(self):
        self.send_header("Cache-Control", "no-store")
        super().end_headers()

    def text(self, code, message):
        body = message.encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "text/plain; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        path = urllib.parse.urlparse(self.path).path
        if path.endswith("/tace-fetch.php"):
            return self.proxy()
        # Never expose helper scripts or dotfiles.
        name = os.path.basename(path)
        if name.startswith(".") or name.endswith((".scr", "~", ".py")):
            return self.text(403, "Forbidden")
        if path in ("", "/"):
            self.send_response(302)
            self.send_header("Location", "/" + APP)
            self.end_headers()
            return
        return super().do_GET()

    def proxy(self):
        query = urllib.parse.parse_qs(urllib.parse.urlparse(self.path).query)
        url = (query.get("url") or [""])[0]
        problem = check_url(url)
        if problem:
            return self.text(400, problem)
        opener = urllib.request.build_opener(CheckedRedirect)
        request = urllib.request.Request(url, headers={
            "User-Agent": "TACE playlist fetcher",
            "Accept": "text/plain, text/csv, application/vnd.apple.mpegurl, */*",
        })
        try:
            with opener.open(request, timeout=TIMEOUT) as response:
                body = response.read(MAX_BYTES + 1)
                ctype = response.headers.get("Content-Type", "text/plain")
        except Exception as error:
            return self.text(502, "Remote playlist fetch failed: %s" % error)
        if len(body) > MAX_BYTES:
            return self.text(413, "Playlist is too large.")
        self.send_response(200)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, fmt, *args):
        sys.stderr.write("  %s\n" % (fmt % args))


def main():
    parser = argparse.ArgumentParser(description="Run the Mary music app locally.")
    parser.add_argument("--port", type=int, default=8080)
    parser.add_argument("--no-browser", action="store_true")
    args = parser.parse_args()

    if sys.version_info < (3, 8):
        sys.exit("Python 3.8 or newer is required. Get it from https://www.python.org/downloads/")
    if not os.path.isfile(os.path.join(ROOT, APP)):
        sys.exit("Cannot find %s next to mary.py. Keep mary.py in the folder that contains 'thelady'." % APP)

    try:
        server = ThreadingHTTPServer(("127.0.0.1", args.port), Handler)
    except OSError:
        sys.exit("Port %d is busy. Try: python mary.py --port 9000" % args.port)

    url = "http://localhost:%d/%s" % (args.port, APP)
    print("Mary is running at:\n  %s\nPress Ctrl+C to stop.\n" % url)
    if not args.no_browser:
        threading.Timer(0.8, webbrowser.open, args=(url,)).start()
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopped.")


if __name__ == "__main__":
    main()
