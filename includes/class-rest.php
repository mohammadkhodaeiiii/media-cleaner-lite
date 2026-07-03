<?php
/**
 * REST API integration.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

use MediaCleanerLite\Interfaces\ServiceInterface;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes capability-gated REST endpoints for internal integrations.
 */
final class Rest implements ServiceInterface {

	/**
	 * REST namespace.
	 */
	public const REST_NAMESPACE = 'media-cleaner-lite/v1';

	/**
	 * Shared hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Reporter.
	 *
	 * @var Reporter
	 */
	private Reporter $reporter;

	/**
	 * Storage abstraction.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Constructor.
	 *
	 * @param Loader   $loader   Shared hook loader.
	 * @param Reporter $reporter Reporter.
	 * @param Database $database Storage abstraction.
	 */
	public function __construct( Loader $loader, Reporter $reporter, Database $database ) {
		$this->loader   = $loader;
		$this->reporter = $reporter;
		$this->database = $database;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->loader->add_action( 'rest_api_init', $this, 'register_routes' );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/report',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_report' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/unused',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_unused' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/index',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_index' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		/**
		 * Fires after core REST routes are registered.
		 *
		 * @param string $namespace REST namespace.
		 */
		do_action( 'mcl_register_rest_routes', self::REST_NAMESPACE );
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function can_view(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return the latest report.
	 *
	 * @return WP_REST_Response
	 */
	public function get_report(): WP_REST_Response {
		return new WP_REST_Response( $this->reporter->get_last_report(), 200 );
	}

	/**
	 * Return unused media list.
	 *
	 * @return WP_REST_Response
	 */
	public function get_unused(): WP_REST_Response {
		return new WP_REST_Response( array_values( $this->database->get_unused() ), 200 );
	}

	/**
	 * Return media index.
	 *
	 * @return WP_REST_Response
	 */
	public function get_index(): WP_REST_Response {
		return new WP_REST_Response( $this->database->get_index(), 200 );
	}
}
