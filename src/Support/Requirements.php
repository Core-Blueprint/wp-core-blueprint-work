<?php
declare(strict_types=1);

namespace CB\Work\Support;

defined( 'ABSPATH' ) || exit;

/** Bootstrap-safe dependency checks only. */
final class Requirements {
	public static function api_compatible( string $available, string $required ): bool {
		if ( 1 !== preg_match( '/^(\d+)\.(\d+)$/', $available, $available_match ) ) {
			return false;
		}
		if ( 1 !== preg_match( '/^(\d+)\.(\d+)$/', $required, $required_match ) ) {
			return false;
		}

		return (int) $available_match[1] === (int) $required_match[1]
			&& (int) $available_match[2] >= (int) $required_match[2];
	}

	/** @return string[] Stable machine-readable bootstrap issue IDs. */
	public static function issues(): array {
		$issues = [];

		if ( version_compare( PHP_VERSION, '8.4', '<' ) ) {
			$issues[] = 'php-version';
		}

		if ( ! defined( 'CB_CORE_API_VERSION' ) ) {
			$issues[] = 'base-missing';
			return $issues;
		}

		if ( ! self::api_compatible( (string) CB_CORE_API_VERSION, CB_WORK_REQUIRED_API ) ) {
			$issues[] = 'base-api-incompatible';
		}

		return array_values( array_unique( $issues ) );
	}

	public static function runtime_ready(): bool {
		return [] === self::issues();
	}

	public static function operator_message(): string {
		switch ( self::primary_issue() ) {
			case 'php-version':
				return sprintf(
					/* translators: %s: current PHP version. */
					__( 'PHP 8.4 or newer is required. This server runs PHP %s.', 'core-blueprint-work' ),
					PHP_VERSION
				);
			case 'base-missing':
				return __( 'An active Core Blueprint Base installation is required.', 'core-blueprint-work' );
			case 'base-api-incompatible':
				return sprintf(
					/* translators: 1: required API, 2: available API. */
					__( 'Core API %1$s or a newer compatible minor version is required. This site provides %2$s.', 'core-blueprint-work' ),
					CB_WORK_REQUIRED_API,
					defined( 'CB_CORE_API_VERSION' ) ? (string) CB_CORE_API_VERSION : __( 'none', 'core-blueprint-work' )
				);
			default:
				return __( 'Ready', 'core-blueprint-work' );
		}
	}

	public static function health_detail(): string {
		return self::operator_message();
	}

	private static function primary_issue(): string {
		$issues = self::issues();
		return (string) ( $issues[0] ?? '' );
	}
}
