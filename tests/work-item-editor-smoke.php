<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );

	final class WP_Post {
		public function __construct(
			public int $ID,
			public string $post_type = 'cb_work_item',
			public string $post_status = 'draft'
		) {}
	}

	class WP_Error {}

	$GLOBALS['cb_work_picker_calls'] = [];
	$GLOBALS['cb_work_picker_enqueue_count'] = 0;
	$GLOBALS['cb_work_screen'] = (object) [ 'post_type' => 'cb_work_item' ];

	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { return true; }
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { return true; }
	function get_current_screen(): object { return $GLOBALS['cb_work_screen']; }
	function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' ); }
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
	function wp_unslash( mixed $value ): mixed { return $value; }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function __( string $text, string $domain = 'default' ): string { return $text; }
	function esc_html__( string $text, string $domain = 'default' ): string { return $text; }
	function esc_html_e( string $text, string $domain = 'default' ): void { echo $text; }
	function esc_html( string $text ): string { return $text; }
	function esc_attr( string $text ): string { return $text; }
	function selected( mixed $selected, mixed $current, bool $display = true ): string {
		$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
		if ( $display ) { echo $result; }
		return $result;
	}
	function wp_nonce_field( string $action, string $name ): void { unset( $action, $name ); }
	function wp_create_nonce( string $action ): string { return 'nonce-' . $action; }
	function current_user_can( string $capability, mixed ...$args ): bool { return true; }
	function wp_is_post_revision( int $post_id ): bool { return false; }
	function wp_is_post_autosave( int $post_id ): bool { return false; }
	function wp_verify_nonce( string $nonce, string $action ): bool { return '' !== $nonce && '' !== $action; }
	function get_current_user_id(): int { return 7; }
	function get_userdata( int $user_id ): object|false { return false; }
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "Work Item editor smoke failed: {$message}\n" );
			exit( 1 );
		}
	}
}

namespace CB\Core\Governance {
	final class Audit {
		public static function record( string $id, string $severity = 'info', array $context = [] ): bool {
			unset( $id, $severity, $context );
			return true;
		}
	}
}

namespace CB\Core\UI {
	final class Assets {
		public static function enqueue_object_picker(): void {
			$GLOBALS['cb_work_picker_enqueue_count']++;
		}
	}

	final class ObjectPicker {
		public static function render( array $args ): string {
			$GLOBALS['cb_work_picker_calls'][] = $args;
			return '<div class="object-picker"></div>';
		}
	}
}

namespace CB\Work {
	final class Capabilities { public const MANAGE = 'cb_manage_work'; }
}

namespace CB\Work\Content {
	final class PostTypes {
		public const PROJECT = 'cb_work_project';
		public const WORK_ITEM = 'cb_work_item';
	}
}

namespace CB\Work\Domain {
	final class BillingDisposition {
		public static function all(): array { return [ 'hourly', 'fixed', 'included', 'non_billable' ]; }
	}
	final class WorkItemPriority {
		public const NORMAL = 'normal';
		public static function all(): array { return [ 'low', 'normal', 'high', 'urgent' ]; }
	}
	final class WorkItemStatus {
		public const PLANNED = 'planned';
		public static function transitions_from( string $status ): array { unset( $status ); return []; }
	}
}

namespace CB\Work\Governance {
	final class Events {
		public const WORK_ITEM_STATUS_CHANGED = 'work.item.status.changed';
		public const WORK_ITEM_UPDATED = 'work.item.updated';
	}
}

namespace CB\Work\Integration {
	final class CRMCustomers {
		public static function available(): bool { return true; }
		public static function selected( string $provider, string $type, string $id ): ?array {
			if ( 'crm' === $provider && 'contact' === $type && '42' === $id ) {
				return [ 'id' => 42, 'label' => 'Customer 42', 'meta' => 'Contact' ];
			}
			return null;
		}
		public static function reference( int $object_id ): array|null|\WP_Error {
			return 42 === $object_id ? [ 'provider' => 'crm', 'type' => 'contact', 'id' => '42' ] : null;
		}
		public static function search( string $term, int $limit = 20 ): array { unset( $term, $limit ); return []; }
	}
}

