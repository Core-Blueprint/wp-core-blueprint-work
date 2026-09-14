<?php
declare(strict_types=1);

namespace CB\Work\Integration;

defined( 'ABSPATH' ) || exit;

/** Fail-soft adapter over CRM's documented builder-neutral customer query contracts. */
final class CRMCustomers {
	private const CONTACT_QUERY      = '\\CB\\CRM\\Frontend\\Queries\\Contacts';
	private const ORGANIZATION_QUERY = '\\CB\\CRM\\Frontend\\Queries\\Organizations';
	private const PROVIDER           = 'crm';
	private const TYPES              = [ 'contact', 'organization' ];

	/** @var array<string,array{id:int,label:string,meta:string}|null> */
	private static array $cache = [];

	public static function available(): bool {
		return self::work_ready() && class_exists( self::CONTACT_QUERY ) && class_exists( self::ORGANIZATION_QUERY );
	}

	/** @return array<int,array{id:string,label:string,meta:string}>|\WP_Error */
	public static function search( string $term, int $limit = 20 ): array|\WP_Error {
		if ( ! self::available() ) {
			return new \WP_Error( 'work_crm_unavailable', __( 'CRM customer selection is unavailable.', 'core-blueprint-work' ) );
		}
		$term  = sanitize_text_field( trim( $term ) );
		$limit = max( 1, min( 50, $limit ) );
		$items = [];
		$contacts = self::CONTACT_QUERY::staff( [ 'search' => $term, 'per_page' => $limit ] );
		if ( is_wp_error( $contacts ) ) { return $contacts; }
		foreach ( (array) ( $contacts['items'] ?? [] ) as $contact ) {
			if ( ! is_array( $contact ) ) { continue; }
			$id = absint( $contact['id'] ?? 0 );
			if ( $id > 0 ) { $items[] = [ 'id' => self::transport_identifier( 'contact', $id ), 'label' => sanitize_text_field( (string) ( $contact['display_name'] ?? '' ) ), 'meta' => __( 'Contact', 'core-blueprint-work' ) ]; }
		}
		$organizations = self::ORGANIZATION_QUERY::staff( [ 'search' => $term, 'per_page' => $limit ] );
		if ( is_wp_error( $organizations ) ) { return $organizations; }
		foreach ( (array) ( $organizations['items'] ?? [] ) as $organization ) {
			if ( ! is_array( $organization ) ) { continue; }
			$id = absint( $organization['id'] ?? 0 );
			if ( $id > 0 ) { $items[] = [ 'id' => self::transport_identifier( 'organization', $id ), 'label' => sanitize_text_field( (string) ( $organization['name'] ?? '' ) ), 'meta' => __( 'Organization', 'core-blueprint-work' ) ]; }
		}
		usort( $items, static fn( array $a, array $b ): int => strcasecmp( $a['label'], $b['label'] ) );
		return array_slice( $items, 0, $limit );
	}

	/** @return array{provider:string,type:string,id:string}|null|\WP_Error */
	public static function reference( string $transport_identifier ): array|null|\WP_Error {
		if ( ! self::work_ready() ) { return new \WP_Error( 'work_unavailable' ); }
		$transport_identifier = trim( $transport_identifier );
		if ( '' === $transport_identifier ) { return null; }
		$decoded = self::decode_transport_identifier( $transport_identifier );
		if ( null === $decoded ) { return new \WP_Error( 'work_invalid_customer_identifier', __( 'The selected CRM customer identifier is invalid.', 'core-blueprint-work' ) ); }
		$item = self::resolve( $decoded['type'], $decoded['id'] );
		if ( is_wp_error( $item ) ) { return $item; }
		if ( null === $item ) { return new \WP_Error( 'work_customer_not_found', __( 'The selected CRM customer could not be resolved.', 'core-blueprint-work' ) ); }
		return [ 'provider' => self::PROVIDER, 'type' => $decoded['type'], 'id' => (string) $decoded['id'] ];
	}

	/** @return array{id:string,label:string,meta:string}|null */
	public static function selected( string $provider, string $type, string $id ): ?array {
		if ( ! self::work_ready() ) { return null; }
		$type = sanitize_key( $type );
		if ( self::PROVIDER !== sanitize_key( $provider ) || ! in_array( $type, self::TYPES, true ) ) { return null; }
		$object_id = absint( $id );
		if ( $object_id <= 0 ) { return null; }
		$item = self::resolve( $type, $object_id );
		if ( ! is_array( $item ) ) { return null; }
		return [ 'id' => self::transport_identifier( $type, $object_id ), 'label' => $item['label'], 'meta' => $item['meta'] ];
	}

	public static function label( string $provider, string $type, string $id ): string {
		$item = self::selected( $provider, $type, $id );
		if ( null !== $item ) { return $item['label']; }
		return self::work_ready() && '' !== $provider ? __( 'Linked customer', 'core-blueprint-work' ) : '';
	}

	private static function work_ready(): bool {
		return function_exists( 'cb_work_runtime_ready' ) && \cb_work_runtime_ready();
	}

	private static function transport_identifier( string $type, int $object_id ): string { return self::PROVIDER . ':' . $type . ':' . $object_id; }

	/** @return array{type:string,id:int}|null */
	private static function decode_transport_identifier( string $identifier ): ?array {
		if ( 1 !== preg_match( '/^crm:(contact|organization):([1-9][0-9]*)$/', $identifier, $matches ) ) { return null; }
		$object_id = absint( $matches[2] );
		return $object_id > 0 && (string) $object_id === $matches[2] ? [ 'type' => $matches[1], 'id' => $object_id ] : null;
	}

	/** @return array{id:int,label:string,meta:string}|null|\WP_Error */
	private static function resolve( string $type, int $object_id ): array|null|\WP_Error {
		if ( ! self::available() ) { return null; }
		$key = $type . ':' . $object_id;
		if ( array_key_exists( $key, self::$cache ) ) { return self::$cache[ $key ]; }
		$class = 'contact' === $type ? self::CONTACT_QUERY : self::ORGANIZATION_QUERY;
		$result = $class::staff( [ 'include_ids' => [ $object_id ], 'per_page' => 1 ] );
		if ( is_wp_error( $result ) ) { return $result; }
		foreach ( (array) ( $result['items'] ?? [] ) as $row ) {
			if ( ! is_array( $row ) || absint( $row['id'] ?? 0 ) !== $object_id ) { continue; }
			$item = [ 'id' => $object_id, 'label' => sanitize_text_field( (string) ( 'contact' === $type ? ( $row['display_name'] ?? '' ) : ( $row['name'] ?? '' ) ) ), 'meta' => 'contact' === $type ? __( 'Contact', 'core-blueprint-work' ) : __( 'Organization', 'core-blueprint-work' ) ];
			self::$cache[ $key ] = $item;
			return $item;
		}
		self::$cache[ $key ] = null;
		return null;
	}
}
