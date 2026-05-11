# Justification

Rationale behind the non-obvious calls on this project — what I picked, what I considered instead, and the tradeoff accepted.  
Entries get appended as decisions land and revisited when circumstances change.

## Tooling (Dx)

### Build pipeline

Common thread across these picks: modern, fast, and secure tools over the legacy (Vite 5 + postcss-autoprefixer + cssnano). Each is mature enough to be past the early-adopter risk threshold; the security gain comes mainly from smaller dep trees and fewer abandoned transitive deps.

#### Vite 8

Rolldown (Rust) replaces Rollup → ~10–30× faster builds. Two-month-old major at .11 patch — reasonable maturity.

#### LightningCSS

Replaces the postcss-autoprefixer + cssnano JS pipeline with a single Rust pass. Strictly faster + smaller output than esbuild for CSS. Targets driven by `.browserslistrc`.

#### SCSS as the direct Vite entry (drop the JS stub)

`assets/js/index.js` was a one-liner, the historical Vite/webpack idiom where JS is the bundler entry and CSS rides along. Project has no JS, so SCSS becomes the direct entry. HMR still works in dev (the hot-file helper always injects `<script type="module" src="$hot/@vite/client">`).

### Docker + dev workflow

Three needs that happen to align on the same setup:

1. **Keep PHP off the workstation** — personal preference; no local PHP install just for one demo project.
2. **Deploy to Render** — Docker required, prod-parity locally desirable.
3. **Don't lock out PHP-having contributors** — those who already have PHP installed can skip Docker for the inner loop if they want.

