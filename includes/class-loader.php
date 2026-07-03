<?php
/**
 * Hook loader.
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects every action and filter and registers them with WordPress in one place.
 */
final class Loader {

	/**
	 * Queued actions.
	 *
	 * @var array<int, array{hook:string, component:object, callback:string, priority:int, args:int}>
	 */
	private array $actions = array();

	/**
	 * Queued filters.
	 *
	 * @var array<int, array{hook:string, component:object, callback:string, priority:int, args:int}>
	 */
	private array $filters = array();

	/**
	 * Default hook priority.
	 */
	private const DEFAULT_PRIORITY = 10;

	/**
	 * Default accepted argument count.
	 */
	private const DEFAULT_ARGS = 1;

	/**
	 * Queue an action.
	 *
	 * @param string $hook      WordPress action name.
	 * @param object $component Object owning the callback.
	 * @param string $callback  Method name on the component.
	 * @param int    $priority  Hook priority.
	 * @param int    $args      Number of accepted arguments.
	 * @return void
	 */
	public function add_action( string $hook, object $component, string $callback, int $priority = self::DEFAULT_PRIORITY, int $args = self::DEFAULT_ARGS ): void {
		$this->actions[] = $this->normalize( $hook, $component, $callback, $priority, $args );
	}

	/**
	 * Queue a filter.
	 *
	 * @param string $hook      WordPress filter name.
	 * @param object $component Object owning the callback.
	 * @param string $callback  Method name on the component.
	 * @param int    $priority  Hook priority.
	 * @param int    $args      Number of accepted arguments.
	 * @return void
	 */
	public function add_filter( string $hook, object $component, string $callback, int $priority = self::DEFAULT_PRIORITY, int $args = self::DEFAULT_ARGS ): void {
		$this->filters[] = $this->normalize( $hook, $component, $callback, $priority, $args );
	}

	/**
	 * Register every queued action and filter with WordPress.
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->filters as $filter ) {
			add_filter( $filter['hook'], array( $filter['component'], $filter['callback'] ), $filter['priority'], $filter['args'] );
		}

		foreach ( $this->actions as $action ) {
			add_action( $action['hook'], array( $action['component'], $action['callback'] ), $action['priority'], $action['args'] );
		}
	}

	/**
	 * Build a normalized hook definition.
	 *
	 * @param string $hook      Hook name.
	 * @param object $component Component object.
	 * @param string $callback  Callback method.
	 * @param int    $priority  Priority.
	 * @param int    $args      Accepted arguments.
	 * @return array{hook:string, component:object, callback:string, priority:int, args:int}
	 */
	private function normalize( string $hook, object $component, string $callback, int $priority, int $args ): array {
		return array(
			'hook'      => $hook,
			'component' => $component,
			'callback'  => $callback,
			'priority'  => $priority,
			'args'      => $args,
		);
	}
}
