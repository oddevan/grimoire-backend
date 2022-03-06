<?php
/**
 * Base class for API endpoints
 *
 * @package oddEvan\Grimoire
 */

namespace oddEvan\Grimoire\RestApi;

use \WP_REST_Request;
use \WP_REST_Response;

/**
 * Base class for API Endpoints
 */
abstract class BaseEndpoint {
	/**
	 * Endpoint namespace Defaults to grimoire/v1.
	 *
	 * @var string
	 */
	protected $namespace = 'grimoire/v1';

	/**
	 * Endpoint route.
	 *
	 * @var string
	 */
	protected $route;

	/**
	 * Whether to override any existing matching routes. Defaults to false.
	 *
	 * @var bool
	 */
	protected $override = false;

	/**
	 * Callback to register the route type with WordPress.
	 */
	public function register() {
		$args = array_merge( $this->get_default_args(), $this->get_args() );
		register_rest_route( $this->namespace, $this->route, $args, $this->override );
	}

	/**
	 * Get the arguments for this post type.
	 *
	 * Extending classes can override this method to pass in their own customizations specific to the endpoint.
	 */
	protected function get_args() : array {
		return [];
	}

	/**
	 * Get default arguments so subclasses don't have to define them
	 */
	protected function get_default_args() : array {
		return [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'run' ],
			'permission_callback' => '__return_true',
		];
	}

	/**
	 * Function that is called when the endpoint is hit
	 *
	 * @param WP_REST_Request $request Request information.
	 * @return WP_REST_Response Response information
	 */
	abstract public function run( WP_REST_Request $request);
}
