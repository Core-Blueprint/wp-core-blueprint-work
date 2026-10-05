<?php
declare(strict_types=1);

namespace CB\Work\Billing;

use CB\Work\Content\ServicePricing;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\BillingUnit;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Pricing\Resolver;
use CB\Work\PublicApi\Services;
use CB\Work\PublicApi\TaxRates;
use CB\Work\Repository\TimeEntries;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

/** Builds immutable operational/commercial handoff input for billing readiness. */
final class SnapshotBuilder {
	/**
	 * @return array{unit_type:string,unit_id:int,work_item_id:int,source_revision:int,fingerprint:string,payload:array<string,mixed>}|\WP_Error
	 */
	public static function build( string $unit_type, int $unit_id ): array|\WP_Error {
		$unit_type = sanitize_key( $unit_type );
		if ( ! BillingUnit::is_type( $unit_type ) || $unit_id <= 0 ) {
			return new \WP_Error( 'work_billing_unit_invalid' );
		}

		$entry = null;
		if ( BillingUnit::TIME_ENTRY === $unit_type ) {
			$entry = TimeEntries::get( $unit_id );
			if ( null === $entry || null === $entry['ended_at'] || (int) $entry['duration_seconds'] <= 0 ) {
				return new \WP_Error( 'work_billing_time_entry_unavailable' );
			}
			$work_item_id = (int) $entry['work_item_id'];
		} else {
			$work_item_id = $unit_id;
		}

		$item = WorkItems::get( $work_item_id );
		if ( null === $item ) {
			return new \WP_Error( 'work_billing_work_item_unavailable' );
		}

		$context = WorkContext::sanitize( $item['work_context'] ?? '' );
		if ( WorkContext::CUSTOMER !== $context ) {
			return new \WP_Error( WorkContext::INTERNAL === $context ? 'work_billing_internal' : 'work_billing_context_required' );
		}

		$disposition = sanitize_key( (string) ( $item['billing_disposition'] ?? '' ) );
		if ( BillingDisposition::INCLUDED === $disposition || BillingDisposition::NON_BILLABLE === $disposition || '' === $disposition ) {
			return new \WP_Error( 'work_billing_not_billable' );
		}
		if ( BillingUnit::TIME_ENTRY === $unit_type && BillingDisposition::HOURLY !== $disposition ) {
			return new \WP_Error( 'work_billing_unit_mismatch' );
		}
		if ( BillingUnit::WORK_ITEM === $unit_type && BillingDisposition::FIXED !== $disposition ) {
			return new \WP_Error( 'work_billing_unit_mismatch' );
		}

		$customer = [
			'provider' => sanitize_key( (string) ( $item['customer_provider'] ?? '' ) ),
			'type'     => sanitize_key( (string) ( $item['customer_type'] ?? '' ) ),
			'id'       => substr( sanitize_text_field( (string) ( $item['customer_id'] ?? '' ) ), 0, 191 ),
		];
		if ( '' === $customer['provider'] || '' === $customer['type'] || '' === $customer['id'] ) {
			return new \WP_Error( 'work_billing_customer_required' );
		}

		$service_id = (int) ( $item['service_id'] ?? 0 );
		$service    = $service_id > 0 ? Services::get( $service_id ) : null;
		if ( null === $service ) {
			return new \WP_Error( 'work_billing_service_required' );
		}

		$source_revision = 0;
		$source_context  = [];
		if ( BillingUnit::TIME_ENTRY === $unit_type && is_array( $entry ) ) {
			$source_revision = max( 1, (int) $entry['revision'] );
			$effective_at    = substr( (string) $entry['ended_at'], 0, 10 );
			$quantity        = [ 'type' => 'seconds', 'value' => max( 0, (int) $entry['duration_seconds'] ) ];
			$source_context  = [
				'started_at'       => (string) $entry['started_at'],
				'ended_at'         => (string) $entry['ended_at'],
				'duration_seconds' => max( 0, (int) $entry['duration_seconds'] ),
				'revision'         => $source_revision,
			];
		} else {
			if ( WorkItemStatus::COMPLETED !== (string) ( $item['status'] ?? '' ) ) {
				return new \WP_Error( 'work_billing_work_not_completed' );
			}
			$completed_at = (string) ( $item['completed_at'] ?? '' );
			if ( '' === $completed_at ) {
				return new \WP_Error( 'work_billing_completion_missing' );
			}
			$effective_at   = substr( $completed_at, 0, 10 );
			$quantity       = [ 'type' => 'unit', 'value' => 1 ];
			$source_context = [ 'completed_at' => $completed_at ];
		}

		$pricing = Resolver::resolve( $service_id, [
			'customer_provider'     => $customer['provider'],
			'customer_type'         => $customer['type'],
			'customer_id'           => $customer['id'],
			'customer_reference_id' => $customer['id'],
			'effective_at'          => $effective_at,
		] );
		if ( null === $pricing || null === $pricing['amount_minor'] ) {
			return new \WP_Error( 'work_billing_pricing_required' );
		}
		if ( BillingDisposition::HOURLY === $disposition && ServicePricing::MODEL_HOURLY !== $pricing['pricing_model'] ) {
			return new \WP_Error( 'work_billing_pricing_mismatch' );
		}
		if (
			BillingDisposition::FIXED === $disposition
			&& ! in_array( $pricing['pricing_model'], [ ServicePricing::MODEL_FIXED, ServicePricing::MODEL_RECURRING ], true )
		) {
			return new \WP_Error( 'work_billing_pricing_mismatch' );
		}

		$tax_context = null;
		$tax_rate_id = (int) $pricing['tax_rate_id'];
		if ( ServicePricing::TAX_EXEMPT !== $pricing['tax_mode'] ) {
			if ( $tax_rate_id <= 0 ) {
				return new \WP_Error( 'work_billing_tax_context_required' );
			}
			$tax = TaxRates::get( $tax_rate_id );
			if ( null === $tax || ! TaxRates::is_available( $tax_rate_id, $effective_at ) ) {
				return new \WP_Error( 'work_billing_tax_context_invalid' );
			}
			$tax_context = [
				'id'           => $tax_rate_id,
				'code'         => (string) ( $tax['code'] ?? '' ),
				'label'        => (string) ( $tax['label'] ?? '' ),
				'country_code' => (string) ( $tax['country_code'] ?? '' ),
				'rate_bp'      => max( 0, (int) ( $tax['rate_bp'] ?? 0 ) ),
				'valid_from'   => null === ( $tax['valid_from'] ?? null ) ? null : (string) $tax['valid_from'],
				'valid_until'  => null === ( $tax['valid_until'] ?? null ) ? null : (string) $tax['valid_until'],
			];
		}

		$payload = [
			'schema'   => 1,
			'unit'     => [
				'type'            => $unit_type,
				'id'              => $unit_id,
				'work_item_id'    => $work_item_id,
				'source_revision' => $source_revision,
				'quantity'        => $quantity,
				'effective_at'    => $effective_at,
				'source'          => $source_context,
			],
			'work'     => [
				'title'               => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'work_context'        => $context,
				'project_id'          => (int) ( $item['project_id'] ?? 0 ),
				'service_id'          => $service_id,
				'work_type_id'        => (int) ( $item['work_type_id'] ?? 0 ),
				'billing_disposition' => $disposition,
			],
			'customer' => $customer,
			'service'  => [
				'id'    => $service_id,
				'title' => sanitize_text_field( (string) ( $service['title'] ?? '' ) ),
			],
			'pricing_context' => [
				'rate_amount_minor' => max( 0, (int) $pricing['amount_minor'] ),
				'currency'          => (string) $pricing['currency'],
				'pricing_model'     => (string) $pricing['pricing_model'],
				'recurring_period'  => (string) $pricing['recurring_period'],
				'tax_mode'          => (string) $pricing['tax_mode'],
				'tax_rate_id'       => $tax_rate_id,
				'effective_at'      => (string) $pricing['effective_at'],
				'source'            => (string) $pricing['source'],
				'provider'          => (string) $pricing['provider'],
				'reference_type'    => (string) $pricing['reference_type'],
				'reference_id'      => (string) $pricing['reference_id'],
			],
			'tax_context' => $tax_context,
		];

		$encoded = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			return new \WP_Error( 'work_billing_snapshot_encode_failed' );
		}

		return [
			'unit_type'       => $unit_type,
			'unit_id'         => $unit_id,
			'work_item_id'    => $work_item_id,
			'source_revision' => $source_revision,
			'fingerprint'     => hash( 'sha256', $encoded ),
			'payload'         => $payload,
		];
	}
}
