<?php
declare(strict_types=1);

namespace CB\Work\Governance;

use CoreBlueprint\Core\Governance\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Preserve canonical Work event contracts outside a Project bundle.
 * Inside a bundle, queue Work-owned events in the same SQL transaction.
 *
 * WordPress core save_post/meta hooks are outside this scoped guarantee.
 */
final class DeferredEvents {
	/** @var array<int,array<int,array{kind:string,name:string,payload:array}>> */
	private static array $frames = [];

	public static function begin(): void {
		self::$frames[] = [];
	}

	public static function cancel(): void {
		array_pop( self::$frames );
	}

	public static function finish(): bool {
		if ( [] === self::$frames ) {
			return false;
		}
		$events = self::$frames[ count( self::$frames ) - 1 ];
		if ( ! WorkEventOutbox::enqueue( $events ) ) {
			return false;
		}
		array_pop( self::$frames );
		return true;
	}

	/** @param array<string|int,mixed> $context */
	public static function audit( string $event_id, string $severity = 'info', array $context = [] ): bool {
		if ( [] === self::$frames ) {
			return Audit::record( $event_id, $severity, $context );
		}
		self::queue( 'audit', $event_id, [ 'severity' => $severity, 'context' => $context ] );
		return true;
	}

	public static function lifecycle( string $hook, mixed ...$arguments ): void {
		if ( [] === self::$frames ) {
			do_action( $hook, ...$arguments );
			return;
		}
		self::queue( 'lifecycle', $hook, [ 'args' => $arguments ] );
	}

	private static function queue( string $kind, string $name, array $payload ): void {
		$last = count( self::$frames ) - 1;
		if ( $last < 0 ) {
			throw new \LogicException( 'Work event queue has no active transaction.' );
		}
		if ( count( self::$frames[ $last ] ) >= 2000 ) {
			throw new \RuntimeException( 'Work Project bundle generated too many events.' );
		}
		self::$frames[ $last ][] = [ 'kind' => $kind, 'name' => $name, 'payload' => $payload ];
	}

	private function __construct() {}
}
