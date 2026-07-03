<?php
/**
 * Media scanner orchestrator.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walks attachments and content in memory-efficient batches.
 */
class Scanner {

	/**
	 * Storage abstraction.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Cache layer.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Media indexer.
	 *
	 * @var MediaIndexer
	 */
	private MediaIndexer $media_indexer;

	/**
	 * Reference detector.
	 *
	 * @var ReferenceDetector
	 */
	private ReferenceDetector $reference_detector;

	/**
	 * Unused detector.
	 *
	 * @var UnusedDetector
	 */
	private UnusedDetector $unused_detector;

	/**
	 * Reporter.
	 *
	 * @var Reporter
	 */
	private Reporter $reporter;

	/**
	 * Constructor.
	 *
	 * @param Database          $database           Storage abstraction.
	 * @param Cache             $cache              Cache layer.
	 * @param MediaIndexer      $media_indexer      Media indexer.
	 * @param ReferenceDetector $reference_detector Reference detector.
	 * @param UnusedDetector    $unused_detector    Unused detector.
	 * @param Reporter          $reporter           Reporter.
	 */
	public function __construct(
		Database $database,
		Cache $cache,
		MediaIndexer $media_indexer,
		ReferenceDetector $reference_detector,
		UnusedDetector $unused_detector,
		Reporter $reporter
	) {
		$this->database           = $database;
		$this->cache              = $cache;
		$this->media_indexer      = $media_indexer;
		$this->reference_detector = $reference_detector;
		$this->unused_detector    = $unused_detector;
		$this->reporter           = $reporter;
	}

	/**
	 * Start a new scan.
	 *
	 * @return array<string, mixed>
	 */
	public function start(): array {
		$settings   = Helper::get_settings();
		$post_types = Helper::sanitize_post_types( $settings['post_types'] ?? array() );

		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		$attachments = $this->media_indexer->collect_attachment_ids();
		$posts       = $this->reference_detector->collect_post_ids( $post_types, 0 );

		$this->database->purge_scan_data();

		$state = $this->reporter->create_state( $attachments, $posts, $post_types );
		$this->database->save_state( $state );

		return $this->progress( $state );
	}

	/**
	 * Process the next batch.
	 *
	 * @return array<string, mixed>
	 */
	public function process_batch(): array {
		$state = $this->database->get_state();

		if ( empty( $state ) || ! in_array( $state['status'] ?? '', array( 'running' ), true ) ) {
			return array(
				'status'  => 'idle',
				'percent' => 0,
				'stats'   => $this->reporter->get_last_report(),
			);
		}

		if ( $this->is_time_exceeded( $state ) ) {
			$state['status'] = 'paused';
			$this->database->save_state( $state );

			return array_merge(
				$this->progress( $state ),
				array(
					'status'  => 'paused',
					'message' => __( 'اسکن به دلیل رسیدن به حداکثر زمان مجاز متوقف شد. برای ادامه روی «ادامه» بزنید.', 'media-cleaner-lite' ),
				)
			);
		}

		$phase = (string) ( $state['phase'] ?? 'index' );

		if ( 'index' === $phase ) {
			return $this->process_index_batch( $state );
		}

		if ( 'references' === $phase ) {
			return $this->process_reference_batch( $state );
		}

		if ( 'analyze' === $phase ) {
			return $this->process_analysis( $state );
		}

		return $this->progress( $state );
	}

	/**
	 * Pause the current scan.
	 *
	 * @return array<string, mixed>
	 */
	public function pause(): array {
		$state = $this->database->get_state();

		if ( empty( $state ) ) {
			return array( 'status' => 'idle' );
		}

		$state['status'] = 'paused';
		$this->database->save_state( $state );

		return array_merge(
			$this->progress( $state ),
			array( 'status' => 'paused' )
		);
	}

	/**
	 * Resume a paused scan.
	 *
	 * @return array<string, mixed>
	 */
	public function resume(): array {
		$state = $this->database->get_state();

		if ( empty( $state ) ) {
			return array( 'status' => 'idle' );
		}

		$state['status'] = 'running';
		$this->database->save_state( $state );

		return $this->process_batch();
	}

	/**
	 * Cancel the current scan.
	 *
	 * @return array<string, mixed>
	 */
	public function cancel(): array {
		$this->database->purge_scan_data();

		return array(
			'status'  => 'cancelled',
			'percent' => 0,
		);
	}

	/**
	 * Process one indexing batch.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed>
	 */
	private function process_index_batch( array $state ): array {
		$batch_size = Helper::clamp_int( Helper::get_setting( 'batch_size', 25 ), Helper::BATCH_MIN, Helper::BATCH_MAX );
		$queue      = isset( $state['attachment_queue'] ) && is_array( $state['attachment_queue'] ) ? $state['attachment_queue'] : array();
		$ids        = array_splice( $queue, 0, $batch_size );
		$index      = isset( $state['index'] ) && is_array( $state['index'] ) ? $state['index'] : array();

		$index = array_replace( $index, $this->media_indexer->index_batch( $ids ) );

		$state['attachment_queue'] = array_values( $queue );
		$state['index']            = $index;
		$state['indexed_count']    = count( $index );

		if ( empty( $state['attachment_queue'] ) ) {
			$state['phase'] = 'references';
		}

		$this->database->save_state( $state );

		return $this->progress( $state );
	}

