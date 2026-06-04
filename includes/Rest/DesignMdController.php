<?php
/**
 * REST controller for DESIGN.md generation and settings.
 *
 * @package wp-designmd
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers wp-designmd/v1 REST routes.
 */
class WP_Designmd_DesignMdController {

	private const REST_NAMESPACE = 'wp-designmd/v1';

	private WP_Designmd_BlockThemeChecker $block_theme_checker;
	private WP_Designmd_SiteScanner $site_scanner;
	private WP_Designmd_FrontEndSampler $front_end_sampler;
	private WP_Designmd_TokenMapper $token_mapper;
	private WP_Designmd_ProseGenerator $prose_generator;
	private WP_Designmd_DesignMdWriter $writer;
	private WP_Designmd_DesignMdLinter $linter;
	private WP_Designmd_DesignMdParser $parser;
	private WP_Designmd_GapFiller $gap_filler;

	public function __construct() {
		$this->block_theme_checker = new WP_Designmd_BlockThemeChecker();
		$this->site_scanner        = new WP_Designmd_SiteScanner();
		$this->front_end_sampler   = new WP_Designmd_FrontEndSampler();
		$this->token_mapper        = new WP_Designmd_TokenMapper();
		$this->prose_generator     = new WP_Designmd_ProseGenerator();
		$this->writer              = new WP_Designmd_DesignMdWriter();
		$this->linter              = new WP_Designmd_DesignMdLinter();
		$this->parser              = new WP_Designmd_DesignMdParser();
		$this->gap_filler          = new WP_Designmd_GapFiller( $this->parser, $this->writer );
	}

