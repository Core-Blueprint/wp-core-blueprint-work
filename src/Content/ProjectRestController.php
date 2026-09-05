<?php
declare(strict_types=1);

namespace CB\Work\Content;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Gutenberg REST bridge for private Work Projects.
 *
 * This controller exists only so WordPress' block editor can use the native
 * Project CPT without turning the core posts endpoint into frontend exposure.
 * Builder-neutral frontend resources remain a separate, opt-in contract.
 */
final class ProjectRestController extends \WP_REST_Posts_Controller {
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return self::forbidden();
		}

		return parent::get_items_permissions_check( $request );
	}

	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return self::forbidden();
		}

		return parent::get_item_permissions_check( $request );
	}

	private static function forbidden(): \WP_Error {
		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to read Work Projects.', 'core-blueprint-work' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}
}
