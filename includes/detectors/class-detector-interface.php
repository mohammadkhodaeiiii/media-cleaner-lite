<?php
/**
 * Detector contract for modular reference detection.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite\Detectors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every reference detector must implement this contract.
 */
interface DetectorInterface {

	/**
	 * Unique detector identifier.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Human-readable detector label.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Detect attachment references.
	 *
	 * @return array<int, array{attachment_id:int, source:string, confidence:int}>
	 */
	public function detect(): array;
}
