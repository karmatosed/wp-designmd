# wp-designmd Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WordPress plugin that scans block theme design data + a capped front-end sample, generates a spec-compliant DESIGN.md file, and provides a WPDS React admin UI under Appearance → DesignMD.

**Architecture:** PHP backend (scanner, token mapper, writer, linter, optional AI prose) exposed via `wp-designmd/v1` REST API. React/TypeScript admin app built with `@wordpress/scripts`, using `@wordpress/admin-ui`, `@wordpress/ui`, and `@wordpress/theme`. Generated file stored at `wp-content/uploads/wp-designmd/DESIGN.md`.

**Tech Stack:** PHP 7.4+, WordPress 7.0+, TypeScript, React, `@wordpress/scripts`, WPDS packages, WP-CLI via `studio wp`.

**Spec:** `docs/superpowers/specs/2026-05-29-wp-designmd-design.md`

---

## File Map

| File | Responsibility |
|---|---|
| `wp-designmd.php` | Plugin header, constants, bootstrap |
| `includes/class-plugin.php` | Hook loader, registers all components |
| `includes/class-admin.php` | `add_theme_page`, enqueue React bundle |
| `includes/Rest/DesignMdController.php` | REST routes |
| `includes/Scanner/SiteScanner.php` | theme.json, templates, patterns |
| `includes/Scanner/FrontEndSampler.php` | Loopback URL fetch + CSS var extraction |
| `includes/Generator/TokenMapper.php` | WP presets → DESIGN.md YAML structure |
| `includes/Generator/ProseGenerator.php` | Templated + AI prose |
| `includes/Generator/GapFiller.php` | AI sparse-section fill |
| `includes/Generator/DesignMdWriter.php` | Assemble + write file |
| `includes/Linter/DesignMdLinter.php` | Spec-aligned validation |
| `includes/Support/BlockThemeChecker.php` | Validates block theme |
| `includes/Support/DesignMdParser.php` | Parse YAML front matter + markdown |
| `src/index.tsx` | React mount |
| `src/App.tsx` | Main admin layout |
| `src/components/*.tsx` | UI components |
| `src/hooks/useDesignMd.ts` | REST data hook |
| `src/types/designmd.ts` | Shared TS types |
| `uninstall.php` | Remove options + uploads dir |

---

### Task 1: Plugin scaffold and activation

**Files:**
- Create: `wp-designmd.php`
- Create: `includes/class-plugin.php`
- Create: `includes/Support/BlockThemeChecker.php`
- Create: `uninstall.php`
- Create: `readme.txt`

- [ ] **Step 1: Create main plugin file**

```php
<?php
/**
 * Plugin Name:       wp-designmd
 * Description:       Generate DESIGN.md from your block theme's visual identity.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            wp-designmd
 * License:           GPL-2.0-or-later
 * Text Domain:       wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_DESIGNMD_VERSION', '0.1.0' );
define( 'WP_DESIGNMD_FILE', __FILE__ );
define( 'WP_DESIGNMD_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_DESIGNMD_URL', plugin_dir_url( __FILE__ ) );

require_once WP_DESIGNMD_PATH . 'includes/class-plugin.php';

function wp_designmd(): WP_Designmd_Plugin {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new WP_Designmd_Plugin();
	}
	return $instance;
}

wp_designmd()->init();
```

- [ ] **Step 2: Create BlockThemeChecker**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_BlockThemeChecker {

	public function is_block_theme(): bool {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}
}
```

- [ ] **Step 3: Create Plugin loader**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_Plugin {

	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'load' ) );
		register_activation_hook( WP_DESIGNMD_FILE, array( $this, 'activate' ) );
	}

	public function load(): void {
		require_once WP_DESIGNMD_PATH . 'includes/Support/BlockThemeChecker.php';
		require_once WP_DESIGNMD_PATH . 'includes/class-admin.php';
		require_once WP_DESIGNMD_PATH . 'includes/Rest/DesignMdController.php';

		if ( is_admin() ) {
			new WP_Designmd_Admin();
		}

		add_action( 'rest_api_init', array( new WP_Designmd_DesignMdController(), 'register_routes' ) );
	}

	public function activate(): void {
		$upload_dir = wp_upload_dir();
		wp_mkdir_p( $upload_dir['basedir'] . '/wp-designmd' );
	}
}
```

- [ ] **Step 4: Create uninstall.php**

```php
<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'wp_designmd_settings' );
delete_option( 'wp_designmd_meta' );
$upload_dir = wp_upload_dir();
$dir        = $upload_dir['basedir'] . '/wp-designmd';
if ( is_dir( $dir ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	array_map( 'unlink', glob( $dir . '/*' ) ?: array() );
	rmdir( $dir );
}
```

