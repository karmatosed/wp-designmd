# REST API reference

Namespace: **`wp-designmd/v1`**

All routes require the **`edit_theme_options`** capability. Admin requests use cookie authentication with the WordPress REST nonce (`X-WP-Nonce`).

Base URL: `{site_url}/wp-json/wp-designmd/v1`

---

## GET `/designmd`

Returns the current DESIGN.md state for the admin preview.

### Response `200`

```json
{
  "tokens": {
    "version": "alpha",
    "name": "Twenty Twenty-Five",
    "colors": { "primary": "#111111" },
    "typography": {},
    "spacing": {},
    "rounded": {},
    "components": {}
  },
  "prose": {
    "Overview": "...",
    "Colors": "..."
  },
  "lint": {
    "findings": [],
    "summary": { "errors": 0, "warnings": 0, "info": 0 }
  },
  "meta": {
    "generated_at": "2026-05-29T12:00:00+00:00",
    "theme_slug": "twentytwentyfive",
    "scanned_urls": ["http://localhost:8902/"],
    "lint_summary": { "errors": 0, "warnings": 1, "info": 0 },
    "sparse": true
  },
  "sparse": true,
  "notices": [],
  "raw": "---\nname: ...\n---\n\n## Overview\n..."
}
```

Returns empty tokens/prose when no file exists yet.

---

## POST `/designmd/generate`

Runs the full generation pipeline and writes `uploads/wp-designmd/DESIGN.md`.

### Response `200`

Same shape as `GET /designmd` after successful generation.

### Response `400`

Block theme not active.

```json
{
  "code": "wp_designmd_not_block_theme",
  "message": "DesignMD generation only works with block themes.",
  "data": { "status": 400 }
}
```

### Response `422`

Generated content failed lint — file **not** written.

```json
{
  "code": "wp_designmd_lint_failed",
  "message": "Generated DESIGN.md failed validation and was not saved.",
  "data": {
    "status": 422,
    "findings": [ ... ]
  }
}
```

### Response `500`

Write failure or unparseable assembled content.

---

## POST `/designmd/regenerate`

Same pipeline as generate. Overwrites the existing file.

### Additional field on success

```json
{
  "diff": {
    "colors": { "added": [], "removed": [], "modified": ["tertiary"] },
    "typography": { "added": [], "removed": [], "modified": [] },
    "spacing": { "added": [], "removed": [], "modified": [] },
    "rounded": { "added": ["sm"], "removed": [], "modified": [] },
    "components": { "added": ["nav-link"], "removed": [], "modified": [] }
  }
}
```

`diff` is omitted on first generate (no previous file).

---

## POST `/designmd/fill-gaps`

AI-enriches sparse prose sections in the existing file. YAML tokens are not overwritten unless empty.

### Response `200`

Updated designmd payload (same shape as GET).

### Response `400`

AI unavailable or no file to fill.

---

## GET `/designmd/download`

Returns raw markdown for browser download.

### Response `200`

- `Content-Type: text/markdown; charset=utf-8`
- `Content-Disposition: attachment; filename="DESIGN.md"`
- Body: raw DESIGN.md file contents (not JSON)

Served via `rest_pre_serve_request` to avoid JSON encoding.

### Response `404`

No DESIGN.md file exists.

---

## GET `/settings`

Returns plugin settings.

```json
{
  "feature_page_ids": [12, 45],
  "use_ai_prose": true
}
```

---

## POST `/settings`

Saves plugin settings.

### Request body

```json
{
  "feature_page_ids": [12, 45],
  "use_ai_prose": true
}
```

| Field | Type | Description |
|---|---|---|
| `feature_page_ids` | `int[]` | Page IDs always included in front-end sample |
| `use_ai_prose` | `bool` | Use AI for prose on generate when available |

### Response `200`

Returns saved settings object.

### Response `400`

Invalid `feature_page_ids` (must be array of positive integers).

---

## Options stored

| Option | Contents |
|---|---|
| `wp_designmd_settings` | `feature_page_ids`, `use_ai_prose` |
| `wp_designmd_meta` | `generated_at`, `theme_slug`, `scanned_urls`, `lint_summary`, `sparse` |

Both options are deleted on uninstall.

---

## Client usage (React)

The admin app uses `@wordpress/api-fetch`:

```typescript
import apiFetch from '@wordpress/api-fetch';

// Generate
await apiFetch( { path: '/wp-designmd/v1/designmd/generate', method: 'POST' } );

// Download (raw body)
const markdown = await apiFetch( {
  path: '/wp-designmd/v1/designmd/download',
  parse: false,
} ).then( ( r ) => r.text() );
```

See `src/hooks/useDesignMd.ts` for the full client implementation.
