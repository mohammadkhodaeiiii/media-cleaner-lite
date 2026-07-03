<?php
/**
 * Service contract.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every bootable service must implement this contract.
 */
interface ServiceInterface {

	/**
	 * Register the service hooks with WordPress.
	 *
	 * @return void
	 */
	public function register(): void;
}
