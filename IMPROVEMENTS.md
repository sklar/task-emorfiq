# Improvements

This is a tip-of-the-iceberg list, not a production audit. Browser support targets, WCAG conformance level, and the broader UX/UI direction are all unknown to me.  
Treat each item as a humble suggestion rather than a hard finding.

**Explicitly out of scope:**

- CSS specificity audit, selector-performance stats.
- Overall UX/UI design choices — Figma is the source of truth, not second-guessing it.
- Production-grade SEO/semantics (headings, landmarks, OG cards, sitemap, favicons, etc.) — the demo is a single product list, not a real e-commerce page. Only JSON-LD is flagged below.

## Code

- ### [change-value](assets/scss/mixins/change-value.scss) mixin sentinel naming
  Rename `nones` to `null` or something loudly artificial like `__SKIP__`. Pattern works but `nones` reads like a typo and consistently surprises readers.
- ### [ProductCardLayout](assets/scss/theme/product-card-layout/product-card-layout.scss) breakpoint dedup
  ~18 lines of prev-value state tracking (`$prevGap`, `$prevColumns`, `$prevSpacer`) + `@if`-guards to skip emitting CSS properties that match the previous breakpoint. With Brotli compression and far-future caching of the CSS bundle, the dedup saves nothing meaningful — on a product list page, image bytes dominate the budget anyway.
- ### [ProductCardLayout](assets/scss/theme/product-card-layout/product-card-layout.scss) `max-width: 399px` hack
  A `@media (max-width: 399px) { grid-template-columns: 1fr 1fr }` block sits outside the config-driven `@each` loop and overrides the smallest breakpoint with hardcoded `1fr 1fr`. Mixes `max-width` with the loop's mobile-first `min-width` cascade. Should live in `$product-card-layout` config alongside the rest. Also worth questioning the UX: at <400px each card is ~150px wide, photos illegible — modern e-commerce defaults to single-column on narrow mobile.
- ### CSS layers order
  The codebase originally used `@layer reset`, `@layer settings.ui`, `@layer generic.ui`, `@layer components.ui`, `@layer components.eshop` across partials without **ever declaring the order**. Without an `@layer A, B, C;` declaration, order is set by **first appearance in source** (later = higher precedence), which produced a backwards cascade (reset overriding `components.eshop`). Consider adding the explicit declaration at the top of [assets/scss/index.scss](assets/scss/index.scss):

  ```scss
  @layer settings, reset, generic, typography, components, theme, utilities;
  @layer components.ui, components.eshop;
  ```

