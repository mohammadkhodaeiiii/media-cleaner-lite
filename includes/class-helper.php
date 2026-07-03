<?php
/**
 * Helper utilities.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless utility methods for sanitization, validation and option access.
 */
final class Helper {

	/**
	 * Minimum allowed batch size.
	 */
	public const BATCH_MIN = 1;

	/**
	 * Maximum allowed batch size.
	 */
	public const BATCH_MAX = 200;

	/**
	 * Minimum cache lifetime in seconds.
	 */
	public const CACHE_MIN = 60;

	/**
	 * Maximum cache lifetime in seconds.
	 */
	public const CACHE_MAX = 86400;

	/**
	 * Minimum maximum-scan-time value in seconds.
	 */
	public const SCAN_TIME_MIN = 30;

	/**
	 * Maximum maximum-scan-time value in seconds.
	 */
	public const SCAN_TIME_MAX = 3600;

	/**
	 * Minimum scan interval in hours.
	 */
	public const INTERVAL_MIN = 1;

	/**
	 * Maximum scan interval in hours.
	 */
	public const INTERVAL_MAX = 720;

	/**
	 * Retrieve the default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		/**
		 * Filter the default plugin settings.
		 *
		 * @param array<string, mixed> $defaults Default settings.
		 */
		return (array) apply_filters(
			'mcl_default_settings',
			array(
				'enabled'                 => true,
				'batch_size'              => 25,
				'scan_interval'           => 168,
				'ignore_svg'              => false,
				'ignore_external'         => true,
				'trash_instead_of_delete' => true,
				'cache_lifetime'          => 3600,
				'max_scan_time'           => 300,
				'post_types'              => array( 'post', 'page' ),
			)
		);
	}

	/**
	 * Retrieve the merged plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$stored = get_option( MCL_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::default_settings() );
	}

	/**
	 * Retrieve a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is missing.
	 * @return mixed
	 */
	public static function get_setting( string $key, mixed $default = null ): mixed {
		$settings = self::get_settings();

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Sanitize the full settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( mixed $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::default_settings();

		return array(
			'enabled'                 => self::to_bool( $input['enabled'] ?? false ),
			'batch_size'              => self::clamp_int( $input['batch_size'] ?? $defaults['batch_size'], self::BATCH_MIN, self::BATCH_MAX ),
			'scan_interval'           => self::clamp_int( $input['scan_interval'] ?? $defaults['scan_interval'], self::INTERVAL_MIN, self::INTERVAL_MAX ),
			'ignore_svg'              => self::to_bool( $input['ignore_svg'] ?? false ),
			'ignore_external'         => self::to_bool( $input['ignore_external'] ?? false ),
			'trash_instead_of_delete' => self::to_bool( $input['trash_instead_of_delete'] ?? false ),
			'cache_lifetime'          => self::clamp_int( $input['cache_lifetime'] ?? $defaults['cache_lifetime'], self::CACHE_MIN, self::CACHE_MAX ),
			'max_scan_time'           => self::clamp_int( $input['max_scan_time'] ?? $defaults['max_scan_time'], self::SCAN_TIME_MIN, self::SCAN_TIME_MAX ),
			'post_types'              => self::sanitize_post_types( $input['post_types'] ?? array() ),
		);
	}

	/**
	 * Cast a mixed value to boolean.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function to_bool( mixed $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize and clamp an integer to a range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Lower bound.
	 * @param int   $max   Upper bound.
	 * @return int
	 */
	public static function clamp_int( mixed $value, int $min, int $max ): int {
		$number = (int) $value;

		return (int) max( $min, min( $max, $number ) );
	}

	/**
	 * Sanitize a list of post type slugs against the registered public types.
	 *
	 * @param mixed $value Array or comma separated string.
	 * @return array<int, string>
	 */
	public static function sanitize_post_types( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value ) ?: array();
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = self::scannable_post_types();
		$clean   = array();

		foreach ( $value as $item ) {
			$key = sanitize_key( (string) $item );

			if ( '' !== $key && in_array( $key, $allowed, true ) ) {
				$clean[] = $key;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Retrieve the list of post type slugs that can be scanned.
	 *
	 * @return array<int, string>
	 */
	public static function scannable_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'names' );

		unset( $types['attachment'] );

		/**
		 * Filter the post types that are eligible for reference scanning.
		 *
		 * @param array<int, string> $types Public post type slugs.
		 */
		$types = (array) apply_filters( 'mcl_scannable_post_types', array_values( $types ) );

		return array_values( array_unique( array_map( 'sanitize_key', $types ) ) );
	}

	/**
	 * Format bytes for display.
	 *
	 * @param int $bytes File size in bytes.
	 * @return string
	 */
	public static function format_bytes( int $bytes ): string {
		$bytes = max( 0, $bytes );

		if ( $bytes < 1024 ) {
			return sprintf( '%s بایت', number_format_i18n( $bytes ) );
		}

		if ( $bytes < 1048576 ) {
			return sprintf( '%s کیلوبایت', number_format_i18n( $bytes / 1024, 1 ) );
		}

		if ( $bytes < 1073741824 ) {
			return sprintf( '%s مگابایت', number_format_i18n( $bytes / 1048576, 1 ) );
		}

		return sprintf( '%s گیگابایت', number_format_i18n( $bytes / 1073741824, 2 ) );
	}

	/**
	 * Format a duration in seconds for display.
	 *
	 * @param float $seconds Duration.
	 * @return string
	 */
	public static function format_duration( float $seconds ): string {
		return number_format_i18n( max( 0.0, $seconds ), 2 );
	}

	/**
	 * Whether a URL belongs to this site.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	public static function is_internal_url( string $url ): bool {
		$url = trim( $url );

		if ( '' === $url || '#' === $url[0] ) {
			return false;
		}

		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return true;
		}

		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( null === $host || '' === (string) $host ) {
			return true;
		}

		return is_string( $home ) && strtolower( (string) $host ) === strtolower( $home );
	}

	/**
	 * Resolve a URL to an attachment ID.
	 *
	 * @param string $url Media URL.
	 * @return int
	 */
	public static function url_to_attachment_id( string $url ): int {
		$url = trim( $url );

		if ( '' === $url ) {
			return 0;
		}

		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			$url = home_url( $url );
		}

		return (int) attachment_url_to_postid( $url );
	}

	/**
	 * Strip HTML tags and decode entities from a string.
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	public static function strip_html( string $html ): string {
		$text = wp_strip_all_tags( $html, true );

		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Extract attachment IDs from a string containing URLs or numeric IDs.
	 *
	 * @param string $content Content to search.
	 * @return array<int, int>
	 */
	public static function extract_attachment_ids_from_content( string $content ): array {
		$ids = array();

		if ( '' === trim( $content ) ) {
			return $ids;
		}

		if ( preg_match_all( '/wp-image-(\d+)/i', $content, $class_matches ) ) {
			foreach ( $class_matches[1] as $match ) {
				$id = absint( $match );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		if ( preg_match_all( '/"id"\s*:\s*(\d+)/', $content, $json_matches ) ) {
			foreach ( $json_matches[1] as $match ) {
				$id = absint( $match );
				if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
					$ids[] = $id;
				}
			}
		}

		if ( preg_match_all( '/\b(?:src|href|url)=["\']([^"\']+)["\']/i', $content, $url_matches ) ) {
			foreach ( $url_matches[1] as $url ) {
				$url = html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				if ( Helper::to_bool( Helper::get_setting( 'ignore_external', true ) ) && ! self::is_internal_url( $url ) ) {
					continue;
				}

				$attachment_id = self::url_to_attachment_id( $url );
				if ( $attachment_id > 0 ) {
					$ids[] = $attachment_id;
				}
			}
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Get upload directory base URL.
	 *
	 * @return string
	 */
	public static function upload_base_url(): string {
		$upload = wp_upload_dir();

		return is_array( $upload ) && ! empty( $upload['baseurl'] ) ? (string) $upload['baseurl'] : '';
	}

	/**
	 * Get upload directory base path.
	 *
	 * @return string
	 */
	public static function upload_base_path(): string {
		$upload = wp_upload_dir();

		return is_array( $upload ) && ! empty( $upload['basedir'] ) ? (string) $upload['basedir'] : '';
	}
}
