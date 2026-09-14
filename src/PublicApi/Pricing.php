<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Pricing\Resolver;

defined( 'ABSPATH' ) || exit;

/** Supported effective-pricing contract for sibling integrations. */
final class Pricing {
	/**
	 * @param array<string,mixed> $context
	 * @param array<string,mixed> $explicit_override
	 * @return array<string,mixed>|null
	 */
	public static function resolve( int $service_id, array $context = [], array $explicit_override = [] ): ?array {
		return self::runtime_ready() ? Resolver::resolve( $service_id, $context, $explicit_override ) : null;
	}

	private static function runtime_ready(): bool {
		return function_exists( 'cb_work_runtime_ready' ) && \cb_work_runtime_ready();
	}
}
