# AI Instructions — wp-designmd

WordPress plugin that generates [DESIGN.md](https://github.com/google-labs-code/design.md) files from block theme design data. Use this file when working in this repository.

## Project summary

| Item | Value |
|---|---|
| Type | WordPress plugin |
| Target WP | 7.0+ |
| Target PHP | 8.0+ |
| Theme support | Block themes only |
| Admin UI | React + WPDS under **Appearance → DesignMD** |
| Output file | `wp-content/uploads/wp-designmd/DESIGN.md` |
| REST namespace | `wp-designmd/v1` |

## Local development (WordPress Studio)

When the plugin is symlinked or copied into a Studio site, prefix all `wp` commands with `studio`:

```bash
studio wp plugin activate wp-designmd
studio wp eval 'echo wp_is_block_theme() ? "ok" : "no";'
```

See [docs/development.md](docs/development.md) for full setup.

## Architecture (read before changing code)

```
React admin (src/)  →  REST API (includes/Rest/)  →  PHP services
                                                      ├── Scanner/
                                                      ├── Generator/
                                                      ├── Linter/
                                                      └── Support/
```

**Rules:**

- **PHP** owns scanning, token mapping, file I/O, linting, and AI calls (`wp_ai_client_prompt()`).
- **React** owns admin UI only. No business logic duplication in JS.
- **Token extraction is deterministic.** Never use AI for YAML tokens.
- **AI is optional** for prose and manual “Fill gaps” only.
- **Do not modify the active theme.** Output goes to uploads.

Full architecture: [docs/architecture.md](docs/architecture.md)

## Key files

| Path | Purpose |
|---|---|
| `wp-designmd.php` | Bootstrap, constants, autoload |
| `includes/class-plugin.php` | Hook loader |
| `includes/class-admin.php` | Enqueues React bundle |
| `includes/Rest/DesignMdController.php` | REST routes + generate pipeline |
| `includes/Scanner/SiteScanner.php` | theme.json, templates, patterns |
| `includes/Scanner/FrontEndSampler.php` | Loopback URL sample (≤8 + feature pages) |
| `includes/Generator/TokenMapper.php` | WP presets → DESIGN.md YAML |
| `includes/Generator/ProseGenerator.php` | Templated + optional AI prose |
| `includes/Generator/GapFiller.php` | AI sparse-section fill |
| `includes/Generator/DesignMdWriter.php` | Assemble + write file |
| `includes/Linter/DesignMdLinter.php` | Spec-aligned validation |
| `src/App.tsx` | Admin layout |
| `src/hooks/useDesignMd.ts` | REST client |

## Skills to use

When working on this plugin, prefer these skills (from the parent site or global skills path):

| Task | Skill |
|---|---|
| Plugin structure, hooks, lifecycle | `wp-plugin-development` |
| REST routes, permissions | `wp-rest-api` |
| React admin / WPDS UI | `wpds` |
| Block theme / theme.json | `wp-block-themes` |
| Studio CLI | `studio-cli` |
| WP-CLI verification | `wp-wpcli-and-ops` |

## Coding conventions

### PHP

- `declare(strict_types=1);` in all files
- Classes prefixed `WP_Designmd_`
- Guard direct access with `ABSPATH` check
- Capability: `edit_theme_options` for all admin/REST actions
- Use `WP_Error` with HTTP status in REST layer
- No Node subprocess for linting — PHP linter only

### React / TypeScript

- Build with `@wordpress/scripts`
- UI packages: `@wordpress/admin-ui`, `@wordpress/ui`, `@wordpress/theme`, `@wordpress/components`
- Admin chrome uses `--wpds-*` tokens; site token preview uses extracted DESIGN.md values inline
- No Tailwind or external UI libraries

### DESIGN.md output

Follow the [Google DESIGN.md spec](https://github.com/google-labs-code/design.md/blob/main/docs/spec.md):

- YAML front matter (tokens) + markdown body (`##` sections in canonical order)
- Validate with `@google/design.md lint` externally; plugin uses PHP subset

## Common tasks

### Add a new token mapping

1. Extend `TokenMapper` — keep mapping deterministic
2. Update `DesignMdLinter` if new validation rules apply
3. Update `TokenPreview.tsx` if the admin should display it
4. Document in [docs/design-md-format.md](docs/design-md-format.md)

### Add a REST endpoint

1. Register in `DesignMdController::register_routes()`
2. Add `permission_callback` → `edit_theme_options`
3. Wire hook in `useDesignMd.ts`
4. Document in [docs/rest-api.md](docs/rest-api.md)

### Change admin UI

1. Edit components in `src/components/`
2. Run `npm run build` (or `npm run start` in watch mode)
3. Follow WPDS patterns — consult `wpds` skill when choosing components

## Verification checklist

Before claiming work is complete:

```bash
# PHP syntax
find . -name '*.php' -not -path './vendor/*' | xargs php -l

# Build admin assets
npm run build

# Studio smoke test (when site is running)
studio wp plugin activate wp-designmd
studio wp eval '
$user = get_user_by("login", "admin");
wp_set_current_user($user->ID);
$r = rest_do_request(new WP_REST_Request("POST", "/wp-designmd/v1/designmd/generate"));
echo $r->get_status();
'
# Expected: 200
```

Manual: **Appearance → DesignMD** loads, Generate works, Download saves a file.

## Documentation map

| Doc | Contents |
|---|---|
| [docs/README.md](docs/README.md) | Documentation index |
| [docs/architecture.md](docs/architecture.md) | System design |
| [docs/development.md](docs/development.md) | Setup, build, test |
| [docs/rest-api.md](docs/rest-api.md) | REST reference |
| [docs/design-md-format.md](docs/design-md-format.md) | Token mapping + spec compliance |
| [docs/admin-ui.md](docs/admin-ui.md) | WPDS React admin |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution guide |
| [docs/superpowers/specs/2026-05-29-wp-designmd-design.md](docs/superpowers/specs/2026-05-29-wp-designmd-design.md) | Approved design spec |

## Out of scope (v1)

Do not implement without an explicit request and spec update:

- Classic theme support
- In-admin DESIGN.md editing
- `@google/design.md` CLI subprocess on server
- Multisite network admin
- Export to Tailwind/DTCG
- Auto-regenerate on theme switch

## Git

Do not commit unless the user explicitly asks. Do not commit `vendor/`, `node_modules/`, or secrets.
