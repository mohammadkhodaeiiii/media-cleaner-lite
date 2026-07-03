<?php
/**
 * Plugin bootstrapper.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

use MediaCleanerLite\Interfaces\ServiceInterface;
use MediaCleanerLite\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin: wires the loader, instantiates services and runs them.
 */
final class Plugin {

	use Singleton;

	/**
	 * Hook loader shared across services.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Registered services.
	 *
	 * @var array<int, ServiceInterface>
	 */
	private array $services = array();

	/**
	 * Whether the plugin has already booted.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Bootstrap the plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;
		$this->loader = new Loader();

		$this->load_textdomain();
		$this->register_services();
		$this->loader->run();
	}

	/**
	 * Access the shared loader instance.
	 *
	 * @return Loader
	 */
	public function loader(): Loader {
		return $this->loader;
	}

	/**
	 * Build, register and store every service.
	 *
	 * @return void
	 */
	private function register_services(): void {
		$database           = $this->build_storage();
		$cache              = new Cache( $database );
		$media_indexer      = new MediaIndexer();
		$reference_detector = new ReferenceDetector();
		$unused_detector    = new UnusedDetector();
		$reporter           = new Reporter( $database );
		$safe_delete        = new SafeDelete( $database, $reference_detector );
		$scanner            = new Scanner(
			$database,
			$cache,
			$media_indexer,
			$reference_detector,
			$unused_detector,
			$reporter
		);

		$this->services = array(
			new Settings( $this->loader ),
			new Assets( $this->loader ),
			new Admin( $this->loader, $reporter, $database ),
			new Ajax( $this->loader, $scanner, $reporter, $database, $safe_delete, $cache ),
			new Rest( $this->loader, $reporter, $database ),
		);

		/**
		 * Filter the registered services before they hook into WordPress.
		 *
		 * @param array<int, ServiceInterface> $services Registered services.
		 * @param Loader                       $loader   Shared hook loader.
		 * @param Database                     $database Storage abstraction.
		 */
		$this->services = (array) apply_filters( 'mcl_services', $this->services, $this->loader, $database );

		foreach ( $this->services as $service ) {
			if ( $service instanceof ServiceInterface ) {
				$service->register();
			}
		}
	}

	/**
	 * Build the storage abstraction.
	 *
	 * @return Database
	 */
	private function build_storage(): Database {
		$database = new Database();

		/**
		 * Filter the storage implementation used by the plugin.
		 *
		 * @param Database $database Default Options/Transients storage.
		 */
		$filtered = apply_filters( 'mcl_storage', $database );

		return $filtered instanceof Database ? $filtered : $database;
	}

	/**
	 * Load the plugin translations.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'media-cleaner-lite',
			false,
			dirname( plugin_basename( MCL_FILE ) ) . '/languages'
		);
	}
}
