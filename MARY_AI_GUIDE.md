# Mary — Guide for AI Assistants

**Purpose.** Upload or paste this file into an AI assistant (ChatGPT, Gemini, Copilot, Claude, etc.) so it can guide a person who installs, runs, publishes, troubleshoots or extends the **Mary** app. Treat this document as the source of truth. When something is marked *(unverified)*, say so to the user instead of asserting it.

---

## 1. How to behave

1. Ask first: **operating system** (Windows / Linux / macOS / other), **which step** they are on, and the **exact error message**.
2. Give **one step at a time**, with the exact command for their OS. Wait for the result before continuing.
3. Windows: use **Command Prompt (cmd)** commands, not PowerShell, unless asked. Linux/macOS/other: POSIX/GNU shell commands.
4. Never ask for passwords or tokens. The surge.sh password is typed only into the user's own terminal.
5. Do not invent files, flags or features. If you are unsure, tell the user to check the file named in this guide.
6. For development questions, read section 9 before proposing changes.

---

## 2. What Mary is

A browser music player and resource library. It is **static HTML/JavaScript** (no build step, no framework, no database server). The main file is one large page:

- `thelady/music.html` (about 8,700 lines, ~200 KB, all CSS and JS inline).

It plays:

- local audio files chosen with the **Load** button (mp3, mp4, ogg, ...),
- audio stream / file URLs,
- playlists in **CSV** and **M3U** format,
- **YouTube** videos and playlists (via the YouTube IFrame API).

Playlists can be passed in the URL: `music.html?source=<url-or-path>`.

---

## 3. Repository layout

```
mary/                        <- repository root (clone this)
├── index.html               redirects "/" to /thelady/music.html (keeps ?query and #hash)
├── install.html             guided browser installer (per-OS steps, progress in IndexedDB)
├── mary.py                  pure-Python local server (stdlib only)
├── Dockerfile, apache.conf, .dockerignore   Docker image (php:8.3-apache)
├── .surgeignore             files NOT published by surge.sh
├── MARY_AI_GUIDE.md         this file
├── surgeUp.scr, serverPHP.scr, *.scr        author's personal helper scripts
└── thelady/                 THE APP
    ├── music.html           main app
    ├── tace-fetch.php       CORS proxy for remote playlists (PHP servers only)
    ├── csv/  m3u/           playlists
    └── htm/ md/ txt/ img/ fonts/ favicon.ico   resources
```

The TACE governance documents are **not** in this repo; they live in https://github.com/pere000/pretace (folder `docs/`).

---

## 4. Ways to run Mary

After installation, Mary runs in the browser in one of these ways:

