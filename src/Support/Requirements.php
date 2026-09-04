<?php
declare(strict_types=1);

namespace CB\Work\Support;

defined( 'ABSPATH' ) || exit;

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

	/** @return string[] Stable machine-readable issue IDs. */
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
			return $issues;
		}

		$required_contracts = [
			'\\CB\\Core\\ExtensionRegistry',
			'\\CB\\Core\\Admin\\PageRegistry',
			'\\CB\\Core\\Dashboard\\CardRegistry',
			'\\CB\\Core\\Governance\\Audit',
			'\\CB\\Core\\Governance\\EventRegistry',
		];
		foreach ( $required_contracts as $class ) {
			if ( ! class_exists( $class ) ) {
				$issues[] = 'base-contract-unavailable';
				break;
			}
		}

		if ( ! interface_exists( '\\CB\\Core\\Admin\\Page' ) ) {
			$issues[] = 'base-contract-unavailable';
		}

		return array_values( array_unique( $issues ) );
	}

	public static function runtime_ready(): bool {
		return [] === self::issues();
	}

	public static function operator_message(): string {
		return match ( self::primary_issue() ) {
			'php-version' => sprintf(
				/* translators: %s: current PHP version. */
				__( 'PHP 8.4 or newer is required. This server runs PHP %s.', 'core-blueprint-work' ),
				PHP_VERSION
			),
			'base-missing' => __( 'An active Core Blueprint Base installation is required.', 'core-blueprint-work' ),
			'base-api-incompatible' => sprintf(
				/* translators: 1: required API, 2: available API. */
				__( 'Core API %1$s or a newer compatible minor version is required. This site provides %2$s.', 'core-blueprint-work' ),
				CB_WORK_REQUIRED_API,
				defined( 'CB_CORE_API_VERSION' ) ? (string) CB_CORE_API_VERSION : __( 'none', 'core-blueprint-work' )
			),
			'base-contract-unavailable' => __( 'Required public Core Blueprint Base contracts are unavailable.', 'core-blueprint-work' ),
			default => __( 'Ready', 'core-blueprint-work' ),
		};
	}

	public static function health_detail(): string {
		return self::operator_message();
	}

	private static function primary_issue(): string {
		$issues = self::issues();
		return (string) ( $issues[0] ?? '' );
	}
}