One Dockerfile, three stages — `runtime-dev` (Apache+PHP only, for compose), `assets` (Node builds `dist/`), `runtime` (default; extends `runtime-dev` + copies `dist/`, what Render builds). Plain `docker build` → prod image; `docker compose` → dev base. See [README → Local dev](README.md#local-dev) for the three concrete workflows this enables.

The non-obvious calls beyond the stage split:

#### Apache + mod_php (not nginx + PHP-FPM)

Single image, single process. `php:8.4.21-apache-trixie` is the official PHP-team image, drop-in. Nginx+FPM would mean two containers (more compose surface, more for Render to schedule) or supervisord/s6. Performance indistinguishable at this scale.

#### Dev/prod asset toggle via `public/hot`

A manual `if ($mode === 'development')` flag was easy to forget and needed a Dockerfile `sed` to flip. Laravel-inspired alternative: a tiny Vite plugin writes `public/hot` on dev startup; the PHP `vite_assets()` helper checks for the file — present → dev-server tags, missing → hashed prod tags from `dist/.vite/manifest.json`. No env flag, no `sed`.

### Formatting tools (OCD kicked in)

Three tools, one principle: **format-on-save should work for every filetype in the repo with zero per-file ceremony**.  
More personal preference (read: OCD) than project requirement, but it scales well to collaborators.

#### oxfmt for everything (except PHP)

Single formatter for js/ts/scss/css/json/md. Rust speed (~250 ms full repo). Reads `.editorconfig` natively for indent/EOL/finalNewline — no duplication in `.oxfmtrc.json`. Ignore patterns live in `.oxfmtrc.json`'s `ignorePatterns`, no separate `.prettierignore`/`.oxfmtignore` file.

#### No PHP CS-Fixer (manual PSR-12)

`junstyle.php-cs-fixer` requires a local PHP binary to execute the bundled phar. The runtime is Docker-only PHP, so requiring local PHP just for formatting is asymmetric. Alternatives considered and rejected: `brew install php` (breaks the "Docker-only" principle), Docker-wrapped php-cs-fixer (cold container per save, ~1–2 s lag). With 3 PHP files (mostly HTML templates), manual PSR-12 alignment is proportionate.

#### Intelephense for PHP IntelliSense

LSP-based PHP analyzer that ships its own engine in JS — no local PHP binary needed. Replaces VSCode's built-in PHP validator (which shells out to `php -l` and warns when PHP is absent). Built-in validator silenced via `php.validate.enable: false`.

## Code

### Intrinsic grid in `.ProductCardLayout` theme override

The component originally drove `.ProductCardLayout` columns/gap via a per-breakpoint `@each` loop with prev-state dedupe (~18 lines of state tracking + `@if` guards; see [IMPROVEMENTS.md](IMPROVEMENTS.md) for the autopsy). The theme override sidesteps that entirely with a single declaration:

```css
grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px + 2dvi), 1fr));
```

`auto-fit` collapses empty tracks; `minmax(min(100%, 240px + 2dvi), 1fr)` lets each track shrink to single-column at narrow viewports and grow up to `1fr` at wider ones — no media queries, no breakpoint config, no state-tracking `@each`. Less code, intrinsic responsiveness, and the "clever but obsolete" dedupe is bypassed without touching the parent component (which stays untouched per the **theme overrides** rule in the README).

### Theme imported before components — Sass variable overrides need it

`index.scss` imports `theme` **before** `components`, which looks counterintuitive (theme rules should cascade _after_ components, not before). Two reasons it works as intended:

1. **Sass variable overrides need to win at compile time.** Component partials declare config like `$product-card-hover-image-scale: 1.25 !default;` in [components/product-card/\_variables.scss](assets/scss/components/product-card/_variables.scss), then their `_image.scss` does `@if ($product-card-hover-image-scale) { … }` to gate rule emission. Sass `!default` flag only honors an override that was set _before_ the `!default` declaration ran. Importing theme **after** components means overrides land too late — the `@if` already fired with the default value, and the override is dead code.

2. **CSS cascade layers separate source order from precedence.** With `@layer settings, reset, generic, typography, components, theme, utilities;` declared at the top of `index.scss`, theme rules wrapped in `@layer theme { … }` always cascade after `@layer components`, regardless of where in the source they appear. Source order drives Sass-time evaluation; declared layer order drives CSS-time precedence.

**Bottom line:** theme variable overrides apply _before_ components evaluate them at compile time; theme CSS rules still win the cascade at runtime via explicit layer order. The two concerns are decoupled. Without cascade layers this trick wouldn't work — you'd have to choose between variable override ergonomics and cascade correctness.

### Bypassing `.Badge` component + `$product-card__badges_positions` for labels

The original card had three independent badge slots (`primaryBadges` for `-10%` circle, `tertiaryBadges` for category rectangle, `secondaryBadges` for a hex-coloured pill), each driven by the [`.Badge` component](assets/scss/components/badge/badge.scss) (size/type variants from a `$badge-types` map) and positioned per a `$product-card__badges_positions` map in [`components/product-card/_variables.scss`](assets/scss/components/product-card/_variables.scss).

The new design (per Figma) collapses all of that into **one list** of typed labels (`new`, `sale`, `clearance`, `tip`) overlaid bottom-left of the image. The existing infrastructure doesn't map cleanly:

- `.Badge--rectangle` / `--circle` / `--rectangleSide` variants assume a different visual taxonomy.
- `$product-card__badges_positions` configures three positioned slots, not one list.
- `$badge-types` map keys (sizes, types) don't correspond to the new label types.

**Decision**: skip both, use theme overrides directly. Markup ships `.ProductCard-label--{new,sale,clearance,tip}` classes; theme layer styles them as pills. The original `.Badge` component + `$product-card__badges_positions` go unused.

### Safari Tab-skip-links default — `tabindex="0"` workaround on anchors

The card reveals its size-selector overlay via `:where(:focus-within, :hover)` on `.ProductCard`. In Firefox and Chrome this fires when keyboard users Tab onto the card's title link. In **default Safari/macOS it doesn't** — Safari excludes `<a>` elements from the default Tab cycle (only `<input>`, `<button>`, `<select>`, `<textarea>` participate). The card's title link and size links are all `<a>`, so no descendant ever receives focus via plain Tab → `:focus-within` never fires → the size overlay never reveals.

**Workaround applied**: explicit `tabindex="0"` on the title link and each size link in [templates/productCard.php](templates/productCard.php). Safari treats an explicit `tabindex` attribute as "the author opted this element into the Tab cycle", overriding its default anchor-skip behavior. Redundant in Firefox/Chrome (their default behavior already includes anchors), harmless side effect.

Chosen workaround is the minimum-friction unlock: two extra attributes per card, no markup restructure, no semantic confusion. When the size-selector UX direction is settled (likely `<button>` for state-based selectors), the `tabindex="0"` on size links can be dropped.

### Manrope CSS inlined via `readfile`

[public/fonts/manrope.css](public/fonts/manrope.css) is ~600 B of stable `@font-face` declarations. Linking it via `<link rel="stylesheet">` adds a render-blocking round-trip on the critical path; inlining via `<style><?php readfile(...) ?></style>` in [index.php](index.php) kills it.

**Tradeoff accepted**: font CSS is no longer separately cacheable. For 600 B of declarations that change only when the font file set itself does, the round-trip win dwarfs the cache loss. `readfile` keeps `public/fonts/manrope.css` as single source of truth (edit there, served inline).
