<?php
/**
 * PSR-4 style autoloader mapping the plugin namespace to WordPress-style file names.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$segments = explode( '\\', $relative );
		$name     = (string) array_pop( $segments );

		$directory = MCL_PATH . 'includes/';
		foreach ( $segments as $segment ) {
			$directory .= strtolower( $segment ) . '/';
		}

		if ( in_array( 'Interfaces', $segments, true ) || str_ends_with( $name, 'Interface' ) ) {
			$type = 'interface';
			$name = (string) preg_replace( '/Interface$/', '', $name );
		} elseif ( in_array( 'Traits', $segments, true ) ) {
			$type = 'trait';
		} else {
			$type = 'class';
		}

		$slug = strtolower( (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $name ) );
		$file = $directory . $type . '-' . $slug . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
