# Frontend Demo Task

A two-part frontend task:

1. **Match the design**  
   Update the product card markup and styles to align with the reference design in [`resources/theme.fig`](resources/theme.fig).

2. **Review and improve the code**  
   Analyze the existing HTML/CSS and suggest improvements related to accessibility, performance, structure, semantics, conventions, or anything else worth addressing.

## Stack

- [PHP](https://www.php.net/) + [Apache](https://httpd.apache.org/) (runtime)
- [pnpm](https://pnpm.io/) via [Corepack](https://nodejs.org/api/corepack.html) (package manager)
- [Vite](https://vite.dev/) + [Rolldown](https://rolldown.rs/) (build, dev server)
- [Sass](https://sass-lang.com/) + [LightningCSS](https://lightningcss.dev/) (styles, autoprefixing, minify)
- [oxfmt](https://oxc.rs/docs/guide/usage/formatter) (format)
- [Docker](https://www.docker.com/) + [Render](https://render.com/) (deploy / dev parity)

## Local dev

### Hybrid (recommended)

Apache+PHP in a container, Vite on the host. No local PHP needed.

```sh
pnpm dev                                # Vite on :5173
docker compose up                       # in another tab → :8080
```

Open http://localhost:8080. Order doesn't matter — Vite rewrites `public/hot` on every startup, and the container reads it on each request.

How it wires up: [templates/vite.php](templates/vite.php) checks for `public/hot`. If present (Vite running), it emits dev tags pointing at `http://localhost:5173`; otherwise it reads `dist/.vite/manifest.json` and emits hashed prod tags. Compose volume-mounts `public/`, so the host-written hot file is visible inside the container.

### Host-only (alternative)

If you have PHP 8.4+ installed and don't want Docker:

```sh
pnpm dev                                # Vite on :5173
php -S 0.0.0.0:8080 index.php           # in another tab → :8080
```

### Prod-parity test (mirrors Render)

```sh
docker build -t emorfiq-fe .
docker run --rm -p 8080:80 emorfiq-fe
```

Open http://localhost:8080. To simulate Render's `$PORT` injection:

```sh
docker run --rm -e PORT=10000 -p 10000:10000 emorfiq-fe
```

The Dockerfile is a 3-stage build: a `runtime-dev` base (Apache+PHP only), an `assets` stage (Node+pnpm builds `dist/`), and a `runtime` stage (default, what Render builds — copies source + the built `dist/`). `docker compose` targets `runtime-dev`; `docker build` with no `--target` produces `runtime`.

## Commands

| Command                                 | Action                                             |
| --------------------------------------- | -------------------------------------------------- |
| `pnpm dev`                              | Start Vite at `localhost:5173`, write `public/hot` |
| `pnpm build`                            | Build to `./dist/` (manifest + hashed filenames)   |
| `pnpm preview`                          | Preview the production build                       |
| `pnpm format:check`                     | Check formatting with oxfmt                        |
| `pnpm format:write`                     | Apply oxfmt formatting                             |
| `docker compose up`                     | Run Apache+PHP in a container (hybrid dev)         |
| `docker build -t emorfiq-fe .`          | Build the prod image (mirrors Render)              |
| `docker run --rm -p 8080:80 emorfiq-fe` | Run the prod image locally                         |

## Theming

Component styles live in [assets/scss/components/](assets/scss/components/). Theme overrides go in [assets/scss/theme/](assets/scss/theme/).

Apply changes in this order:

1. **Override a variable**  
   First try to change the look by overriding an existing SCSS variable (e.g. background of a product card). See [assets/scss/theme/product-card/](assets/scss/theme/product-card/) for the canonical example.
2. **Add custom CSS**  
   If no suitable variable exists, add new CSS under `theme/`.  
   **Never modify CSS inside the component itself.**
3. **HTML last resort**  
   Prefer not to touch the template markup. Edit it only if rules 1 and 2 can't get you there.

## Project Structure

```
.
├── assets/
│   └── scss/                   # source styles (Vite entry)
├── data/
│   └── products.json           # mock product data
├── docker/                     # Apache config
├── public/
│   ├── fonts/                  # self-hosted Manrope variable
│   └── images/                 # static images
├── resources/
│   └── theme.fig               # design reference
├── templates/
│   ├── helpers.php             # format_price() etc.
│   ├── productCard.php
│   └── vite.php                # vite_assets() helper
├── Dockerfile                  # 3-stage build, prod target = runtime
├── docker-compose.yml          # hybrid local dev
├── index.php                   # entry page
├── package.json
└── vite-plugin-hot-file.js     # writes public/hot during pnpm dev
```

## Troubleshooting

### `docker build` fails at stage 1 with `ERR_PNPM_NO_LOCKFILE`

Run `pnpm install` on the host first — the Dockerfile uses `--frozen-lockfile` and needs `pnpm-lock.yaml`.

### Page loads but no styles

Vite isn't running, or it crashed but `public/hot` survived. Run `pnpm dev`.  
If that doesn't help, delete `public/hot` and restart.

### Port `:8080` already in use

You're running more than one of compose / `docker run` / `php -S` at the same time. Stop one. Or pass `-p 8081:80` to one of them.

### Compose container shows old PHP after editing `templates/`

Volume mounts are live, but PHP opcode cache may hold a stale version.  
`docker compose restart web` clears it.

### HMR not triggering on SCSS edits

Confirm Vite is running (`curl -I http://localhost:5173/@vite/client` should return 200) and `public/hot` exists.  
The browser's network tab should show a WebSocket connection to `:5173`.

### Ports

All three app workflows bind to `:8080`. They're mutually exclusive — only run one at a time.

| Port | Service                   | Workflow          |
| ---- | ------------------------- | ----------------- |
| 5173 | Vite dev server           | host (`pnpm dev`) |
| 8080 | Apache+PHP — compose      | hybrid dev        |
| 8080 | `php -S` built-in server  | host-only dev     |
| 8080 | Apache+PHP — `docker run` | prod-parity test  |
