<?php
/**
 * Report builder.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns scan data into dashboard statistics and stores the final report.
 */
class Reporter {

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
	 * Create a fresh scan-state accumulator.
	 *
	 * @param array<int, int>    $attachment_queue Attachment IDs to index.
	 * @param array<int, int>    $post_queue       Post IDs to scan.
	 * @param array<int, string> $post_types       Post type slugs.
	 * @return array<string, mixed>
	 */
	public function create_state( array $attachment_queue, array $post_queue, array $post_types ): array {
		return array(
			'status'            => 'running',
			'phase'             => 'index',
			'attachment_queue'  => array_values( array_map( 'absint', $attachment_queue ) ),
			'post_queue'        => array_values( array_map( 'absint', $post_queue ) ),
			'indexed_count'     => 0,
			'processed_posts'   => 0,
			'total_attachments' => count( $attachment_queue ),
			'total_posts'       => count( $post_queue ),
			'started_at'        => microtime( true ),
			'post_types'        => array_values( $post_types ),
			'index'             => array(),
			'references'        => array(),
		);
	}

	/**
	 * Build report statistics from classification results.
	 *
	 * @param array<int, array<string, mixed>>             $index        Media index.
	 * @param array<string, array<int, array<string, mixed>>> $classified  Classification.
	 * @param float                                          $duration    Scan duration.
	 * @return array<string, mixed>
	 */
	public function summarize( array $index, array $classified, float $duration ): array {
		$referenced = $classified['referenced'] ?? array();
		$unused     = $classified['unused'] ?? array();
		$potential  = $classified['potentially_unused'] ?? array();

		$recoverable = 0;
		$largest     = array();
		$by_mime     = array();

		foreach ( $unused as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$size        = (int) ( $item['file_size'] ?? 0 );
			$recoverable += $size;
			$mime        = (string) ( $item['mime_type'] ?? 'unknown' );

			if ( ! isset( $by_mime[ $mime ] ) ) {
				$by_mime[ $mime ] = array( 'count' => 0, 'size' => 0 );
			}

			++$by_mime[ $mime ]['count'];
			$by_mime[ $mime ]['size'] += $size;

			$largest[] = $item;
		}

		usort(
			$largest,
			static function ( array $a, array $b ): int {
				return (int) ( $b['file_size'] ?? 0 ) <=> (int) ( $a['file_size'] ?? 0 );
			}
		);

		$largest = array_slice( $largest, 0, 10 );

		$total       = count( $index );
		$used_count  = count( $referenced );
		$coverage    = $total > 0 ? (int) round( ( $used_count / $total ) * 100 ) : 0;

		return array(
			'total_media'         => $total,
			'used_media'          => $used_count,
			'unused_media'        => count( $unused ),
			'potentially_unused'  => count( $potential ),
			'recoverable_bytes'   => $recoverable,
			'recoverable_human'   => Helper::format_bytes( $recoverable ),
			'largest_unused'      => $largest,
			'media_by_mime'       => $by_mime,
			'reference_coverage'  => $coverage,
			'duration'            => round( max( 0.0, $duration ), 2 ),
			'potential_risks'     => count( $potential ),
		);
	}

	/**
	 * Finalize and store a completed report.
	 *
	 * @param array<string, mixed>                           $state       Scan state.
	 * @param array<int, array<string, mixed>>               $index       Media index.
	 * @param array<string, array<int, array<string, mixed>>> $classified Classification.
	 * @return array<string, mixed>
	 */
	public function finalize( array $state, array $index, array $classified ): array {
		$started  = (float) ( $state['started_at'] ?? microtime( true ) );
		$duration = max( 0.0, microtime( true ) - $started );

		$report = $this->summarize( $index, $classified, $duration );
		$report['post_types']   = array_values( (array) ( $state['post_types'] ?? array() ) );
		$report['completed_at'] = time();

		$this->database->save_report( $report );

		$unused_detector = new UnusedDetector();
		$this->database->save_unused( $unused_detector->flatten( $classified ) );

		return $report;
	}

	/**
	 * Retrieve the last stored report.
	 *
	 * @return array<string, mixed>
	 */
	public function get_last_report(): array {
		return $this->database->get_report();
	}

	/**
	 * Build a lightweight progress summary for AJAX responses.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed>
	 */
	public function progress_stats( array $state ): array {
		$report = $this->get_last_report();

		if ( ! empty( $report ) ) {
			return $report;
		}

		$index = isset( $state['index'] ) && is_array( $state['index'] ) ? $state['index'] : array();

		return array(
			'total_media'        => (int) ( $state['total_attachments'] ?? 0 ),
			'indexed_count'      => count( $index ),
			'processed_posts'    => (int) ( $state['processed_posts'] ?? 0 ),
			'reference_coverage' => 0,
			'duration'           => round( max( 0.0, microtime( true ) - (float) ( $state['started_at'] ?? microtime( true ) ) ), 2 ),
		);
	}
}
