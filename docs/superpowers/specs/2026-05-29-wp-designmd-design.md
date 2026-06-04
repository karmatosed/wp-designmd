# wp-designmd Plugin Design Spec

**Date:** 2026-05-29  
**Status:** Approved (brainstorming)  
**Target:** WordPress 7.0+, block themes only

## Summary

wp-designmd is a WordPress plugin that scans an active block theme and a capped sample of live front-end pages, then produces a valid [DESIGN.md](https://github.com/google-labs-code/design.md) file describing the site's visual identity. The admin UI lives under **Appearance → DesignMD**, built with the WordPress Design System (WPDS) in React. PHP handles all server-side scanning, generation, AI, and file I/O.

## Goals

- Generate a spec-compliant `DESIGN.md` from existing site design data without modifying the theme
- Provide a visual preview of tokens and prose in admin using native WPDS components
- Support generate, regenerate, fill-gaps (AI), and download workflows
- Use WordPress 7.0 AI (`wp_ai_client_prompt()`) only when needed — prose and gap-filling, never token extraction

## Non-Goals (v1)

- Classic theme support
- Multiple DESIGN.md files (per-theme, per-page)
- In-admin editing of DESIGN.md source
- `@google/design.md` CLI subprocess (Node dependency)
- Auto-sync on theme changes (manual regenerate only)
- Multisite network admin
- Export to Tailwind/DTCG formats
- Resolving `color-mix()` values to hex

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Appearance → DesignMD (React / WPDS admin page)            │
│  [ Generate ] [ Regenerate ] [ Fill gaps ] [ Download ]     │
└────────────────────────────┬────────────────────────────────┘
                             │  REST (wp-designmd/v1)
                             ▼
                   DesignMdController
                             │
         ┌───────────────────┼───────────────────┐
         ▼                   ▼                   ▼
   SiteScanner      FrontEndSampler      ProseGenerator
   (theme.json,      (≤8 URLs +          (template or
    templates,        feature pages)       wp_ai_client)
    patterns,
    global styles)
         │                   │                   │
         └───────────────────┼───────────────────┘
                             ▼
                      TokenMapper
                   (WP presets → DESIGN.md YAML)
                             │
                             ▼
                      DesignMdWriter
              wp-content/uploads/wp-designmd/DESIGN.md
                             │
                             ▼
                      DesignMdLinter → REST response
                             │
                             ▼
                   Token Preview Panel (React / WPDS)
```

### Approach

**PHP-first backend + WPDS React admin** (Approach 1 backend, Approach 2 UI).

- PHP: scanning, mapping, writing, linting, AI calls
- React/TypeScript: admin UI only, via `@wordpress/scripts` build
- No Tailwind or external UI libraries

---

## Scanning Strategy (Hybrid)

### Server-side sources (`SiteScanner`)

| Source | API | Purpose |
|---|---|---|
| Merged theme.json | `WP_Theme_JSON_Resolver::get_merged_data()` | Colors, typography, spacing, layout, block styles |
| Templates | `get_block_templates()` | Template inventory, block usage |
| Template parts | `get_block_templates( [ 'post_type' => 'wp_template_part' ] )` | Header/footer/sidebar structure |
| Patterns | `WP_Block_Patterns_Registry` + theme `patterns/` | Recurring layouts |
| Global styles | User Site Editor customizations (merged) | Preset overrides |
| Settings | Option `wp_designmd_settings` | Feature page IDs |

Output: structured `ScanResult` PHP object (not markdown).

### Front-end sample (`FrontEndSampler`)

**Auto-discovery (cap: 8 URLs, deduped by template):**

1. Homepage (`/`)
2. One single post (most recent published)
3. One page (if exists)
4. One archive
5. Additional template types until cap reached

**Feature pages:** Admin-configured page IDs always included (do not count toward cap).

**Fetch:** `wp_remote_get()` loopback to each URL.

**Extract from rendered HTML:**

- CSS custom properties: `--wp--preset--color--*`, `--wp--preset--font-size--*`, `--wp--preset--spacing--*`
- Core block presence (buttons, groups, navigation) for component inference
- Regex + `DOMDocument` for `<style>` blocks — no full CSS engine

**Purpose:** Validate theme.json tokens appear on the live front; flag orphaned tokens for linter and Fill gaps hints.

**Failure:** If loopback fails, continue with theme.json only; return notice `"Front-end sample skipped"`.

---

## Token Mapping (`TokenMapper`)

Deterministic. No AI.

### Colors

| WP palette slug | DESIGN.md token | Notes |
|---|---|---|
| `contrast` (or darkest) | `colors.primary` | Heuristic: first dark/text color |
| `base` | `colors.neutral` | Lightest background |
| `accent-1` | `colors.tertiary` | Primary accent |
| Other slugs | `colors.{slug}` | Preserve original names |

- Normalize to `#RRGGBB`
- Skip `color-mix()` values; note in prose as dynamic color

### Typography

Map `settings.typography.fontSizes` and `fontFamilies` to `typography.{slug}` tokens with `fontFamily`, `fontSize`, `fontWeight`, `lineHeight` where available.

Map common slugs to spec-friendly names where obvious (`large` → `headline-md`, `medium` → `body-md`).

### Spacing

Direct map from `settings.spacing.spacingSizes` → `spacing.{slug}`.

### Rounded

Extract from `styles.blocks['core/button'].border.radius` and global border settings → `rounded.{slug}`.

### Components

Infer from `styles.blocks`:

| WP block | DESIGN.md component |
|---|---|
| `core/button` | `button-primary`, `button-secondary` |
| `core/search` | `input-field` |
| Navigation styles | `nav-link` |

Use token references (`{colors.tertiary}`) not raw hex where possible.

### Layout

Prose in `## Layout` section. Map `contentSize` / `wideSize` to `spacing.gutter`, `spacing.margin` where applicable.

---

## DESIGN.md Output

### File format

Follow [Google DESIGN.md spec](https://github.com/google-labs-code/design.md/blob/main/docs/spec.md):

1. YAML front matter (tokens)
2. Markdown body with `##` sections in canonical order:
   Overview → Colors → Typography → Layout → Elevation & Depth → Shapes → Components → Do's and Don'ts

### Storage

**Path:** `wp-content/uploads/wp-designmd/DESIGN.md`

- Plugin-owned directory, created on activation via `wp_mkdir_p()`
- Not written to theme directory
- Survives theme switches

**Meta option:** `wp_designmd_meta`

```json
{
  "generated_at": "2026-05-29T12:00:00+00:00",
  "theme_slug": "twentytwentyfive",
  "scanned_urls": ["https://example.com/", "..."],
  "lint_summary": { "errors": 0, "warnings": 1, "info": 2 },
  "sparse": false
}
```

### Prose generation

**Default (no AI):** Templated prose from token data per section.

**With AI (`use_ai_prose: true` and `wp_supports_ai()`):** `wp_ai_client_prompt()` writes/enriches markdown sections. YAML tokens are never modified unless literally empty.

**Fill gaps (manual trigger):** Runs on existing file. AI fills only sparse sections. YAML tokens never overwritten unless empty.

### Sparse detection (shows Fill gaps button)

Any of:

- Overview < 40 words
- Any present section has < 2 prose bullets
- Linter reports `missing-typography`, `missing-primary`, or `orphaned-tokens`
- Fewer than 3 component tokens inferred

---

## AI Usage

| Action | AI? | Fallback |
|---|---|---|
| Generate (tokens) | Never | Deterministic `TokenMapper` |
| Generate (prose) | Optional (`use_ai_prose`) | Templated prose |
| Fill gaps | Yes (on demand) | Button disabled if `! wp_supports_ai()` |
| Regenerate | Same as Generate | Same |

All AI actions check `wp_supports_ai()` first. Admin shows `Notice` when AI unavailable.

---

## Linting (`DesignMdLinter`)

PHP implementation aligned with `@google/design.md` rules (no Node subprocess):

| Rule | Severity |
|---|---|
| `broken-ref` | error |
| `missing-primary` | warning |
| `contrast-ratio` | warning |
| `orphaned-tokens` | warning |
| `token-summary` | info |
| `missing-sections` | info |
| `missing-typography` | warning |
| `section-order` | warning |
| Duplicate section heading | error |

Lint results returned in REST `GET /designmd` response and displayed in admin.

Invalid YAML after assembly: do not write file; return errors to UI.

---

## REST API

**Namespace:** `wp-designmd/v1`  
**Auth:** Cookie + `X-WP-Nonce` (standard admin REST)  
**Permission:** `current_user_can( 'edit_theme_options' )` on all routes

| Method | Route | Description |
|---|---|---|
| `GET` | `/designmd` | Current content, parsed tokens, lint report, meta, `sparse` flag |
| `POST` | `/designmd/generate` | Full scan + write file |
| `POST` | `/designmd/regenerate` | Overwrite existing file |
| `POST` | `/designmd/fill-gaps` | AI gap-fill on existing file |
| `GET` | `/designmd/download` | File download (`Content-Disposition: attachment`) |
| `GET` | `/settings` | Feature pages, AI preference |
| `POST` | `/settings` | Save settings |

Long-running operations: single POST with spinner in UI (no streaming in v1).

Regenerate returns diff summary: token count changes, sections added/removed.

---

## Admin UI (WPDS)

### Tech stack

| Package | Usage |
|---|---|
| `@wordpress/admin-ui` | `Page` — standard admin page shell |
| `@wordpress/ui` | `Stack` and layout primitives for preview panels |
| `@wordpress/theme` | `--wpds-*` tokens via `design-tokens.css` |
| `@wordpress/components` | `Button`, `Notice`, `Spinner`, `TabPanel`, `FormTokenField` |
| `@wordpress/icons` | Action icons |
| `@wordpress/api-fetch` | REST calls |
| `@wordpress/i18n` | Strings |

**Rules:**

- Admin chrome uses `--wpds-*` tokens only (no hardcoded colors/spacing)
- Site token preview renders from extracted DESIGN.md YAML values (inline styles on swatches/samples)
- Do not mix `--wp--preset--*` (site) with `--wpds-*` (admin) for admin chrome styling
- Enqueue `wp-components` stylesheet as dependency of plugin stylesheet
- TypeScript (`.tsx`) throughout `src/`

### Page layout

```
Page (admin-ui)
├── Breadcrumbs: Appearance → DesignMD
├── ActionsPanel
│   ├── Generate | Regenerate | Fill gaps | Download
│   └── Last generated meta + theme slug
├── Notice(s): lint warnings, AI unavailable, scan skipped
├── Two-column Stack layout
│   ├── TokenPreview
│   │   ├── Colors (swatches + hex labels)
│   │   ├── Typography (rendered samples)
│   │   └── Spacing / Rounded (scale list)
│   └── ProseViewer (markdown sections)
└── Settings (collapsible PanelBody)
    └── FeaturePagesPicker (FormTokenField → wp/v2/pages search)
```

### PHP admin shell

```php
add_theme_page( 'DesignMD', 'DesignMD', 'edit_theme_options', 'wp-designmd', [ Admin::class, 'render_page' ] );
```

`render_page()` outputs `<div id="wp-designmd-admin"></div>` and enqueues `build/index.js` + `build/index.css`.

No PHP templates for UI content.

### Settings option

`wp_designmd_settings`:

```json
{
  "feature_page_ids": [12, 45],
  "use_ai_prose": true
}
```

---

## Plugin Structure

```
wp-designmd/
├── wp-designmd.php
├── uninstall.php
├── includes/
│   ├── class-plugin.php
│   ├── class-admin.php
│   ├── Rest/
│   │   └── DesignMdController.php
│   ├── Scanner/
│   │   ├── SiteScanner.php
│   │   └── FrontEndSampler.php
│   ├── Generator/
│   │   ├── TokenMapper.php
│   │   ├── ProseGenerator.php
│   │   ├── GapFiller.php
│   │   └── DesignMdWriter.php
│   └── Linter/
│       └── DesignMdLinter.php
├── src/
│   ├── index.tsx
│   ├── App.tsx
│   ├── components/
│   │   ├── TokenPreview.tsx
│   │   ├── ProseViewer.tsx
│   │   ├── ActionsPanel.tsx
│   │   ├── LintReport.tsx
│   │   └── FeaturePagesPicker.tsx
│   ├── hooks/
│   │   └── useDesignMd.ts
│   └── types/
│       └── designmd.ts
├── build/                  # Compiled (gitignored in dev)
├── docs/
│   └── superpowers/specs/
├── package.json
├── tsconfig.json
└── readme.txt
```

---

## Error Handling

| Failure | Behavior |
|---|---|
| Not a block theme | Admin error; no file written |
| Loopback fetch fails | Continue theme.json-only; notice in UI |
| AI unavailable | Templated prose; Fill gaps disabled with notice |
| Invalid YAML after assembly | Do not write; show linter errors |
| Uploads not writable | Error with path + permission hint |
| No DESIGN.md yet | Empty state with prominent Generate button |

---

## Security

- All REST routes: `edit_theme_options` capability
- Nonce via standard `api-fetch` middleware
- Sanitize feature page IDs as positive integers
- Escape all output in PHP; React handles admin output
- No arbitrary file paths — fixed uploads location only
- AI prompts include only scanned design data, no user PII

---

## Testing

### PHP

- `TokenMapper` unit tests: fixture `theme.json` → expected YAML
- `DesignMdLinter` against valid/invalid DESIGN.md fixtures
- REST permission: 403 without capability

### JavaScript (Jest via `@wordpress/scripts`)

- `TokenPreview` renders swatches from parsed token JSON
- `useDesignMd` hook: loading, error, success states

### Manual smoke test

1. Activate on twentytwentyfive block theme
2. Generate → file exists in uploads
3. Preview colors/typography match theme
4. Download → validate locally with `npx @google/design.md lint`
5. Regenerate → meta timestamp updates
6. Fill gaps (when sparse) → prose enriched

### Studio verification

```bash
studio wp plugin activate wp-designmd
studio wp eval 'echo file_exists( wp_upload_dir()["basedir"] . "/wp-designmd/DESIGN.md" ) ? "yes" : "no";'
```

---

## Future Enhancements (post-v1)

- Classic theme best-effort support
- `@google/design.md` lint parity via bundled PHP port or dev-only CLI hook
- Export to DTCG / Tailwind via `@google/design.md export`
- WP-CLI: `wp designmd generate`
- Auto-regenerate on theme switch (opt-in)
- In-admin markdown editor with live preview

---

## Decisions Log

| Decision | Choice | Rationale |
|---|---|---|
| Scan strategy | Hybrid (theme + front-end sample) | Reliable tokens + live validation |
| Storage | Uploads directory | WP-native; no theme modification |
| AI scope | Prose only + Fill gaps button | Tokens stay deterministic and spec-valid |
| Front-end URLs | Auto cap 8 + feature pages | Coverage without full crawl |
| Theme support | Block themes only (v1) | theme.json is primary token source |
| Admin UI | WPDS React (`admin-ui` + `ui`) | Modern WordPress admin patterns |
| Admin preview | Token swatches + prose viewer | Lightest useful visual representation |
| Lint | PHP subset of Google spec | No Node on WP hosts |
