# Admin UI

The wp-designmd admin screen lives at **Appearance → DesignMD**. It is a React application built with the WordPress Design System (WPDS).

## Tech stack

| Package | Role |
|---|---|
| `@wordpress/admin-ui` | `Page` layout — standard admin page shell |
| `@wordpress/ui` | `Stack` and layout primitives |
| `@wordpress/theme` | `--wpds-*` design tokens via `design-tokens.css` |
| `@wordpress/components` | `Button`, `Notice`, `Spinner`, `Card`, `PanelBody`, `FormTokenField` |
| `@wordpress/api-fetch` | REST client |
| `@wordpress/i18n` | Translatable strings |
| `@wordpress/icons` | Action icons |

Built with `@wordpress/scripts` (`npm run build` → `build/`).

## Page layout

```
Page (admin-ui)
├── ActionsPanel
│   ├── Generate
│   ├── Regenerate
│   ├── Fill gaps (visible when sparse)
│   └── Download
├── Notice(s) — errors, lint, AI unavailable, sample skipped
├── Two-column Stack
│   ├── TokenPreview — colors, typography, spacing, rounded
│   └── ProseViewer — markdown sections
├── LintReport — findings by severity
└── FeaturePagesPicker — settings panel
```

## Source files

| File | Purpose |
|---|---|
| `src/index.tsx` | Mount point (`#wp-designmd-admin`), imports design tokens CSS |
| `src/App.tsx` | Composes layout and wires hooks |
| `src/hooks/useDesignMd.ts` | REST data fetching and mutations |
| `src/components/ActionsPanel.tsx` | Primary action buttons |
| `src/components/TokenPreview.tsx` | Visual token swatches and samples |
| `src/components/ProseViewer.tsx` | Read-only prose sections |
| `src/components/LintReport.tsx` | Lint findings as notices |
| `src/components/FeaturePagesPicker.tsx` | Feature page selection |
| `src/types/designmd.ts` | TypeScript interfaces |

## WPDS styling rules

1. **Admin chrome** uses `--wpds-*` tokens from `@wordpress/theme`. Do not hardcode colors or spacing for UI chrome.

2. **Site token preview** renders extracted DESIGN.md values with inline styles (e.g. `backgroundColor: '#111111'` on swatches). These represent the *site's* design, not the admin UI.

3. **Do not mix namespaces:**
   - `--wp--preset--*` — site theme presets (preview content)
   - `--wpds-*` — admin UI chrome

4. Enqueue `wp-components` as a stylesheet dependency (handled in `class-admin.php`).

5. No Tailwind, Bootstrap, or other external CSS frameworks.

## PHP shell

`WP_Designmd_Admin` registers the page and enqueues assets:

```php
add_theme_page(
    'DesignMD',
    'DesignMD',
    'edit_theme_options',
    'wp-designmd',
    [ $this, 'render_page' ]
);
```

The render callback outputs only:

```html
<div id="wp-designmd-admin"></div>
```

Assets load exclusively on this admin screen (matched by hook suffix).

## REST integration

`useDesignMd` hook:

| Method | REST route |
|---|---|
| `refresh()` | `GET /wp-designmd/v1/designmd` |
| `generate()` | `POST /wp-designmd/v1/designmd/generate` |
| `regenerate()` | `POST /wp-designmd/v1/designmd/regenerate` |
| `fillGaps()` | `POST /wp-designmd/v1/designmd/fill-gaps` |
| `download()` | `GET /wp-designmd/v1/designmd/download` (raw text) |

Feature pages save via `POST /wp-designmd/v1/settings`. Page search uses core `GET /wp/v2/pages?search=`.

## UI states

| State | Behaviour |
|---|---|
| Empty | No DESIGN.md yet — prominent Generate button |
| Loading | Buttons disabled, spinner visible |
| Error | Error notice from REST failure |
| Sparse | Fill gaps button enabled |
| Lint warnings | Yellow notices in LintReport |

## Development

```bash
npm run start   # watch mode
npm run build   # production build
```

After UI changes, reload **Appearance → DesignMD** in wp-admin.

## Accessibility

- Use `@wordpress/components` for interactive controls (built-in a11y)
- Color swatches include text labels with hex values
- Notices use appropriate status roles

## Further reading

- [@wordpress/admin-ui handbook](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-admin-ui/)
- [REST API reference](rest-api.md)
- [Development guide](development.md)