1. Directly from `thelady/music.html` (very restricted: local audio files only).
2. From a local web server of the user's choice: **A.** Python (`mary.py`, the default in the install steps), **B.** the PHP built-in web server (`php -S`), or **C.** any server the user personally possesses.
3. From a non-profit public HTML server based on surge.sh (the app folders can also be uploaded to any web server, at the user's own risk).

Details:

| Option | Command | Remote playlists (`?source=https://...`) | Needs |
|---|---|---|---|
| 1. Open the file directly | double-click `thelady/music.html` | No. Very restricted: local audio via **Load/Stop** only | a browser |
| 2A. Python server | `python mary.py` (Windows: `python` or `py`; Linux/macOS: `python3 mary.py`) | Yes (built-in Python proxy) | Python 3.8+ |
| 2B. PHP built-in server | `php -S localhost:8000` in the repo root | Yes (`tace-fetch.php`) | PHP |
| 2C. Any server you own (Apache, nginx, ...) | serve the repo root | Yes if PHP is available for `tace-fetch.php` | server + PHP |
| 3. Docker/Podman image | see section 7 | Yes | Docker or Podman |
| 3b. Ready-made download (no Python/git/PHP) | unzip `Mary-<windows|linux|macos>.zip` from the GitHub Releases page, run `mary.exe` (Windows) or `./mary` (Linux/macOS) | Yes (same built-in proxy) | a browser |
| 4. Published copy on surge.sh (or any web host you upload the folders to, at your own risk) | see section 6 | Direct fetch only; **no proxy** (static host) | Node.js |

URLs once running: `http://localhost:8080/thelady/music.html` (Python/Docker) or `http://localhost:8000/thelady/music.html` (PHP).

### Ready-made downloads (`mary.exe`)
`.github/workflows/release.yml` builds, with PyInstaller, a single-file `mary` program for Windows, Linux and macOS and zips each next to the `thelady` folder (`Mary-windows.zip`, `Mary-linux.zip`, `Mary-macos.zip`). They are attached to a GitHub Release when a tag such as `v1.0` is pushed (`git tag v1.0 && git push origin v1.0`), or built on demand with the workflow's "Run workflow" button. Users unzip and double-click `mary.exe` (Windows) or run `./mary` (Linux/macOS; may need `chmod +x mary`). The program looks for `thelady` **next to itself**. The files are unsigned: Windows SmartScreen ("More info" → "Run anyway") and macOS Gatekeeper (right-click → Open) may warn, and some antivirus tools flag PyInstaller programs. Same options as `mary.py`: `--port N`, `--no-browser`.

### `mary.py`
- Serves the repo folder on `127.0.0.1:8080`, redirects `/` to `/thelady/music.html`, opens the browser.
- Options: `--port N`, `--no-browser`.
- Implements its own proxy at the `tace-fetch.php` URL, so the page works unchanged. Only `csv`, `m3u`, `m3u8` are proxied; private IPs and unsafe redirects are blocked; 10 MB cap.
- Returns 403 for `.scr`, `.py`, files ending in `~`, and dotfiles.
- **Must be run from inside the repo folder** (the one containing `mary.py` and `thelady`).
- Python never runs PHP. Mary's `.php` file is only for PHP servers.

---

## 5. Installation (step order)

Easiest route: download the ready-made zip (section 4, row 3b) and skip steps 1-6. Otherwise requirements: **git** (always) and a way to serve the app (Python, PHP, or your own server). Docker/Podman is optional but recommended.

1. **Check git:** `git --version`.
2. **Install git** if missing. Windows: `winget install Git.Git` (or git-scm.com). Debian/Ubuntu: `sudo apt install git`. Fedora: `sudo dnf install git`. Arch: `sudo pacman -S git`. macOS: `xcode-select --install` or `brew install git`.
3. **Clone:** `git clone https://github.com/pere000/mary.git` then `cd mary`. *(No GitHub account is needed to clone a public repo. The repo is public at https://github.com/pere000/mary. Alternative without git: "Code → Download ZIP" on that page.)*
4. *(Optional)* Publish your own copy to surge.sh (section 6).
5. **Check/install Python** (`python --version` or `python3 --version`, 3.8+). Windows: `winget install Python.Python.3.12` or python.org (tick "Add python.exe to PATH"). Linux: `sudo apt install python3`. macOS: `brew install python`.
6. **Run:** `python mary.py` (Windows) or `python3 mary.py`.

The guided version of this is `install.html`. Progress is stored in the browser's IndexedDB database `mary-install` (stores `session` and `log`). "Start over" clears it.

Without installing anything: `thelady/music.html` opened directly still plays local audio files.

---

## 6. Publishing your own copy on surge.sh

Needs **Node.js** (not Python). Run inside the cloned folder:

```
node --version                       # check
npx surge . yourDesiredName.surge.sh
```

- First run asks for an email and password: this creates a free account. The password is not shown while typing.
- Names are lowercase letters, digits and hyphens. A name already owned by someone else is refused: choose another.
- Windows: use cmd; PowerShell may block `npx` through the execution policy.
- Linux: do not use `sudo` with `npx` (EACCES errors). Fix npm folder permissions or install Node with `nvm`.
- `.surgeignore` lists what is NOT uploaded (one path per line, leading `/`). It excludes the author's `.scr` scripts, `tace-fetch.php`, `mary.py`, `install.html`, Docker files.
- The root `index.html` redirects to `/thelady/music.html`, so `https://yourDesiredName.surge.sh` works.
- Surge is static: **no PHP**, so the `tace-fetch.php` proxy does not exist there. Cross-origin playlists must allow CORS themselves.

---

## 7. Docker / Podman

`Dockerfile` is based on `php:8.3-apache` and adds bash, coreutils, gawk and similar GNU tools. `apache.conf` denies `.scr`/`.surgeignore` and restricts `tace-fetch.php` to local requests.

Docker, standard host:
```
docker build -t perevictor/mary:1.0 .
docker run -d --name mary -p 8080:80 perevictor/mary:1.0
# open http://localhost:8080/thelady/music.html
```
Push (Docker Hub user `perevictor`): `docker login` then `docker push perevictor/mary:1.0` (also tag `latest`).

**Podman quirk (author's machine, `docker` = Podman):** the default `pasta` networking crashed, so build with `docker build --network=host ...` and run with `--network=host`. Apache listens on port 80 inside the image; with host networking, change it to 8080 (the author used `sed` on Apache's `Listen`). Podman ignores `HEALTHCHECK` in OCI format. A normal Docker host does not need these workarounds.

Live-edit the code from the host: add `-v "$PWD/thelady:/var/www/html/thelady:Z"` (the `:Z` label is for SELinux/Podman).

---

## 8. Troubleshooting (symptom → fix)

| Symptom | Fix |
|---|---|
| `git`/`python`/`node` "not recognized" / "command not found" right after installing | Close and reopen the terminal (PATH is read at start). Then reinstall if still missing. |
| Windows: `python` opens the Microsoft Store | Use `py mary.py`, or turn off the "App execution aliases" for python.exe, or install from python.org. |
| `winget` not recognized | Use the installers from git-scm.com / python.org / nodejs.org. |
| Linux/macOS: `python` missing | Use `python3`. |
| `git clone` fails | Check the URL and internet; the repo must be public. |
| `cd mary` says folder not found | You are in the wrong folder; `ls`/`dir` and look for `mary`. |
| `mary.py` says "Cannot find thelady/music.html" / "can't open file" | Run it from the repo root (contains `mary.py` **and** `thelady`). |
| "Python 3.8 or newer is required" | Install a newer Python. |
| "Port 8080 is busy" | `python mary.py --port 9000`, then open `http://localhost:9000/thelady/music.html`. |
| Browser did not open | Open the URL manually while `mary.py` is still running. |
| Windows firewall prompt for Python | Allow on **private** networks. The server listens on 127.0.0.1 only. |
| Remote `?source=https://...` playlist does not load | Needs Python/PHP/Docker server (not direct file, not surge). Only csv/m3u/m3u8 are proxied. Check the URL opens in a browser tab. |
| Installer says progress can't be saved / steps reset | Private/incognito or blocked site data. Use a normal window. |
| surge: domain not available / no permission | Pick another name. |
| PowerShell blocks `npx` | Use cmd. |

---

## 9. Developing Mary

### 9.1 Architecture facts (from the code)
- Single page app: `thelady/music.html`; scripts begin near line 818 and line 8694.
- Local persistence: **IndexedDB** database `TACE_Audio_Player_v2`, version 1, object store `songs` (saved songs). Also `localStorage` for caches (for example YouTube playlist order). Buttons: SAVE, EXPORT PLAYLIST, DELETE DB, REPAIR DB.
- Main UI element ids: `author`, `title`, `urlFileName` (audio URL / csv path), `urlLyric` (docs link), `loadBtn`, `playBtn`, `stopBtn`, `saveBtn`, `exportBtn`, `deleteDbBtn`, `audio`, `stationSelect`, `youtubePlaylistSelect`, `nowPlayingText`, `message`, `debug`.
- **CSV playlist format:** pipe-delimited `|`, header `Author|Title|urlFileName|urlLyric`, one track per line (see `thelady/csv/celtic.csv`). `urlFileName` may be a file, stream, or YouTube URL; `urlLyric` is an optional document link.
- **M3U playlists** live in `thelady/m3u/`.
- URL parameter: `?source=...` takes **everything after `?source=`** (not `URLSearchParams`), so the source may contain its own `?` and `&`, for example a YouTube playlist URL. The value is treated as data and never executed.
- `isLocalServer()` is true for hostnames `localhost`, `127.0.0.1`, `::1`. In that case remote playlists go through `thelady/tace-fetch.php`; there is **no direct-fetch fallback**. `isLocalServer()` is defined twice in the file (around lines 1301 and 4921).
- YouTube: IFrame API (`https://www.youtube.com/iframe_api`) and the Data API v3 `playlistItems` call to get the real playlist order.

### 9.2 Security notes (do not weaken)
- `tace-fetch.php` (and the Python equivalent in `mary.py`) is a proxy: it must stay local-only. The PHP file checks that Host is localhost, the client IP is loopback/private, and that no `X-Forwarded-For`, `Forwarded` or `X-Real-IP` header is present; `apache.conf` adds an Apache rule too. Never expose it to the internet.
- `mary.py` blocks private IP targets and unsafe redirects: keep that (SSRF protection).
- **A YouTube Data API key is hard-coded in `thelady/music.html`** (constant `YOUTUBE_API_KEY`, near line 2775). Anything published on surge.sh or GitHub exposes it. Recommend the owner restrict it in Google Cloud Console (HTTP referrers + YouTube Data API only) or replace it.
- Never commit passwords, tokens or `.env` files. Do not suggest `chmod 777`.

### 9.3 Governance (read before changing the code)
Mary is part of the TACE project, whose governing documents are in the GitHub repo https://github.com/pere000/pretace under `docs/` (raw base: `https://raw.githubusercontent.com/pere000/pretace/main/docs/`). If the user wants to develop the app, ask them to give you (or fetch) these:

1. `docs/developer/AI_SESSION_BOOTSTRAP.md` (how an AI session must start; role: *Implementation Engineer*; authority order: Philosophical Constitution → Semantic Constitution → Ontology → accepted ADRs → Software Constitution → Functional Specs → GOVERNANCE.md → DEVELOPMENT_MODE.md),
2. `docs/developer/DEVELOPMENT_MODE.md`,
3. `docs/constitution/INDEX.md` and `docs/constitution/README.md`,
4. `docs/architecture/TACE_ARCHITECTURAL_AXIOMS.md` and `docs/architecture/TACE_TETRAHEDRAL_ARCHITECTURE_UPDATED_1788571938433.md`.

Follow them over any general habit of yours. If they conflict with this guide, **they win** for design decisions; this guide wins for install/run facts.

### 9.4 Working method
- Make small, reviewable changes; keep `music.html` working as a single file unless the user asks otherwise.
- Test in a browser (hard refresh: Ctrl+Shift+R). IndexedDB changes: bump `DB_VERSION` deliberately and keep a migration path; REPAIR DB / DELETE DB exist for users with stale schema.
- Test both: file opened directly and via a local server (behaviour differs: `isLocalServer()`, CORS, the proxy).
- Run it with `python mary.py --no-browser` or `php -S localhost:8000` while developing.
- Keep paths **relative** so the app works under any host and sub-path.
- After changing which files exist, update `.surgeignore`, `.dockerignore` and `install.html`/this guide if affected.
- Windows/macOS instructions in `install.html` were written from general knowledge and not tested on those systems; Linux was tested.

---

## 10. Contributing / publishing the source

- Create a public GitHub repo (owner `pere000`, name `mary`) so `git clone` works for others. Use a `.gitignore`; do not commit secrets.
- Typical flow: `git init`, `git add .`, `git commit -m "message"`, `git branch -M main`, `git remote add origin https://github.com/pere000/mary.git`, `git push -u origin main`.
- Docker Hub image: `perevictor/mary` (not pushed yet at the time of writing).
- Installer page entry point: `install.html?start=1` starts immediately (intended to be opened in a new tab from a future `howToUseThisResource.html` "Install Mary" button).

---

## 11. Quick answers

- *Do I need PHP?* No, unless you choose option 2B/2C/3 or want the PHP proxy.
- *Do I need a GitHub account?* No, to clone a public repo.
- *Do I need Python to publish on surge.sh?* No, Node.js.
- *Why do remote playlists fail on my surge.sh site?* Static host: no proxy. Use a local server, or host playlists with CORS enabled.
- *Why does the page only play local files?* It was opened as a file or no server is running; start a server (section 4).
- *Is anything uploaded to a third party?* Only what the user chooses: surge.sh publishing, YouTube playback, and remote playlist URLs the user enters.
