# DESIGN.md format and mapping

wp-designmd generates files conforming to the [Google DESIGN.md specification](https://github.com/google-labs-code/design.md/blob/main/docs/spec.md).

## File structure

Every generated file has two parts:

1. **YAML front matter** — machine-readable design tokens between `---` fences
2. **Markdown body** — human-readable rationale in `##` sections

```markdown
---
version: alpha
name: Twenty Twenty-Five
colors:
  primary: "#111111"
  neutral: "#FFFFFF"
typography:
  body-md:
    fontFamily: inherit
    fontSize: 1rem
---

## Overview

Twenty Twenty-Five is a block theme design system...

## Colors

- **Primary (#111111):** Theme preset color.
```

## Canonical section order

Sections present must follow this order:

1. Overview
2. Colors
3. Typography
4. Layout
5. Elevation & Depth
6. Shapes
7. Components
8. Do's and Don'ts

The plugin assembles prose in this order via `DesignMdWriter::assemble_prose()`.

## Token mapping (WordPress → DESIGN.md)

Mapping is **deterministic** in `TokenMapper`. No AI is involved.

### Colors

Source: `theme.json` → `settings.color.palette`

| WP slug | DESIGN.md token |
|---|---|
| `contrast` | `primary` |
| `base` | `neutral` |
| `accent-1` | `tertiary` |
| other slugs | preserved as-is |

- Values normalized to uppercase `#RRGGBB`
- Non-hex values (e.g. `color-mix(...)`) are skipped; noted in prose as dynamic

### Typography

Source: `settings.typography.fontSizes` + `fontFamilies`

| WP slug | DESIGN.md token |
|---|---|
| `large` | `headline-md` |
| `medium` | `body-md` |
| `small` | `body-sm` |
| other slugs | preserved |

Each token includes `fontFamily` and `fontSize` at minimum.

### Spacing

Source: `settings.spacing.spacingSizes` → direct slug-to-value map.

### Rounded

Source: `styles.blocks.core/button.border.radius` → `rounded.sm` when present.

Component tokens reference `{rounded.sm}` only when that token exists.

### Components

Inferred from theme block styles and front-end sample block classes:

| Signal | Component |
|---|---|
| `core/button` styles in theme.json | `button-primary` |
| `wp-block-search` on sampled pages | `input-field` |
| `wp-block-navigation` on sampled pages | `nav-link` |

Component properties use token references where possible:

```yaml
components:
  button-primary:
    backgroundColor: "{colors.tertiary}"
    textColor: "{colors.neutral}"
```

## Prose generation

### Default (no AI)

Templated bullets derived from token names and values. Functional but may be flagged as **sparse**.

### With AI (`use_ai_prose: true`)

`ProseGenerator` calls `wp_ai_client_prompt()` to rewrite section prose. YAML tokens are unchanged.

### Fill gaps (manual)

`GapFiller` detects sparse sections and uses AI to enrich prose only:

- Overview under 40 words
- Sections with fewer than 2 bullet points
- Fewer than 3 component tokens
- Linter warnings for missing typography/primary/orphaned tokens

## Linting

The PHP linter (`DesignMdLinter`) implements a subset of [@google/design.md lint rules](https://github.com/google-labs-code/design.md):

| Rule | Severity | Check |
|---|---|---|
| Invalid structure | error | Missing or malformed front matter |
| `broken-ref` | error | `{token.path}` does not resolve |
| `missing-primary` | warning | Colors without `primary` |
| `missing-typography` | warning | Colors without typography |
| `section-order` | warning | Sections out of canonical order |
| Orphaned colors | warning | Color token not seen in front-end CSS vars |

**Write gate:** If lint reports any **errors**, the file is not saved.

### External validation

For full spec compliance checking, use the official CLI locally:

```bash
npx @google/design.md lint path/to/DESIGN.md
npx @google/design.md diff DESIGN-v1.md DESIGN-v2.md
```

The plugin does not invoke the CLI on the server (Node is not assumed available on WordPress hosts).

## Front-end validation

`FrontEndSampler` extracts CSS custom properties from rendered pages:

```
--wp--preset--color--contrast
--wp--preset--font-size--medium
--wp--preset--spacing--50
```

These cross-check theme.json tokens. Colors defined but not observed on sampled pages trigger orphaned-color warnings.

## Version field

Generated files include `version: alpha` per the current DESIGN.md spec status.

## Further reading

- [Google DESIGN.md spec](https://github.com/google-labs-code/design.md/blob/main/docs/spec.md)
- [Architecture](architecture.md) — how scanning and generation connect
- [REST API](rest-api.md) — generate and download endpoints
