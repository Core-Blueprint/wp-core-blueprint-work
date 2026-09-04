<?php
declare(strict_types=1);

namespace CB\Work;

defined( 'ABSPATH' ) || exit;

final class Capabilities {
	public const MANAGE = 'cb_manage_work';

	public static function init(): void {
		add_filter( 'cb_core_capability_catalog', [ self::class, 'catalog' ] );
	}

	public static function install(): void {
		foreach ( [ 'administrator', 'cb_operator' ] as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( self::MANAGE );
			}
		}
	}

	/** @param array<string,array<string,mixed>> $catalog @return array<string,array<string,mixed>> */
	public static function catalog( array $catalog ): array {
		$ready = did_action( 'init' ) > 0 || doing_action( 'init' );
		$catalog[ self::MANAGE ] = [
			'label'       => $ready ? __( 'Manage Work', 'core-blueprint-work' ) : 'Manage Work',
			'group'       => $ready ? __( 'Core Blueprint Work', 'core-blueprint-work' ) : 'Core Blueprint Work',
			'source'      => 'Core Blueprint Work',
			'description' => $ready
				? __( 'View and manage Work services, projects, work items, time and billing-ready records.', 'core-blueprint-work' )
				: 'View and manage Work services, projects, work items, time and billing-ready records.',
			'policy_grant' => false,
		];
		return $catalog;
	}
}
