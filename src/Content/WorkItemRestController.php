<?php
declare(strict_types=1);

namespace CB\Work\Content;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Gutenberg REST bridge for private Work Items.
 *
 * The native posts endpoint exists only for authenticated editing. Builder-
 * neutral frontend exposure is a separate opt-in contract owned by D3.
 */
final class WorkItemRestController extends \WP_REST_Posts_Controller {
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
			__( 'You do not have permission to read Work Items.', 'core-blueprint-work' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}
}
