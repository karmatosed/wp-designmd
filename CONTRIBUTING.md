# Contributing to wp-designmd

Thank you for contributing. This plugin generates [DESIGN.md](https://github.com/google-labs-code/design.md) files from WordPress block themes.

## Prerequisites

- WordPress 7.0+ with an active block theme
- PHP 8.0+
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) 18+ and npm
- [WordPress Studio](https://developer.wordpress.com/studio/) (recommended for local development)

## Getting started

1. Fork and clone the repository.

2. Symlink or copy the plugin into your Studio site:

   ```bash
   ln -s /path/to/wp-designmd /path/to/studio-site/wp-content/plugins/wp-designmd
   ```

3. Install dependencies and build assets:

   ```bash
   cd wp-designmd
   composer install
   npm install
   npm run build
   ```

4. Activate the plugin:

   ```bash
   studio wp plugin activate wp-designmd
   ```

5. Open **Appearance → DesignMD** in wp-admin.

Full details: [docs/development.md](docs/development.md)

## Development workflow

| Command | Purpose |
|---|---|
| `npm run start` | Watch mode — rebuild admin UI on change |
| `npm run build` | Production build → `build/` |
| `npm run test` | JavaScript unit tests |
| `composer install` | PHP dependencies (Spyc for YAML) |

When changing PHP, run:

```bash
find . -name '*.php' -not -path './vendor/*' | xargs php -l
```

When changing the admin UI, always run `npm run build` before testing in the browser (unless using watch mode).

## Project structure

```
wp-designmd/
├── includes/           PHP backend
│   ├── Scanner/        Site + front-end sampling
│   ├── Generator/      Token mapping, prose, writer
│   ├── Linter/         DESIGN.md validation
│   ├── Rest/           REST API controller
│   └── Support/        Parser, block theme check
├── src/                React/TypeScript admin (WPDS)
├── build/              Compiled admin assets (generated)
├── docs/               Documentation
├── wp-designmd.php     Plugin bootstrap
└── uninstall.php       Cleanup on uninstall
```

## How to contribute

### Reporting bugs

Open an issue with:

- WordPress and PHP versions
- Active theme name
- Steps to reproduce
- Expected vs actual behaviour
- Relevant REST response or PHP error (no secrets)

### Pull requests

1. Create a focused branch from `main`.
2. Make the smallest change that solves the problem.
3. Follow existing code style (strict PHP types, WP coding standards, WPDS for React).
4. Update documentation if behaviour changes.
5. Verify locally (see checklist below).
6. Open a PR with a clear description and test plan.

### Documentation

- User-facing: `readme.txt`, `README.md`
- Developer: `docs/`
- AI agents: `AGENTS.md`

Update the relevant doc when you change behaviour, APIs, or setup steps.

## Code guidelines

### PHP

- Use WordPress APIs (`WP_Theme_JSON_Resolver`, `wp_remote_get`, options API).
- Sanitize input; escape output.
- REST routes must include a `permission_callback`.
- Token mapping stays deterministic — no AI in `TokenMapper`.
- Do not write generated files into the theme directory.

### JavaScript / TypeScript

- Use `@wordpress/scripts` for build tooling.
- Prefer `@wordpress/admin-ui`, `@wordpress/ui`, and `@wordpress/components`.
- Do not add Tailwind or other external UI frameworks.
- REST calls via `@wordpress/api-fetch`.

### DESIGN.md compliance

Generated output must conform to the [Google DESIGN.md spec](https://github.com/google-labs-code/design.md/blob/main/docs/spec.md). Validate externally:

```bash
npx @google/design.md lint path/to/DESIGN.md
```

## Verification checklist

Before submitting a PR:

- [ ] `php -l` passes on all plugin PHP files
- [ ] `npm run build` succeeds
- [ ] Plugin activates without fatal errors
- [ ] **Appearance → DesignMD** loads
- [ ] Generate creates/updates `uploads/wp-designmd/DESIGN.md`
- [ ] Download returns valid markdown
- [ ] REST endpoints return 403 without `edit_theme_options`

Quick REST smoke test:

```bash
studio wp eval '
$user = get_user_by("login", "admin");
wp_set_current_user($user->ID);
echo rest_do_request(new WP_REST_Request("POST", "/wp-designmd/v1/designmd/generate"))->get_status();
'
```

Expected: `200` on a block theme site.

## AI-assisted development

If you use AI coding tools, point them at [AGENTS.md](AGENTS.md) for project-specific instructions.

## License

By contributing, you agree that your contributions will be licensed under the GPL-2.0-or-later license that covers this project.
