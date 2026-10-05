<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Governance\Events;
use CB\Work\Repository\Projects as ProjectRepository;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\Repository\WorkItems as WorkItemRepository;

defined( 'ABSPATH' ) || exit;

/** Governed server-side mutation contract for canonical Work Projects. */
final class ProjectActions {
	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public static function create( array $input ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}

		$input['created_by'] = get_current_user_id();
		$project_id = ProjectRepository::create( $input );
		if ( $project_id <= 0 ) {
			return new \WP_Error( 'work_project_create_failed' );
		}
		$project = Projects::get( $project_id );
		if ( null === $project ) {
			return new \WP_Error( 'work_project_unavailable' );
		}

		Audit::record( Events::PROJECT_CREATED, 'notice', [
			'project_id'    => $project_id,
			'actor_user_id' => get_current_user_id(),
			'channel'       => 'public_api',
		] );
		return $project;
	}

	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public static function update( int $project_id, array $input ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}
		if ( $project_id <= 0 || null === Projects::get( $project_id ) ) {
			return new \WP_Error( 'work_project_unavailable' );
		}
		if ( ! ProjectRepository::update( $project_id, $input ) ) {
			return new \WP_Error( 'work_project_update_failed' );
		}

		WorkItemRepository::sync_project_context( $project_id );
		RecurrenceRules::sync_project_context( $project_id );

		$project = Projects::get( $project_id );
		if ( null === $project ) {
			return new \WP_Error( 'work_project_unavailable' );
		}

		Audit::record( Events::PROJECT_UPDATED, 'notice', [
			'project_id'    => $project_id,
			'actor_user_id' => get_current_user_id(),
			'channel'       => 'public_api',
		] );
		return $project;
	}

	private static function authorize_manage(): ?\WP_Error {
		return current_user_can( Capabilities::MANAGE ) ? null : new \WP_Error( 'work_action_forbidden' );
	}

	private function __construct() {}
}
