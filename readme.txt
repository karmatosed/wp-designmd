=== wp-designmd ===
Contributors: wp-designmd
Tags: design, block-theme, documentation, design-tokens, ai
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate a spec-compliant DESIGN.md from your block theme's visual identity for AI-assisted development workflows.

== Description ==

wp-designmd scans your active block theme and a capped sample of live front-end pages, then produces a valid [DESIGN.md](https://github.com/google-labs-code/design.md) file describing your site's visual identity.

**Features:**

* Hybrid scanning — theme.json, templates, patterns, global styles, and front-end CSS custom properties
* Deterministic token mapping — colors, typography, spacing, and layout presets mapped to DESIGN.md YAML
* Token preview panel — visualize extracted design tokens in the admin UI
* Generate, regenerate, and download workflows
* Optional AI prose and gap-filling via WordPress 7.0 AI (`wp_ai_client_prompt()`)
* Built-in PHP linter for DESIGN.md validation
* Feature pages setting — include specific pages in the front-end sample

**Requirements:**

* WordPress 7.0 or later
* PHP 8.0 or later
* An active block theme (classic themes are not supported)

The generated file is stored at `wp-content/uploads/wp-designmd/DESIGN.md`.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/wp-designmd`, or install through the WordPress plugins screen.
2. Run `composer install` inside the plugin directory if deploying from source (installs PHP dependencies).
3. Run `npm install && npm run build` if deploying from source (builds the admin UI assets in `build/`).
4. Activate the plugin through the **Plugins** screen in WordPress.
5. Ensure your site uses an active block theme.

== Usage ==

1. Go to **Appearance → DesignMD** in the WordPress admin.
2. Optionally configure **Feature pages** to include specific URLs in the front-end sample.
3. Click **Generate** to create `DESIGN.md` from your theme and site data.
4. Review the token preview and prose sections in the admin panel.
5. Use **Regenerate** to refresh the file after theme or site changes.
6. Use **Fill gaps** to let WordPress AI enrich missing prose sections (requires WordPress 7.0 AI support).
7. Click **Download** to save a copy of the generated file.

**External validation:**

Validate the generated file locally with the official linter:

`npx @google/design.md lint path/to/DESIGN.md`

== Frequently Asked Questions ==

= Does this work with classic themes? =

No. wp-designmd requires an active block theme with theme.json support.

= Where is DESIGN.md stored? =

In `wp-content/uploads/wp-designmd/DESIGN.md`. The uploads directory is used so the file persists independently of theme updates.

= Does token extraction use AI? =

No. Token mapping is fully deterministic. AI is used only for optional prose generation and gap-filling when enabled and when WordPress AI is available.

= What happens if front-end sampling fails? =

If loopback requests to your site fail, generation continues using theme.json and server-side sources only. A notice is returned indicating the front-end sample was skipped.

== Changelog ==

= 0.1.0 =
* Initial release.
* Block theme scanning and DESIGN.md generation.
* WPDS React admin UI under Appearance → DesignMD.
* REST API at `wp-designmd/v1`.
* Token preview, generate/regenerate/download, fill gaps, and PHP linter.
