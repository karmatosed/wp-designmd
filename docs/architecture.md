# Architecture

wp-designmd scans block theme design sources and a capped front-end sample, then produces a valid DESIGN.md file. The admin UI is a React app; all generation logic runs in PHP.

## High-level flow

```
┌─────────────────────────────────────────────────────────────┐
│  Appearance → DesignMD (React / WPDS)                       │
│  Generate │ Regenerate │ Fill gaps │ Download               │
└────────────────────────────┬────────────────────────────────┘
                             │  REST  wp-designmd/v1
                             ▼
                   DesignMdController
                             │
         ┌───────────────────┼───────────────────┐
         ▼                   ▼                   ▼
   SiteScanner      FrontEndSampler      ProseGenerator
   (server-side)    (loopback HTTP)      (template / AI)
         │                   │                   │
         └───────────────────┼───────────────────┘
                             ▼
                      TokenMapper
                   (deterministic YAML)
                             ▼
                   assemble → lint → write
                             ▼
              uploads/wp-designmd/DESIGN.md
```

## Components

### Scanner layer

| Class | Responsibility |
|---|---|
| `WP_Designmd_SiteScanner` | Reads merged `theme.json`, block templates, template parts, registered patterns |
| `WP_Designmd_FrontEndSampler` | Fetches up to 8 auto-discovered URLs plus configured feature pages; extracts CSS custom properties and block class names from rendered HTML |

The hybrid approach uses server-side data for reliable token extraction and front-end sampling for validation (orphaned color warnings, component inference from rendered blocks).

### Generator layer

| Class | Responsibility |
|---|---|
| `WP_Designmd_TokenMapper` | Maps WP presets to DESIGN.md YAML structure. **No AI.** |
| `WP_Designmd_ProseGenerator` | Builds markdown section bodies from templates; optionally enriches via `wp_ai_client_prompt()` |
| `WP_Designmd_GapFiller` | On demand, AI fills sparse prose sections only |
| `WP_Designmd_DesignMdWriter` | Assembles YAML + prose, writes to uploads |

### Support layer

| Class | Responsibility |
|---|---|
| `WP_Designmd_DesignMdParser` | Parses YAML front matter and `##` sections |
| `WP_Designmd_BlockThemeChecker` | Rejects generation on non-block themes |
| `WP_Designmd_DesignMdLinter` | PHP validation aligned with Google DESIGN.md rules |

### REST layer

`WP_Designmd_DesignMdController` orchestrates the generate pipeline, settings persistence, and file download.

### Admin layer

`WP_Designmd_Admin` registers the Appearance submenu and enqueues the compiled React bundle. UI logic lives entirely in `src/`.

## Generate pipeline

1. Verify block theme
2. Load settings (`feature_page_ids`, `use_ai_prose`)
3. `SiteScanner::scan()`
4. `FrontEndSampler::sample( $feature_page_ids )`
5. `TokenMapper::map( $scan, $sample )`
6. `ProseGenerator::generate( $tokens, $scan, $use_ai_prose )`
7. `DesignMdWriter::assemble()` → content string
8. `DesignMdParser::parse()` — reject if unparseable
9. `DesignMdLinter::lint()` — reject if errors (file not written)
10. `DesignMdWriter::write_content()`
11. Update `wp_designmd_meta` option

Regenerate runs the same pipeline and includes a token diff when a previous file existed.

## Storage

| Item | Location |
|---|---|
| Generated file | `wp-content/uploads/wp-designmd/DESIGN.md` |
| Settings | Option `wp_designmd_settings` |
| Generation meta | Option `wp_designmd_meta` |

The uploads directory is intentional: the file persists across theme switches and does not modify theme source.

## AI usage boundaries

| Step | AI allowed? |
|---|---|
| Token extraction | Never |
| Prose on generate | Optional (`use_ai_prose` setting + `wp_supports_ai()`) |
| Fill gaps | Yes, manual trigger only |
| Lint / write gate | Never |

## Security model

- All REST routes require `edit_theme_options`
- Cookie auth + REST nonce for admin requests
- Feature page IDs sanitized as positive integers
- Fixed output path — no user-supplied filesystem paths
- AI prompts include design scan data only

## v1 limitations

- Block themes only
- Single site-wide DESIGN.md
- No in-admin source editing
- No server-side `@google/design.md` CLI
- Manual regenerate (no auto-sync on theme change)
- `color-mix()` values skipped in color tokens

See the [design spec](superpowers/specs/2026-05-29-wp-designmd-design.md) for full scope.
