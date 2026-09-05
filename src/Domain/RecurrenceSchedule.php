<?php
declare(strict_types=1);

namespace CB\Work\Domain;

defined( 'ABSPATH' ) || exit;

final class RecurrenceSchedule {
	public const DAILY   = 'daily';
	public const WEEKLY  = 'weekly';
	public const MONTHLY = 'monthly';
	public const YEARLY  = 'yearly';

	/** @return string[] */
	public static function frequencies(): array {
		return [ self::DAILY, self::WEEKLY, self::MONTHLY, self::YEARLY ];
	}

	public static function is_valid_frequency( string $frequency ): bool {
		return in_array( $frequency, self::frequencies(), true );
	}

	/**
	 * @return array{frequency:string,interval_count:int,start_on:string,end_on:?string}|null
	 */
	public static function normalize( string $frequency, int $interval_count, string $start_on, ?string $end_on = null ): ?array {
		$frequency = strtolower( trim( $frequency ) );
		$start     = self::date( $start_on );
		$end       = null === $end_on || '' === trim( $end_on ) ? null : self::date( $end_on );

		if (
			! self::is_valid_frequency( $frequency )
			|| $interval_count < 1
			|| $interval_count > 999
			|| null === $start
			|| ( null !== $end_on && '' !== trim( $end_on ) && null === $end )
			|| ( null !== $end && $end < $start )
		) {
			return null;
		}

		return [
			'frequency'      => $frequency,
			'interval_count' => $interval_count,
			'start_on'       => $start,
			'end_on'         => $end,
		];
	}

	public static function next_after(
		string $start_on,
		string $current_on,
		string $frequency,
		int $interval_count,
		?string $end_on = null
	): ?string {
		$schedule = self::normalize( $frequency, $interval_count, $start_on, $end_on );
		$current  = self::date_object( $current_on );
		$anchor   = self::date_object( $start_on );
		if ( null === $schedule || null === $current || null === $anchor || $current < $anchor ) {
			return null;
		}

		$next = match ( $schedule['frequency'] ) {
			self::DAILY   => $current->modify( '+' . $schedule['interval_count'] . ' days' ),
			self::WEEKLY  => $current->modify( '+' . ( 7 * $schedule['interval_count'] ) . ' days' ),
			self::MONTHLY => self::next_monthly( $anchor, $current, $schedule['interval_count'] ),
			self::YEARLY  => self::next_yearly( $anchor, $current, $schedule['interval_count'] ),
		};

		$next_on = $next->format( 'Y-m-d' );
		if ( null !== $schedule['end_on'] && $next_on > $schedule['end_on'] ) {
			return null;
		}
		return $next_on;
	}

	public static function add_days( string $date, int $days ): ?string {
		$value = self::date_object( $date );
		if ( null === $value || $days < 0 || $days > 3650 ) {
			return null;
		}
		return 0 === $days ? $date : $value->modify( '+' . $days . ' days' )->format( 'Y-m-d' );
	}

	private static function next_monthly( \DateTimeImmutable $anchor, \DateTimeImmutable $current, int $interval_count ): \DateTimeImmutable {
		$total_months = ( (int) $current->format( 'Y' ) * 12 ) + ( (int) $current->format( 'n' ) - 1 ) + $interval_count;
		$year         = intdiv( $total_months, 12 );
		$month        = ( $total_months % 12 ) + 1;
		return self::anchored_date( $year, $month, (int) $anchor->format( 'j' ) );
	}

	private static function next_yearly( \DateTimeImmutable $anchor, \DateTimeImmutable $current, int $interval_count ): \DateTimeImmutable {
		$year  = (int) $current->format( 'Y' ) + $interval_count;
		$month = (int) $anchor->format( 'n' );
		$day   = (int) $anchor->format( 'j' );
		return self::anchored_date( $year, $month, $day );
	}

	private static function anchored_date( int $year, int $month, int $anchor_day ): \DateTimeImmutable {
		$first = new \DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ) );
		$day   = min( $anchor_day, (int) $first->format( 't' ) );
		return new \DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ) );
	}

	private static function date( string $value ): ?string {
		$date = self::date_object( $value );
		return null === $date ? null : $date->format( 'Y-m-d' );
	}

	private static function date_object( string $value ): ?\DateTimeImmutable {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $date : null;
	}
}