- ### Reboot ≈ normalize.css fork
  Maintaining a cherry-picked subset means manual sync each time normalize.css updates.  
  Consider adding `normalize.css` as a dependency, importing it directly into the `reset` layer, and putting project-specific overrides in a dedicated `base` layer:

  ```scss
  @layer settings, reset, base, generic, typography, components, theme, utilities;

  @layer reset {
    @import 'normalize.css';
  }
  @layer base {
    // project-specific element-level overrides (was reboot)
  }
  ```

  Also, consider replacing the normalize altogether with alternatives:
  - [**modern-normalize**](https://github.com/sindresorhus/modern-normalize) — lighter, drops outdated browser support,
  - [**sanitize.css**](https://csstools.github.io/sanitize.css/) — more opinionated, ships modern defaults (border-box everywhere, accessible focus styles),
  - [**the-new-css-reset**](https://github.com/elad2412/the-new-css-reset) — most aggressive, removes nearly all browser styles for full control.

- ### Dart Sass (upgrade)
  Currently pinned at 1.99.
- ### CSS migration
  Modern CSS covers most of what Sass was brought in for. Biggest win would be that the per-breakpoint Sass variable + `change-value` sentinel mixin pattern becomes obsolete. With CSS custom properties + media queries, the cascade handles mobile→desktop inheritance natively — no sentinels, no `@if` guards, codebase complexity drop.
- ### Container queries
  The product card is a self-contained component. Sizing it relative to its parent container (rather than the viewport) removes a pile of layout-specific media queries.
- ### Pixel-perfect control vs fluid sizing
  Drop the dense breakpoint-by-breakpoint variable overrides for font sizes / spacing in favor of `clamp()` + container-relative units.  
  Loses some pixel fidelity, drops complexity and gains a lot of maintainability.
- ### [CSS subgrid](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_grid_layout/Subgrid) (vertical alignment across cards)
  Card content varies so titles, prices, and buttons end at different vertical positions across siblings.  
  Declaring the card's inner layout as subgrid aligns those elements across the row regardless of per-card content.
- ### [CSS logical properties](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_logical_properties_and_values)
  Writing-mode and i18n-aware (auto-flip in RTL); future-proofs the codebase.

## SEO / semantics

- ### JSON-LD product structured data
  `<script type="application/ld+json">` per card with [schema.org Product](https://schema.org/Product). The format Google actively recommends today. Required for rich results, Merchant Center, Shopping, and AI search grounding (Google AI Overviews, Perplexity, ChatGPT search). Easy programmatic emission: `json_encode($product)` shaped to schema.org.

## Performance

| Issue | Notes |
| --- | --- |
| **Responsive images via `srcset` + `sizes`**<br>Phones currently load desktop-sized images. | Ship pre-rendered widths (e.g. 320w / 480w / 640w / 960w); browser picks by viewport + DPR.<br>Pair with `<picture>` for format negotiation (AVIF → WebP → fallback). |
| **Image format**<br>PNG is wrong for product photography. | Switch to AVIF (optional fallback via `<picture>`) when real photos arrive — typical 50–70% byte reduction over PNG/JPEG at equivalent perceived quality. |
| **`decoding="async"` on `<img>`**<br>Synchronous image decode can cause paint-time jank. | Decodes off the main thread. Default `auto` typically picks `async` for lazy images already; explicit pins consistent behavior across browsers.<br>Independent of `fetchpriority` / `loading`. |

## Accessibility

The main issue is that `.ProductCard-sizes` is hidden by default and revealed only via `:focus-within` / `:hover`, making sizes effectively invisible on touch devices. While gating the behavior behind `@media (hover: hover)` and making sizes always visible on mobile sounds trivial, it introduces layout side effects around the badge overlay. This likely requires a UX redesign (tap-to-reveal, compact always-visible layout, popover, etc.) rather than a simple CSS fix, so it’s intentionally flagged here but left unpatched.

| Issue | WCAG | Notes |
| --- | --- | --- |
| **Phantom anchor → title link**<br>Empty `<a class="ProductCard-link"></a>` has no accessible name. | [2.4.4 Link Purpose (In Context)](https://www.w3.org/WAI/WCAG22/Understanding/link-purpose-in-context) (**A**)<br>[4.1.2 Name, Role, Value](https://www.w3.org/WAI/WCAG22/Understanding/name-role-value) (**A**) | [Heydon-style](https://inclusive-components.design/cards/) overlay. |
| **Keyboard focus indicator**<br>Themes often suppress the browser-default outline; verify every interactive element has a visible focus ring. | [2.4.7 Focus Visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible) (**AA**)<br>[2.4.11 Focus Not Obscured (Min)](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum) (**AA**)<br>[2.4.13 Focus Appearance](https://www.w3.org/WAI/WCAG22/Understanding/focus-appearance) (**AAA**) | Use `:focus-visible` (keyboard only, not mouse). |
| **Touch target size on size links**<br>Renders ~20×16 px, too small for thumbs. | [2.5.8 Target Size Minimum](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum) (**AA**)<br>[2.5.5 Target Size Enhanced](https://www.w3.org/WAI/WCAG22/Understanding/target-size-enhanced) (**AAA**)| Apple HIG 44; Material 48. |
| **Card-level focus ring**<br>Missing `.ProductCard` focus ring. | [2.4.7 Focus Visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible) (**AA**) | — |
| **Tooltips on truncated titles**<br>`text-overflow:ellipsis` hides the tail.<br>Show a tooltip **only when actually truncated** (permanent `title` is noisy on titles that fit). | [1.3.1 Info & Relationships](https://www.w3.org/WAI/WCAG22/Understanding/info-and-relationships) (**A**)<br>[1.4.13 Content on Hover or Focus](https://www.w3.org/WAI/WCAG22/Understanding/content-on-hover-or-focus) (**AA**) | `title` attr unreliable across assistive tech; use [Popover API](https://developer.mozilla.org/en-US/docs/Web/API/Popover_API) or detect truncation via JavaScript. |
