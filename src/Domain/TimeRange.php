<?php
declare(strict_types=1);

namespace CB\Work\Domain;

defined( 'ABSPATH' ) || exit;

final class TimeRange {
	public static function local_to_utc( string $date, string $time, ?string $original_utc = null ): ?string {
		$date = trim( $date );
		$time = trim( $time );
		$with_seconds = 1 === preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date )
			|| ( ! $with_seconds && ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) ) {
			return null;
		}

		$format = $with_seconds ? 'Y-m-d H:i:s' : 'Y-m-d H:i';
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$value = \DateTimeImmutable::createFromFormat( '!' . $format, $date . ' ' . $time, $timezone );
		$errors = \DateTimeImmutable::getLastErrors();
		if (
			false === $value
			|| ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) )
			|| $value->format( $format ) !== $date . ' ' . $time
		) {
			return null;
		}

		// A correction that leaves its local clock fields unchanged must keep
		// the original UTC instant (including seconds and DST fold offset).
		// This also protects older minute-only editing clients.
		if ( null !== $original_utc && self::valid_utc( $original_utc ) ) {
			$original_local = self::utc_to_local_parts( $original_utc, true );
			if ( null !== $original_local
				&& $original_local['date'] === $date
				&& ( $original_local['time'] === $time
					|| ( ! $with_seconds && substr( $original_local['time'], 0, 5 ) === $time ) ) ) {
				return $original_utc;
			}
		}

		// A local time in the repeated hour at winter-time transition maps to
		// two different UTC instants. Reject *new* ambiguous input rather than
		// silently choosing the wrong hour; unchanged edits above are safe.
		if ( self::ambiguous_local_time( $timezone, $date, $time ) ) {
			return null;
		}
		return $value->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/** @return array{date:string,time:string}|null */
	public static function utc_to_local_parts( string $value, bool $include_seconds = false ): ?array {
		if ( ! self::valid_utc( $value ) ) {
			return null;
		}
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$date = new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
		$date = $date->setTimezone( $timezone );
		return [ 'date' => $date->format( 'Y-m-d' ), 'time' => $date->format( $include_seconds ? 'H:i:s' : 'H:i' ) ];
	}

	/**
	 * True when a local wall clock appears twice during a negative UTC-offset
	 * transition. Offset arithmetic deliberately uses a naive local epoch.
	 */
	private static function ambiguous_local_time( \DateTimeZone $timezone, string $date, string $time ): bool {
		$clock = \DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$date . ' ' . ( 5 === strlen( $time ) ? $time . ':00' : $time ),
			new \DateTimeZone( 'UTC' )
		);
		if ( false === $clock ) {
			return false;
		}
		$local_epoch = $clock->getTimestamp();
		$transitions = $timezone->getTransitions( $local_epoch - 172800, $local_epoch + 172800 );
		if ( ! is_array( $transitions ) ) {
			return false; // Fixed-offset zones have no DST folds.
		}
		for ( $index = 1; $index < count( $transitions ); ++$index ) {
			$previous_offset = (int) $transitions[ $index - 1 ]['offset'];
			$offset = (int) $transitions[ $index ]['offset'];
			$transition = (int) $transitions[ $index ]['ts'];
			if ( $offset < $previous_offset
				&& $local_epoch >= $transition + $offset
				&& $local_epoch < $transition + $previous_offset ) {
				return true;
			}
		}
		return false;
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