	/**
	 * Process one reference-detection batch.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed>
	 */
	private function process_reference_batch( array $state ): array {
		$batch_size = Helper::clamp_int( Helper::get_setting( 'batch_size', 25 ), Helper::BATCH_MIN, Helper::BATCH_MAX );
		$queue      = isset( $state['post_queue'] ) && is_array( $state['post_queue'] ) ? $state['post_queue'] : array();
		$ids        = array_splice( $queue, 0, $batch_size );
		$references = isset( $state['references'] ) && is_array( $state['references'] ) ? $state['references'] : array();

		$batch_refs = $this->reference_detector->detect_posts_batch( $ids );
		$references = $this->merge_references( $references, $batch_refs );

		$state['post_queue']      = array_values( $queue );
		$state['references']      = $references;
		$state['processed_posts'] = (int) ( $state['processed_posts'] ?? 0 ) + count( $ids );

		if ( empty( $state['post_queue'] ) ) {
			$state['phase'] = 'analyze';
			$this->database->save_state( $state );

			return $this->process_analysis( $state );
		}

		$this->database->save_state( $state );

		return $this->progress( $state );
	}

	/**
	 * Run final analysis.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed>
	 */
	private function process_analysis( array $state ): array {
		$index      = isset( $state['index'] ) && is_array( $state['index'] ) ? $state['index'] : array();
		$references = isset( $state['references'] ) && is_array( $state['references'] ) ? $state['references'] : array();

		$global = $this->reference_detector->detect_global();
		$references = $this->merge_references( $references, $global );

		$this->database->save_index( $index );
		$this->database->save_references( $references );

		$classified = $this->unused_detector->classify( $index, $references );
		$report     = $this->reporter->finalize( $state, $index, $classified );

		$this->database->delete_state();
		$this->cache->flush();

		return array(
			'status'  => 'complete',
			'percent' => 100,
			'stats'   => $report,
		);
	}

	/**
	 * Merge reference maps.
	 *
	 * @param array<int, array<string, mixed>> $base  Existing references.
	 * @param array<int, array<string, mixed>> $batch New references.
	 * @return array<int, array<string, mixed>>
	 */
	private function merge_references( array $base, array $batch ): array {
		foreach ( $batch as $attachment_id => $data ) {
			$attachment_id = (int) $attachment_id;

			if ( ! is_array( $data ) ) {
				continue;
			}

			if ( ! isset( $base[ $attachment_id ] ) ) {
				$base[ $attachment_id ] = array(
					'attachment_id' => $attachment_id,
					'sources'       => array(),
					'confidence'    => 0,
				);
			}

			$base[ $attachment_id ]['sources'] = array_merge(
				(array) ( $base[ $attachment_id ]['sources'] ?? array() ),
				(array) ( $data['sources'] ?? array() )
			);

			$base[ $attachment_id ]['confidence'] = max(
				(int) ( $base[ $attachment_id ]['confidence'] ?? 0 ),
				(int) ( $data['confidence'] ?? 0 )
			);
		}

		return $base;
	}

	/**
	 * Build a progress payload.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return array<string, mixed>
	 */
	private function progress( array $state ): array {
		$phase             = (string) ( $state['phase'] ?? 'index' );
		$total_attachments = (int) ( $state['total_attachments'] ?? 0 );
		$total_posts       = (int) ( $state['total_posts'] ?? 0 );
		$indexed           = (int) ( $state['indexed_count'] ?? 0 );
		$processed_posts   = (int) ( $state['processed_posts'] ?? 0 );

		$index_weight = 40;
		$ref_weight   = 50;
		$analyze_weight = 10;

		$percent = 0;

		if ( 'index' === $phase ) {
			$percent = $total_attachments > 0 ? (int) floor( ( $indexed / $total_attachments ) * $index_weight ) : 0;
		} elseif ( 'references' === $phase ) {
			$post_percent = $total_posts > 0 ? ( $processed_posts / $total_posts ) : 1;
			$percent      = $index_weight + (int) floor( $post_percent * $ref_weight );
		} elseif ( 'analyze' === $phase ) {
			$percent = 95;
		}

		return array(
			'status'            => (string) ( $state['status'] ?? 'running' ),
			'phase'             => $phase,
			'percent'           => min( 99, max( 0, $percent ) ),
			'indexed_count'     => $indexed,
			'total_attachments' => $total_attachments,
			'processed_posts'   => $processed_posts,
			'total_posts'       => $total_posts,
			'stats'             => $this->reporter->progress_stats( $state ),
		);
	}

	/**
	 * Whether the scan exceeded the configured maximum time.
	 *
	 * @param array<string, mixed> $state Scan state.
	 * @return bool
	 */
	private function is_time_exceeded( array $state ): bool {
		$max_time = Helper::clamp_int(
			Helper::get_setting( 'max_scan_time', 300 ),
			Helper::SCAN_TIME_MIN,
			Helper::SCAN_TIME_MAX
		);
		$started  = (float) ( $state['started_at'] ?? microtime( true ) );

		return ( microtime( true ) - $started ) >= $max_time;
	}
}
