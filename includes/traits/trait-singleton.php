<?php
/**
 * Reusable singleton trait.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a single shared instance for the consuming class.
 */
trait Singleton {

	/**
	 * Shared instance store, keyed by concrete class name.
	 *
	 * @var array<string, static>
	 */
	private static array $instances = array();

	/**
	 * Retrieve the shared instance of the consuming class.
	 *
	 * @return static
	 */
	public static function instance(): static {
		$class = static::class;

		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new static();
		}

		return self::$instances[ $class ];
	}

	/**
	 * Constructor is protected to enforce singleton access.
	 */
	protected function __construct() {}

	/**
	 * Cloning is disabled for singletons.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Unserialization is disabled for singletons.
	 *
	 * @return void
	 */
	public function __wakeup(): void {
		throw new \RuntimeException( 'Singletons cannot be unserialized.' );
	}
}
