<?php
declare(strict_types=1);

namespace CB\Work;

defined( 'ABSPATH' ) || exit;

final class Capabilities {
	public const MANAGE     = 'cb_manage_work';
	public const TRACK_TIME = 'cb_track_work_time';

	public static function init(): void {
		add_filter( 'cb_core_capability_catalog', [ self::class, 'catalog' ] );
	}

	public static function install(): void {
		foreach ( [ 'administrator', 'cb_operator' ] as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( self::MANAGE );
				$role->add_cap( self::TRACK_TIME );
			}
		}
	}

	/** @param array<string,array<string,mixed>> $catalog @return array<string,array<string,mixed>> */
	public static function catalog( array $catalog ): array {
		$ready = did_action( 'init' ) > 0 || doing_action( 'init' );
		$group = $ready ? __( 'Core Blueprint Work', 'core-blueprint-work' ) : 'Core Blueprint Work';

		$catalog[ self::MANAGE ] = [
			'label'       => $ready ? __( 'Manage Work', 'core-blueprint-work' ) : 'Manage Work',
			'group'       => $group,
			'source'      => 'Core Blueprint Work',
			'description' => $ready
				? __( 'View and manage Work services, projects, work items, time and billing-ready records.', 'core-blueprint-work' )
				: 'View and manage Work services, projects, work items, time and billing-ready records.',
			'policy_grant' => false,
		];
		$catalog[ self::TRACK_TIME ] = [
			'label'       => $ready ? __( 'Track Work time', 'core-blueprint-work' ) : 'Track Work time',
			'group'       => $group,
			'source'      => 'Core Blueprint Work',
			'description' => $ready
				? __( 'Track your own time on Work Items assigned to you. Work managers retain full time-management access.', 'core-blueprint-work' )
				: 'Track your own time on Work Items assigned to you. Work managers retain full time-management access.',
			'policy_grant' => false,
		];
		return $catalog;
	}
}
