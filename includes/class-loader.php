<?php
/**
 * Register all actions and filters for the plugin.
 *
 * @package    HikmahLogin
 * @subpackage HikmahLogin/includes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hikmah_Login_Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @var array
	 */
	protected $actions = array();

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Add a new action to the collection.
	 *
	 * @param string $hook          WordPress hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Callback method.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Number of accepted args.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection.
	 *
	 * @param string $hook          WordPress hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Callback method.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Number of accepted args.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function used to register the actions and hooks.
	 *
	 * @param array  $hooks         The collection of hooks.
	 * @param string $hook          WordPress hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Callback method.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Number of accepted args.
	 * @return array The collection of actions and filters.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
