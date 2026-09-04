<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

defined( 'ABSPATH' ) || exit;

/**
 * Public registration seam for optional customer-specific pricing providers.
 *
 * Providers may project a partial pricing override for a Work service. They do
 * not own service defaults, VAT catalog data, or precedence; Work's resolver
 * remains authoritative for those decisions.
 */
final class PricingProviders {
	/** @var array<string,array{id:string,priority:int,resolver:callable}> */
	private static array $providers = [];
	private static bool $registration_fired = false;

	public static function register( string $id, callable $resolver, int $priority = 100 ): bool {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return false;
		}

		self::$providers[ $id ] = [
			'id'       => $id,
			'priority' => $priority,
			'resolver' => $resolver,
		];
		return true;
	}

	/**
	 * Resolve the first provider projection for a pricing context.
	 *
	 * Provider result shape:
	 * - pricing: partial Work pricing override (required)
	 * - reference_type: provider-owned reference type (optional)
	 * - reference_id: provider-owned stable reference ID (optional)
	 *
	 * @param array{service_id:int,customer_type:string,customer_id:int,effective_at:string} $context
	 * @return array{provider:string,pricing:array<string,mixed>,reference_type:string,reference_id:string}|null
	 */
	public static function resolve( array $context ): ?array {
		self::fire_registration_hook();

		$providers = array_values( self::$providers );
		usort( $providers, static function ( array $a, array $b ): int {
			$priority = $a['priority'] <=> $b['priority'];
			return 0 !== $priority ? $priority : strcmp( $a['id'], $b['id'] );
		} );

		foreach ( $providers as $provider ) {
			try {
				$result = ( $provider['resolver'] )( $context );
			} catch ( \Throwable $e ) {
				unset( $e );
				continue;
			}
			if ( ! is_array( $result ) || ! isset( $result['pricing'] ) || ! is_array( $result['pricing'] ) || [] === $result['pricing'] ) {
				continue;
			}

			$reference_id = $result['reference_id'] ?? '';
			return [
				'provider'       => $provider['id'],
				'pricing'        => $result['pricing'],
				'reference_type' => sanitize_key( (string) ( $result['reference_type'] ?? '' ) ),
				'reference_id'   => is_scalar( $reference_id ) ? substr( sanitize_text_field( (string) $reference_id ), 0, 190 ) : '',
			];
		}
		return null;
	}

	private static function fire_registration_hook(): void {
		if ( self::$registration_fired ) {
			return;
		}
		self::$registration_fired = true;

		/** Register optional customer-specific pricing providers for Work. */
		do_action( 'cb_work_register_pricing_providers' );
	}
}
