<?php
declare(strict_types=1);

namespace CB\Work\Domain;

defined( 'ABSPATH' ) || exit;

final class TimeRange {
	public static function local_to_utc( string $date, string $time ): ?string {
		$date = trim( $date );
		$time = trim( $time );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			return null;
		}

		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$value = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, $timezone );
		$errors = \DateTimeImmutable::getLastErrors();
		if (
			false === $value
			|| ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) )
			|| $value->format( 'Y-m-d H:i' ) !== $date . ' ' . $time
		) {
			return null;
		}

		return $value->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/** @return array{date:string,time:string}|null */
	public static function utc_to_local_parts( string $value ): ?array {
		if ( ! self::valid_utc( $value ) ) {
			return null;
		}
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$date = new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
		$date = $date->setTimezone( $timezone );
		return [ 'date' => $date->format( 'Y-m-d' ), 'time' => $date->format( 'H:i' ) ];
	}

	public static function valid_utc( string $value ): bool {
		$value = trim( $value );
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) );
		$errors = \DateTimeImmutable::getLastErrors();
		return false !== $date
			&& ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) )
			&& $date->format( 'Y-m-d H:i:s' ) === $value;
	}

	public static function duration_seconds( string $started_at, string $ended_at ): ?int {
		if ( ! self::valid_utc( $started_at ) || ! self::valid_utc( $ended_at ) ) {
			return null;
		}
		$start = new \DateTimeImmutable( $started_at, new \DateTimeZone( 'UTC' ) );
		$end   = new \DateTimeImmutable( $ended_at, new \DateTimeZone( 'UTC' ) );
		$seconds = $end->getTimestamp() - $start->getTimestamp();
		return $seconds >= 0 && $seconds <= 4294967295 ? $seconds : null;
	}
}
