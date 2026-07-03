<?php
/**
 * Unused media detector.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compares indexed attachments against detected references.
 */
class UnusedDetector {

	/**
	 * Classify media into used, unused and potentially unused buckets.
	 *
	 * @param array<int, array<string, mixed>> $index      Media index.
	 * @param array<int, array<string, mixed>> $references Reference map.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function classify( array $index, array $references ): array {
		$used        = array();
		$unused      = array();
		$potentially = array();

		foreach ( $index as $attachment_id => $item ) {
			$attachment_id = (int) $attachment_id;

			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( isset( $references[ $attachment_id ] ) ) {
				$confidence = (int) ( $references[ $attachment_id ]['confidence'] ?? 100 );
				$entry      = array_merge(
					$item,
					array(
						'status'     => 'referenced',
						'confidence' => $confidence,
						'sources'    => $references[ $attachment_id ]['sources'] ?? array(),
					)
				);
				$used[ $attachment_id ] = $entry;
				continue;
			}

			$parent = (int) wp_get_post_parent_id( $attachment_id );
			$score  = 100;

			if ( $parent > 0 ) {
				$score = 60;
			}

			$entry = array_merge(
				$item,
				array(
					'status'     => $score >= 80 ? 'unused' : 'potentially_unused',
					'confidence' => $score,
					'sources'    => array(),
				)
			);

			if ( $score >= 80 ) {
				$unused[ $attachment_id ] = $entry;
			} else {
				$potentially[ $attachment_id ] = $entry;
			}
		}

		return array(
			'referenced'          => $used,
			'unused'              => $unused,
			'potentially_unused'  => $potentially,
		);
	}

	/**
	 * Flatten classification into a single list with status labels.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $classified Classification result.
	 * @return array<int, array<string, mixed>>
	 */
	public function flatten( array $classified ): array {
		$list = array();

		foreach ( array( 'referenced', 'unused', 'potentially_unused' ) as $bucket ) {
			if ( empty( $classified[ $bucket ] ) || ! is_array( $classified[ $bucket ] ) ) {
				continue;
			}

			foreach ( $classified[ $bucket ] as $id => $item ) {
				$list[ (int) $id ] = $item;
			}
		}

		uasort(
			$list,
			static function ( array $a, array $b ): int {
				return (int) ( $b['file_size'] ?? 0 ) <=> (int) ( $a['file_size'] ?? 0 );
			}
		);

		return $list;
	}
}