	/**
	 * Attach route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_download' ), 10, 4 );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/designmd',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_designmd' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/designmd/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate_designmd' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/designmd/regenerate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'regenerate_designmd' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/designmd/fill-gaps',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'fill_gaps' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/designmd/download',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'download_designmd' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings_route' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings_route' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Shared route permission check.
	 *
	 * @return true|WP_Error
	 */
	public function permissions_check() {
		if ( current_user_can( 'edit_theme_options' ) ) {
			return true;
		}

		return new WP_Error(
			'wp_designmd_forbidden',
			__( 'Sorry, you are not allowed to manage DesignMD.', 'wp-designmd' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * GET /designmd
	 */
	public function get_designmd( WP_REST_Request $request ) {
		unset( $request );

		$raw     = $this->writer->read();
		$parsed  = '' !== $raw ? $this->parser->parse( $raw ) : null;
		$lint    = '' !== $raw ? $this->linter->lint( $raw ) : $this->empty_lint();
		$meta    = $this->get_meta();
		$notices = array();

		if ( '' !== $raw && ! $parsed ) {
			$notices[] = array(
				'type'    => 'error',
				'message' => __( 'The existing DESIGN.md could not be parsed.', 'wp-designmd' ),
			);
		}

		return rest_ensure_response(
			array(
				'tokens'  => $parsed['tokens'] ?? array(),
				'prose'   => $parsed['prose'] ?? array(),
				'lint'    => $lint,
				'meta'    => $meta,
				'sparse'  => $parsed ? $this->gap_filler->is_sparse( $parsed ) : false,
				'notices' => $notices,
				'raw'     => $raw,
			)
		);
	}

	/**
	 * POST /designmd/generate
	 */
	public function generate_designmd( WP_REST_Request $request ) {
		unset( $request );

		return $this->run_generate_pipeline();
	}

	/**
	 * POST /designmd/regenerate
	 */
	public function regenerate_designmd( WP_REST_Request $request ) {
		unset( $request );

		$before_raw     = $this->writer->read();
		$before_parsed  = '' !== $before_raw ? $this->parser->parse( $before_raw ) : null;
		$before_tokens  = $before_parsed['tokens'] ?? null;

		return $this->run_generate_pipeline( $before_tokens );
	}

	/**
	 * POST /designmd/fill-gaps
	 */
	public function fill_gaps( WP_REST_Request $request ) {
		unset( $request );

		$result = $this->gap_filler->fill();

		if ( is_wp_error( $result ) ) {
			return $this->normalize_error( $result, 400 );
		}

		$raw    = $this->writer->read();
		$parsed = $this->parser->parse( $raw );

		if ( ! $parsed ) {
			return new WP_Error(
				'wp_designmd_parse_failed',
				__( 'DESIGN.md could not be parsed after filling gaps.', 'wp-designmd' ),
				array( 'status' => 500 )
			);
		}

		$lint   = $this->linter->lint( $raw );
		$sparse = $this->gap_filler->is_sparse( $parsed );
		$meta   = $this->get_meta();

		$meta['sparse']       = $sparse;
		$meta['lint_summary'] = $lint['summary'] ?? $this->empty_lint()['summary'];
		update_option( 'wp_designmd_meta', $meta );

		return rest_ensure_response(
			array(
				'tokens'  => $parsed['tokens'],
				'prose'   => $parsed['prose'],
				'lint'    => $lint,
				'meta'    => $meta,
				'sparse'  => $sparse,
				'notices' => array(),
			)
		);
	}

	/**
	 * GET /designmd/download
	 */
	public function download_designmd( WP_REST_Request $request ) {
		unset( $request );

		$raw = $this->writer->read();

		if ( '' === $raw ) {
			return new WP_Error(
				'wp_designmd_no_file',
				__( 'No DESIGN.md file is available to download.', 'wp-designmd' ),
				array( 'status' => 404 )
			);
		}

		$response = new WP_REST_Response( $raw, 200 );
		$response->header( 'Content-Type', 'text/markdown; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="DESIGN.md"' );

		return $response;
	}

	/**
	 * Serve raw markdown for download without JSON encoding.
	 *
	 * @param bool              $served  Whether the request has been served.
	 * @param WP_HTTP_Response  $result  Response object.
	 * @param WP_REST_Request   $request Request object.
	 * @param WP_REST_Server    $server  Server instance.
	 */
	public function serve_download( $served, $result, $request, $server ) {
		unset( $server );

		if ( $served || ! ( $request instanceof WP_REST_Request ) ) {
			return $served;
		}

		if ( '/wp-designmd/v1/designmd/download' !== $request->get_route() ) {
			return $served;
		}

		if ( ! ( $result instanceof WP_REST_Response ) ) {
			return $served;
		}

		$data = $result->get_data();
		if ( ! is_string( $data ) ) {
			return $served;
		}

		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="DESIGN.md"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $data;

		return true;
	}

	/**
	 * GET /settings
	 */
	public function get_settings_route( WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response( $this->get_settings() );
	}

	/**
	 * POST /settings
	 */
	public function update_settings_route( WP_REST_Request $request ) {
		$raw_feature_page_ids = $request->get_param( 'feature_page_ids' );
		$feature_page_ids     = array();

		if ( null !== $raw_feature_page_ids && ! is_array( $raw_feature_page_ids ) ) {
			return new WP_Error(
				'wp_designmd_invalid_feature_pages',
				__( 'feature_page_ids must be an array of positive integers.', 'wp-designmd' ),
				array( 'status' => 400 )
			);
		}

		if ( is_array( $raw_feature_page_ids ) ) {
			foreach ( $raw_feature_page_ids as $page_id ) {
				$normalized = absint( $page_id );
				if ( $normalized > 0 ) {
					$feature_page_ids[] = $normalized;
				}
			}
		}

		$feature_page_ids = array_values( array_unique( $feature_page_ids ) );
		$use_ai_prose     = rest_sanitize_boolean( $request->get_param( 'use_ai_prose' ) );

		$settings = array(
			'feature_page_ids' => $feature_page_ids,
			'use_ai_prose'     => $use_ai_prose,
		);

		update_option( 'wp_designmd_settings', $settings );

		return rest_ensure_response( $settings );
	}

	/**
	 * Execute full generate/regenerate pipeline.
	 *
	 * @param array<string, mixed>|null $before_tokens Previous tokens for regenerate diff.
	 * @return WP_REST_Response|WP_Error
	 */
	private function run_generate_pipeline( ?array $before_tokens = null ) {
		if ( ! $this->block_theme_checker->is_block_theme() ) {
			return new WP_Error(
				'wp_designmd_not_block_theme',
				__( 'DesignMD generation only works with block themes.', 'wp-designmd' ),
				array( 'status' => 400 )
			);
		}

		$settings         = $this->get_settings();
		$feature_page_ids = $settings['feature_page_ids'];
		$use_ai_prose     = $settings['use_ai_prose'];

		$scan   = $this->site_scanner->scan();
		$sample = $this->front_end_sampler->sample( $feature_page_ids );
		$tokens = $this->token_mapper->map( $scan, $sample );
		$prose  = $this->prose_generator->generate( $tokens, $scan, $use_ai_prose );

		$content = $this->writer->assemble( $tokens, $prose );
		$parsed  = $this->parser->parse( $content );

		if ( ! $parsed ) {
			return new WP_Error(
				'wp_designmd_invalid_output',
				__( 'Generated DESIGN.md could not be parsed and was not saved.', 'wp-designmd' ),
				array( 'status' => 500 )
			);
		}

		$lint = $this->linter->lint( $content, $sample['css_vars'] ?? array() );
		if ( ( $lint['summary']['errors'] ?? 0 ) > 0 ) {
			return new WP_Error(
				'wp_designmd_lint_failed',
				__( 'Generated DESIGN.md failed validation and was not saved.', 'wp-designmd' ),
				array(
					'status'   => 422,
					'findings' => $lint['findings'],
				)
			);
		}

		$write_result = $this->writer->write_content( $content );
		if ( is_wp_error( $write_result ) ) {
			return $this->normalize_error( $write_result, 500 );
		}

		$raw    = $this->writer->read();
		$sparse = $this->gap_filler->is_sparse( $parsed );
		$meta   = array(
			'generated_at' => gmdate( 'c' ),
			'theme_slug'   => isset( $scan['theme'] ) ? (string) $scan['theme'] : '',
			'scanned_urls' => $sample['urls'] ?? array(),
			'lint_summary' => $lint['summary'] ?? $this->empty_lint()['summary'],
			'sparse'       => $sparse,
		);

		update_option( 'wp_designmd_meta', $meta );

		$notices = array();
		if ( ! empty( $sample['skipped'] ) ) {
			$notices[] = array(
				'type'    => 'warning',
				'message' => __( 'Front-end sample skipped.', 'wp-designmd' ),
			);
		}

		$response = array(
			'tokens'  => $parsed['tokens'],
			'prose'   => $parsed['prose'],
			'lint'    => $lint,
			'meta'    => $meta,
			'sparse'  => $sparse,
			'notices' => $notices,
			'raw'     => $raw,
		);

		if ( null !== $before_tokens ) {
			$response['diff'] = $this->compute_token_diff( $before_tokens, $parsed['tokens'] );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param array<string, mixed> $before
	 * @param array<string, mixed> $after
	 * @return array<string, mixed>
	 */
	private function compute_token_diff( array $before, array $after ): array {
		$groups = array( 'colors', 'typography', 'spacing', 'rounded', 'components' );
		$diff   = array();

		foreach ( $groups as $group ) {
			$before_group = is_array( $before[ $group ] ?? null ) ? $before[ $group ] : array();
			$after_group  = is_array( $after[ $group ] ?? null ) ? $after[ $group ] : array();
			$diff[ $group ] = array(
				'added'    => array_values( array_diff( array_keys( $after_group ), array_keys( $before_group ) ) ),
				'removed'  => array_values( array_diff( array_keys( $before_group ), array_keys( $after_group ) ) ),
				'modified' => array_values(
					array_filter(
						array_keys( $after_group ),
						static function ( $key ) use ( $before_group, $after_group ) {
							return array_key_exists( $key, $before_group ) && $before_group[ $key ] !== $after_group[ $key ];
						}
					)
				),
			);
		}

		return $diff;
	}

	/**
	 * @return array{feature_page_ids: array<int, int>, use_ai_prose: bool}
	 */
	private function get_settings(): array {
		$settings = get_option( 'wp_designmd_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$feature_page_ids = array();

		if ( ! empty( $settings['feature_page_ids'] ) && is_array( $settings['feature_page_ids'] ) ) {
			foreach ( $settings['feature_page_ids'] as $page_id ) {
				$normalized = absint( $page_id );
				if ( $normalized > 0 ) {
					$feature_page_ids[] = $normalized;
				}
			}
		}

		return array(
			'feature_page_ids' => array_values( array_unique( $feature_page_ids ) ),
			'use_ai_prose'     => ! empty( $settings['use_ai_prose'] ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_meta(): array {
		$meta = get_option( 'wp_designmd_meta', array() );

		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * @return array{findings: array<int, array<string, string>>, summary: array{errors: int, warnings: int, info: int}}
	 */
	private function empty_lint(): array {
		return array(
			'findings' => array(),
			'summary'  => array(
				'errors'   => 0,
				'warnings' => 0,
				'info'     => 0,
			),
		);
	}

	/**
	 * Ensure returned WP_Error includes HTTP status.
	 */
	private function normalize_error( WP_Error $error, int $default_status ): WP_Error {
		$error_data = $error->get_error_data();
		$status     = $default_status;

		if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
			$status = (int) $error_data['status'];
		}

		$error->add_data( array( 'status' => $status ) );

		return $error;
	}
}
