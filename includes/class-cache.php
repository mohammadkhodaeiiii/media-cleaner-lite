<?php
/**
 * Cache layer.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstracts transient caching for expensive calculations.
 */
class Cache {

	/**
	 * Storage abstraction.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Constructor.
	 *
	 * @param Database $database Storage abstraction.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
	}

	/**
	 * Retrieve a cached value or compute and store it.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Callback that returns the value when missing.
	 * @return mixed
	 */
	public function remember( string $key, callable $callback ): mixed {
		$cached = $this->database->get_cache( $key );

		if ( null !== $cached ) {
			return $cached;
		}

		$value = $callback();
		$this->set( $key, $value );

		return $value;
	}

	/**
	 * Store a value in the cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @return void
	 */
	public function set( string $key, mixed $value ): void {
		$lifetime = Helper::clamp_int(
			Helper::get_setting( 'cache_lifetime', 3600 ),
			Helper::CACHE_MIN,
			Helper::CACHE_MAX
		);

		$this->database->set_cache( $key, $value, $lifetime );
	}

	/**
	 * Retrieve a cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|null
	 */
	public function get( string $key ): mixed {
		return $this->database->get_cache( $key );
	}

	/**
	 * Clear all cached values.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->database->clear_cache();
	}
}