namespace CB\Work\Repository {
	final class Projects {
		public static function all( int $limit = 100 ): array { unset( $limit ); return []; }
		public static function get( int $id ): ?array { unset( $id ); return null; }
	}
	final class WorkTypes {
		public static function all( bool $include_inactive = false ): array { unset( $include_inactive ); return []; }
	}
	final class WorkItems {
		public static ?array $current = null;
		public static array $saved = [];
		public static function get( int $id ): ?array { unset( $id ); return self::$current; }
		public static function save_editor( int $id, array $input ): bool { unset( $id ); self::$saved = $input; return true; }
		public static function transition_status( int $id, string $status, int $actor_id = 0 ): bool { unset( $id, $status, $actor_id ); return true; }
	}
}

namespace CB\Work\PublicApi {
	final class Services {
		public static function all( int $limit = 100 ): array { unset( $limit ); return []; }
	}
}

namespace CB\Work\Admin {
	final class Menu { public const WORK_ITEMS_SLUG = 'core-blueprint-work-items'; }
}

namespace {
	require dirname( __DIR__ ) . '/src/Admin/Pickers.php';
	require dirname( __DIR__ ) . '/src/Admin/WorkItems.php';

	$_GET = [];
	\CB\Work\Admin\Pickers::enqueue();
	assert_true( 1 === $GLOBALS['cb_work_picker_enqueue_count'], 'ObjectPicker assets enqueue on the native Work Item CPT editor.' );

	$post = new WP_Post( 123 );
	ob_start();
	\CB\Work\Admin\WorkItems::render_details( $post );
	ob_end_clean();

	$calls = $GLOBALS['cb_work_picker_calls'];
	assert_true( 2 === count( $calls ), 'New Work Item render initializes both Customer and Assignee pickers without a type error.' );
	assert_true( 'cb_work_item[customer_object_id]' === ( $calls[0]['name'] ?? '' ), 'Customer picker posts into the Work Item form payload.' );
	assert_true( 'cb-work-item-customer' === ( $calls[0]['id'] ?? '' ), 'Customer picker receives a stable DOM id.' );
	assert_true( false === ( $calls[0]['multiple'] ?? true ), 'Customer picker stays single-select.' );
	assert_true( 'cb_work_item[assigned_user_ids]' === ( $calls[1]['name'] ?? '' ), 'Assignee picker posts into the Work Item form payload.' );
	assert_true( 'cb-work-item-assignees' === ( $calls[1]['id'] ?? '' ), 'Assignee picker receives a stable DOM id.' );
	assert_true( true === ( $calls[1]['multiple'] ?? false ), 'Assignee picker stays multi-select.' );

	$_POST = [
		'cb_work_work_item_nonce' => 'valid',
		'cb_work_item' => [
			'customer_object_id' => '42',
			'assigned_user_ids'  => '3,7',
			'project_id'         => '0',
			'priority'           => 'normal',
		],
		'cb_work_item_status' => 'planned',
	];
	\CB\Work\Admin\WorkItems::save( 123, $post, false );
	$saved = \CB\Work\Repository\WorkItems::$saved;
	assert_true( 'crm' === ( $saved['customer_provider'] ?? '' ) && 'contact' === ( $saved['customer_type'] ?? '' ) && '42' === ( $saved['customer_id'] ?? '' ), 'Editor save resolves the submitted CRM object through the documented adapter.' );
	assert_true( '3,7' === ( $saved['assigned_user_ids'] ?? '' ), 'Editor save forwards ObjectPicker assignments to repository normalization.' );
	assert_true( ! array_key_exists( 'customer_object_id', $saved ), 'Transient picker object id does not leak into canonical Work Item persistence.' );

	echo "Work Item editor smoke passed.\n";
}