- [ ] **Step 5: Verify activation**

Run: `studio wp plugin activate wp-designmd`  
Expected: `Success: Activated 1 of 1 plugins.`  
Run: `studio wp eval 'echo is_dir( wp_upload_dir()["basedir"] . "/wp-designmd" ) ? "yes" : "no";'`  
Expected: `yes`

---

### Task 2: SiteScanner

**Files:**
- Create: `includes/Scanner/SiteScanner.php`

- [ ] **Step 1: Write SiteScanner**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_SiteScanner {

	/**
	 * @return array<string, mixed>
	 */
	public function scan(): array {
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			return array();
		}

		$theme_json = WP_Theme_JSON_Resolver::get_merged_data();
		$data       = $theme_json->get_data();

		return array(
			'theme'           => wp_get_theme()->get_stylesheet(),
			'theme_name'      => wp_get_theme()->get( 'Name' ),
			'theme_json'      => $data,
			'templates'       => $this->get_templates(),
			'template_parts'  => $this->get_template_parts(),
			'pattern_slugs'   => $this->get_pattern_slugs(),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_templates(): array {
		if ( ! function_exists( 'get_block_templates' ) ) {
			return array();
		}
		$templates = get_block_templates( array(), 'wp_template' );
		return array_map(
			static function ( $template ) {
				return array(
					'slug'  => $template->slug,
					'title' => $template->title,
				);
			},
			$templates
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_template_parts(): array {
		if ( ! function_exists( 'get_block_templates' ) ) {
			return array();
		}
		$parts = get_block_templates( array( 'post_type' => 'wp_template_part' ), 'wp_template_part' );
		return array_map(
			static function ( $part ) {
				return array(
					'slug'  => $part->slug,
					'area'  => $part->area,
					'title' => $part->title,
				);
			},
			$parts
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function get_pattern_slugs(): array {
		if ( ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
			return array();
		}
		$registry = WP_Block_Patterns_Registry::get_instance();
		return array_keys( $registry->get_all_registered() );
	}
}
```

- [ ] **Step 2: Smoke test scanner**

Run:
```bash
studio wp eval '
require_once WP_PLUGIN_DIR . "/wp-designmd/includes/Scanner/SiteScanner.php";
$s = new WP_Designmd_SiteScanner();
$r = $s->scan();
echo $r["theme"] . " " . count( $r["templates"] ) . " templates";
'
```
Expected: active theme slug + template count > 0

---

### Task 3: FrontEndSampler

**Files:**
- Create: `includes/Scanner/FrontEndSampler.php`

- [ ] **Step 1: Write FrontEndSampler**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_FrontEndSampler {

	private const MAX_URLS = 8;

	/**
	 * @param array<int, int> $feature_page_ids
	 * @return array{urls: array<int, string>, css_vars: array<string, string>, blocks: array<int, string>, skipped: bool}
	 */
	public function sample( array $feature_page_ids = array() ): array {
		$urls    = $this->discover_urls( $feature_page_ids );
		$vars    = array();
		$blocks  = array();
		$skipped = false;

		foreach ( $urls as $url ) {
			$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
			if ( is_wp_error( $response ) ) {
				$skipped = true;
				continue;
			}
			$body = wp_remote_retrieve_body( $response );
			$vars = array_merge( $vars, $this->extract_css_vars( $body ) );
			$blocks = array_merge( $blocks, $this->extract_blocks( $body ) );
		}

		return array(
			'urls'     => $urls,
			'css_vars' => $vars,
			'blocks'   => array_values( array_unique( $blocks ) ),
			'skipped'  => $skipped && empty( $vars ),
		);
	}

	/**
	 * @param array<int, int> $feature_page_ids
	 * @return array<int, string>
	 */
	private function discover_urls( array $feature_page_ids ): array {
		$urls      = array( home_url( '/' ) );
		$templates = array();

		$post = get_posts( array( 'numberposts' => 1, 'post_status' => 'publish' ) );
		if ( ! empty( $post ) ) {
			$urls[] = get_permalink( $post[0] );
		}

		$page = get_posts( array( 'numberposts' => 1, 'post_type' => 'page', 'post_status' => 'publish' ) );
		if ( ! empty( $page ) ) {
			$urls[] = get_permalink( $page[0] );
		}

		$cat = get_categories( array( 'number' => 1 ) );
		if ( ! empty( $cat ) ) {
			$link = get_category_link( $cat[0]->term_id );
			if ( ! is_wp_error( $link ) ) {
				$urls[] = $link;
			}
		}

		foreach ( $feature_page_ids as $page_id ) {
			$permalink = get_permalink( (int) $page_id );
			if ( $permalink ) {
				$urls[] = $permalink;
			}
		}

		$urls = array_values( array_unique( $urls ) );
		return array_slice( $urls, 0, self::MAX_URLS + count( $feature_page_ids ) );
	}

	/**
	 * @return array<string, string>
	 */
	private function extract_css_vars( string $html ): array {
		$vars = array();
		if ( preg_match_all( '/(--wp--preset--(?:color|font-size|spacing)--[a-z0-9-]+)\s*:\s*([^;}{]+)/i', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$vars[ trim( $match[1] ) ] = trim( $match[2] );
			}
		}
		return $vars;
	}

	/**
	 * @return array<int, string>
	 */
	private function extract_blocks( string $html ): array {
		$blocks = array();
		if ( preg_match_all( '/wp-block-([a-z0-9-]+)/i', $html, $matches ) ) {
			$blocks = $matches[1];
		}
		return $blocks;
	}
}
```

- [ ] **Step 2: Smoke test sampler**

Run:
```bash
studio wp eval '
require_once WP_PLUGIN_DIR . "/wp-designmd/includes/Scanner/FrontEndSampler.php";
$s = new WP_Designmd_FrontEndSampler();
$r = $s->sample();
echo count($r["urls"]) . " urls, " . count($r["css_vars"]) . " vars";
'
```
Expected: at least 1 URL

---

### Task 4: TokenMapper

**Files:**
- Create: `includes/Generator/TokenMapper.php`
- Create: `tests/fixtures/theme-json-twentytwentyfive.json` (trimmed fixture)
- Create: `tests/php/TokenMapperTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
use PHPUnit\Framework\TestCase;

class TokenMapperTest extends TestCase {
	public function test_maps_palette_to_designmd_colors(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Generator/TokenMapper.php';
		$fixture = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/theme-json-twentytwentyfive.json' ), true );
		$mapper  = new WP_Designmd_TokenMapper();
		$tokens  = $mapper->map( array( 'theme_json' => $fixture, 'theme_name' => 'Twenty Twenty-Five' ) );
		$this->assertArrayHasKey( 'colors', $tokens );
		$this->assertArrayHasKey( 'primary', $tokens['colors'] );
		$this->assertStringStartsWith( '#', $tokens['colors']['primary'] );
	}
}
```

- [ ] **Step 2: Write TokenMapper**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_TokenMapper {

	/**
	 * @param array<string, mixed> $scan
	 * @return array<string, mixed>
	 */
	public function map( array $scan ): array {
		$data   = $scan['theme_json'] ?? array();
		$tokens = array(
			'version' => 'alpha',
			'name'    => $scan['theme_name'] ?? 'Site Design',
		);

		$tokens['colors']     = $this->map_colors( $data );
		$tokens['typography'] = $this->map_typography( $data );
		$tokens['spacing']    = $this->map_spacing( $data );
		$tokens['rounded']    = $this->map_rounded( $data );
		$tokens['components'] = $this->map_components( $data );

		return $tokens;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function map_colors( array $data ): array {
		$palette = $data['settings']['color']['palette'] ?? array();
		$mapped  = array();
		foreach ( $palette as $entry ) {
			$slug  = $entry['slug'] ?? '';
			$color = $entry['color'] ?? '';
			if ( ! $slug || ! $this->is_hex( $color ) ) {
				continue;
			}
			$key = match ( $slug ) {
				'contrast'  => 'primary',
				'base'      => 'neutral',
				'accent-1'  => 'tertiary',
				default     => $slug,
			};
			$mapped[ $key ] = strtoupper( $color );
		}
		return $mapped;
	}

	private function is_hex( string $color ): bool {
		return (bool) preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, array<string, mixed>>
	 */
	private function map_typography( array $data ): array {
		$out      = array();
		$families = $data['settings']['typography']['fontFamilies'] ?? array();
		$sizes    = $data['settings']['typography']['fontSizes'] ?? array();
		$default  = $families[0]['fontFamily'] ?? 'inherit';
		foreach ( $sizes as $size ) {
			$slug = $size['slug'] ?? '';
			if ( ! $slug ) {
				continue;
			}
			$name = match ( $slug ) {
				'large'  => 'headline-md',
				'medium' => 'body-md',
				'small'  => 'body-sm',
				default  => $slug,
			};
			$out[ $name ] = array(
				'fontFamily' => $default,
				'fontSize'   => $size['size'] ?? '1rem',
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function map_spacing( array $data ): array {
		$out   = array();
		$sizes = $data['settings']['spacing']['spacingSizes'] ?? array();
		foreach ( $sizes as $size ) {
			$slug = $size['slug'] ?? '';
			if ( $slug ) {
				$out[ $slug ] = $size['size'] ?? '0';
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function map_rounded( array $data ): array {
		$radius = $data['styles']['blocks']['core/button']['border']['radius'] ?? null;
		if ( $radius ) {
			return array( 'sm' => $radius );
		}
		return array();
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, array<string, mixed>>
	 */
	private function map_components( array $data ): array {
		$button = $data['styles']['blocks']['core/button'] ?? array();
		if ( empty( $button ) ) {
			return array();
		}
		return array(
			'button-primary' => array(
				'backgroundColor' => '{colors.tertiary}',
				'textColor'       => '{colors.neutral}',
				'rounded'         => '{rounded.sm}',
			),
		);
	}
}
```

- [ ] **Step 3: Run PHPUnit or eval fallback**

If PHPUnit not configured, run eval smoke test instead:
```bash
studio wp eval '
require_once WP_PLUGIN_DIR . "/wp-designmd/includes/Generator/TokenMapper.php";
require_once WP_PLUGIN_DIR . "/wp-designmd/includes/Scanner/SiteScanner.php";
$scan = (new WP_Designmd_SiteScanner())->scan();
$tokens = (new WP_Designmd_TokenMapper())->map( $scan );
echo isset($tokens["colors"]["primary"]) ? "pass" : "fail";
'
```
Expected: `pass`

---

### Task 5: DesignMdParser and DesignMdWriter

**Files:**
- Create: `includes/Support/DesignMdParser.php`
- Create: `includes/Generator/DesignMdWriter.php`

- [ ] **Step 1: Write DesignMdParser**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_DesignMdParser {

	/**
	 * @return array{tokens: array<string, mixed>, prose: array<string, string>, raw: string}|null
	 */
	public function parse( string $markdown ): ?array {
		if ( ! preg_match( '/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $markdown, $matches ) ) {
			return null;
		}
		$tokens = $this->parse_simple_yaml( $matches[1] );
		$prose  = $this->parse_sections( $matches[2] );
		return array(
			'tokens' => $tokens,
			'prose'  => $prose,
			'raw'    => $markdown,
		);
	}

	/**
	 * Minimal YAML parser for token front matter — uses wp_yaml_encode/decode if available,
	 * falls back to json conversion for flat token structures.
	 *
	 * @return array<string, mixed>
	 */
	private function parse_simple_yaml( string $yaml ): array {
		if ( function_exists( 'yaml_parse' ) ) {
			$parsed = yaml_parse( $yaml );
			return is_array( $parsed ) ? $parsed : array();
		}
		// Fallback: use symfony/yaml if added, or custom recursive parser for v1 token shapes.
		return $this->parse_yaml_lines( $yaml );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parse_yaml_lines( string $yaml ): array {
		// v1: delegate to spyc or implement limited parser in follow-up if needed.
		// For plan execution: add `mustangostang/spyc` via composer or inline minimal parser.
		if ( class_exists( 'Spyc' ) ) {
			return Spyc::YAMLLoadString( $yaml );
		}
		return array();
	}

	/**
	 * @return array<string, string>
	 */
	private function parse_sections( string $body ): array {
		$sections = array();
		if ( preg_match_all( '/^##\s+(.+?)\s*\n(.*?)(?=^##\s+|\z)/ms', $body, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$sections[ trim( $match[1] ) ] = trim( $match[2] );
			}
		}
		return $sections;
	}
}
```

- [ ] **Step 2: Write DesignMdWriter**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_DesignMdWriter {

	public function get_file_path(): string {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'wp-designmd/DESIGN.md';
	}

	/**
	 * @param array<string, mixed> $tokens
	 * @param array<string, string> $prose
	 */
	public function write( array $tokens, array $prose ): bool|WP_Error {
		$content = $this->assemble( $tokens, $prose );
		$path    = $this->get_file_path();
		$dir     = dirname( $path );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'wp_designmd_not_writable', __( 'Cannot create uploads directory.', 'wp-designmd' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $path, $content );
		if ( false === $written ) {
			return new WP_Error( 'wp_designmd_write_failed', __( 'Failed to write DESIGN.md.', 'wp-designmd' ) );
		}
		return true;
	}

	public function read(): string {
		$path = $this->get_file_path();
		return file_exists( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * @param array<string, mixed> $tokens
	 * @param array<string, string> $prose
	 */
	public function assemble( array $tokens, array $prose ): string {
		$yaml = $this->encode_yaml( $tokens );
		$body = $this->assemble_prose( $prose );
		return "---\n{$yaml}---\n\n{$body}";
	}

	/**
	 * @param array<string, mixed> $tokens
	 */
	private function encode_yaml( array $tokens ): string {
		if ( class_exists( 'Spyc' ) ) {
			return Spyc::YAMLDump( $tokens, 2, 0, true );
		}
		// Minimal fallback for flat keys
		$lines = array();
		foreach ( $tokens as $key => $value ) {
			if ( is_array( $value ) ) {
				$lines[] = "{$key}:";
				foreach ( $value as $k => $v ) {
					if ( is_array( $v ) ) {
						$lines[] = "  {$k}:";
						foreach ( $v as $pk => $pv ) {
							$lines[] = is_string( $pv ) ? "    {$pk}: {$pv}" : "    {$pk}: " . json_encode( $pv );
						}
					} else {
						$lines[] = is_string( $v ) ? "  {$k}: \"{$v}\"" : "  {$k}: {$v}";
					}
				}
			} else {
				$lines[] = "{$key}: {$value}";
			}
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @param array<string, string> $prose
	 */
	private function assemble_prose( array $prose ): string {
		$order = array(
			'Overview',
			'Colors',
			'Typography',
			'Layout',
			'Elevation & Depth',
			'Shapes',
			'Components',
			"Do's and Don'ts",
		);
		$out = array();
		foreach ( $order as $section ) {
			if ( ! empty( $prose[ $section ] ) ) {
				$out[] = "## {$section}\n\n{$prose[$section]}";
			}
		}
		return implode( "\n\n", $out ) . "\n";
	}
}
```

- [ ] **Step 3: Add Spyc for YAML (composer in plugin root)**

Create `composer.json`:
```json
{
  "require": {
    "mustangostang/spyc": "^0.6"
  },
  "autoload": {
    "classmap": ["includes/"]
  }
}
```

Run: `cd /Users/karmatosed/Repos/wp-designmd && composer install --no-dev`

Update `wp-designmd.php` to require `vendor/autoload.php` if present.

- [ ] **Step 4: End-to-end write test**

Run:
```bash
studio wp eval '
require_once WP_PLUGIN_DIR . "/wp-designmd/vendor/autoload.php";
require_once WP_PLUGIN_DIR . "/wp-designmd/includes/Scanner/SiteScanner.php";
require_once WP_PLUGIN_DIR . "/wp-designmd/includes/Generator/TokenMapper.php";
require_once WP_PLUGIN_DIR . "/wp-designmd/includes/Generator/DesignMdWriter.php";
$scan = (new WP_Designmd_SiteScanner())->scan();
$tokens = (new WP_Designmd_TokenMapper())->map( $scan );
$prose = array("Overview" => "Test overview.", "Colors" => "- Primary used for text.");
$w = new WP_Designmd_DesignMdWriter();
$result = $w->write( $tokens, $prose );
echo is_bool($result) && $result ? "written" : "failed";
'
```
Expected: `written`

---

### Task 6: ProseGenerator (templated + AI)

**Files:**
- Create: `includes/Generator/ProseGenerator.php`
- Create: `includes/Generator/GapFiller.php`

- [ ] **Step 1: Write templated ProseGenerator**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_ProseGenerator {

	/**
	 * @param array<string, mixed> $tokens
	 * @param array<string, mixed> $scan
	 * @return array<string, string>
	 */
	public function generate( array $tokens, array $scan, bool $use_ai = false ): array {
		$prose = $this->generate_templated( $tokens, $scan );
		if ( $use_ai && function_exists( 'wp_supports_ai' ) && wp_supports_ai() ) {
			$prose = $this->generate_with_ai( $tokens, $prose );
		}
		return $prose;
	}

	/**
	 * @param array<string, mixed> $tokens
	 * @param array<string, mixed> $scan
	 * @return array<string, string>
	 */
	private function generate_templated( array $tokens, array $scan ): array {
		$name = $tokens['name'] ?? 'Site Design';
		$prose = array();
		$prose['Overview'] = "{$name} is a block theme design system extracted from the active WordPress theme.";
		$prose['Colors']   = $this->color_prose( $tokens['colors'] ?? array() );
		$prose['Typography'] = '- Typography levels mapped from theme.json font size presets.';
		$prose['Layout']   = '- Layout follows block theme content and wide size constraints.';
		$prose['Shapes']   = '- Corner radii derived from block button styles where available.';
		$prose['Components'] = '- Core button styles mapped to button-primary component tokens.';
		$prose["Do's and Don'ts"] = "- Do use theme presets for consistency.\n- Don't hardcode colors outside the palette.";
		return $prose;
	}

	/**
	 * @param array<string, string> $colors
	 */
	private function color_prose( array $colors ): string {
		$lines = array();
		foreach ( $colors as $name => $hex ) {
			$lines[] = "- **" . ucfirst( $name ) . " ({$hex}):** Theme preset color.";
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param array<string, mixed> $tokens
	 * @param array<string, string> $prose
	 * @return array<string, string>
	 */
	private function generate_with_ai( array $tokens, array $prose ): array {
		$prompt = wp_ai_client_prompt(
			'Rewrite these DESIGN.md prose sections to be cohesive and professional. Return only valid markdown section bodies keyed by section name. Tokens: '
			. wp_json_encode( $tokens )
			. ' Current prose: '
			. wp_json_encode( $prose )
		);
		// Execute and parse response — implementation uses ->generate_text() or equivalent WP 7.0 API.
		$result = $prompt->using_model( 'default' )->generate_text();
		if ( is_wp_error( $result ) ) {
			return $prose;
		}
		// Parse AI output back into sections (fallback to templated on parse failure).
		return $prose;
	}
}
```

- [ ] **Step 2: Write GapFiller**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_GapFiller {

	public function __construct(
		private WP_Designmd_DesignMdParser $parser,
		private WP_Designmd_DesignMdWriter $writer
	) {}

	public function fill(): bool|WP_Error {
		if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
			return new WP_Error( 'wp_designmd_no_ai', __( 'AI is not available.', 'wp-designmd' ) );
		}
		$raw    = $this->writer->read();
		$parsed = $this->parser->parse( $raw );
		if ( ! $parsed ) {
			return new WP_Error( 'wp_designmd_no_file', __( 'No DESIGN.md to fill.', 'wp-designmd' ) );
		}
		$sparse = $this->detect_sparse( $parsed );
		if ( empty( $sparse ) ) {
			return true;
		}
		// AI fill only sparse sections, merge into prose, rewrite file.
		return $this->writer->write( $parsed['tokens'], $parsed['prose'] );
	}

	/**
	 * @param array{tokens: array<string, mixed>, prose: array<string, string>} $parsed
	 * @return array<int, string>
	 */
	public function detect_sparse( array $parsed ): array {
		$sparse = array();
		$overview = $parsed['prose']['Overview'] ?? '';
		if ( str_word_count( $overview ) < 40 ) {
			$sparse[] = 'Overview';
		}
		foreach ( $parsed['prose'] as $section => $content ) {
			if ( substr_count( $content, '- ' ) < 2 && $section !== 'Overview' ) {
				$sparse[] = $section;
			}
		}
		if ( count( $parsed['tokens']['components'] ?? array() ) < 3 ) {
			$sparse[] = 'Components';
		}
		return array_values( array_unique( $sparse ) );
	}

	public function is_sparse( array $parsed ): bool {
		return ! empty( $this->detect_sparse( $parsed ) );
	}
}
```

- [ ] **Step 3: Wire AI generate_text API**

Read `wp-includes/ai-client.php` and `WP_AI_Client_Prompt_Builder` for exact method name (`generate_text`, `run`, etc.) and update `ProseGenerator::generate_with_ai` accordingly before testing.

---

### Task 7: DesignMdLinter

**Files:**
- Create: `includes/Linter/DesignMdLinter.php`

- [ ] **Step 1: Write linter**

```php
<?php
declare( strict_types=1 );

class WP_Designmd_DesignMdLinter {

	/**
	 * @return array{findings: array<int, array<string, string>>, summary: array{errors: int, warnings: int, info: int}}
	 */
	public function lint( string $markdown ): array {
		$parser  = new WP_Designmd_DesignMdParser();
		$parsed  = $parser->parse( $markdown );
		$findings = array();

		if ( ! $parsed ) {
			$findings[] = array( 'severity' => 'error', 'path' => 'file', 'message' => 'Invalid DESIGN.md structure.' );
			return $this->summarize( $findings );
		}

		$tokens = $parsed['tokens'];
		if ( ! empty( $tokens['colors'] ) && empty( $tokens['colors']['primary'] ) ) {
			$findings[] = array( 'severity' => 'warning', 'path' => 'colors.primary', 'message' => 'Missing primary color.' );
		}
		if ( ! empty( $tokens['colors'] ) && empty( $tokens['typography'] ) ) {
			$findings[] = array( 'severity' => 'warning', 'path' => 'typography', 'message' => 'Colors defined but no typography tokens.' );
		}
		$findings = array_merge( $findings, $this->check_broken_refs( $tokens ) );
		$findings = array_merge( $findings, $this->check_section_order( $parsed['prose'] ) );

		return $this->summarize( $findings );
	}

	/**
	 * @param array<string, mixed> $tokens
	 * @return array<int, array<string, string>>
	 */
	private function check_broken_refs( array $tokens ): array {
		$findings = array();
		$flat     = wp_json_encode( $tokens );
		if ( preg_match_all( '/\{([a-z0-9_.]+)\}/i', $flat, $matches ) ) {
			foreach ( $matches[1] as $ref ) {
				if ( ! $this->ref_exists( $tokens, $ref ) ) {
					$findings[] = array(
						'severity' => 'error',
						'path'     => $ref,
						'message'  => "Broken token reference: {{$ref}}",
					);
				}
			}
		}
		return $findings;
	}

	/**
	 * @param array<string, mixed> $tokens
	 */
	private function ref_exists( array $tokens, string $ref ): bool {
		$parts = explode( '.', $ref );
		$cur   = $tokens;
		foreach ( $parts as $part ) {
			if ( ! is_array( $cur ) || ! array_key_exists( $part, $cur ) ) {
				return false;
			}
			$cur = $cur[ $part ];
		}
		return true;
	}

	/**
	 * @param array<string, string> $prose
	 * @return array<int, array<string, string>>
	 */
	private function check_section_order( array $prose ): array {
		$order    = array( 'Overview', 'Colors', 'Typography', 'Layout', 'Elevation & Depth', 'Shapes', 'Components', "Do's and Don'ts" );
		$present  = array_keys( $prose );
		$findings = array();
		$last     = -1;
		foreach ( $present as $section ) {
			$idx = array_search( $section, $order, true );
			if ( false !== $idx && $idx < $last ) {
				$findings[] = array(
					'severity' => 'warning',
					'path'     => $section,
					'message'  => 'Section out of canonical order.',
				);
			}
			if ( false !== $idx ) {
				$last = $idx;
			}
		}
		return $findings;
	}

	/**
	 * @param array<int, array<string, string>> $findings
	 * @return array{findings: array<int, array<string, string>>, summary: array{errors: int, warnings: int, info: int}}
	 */
	private function summarize( array $findings ): array {
		$summary = array( 'errors' => 0, 'warnings' => 0, 'info' => 0 );
		foreach ( $findings as $f ) {
			++$summary[ $f['severity'] === 'error' ? 'errors' : ( $f['severity'] === 'warning' ? 'warnings' : 'info' ) ];
		}
		return array( 'findings' => $findings, 'summary' => $summary );
	}
}
```

---

### Task 8: REST API controller

**Files:**
- Create: `includes/Rest/DesignMdController.php`
- Modify: `includes/class-plugin.php` (require new classes)

- [ ] **Step 1: Write DesignMdController**

Implement routes per spec:
- `GET /wp-designmd/v1/designmd`
- `POST /wp-designmd/v1/designmd/generate`
- `POST /wp-designmd/v1/designmd/regenerate`
- `POST /wp-designmd/v1/designmd/fill-gaps`
- `GET /wp-designmd/v1/designmd/download`
- `GET|POST /wp-designmd/v1/settings`

Permission: `current_user_can( 'edit_theme_options' )`

`generate` pipeline:
1. Check block theme
2. `$scan = SiteScanner->scan()`
3. `$sample = FrontEndSampler->sample( feature_page_ids )`
4. `$tokens = TokenMapper->map( $scan )`
5. `$prose = ProseGenerator->generate( $tokens, $scan, use_ai )`
6. `$writer->write( $tokens, $prose )`
7. `$lint = linter->lint( $writer->read() )`
8. Update `wp_designmd_meta` option
9. Return JSON: `{ tokens, prose, lint, meta, sparse, notices }`

- [ ] **Step 2: Test REST endpoint**

Run:
```bash
studio wp eval '
$user = get_user_by("login", "admin");
wp_set_current_user($user->ID);
$request = new WP_REST_Request("POST", "/wp-designmd/v1/designmd/generate");
$response = rest_do_request($request);
echo $response->get_status();
'
```
Expected: `200`

---

### Task 9: React admin scaffold

**Files:**
- Create: `package.json`
- Create: `tsconfig.json`
- Create: `src/index.tsx`
- Create: `src/App.tsx`
- Create: `src/types/designmd.ts`
- Modify: `includes/class-admin.php`

- [ ] **Step 1: Create package.json**

```json
{
  "name": "wp-designmd",
  "version": "0.1.0",
  "scripts": {
    "build": "wp-scripts build",
    "start": "wp-scripts start",
    "test": "wp-scripts test-unit-js"
  },
  "devDependencies": {
    "@wordpress/scripts": "^30.0.0"
  },
  "dependencies": {
    "@wordpress/admin-ui": "*",
    "@wordpress/api-fetch": "*",
    "@wordpress/components": "*",
    "@wordpress/element": "*",
    "@wordpress/i18n": "*",
    "@wordpress/icons": "*",
    "@wordpress/theme": "*",
    "@wordpress/ui": "*"
  }
}
```

- [ ] **Step 2: Create types**

```typescript
// src/types/designmd.ts
export interface DesignMdTokens {
  version?: string;
  name?: string;
  colors?: Record<string, string>;
  typography?: Record<string, Record<string, string>>;
  spacing?: Record<string, string>;
  rounded?: Record<string, string>;
  components?: Record<string, Record<string, string>>;
}

export interface LintFinding {
  severity: 'error' | 'warning' | 'info';
  path: string;
  message: string;
}

export interface DesignMdResponse {
  tokens: DesignMdTokens;
  prose: Record<string, string>;
  lint: { findings: LintFinding[]; summary: { errors: number; warnings: number; info: number } };
  meta: { generated_at?: string; theme_slug?: string; sparse?: boolean };
  notices: string[];
  raw?: string;
}
```

- [ ] **Step 3: Create admin enqueue in class-admin.php**

Register script `wp-designmd-admin` pointing to `build/index.js`, deps: `wp-element`, `wp-components`, `wp-api-fetch`, `wp-i18n`. Style deps: `wp-components`.

- [ ] **Step 4: Create index.tsx and App.tsx skeleton**

Mount with `createRoot( document.getElementById( 'wp-designmd-admin' ) )`.

`App.tsx` uses `@wordpress/admin-ui` `Page` and renders placeholder ActionsPanel + TokenPreview.

- [ ] **Step 5: Build assets**

Run: `cd /Users/karmatosed/Repos/wp-designmd && npm install && npm run build`  
Expected: `build/index.js` and `build/index.css` exist

- [ ] **Step 6: Verify admin page loads**

Visit: Appearance → DesignMD in Studio site. Expected: React app mounts without console errors.

---

### Task 10: React components

**Files:**
- Create: `src/hooks/useDesignMd.ts`
- Create: `src/components/ActionsPanel.tsx`
- Create: `src/components/TokenPreview.tsx`
- Create: `src/components/ProseViewer.tsx`
- Create: `src/components/LintReport.tsx`
- Create: `src/components/FeaturePagesPicker.tsx`

- [ ] **Step 1: useDesignMd hook**

Use `@wordpress/api-fetch` to call `/wp-designmd/v1/designmd` on mount. Expose `generate`, `regenerate`, `fillGaps`, `download`, `isLoading`, `error`, `data`.

- [ ] **Step 2: ActionsPanel**

Four `Button` components from `@wordpress/components`. Disable during loading. Show `Spinner` when `isLoading`. Show Fill gaps only when `data.meta.sparse === true`.

- [ ] **Step 3: TokenPreview**

Use `@wordpress/ui` `Stack` for layout. Colors: div swatches with `backgroundColor: hex` from tokens. Typography: render sample text with inline styles from typography tokens. Spacing/rounded: definition list.

- [ ] **Step 4: ProseViewer**

Render each prose section as `Card` with `PanelBody` — markdown as pre-formatted text for v1 (no markdown parser dependency). Upgrade to `@wordpress/block-editor` RichText preview post-v1 if needed.

- [ ] **Step 5: LintReport**

Map findings to `Notice` components by severity.

- [ ] **Step 6: FeaturePagesPicker**

`FormTokenField` saving to `POST /wp-designmd/v1/settings`. Suggestions from `GET /wp/v2/pages?search=`.

- [ ] **Step 7: Rebuild and smoke test full UI flow**

Run: `npm run build`  
Manual: Generate → preview populates → Download saves file → Fill gaps visible when sparse.

---

### Task 11: Integration and readme

**Files:**
- Modify: `readme.txt`
- Create: `.gitignore`

- [ ] **Step 1: Add .gitignore**

```
/node_modules/
/build/
/vendor/
composer.lock
package-lock.json
```

- [ ] **Step 2: Write readme.txt** with installation, usage, requirements (WP 7.0+, block theme).

- [ ] **Step 3: Full smoke test checklist**

1. `studio wp plugin activate wp-designmd`
2. Appearance → DesignMD loads
3. Generate creates `uploads/wp-designmd/DESIGN.md`
4. Token preview shows theme colors
5. Download works
6. Regenerate updates timestamp
7. Validate locally: `npx @google/design.md lint path/to/DESIGN.md`

---

## Spec Coverage Checklist

| Spec requirement | Task |
|---|---|
| Hybrid scan | Task 2, 3 |
| Uploads storage | Task 1, 5 |
| Block themes only | Task 1 BlockThemeChecker, Task 8 |
| Deterministic tokens | Task 4 |
| AI prose optional | Task 6 |
| Fill gaps button | Task 6, 10 |
| Feature pages setting | Task 8, 10 |
| PHP linter | Task 7 |
| REST API | Task 8 |
| WPDS React admin | Task 9, 10 |
| Generate/regenerate/download | Task 8, 10 |
| Token preview panel | Task 10 |

## Out of scope (confirmed)

Classic themes, CLI subprocess, multisite, in-admin edit, export formats — not in any task.
