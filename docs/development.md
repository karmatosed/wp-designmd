# Development guide

Setup and testing for wp-designmd contributors.

## Prerequisites

- WordPress 7.0+
- PHP 8.0+
- Composer
- Node.js 18+ and npm
- WordPress Studio (recommended) or any local WP environment with a block theme

## Initial setup

```bash
git clone <repo-url> wp-designmd
cd wp-designmd

composer install
npm install
npm run build
```

### Link into a Studio site

```bash
ln -s "$(pwd)" /path/to/studio-site/wp-content/plugins/wp-designmd
cd /path/to/studio-site
studio site start --skip-browser
studio wp plugin activate wp-designmd
```

Open **Appearance → DesignMD** at your Studio site URL (from `studio site status`).

## Daily workflow

### PHP changes

Edit files under `includes/`. No build step required for PHP.

Verify syntax:

```bash
find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' | xargs php -l
```

Reload wp-admin or re-run REST calls to test.

### React / admin UI changes

Edit files under `src/`.

**Watch mode** (recommended during UI work):

```bash
npm run start
```

**Production build** (before commit or release):

```bash
npm run build
```

Output lands in `build/`:

- `index.js` / `index.css`
- `index.asset.php` (WordPress dependency manifest)
- `index-rtl.css`

The admin page enqueues these only on the DesignMD screen.

### JavaScript tests

```bash
npm run test
```

## Studio CLI reference

Always prefix WP-CLI with `studio` when using WordPress Studio:

| Task | Command |
|---|---|
| Activate plugin | `studio wp plugin activate wp-designmd` |
| Check block theme | `studio wp eval 'echo wp_is_block_theme();'` |
| Generate via REST | See [REST smoke test](#rest-smoke-test) below |
| Check output file | `studio wp eval 'echo file_exists( wp_upload_dir()["basedir"] . "/wp-designmd/DESIGN.md" ) ? "yes" : "no";'` |

## REST smoke test

```bash
studio wp eval '
$user = get_user_by( "login", "admin" );
wp_set_current_user( $user->ID );
$request  = new WP_REST_Request( "POST", "/wp-designmd/v1/designmd/generate" );
$response = rest_do_request( $request );
echo "HTTP " . $response->get_status() . "\n";
$data = $response->get_data();
if ( is_wp_error( $data ) ) {
  echo $data->get_error_message() . "\n";
} else {
  echo "colors: " . count( $data["tokens"]["colors"] ?? [] ) . "\n";
  echo "theme: " . ( $data["meta"]["theme_slug"] ?? "" ) . "\n";
}
'
```

Expected on a block theme: `HTTP 200` with color tokens and theme slug.

## External validation

After generation, validate the file with Google's official linter:

```bash
npx @google/design.md lint /path/to/wp-content/uploads/wp-designmd/DESIGN.md
```

## Composer dependencies

| Package | Purpose |
|---|---|
| `mustangostang/spyc` | YAML encode/decode for DESIGN.md front matter |

Run `composer install --no-dev` for production deployments from source.

## npm dependencies

| Package | Purpose |
|---|---|
| `@wordpress/scripts` | Build tooling (webpack, ESLint, Jest) |
| `@wordpress/admin-ui` | Admin page shell |
| `@wordpress/ui` | Layout primitives (`Stack`) |
| `@wordpress/theme` | WPDS design tokens |
| `@wordpress/components` | Buttons, notices, form fields |

## Troubleshooting

### Admin page is blank

- Run `npm run build` — `build/index.js` must exist
- Check browser console for script errors
- Confirm plugin is active

### Generate returns 422

Lint failed before write. Inspect findings:

```bash
studio wp eval '
$user = get_user_by("login", "admin");
wp_set_current_user($user->ID);
$r = rest_do_request(new WP_REST_Request("POST", "/wp-designmd/v1/designmd/generate"));
print_r($r->get_data());
'
```

Common cause: broken token references in component definitions.

### Generate returns 400 "block theme required"

Activate a block theme (e.g. Twenty Twenty-Five):

```bash
studio wp theme activate twentytwentyfive
```

### Front-end sample skipped

Loopback `wp_remote_get()` to local URLs failed. Generation still uses theme.json. Notice appears in the REST `notices` array.

### Fill gaps disabled

Requires WordPress 7.0 AI support (`wp_supports_ai()` returns true). Token generation works without AI.

## Release checklist

1. Bump version in `wp-designmd.php` and `readme.txt`
2. `composer install --no-dev`
3. `npm run build`
4. Include `build/` and `vendor/` in the release zip (or document build steps)
5. Run smoke test checklist in [CONTRIBUTING.md](../CONTRIBUTING.md)
