<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\DataExchange\CsvEntityInterface;
use CoreBlueprint\Core\DataExchange\Engine;
use CoreBlueprint\Core\DataExchange\Foundation;
use CoreBlueprint\Core\DataExchange\Mapper;
use CoreBlueprint\Core\DataExchange\Mapper\Renderer;
use CoreBlueprint\Core\DataExchange\MappingEntityInterface;
use CoreBlueprint\Core\UI\Notice;
use CB\Work\Capabilities;
use CB\Work\Integration\DataExchange as DataExchangeIntegration;
use CB\Work\Integration\DataExchange\TaxRateEntity;
use CB\Work\Integration\Suite;
use JsonException;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class TaxRateDataExchange {
	private const NONCE_ACTION  = 'cb_work_tax_rate_data_exchange';
	private const SCRIPT_HANDLE = 'cb-work-tax-rate-data-exchange';

	public static function init(): void {
		add_action( 'admin_post_cb_work_export_tax_rates', [ self::class, 'export' ] );
		add_action( 'wp_ajax_cb_work_tax_rate_mapper_inspect', [ self::class, 'inspect' ] );
		add_action( 'wp_ajax_cb_work_tax_rate_mapper_preview', [ self::class, 'preview' ] );
		add_action( 'wp_ajax_cb_work_tax_rate_mapper_apply', [ self::class, 'apply' ] );
	}

	public static function available(): bool {
		return class_exists( Engine::class )
			&& class_exists( Foundation::class )
			&& class_exists( Mapper::class )
			&& class_exists( Renderer::class )
			&& interface_exists( CsvEntityInterface::class )
			&& interface_exists( MappingEntityInterface::class );
	}

	public static function import_url(): string {
		return Page::settings_url( [ 'cb-work-view' => 'vat-import' ] );
	}

	public static function export_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=cb_work_export_tax_rates' ),
			self::NONCE_ACTION
		);
	}

	public static function render_import(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to import Work VAT rates.', 'core-blueprint-work' ) );
		}

		if ( ! self::available() ) {
			self::render_error( __( 'Data Exchange is unavailable in the installed Core Blueprint Base build.', 'core-blueprint-work' ) );
			return;
		}

		$entity         = new TaxRateEntity();
		$target_fields  = Mapper::entity_schema( $entity, $entity->schema_version() );
		if ( is_wp_error( $target_fields ) ) {
			self::render_error( $target_fields->get_error_message() );
			return;
		}

		$exit_url = Page::settings_url() . '#configured-vat-rates';
		$workspace = Renderer::render( [
			'direction'     => Foundation::DIRECTION_IMPORT,
			'intake'        => true,
			'target_fields' => $target_fields,
			'title'         => __( 'Import VAT rates', 'core-blueprint-work' ),
			'source_label'  => __( 'CSV fields', 'core-blueprint-work' ),
			'target_label'  => __( 'Work VAT fields', 'core-blueprint-work' ),
			'primary_label' => __( 'Validate import', 'core-blueprint-work' ),
			'accept'        => '.csv,text/csv',
			'launch_mode'   => 'direct',
			'exit_url'      => $exit_url,
		] );
		if ( is_wp_error( $workspace ) ) {
			self::render_error( $workspace->get_error_message() );
			return;
		}

		self::enqueue_script();

		echo '<div data-cb-work-tax-rate-import>';
		echo $workspace; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
		echo '</div>';
	}

	public static function export(): never {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to export Work VAT rates.', 'core-blueprint-work' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::NONCE_ACTION );

		if ( ! self::available() ) {
			wp_die( esc_html__( 'Data Exchange is unavailable in the installed Core Blueprint Base build.', 'core-blueprint-work' ), '', [ 'response' => 409 ] );
		}

		$csv = Engine::export_csv( Suite::EXTENSION_ID, DataExchangeIntegration::TAX_RATE_ENTITY );
		if ( is_wp_error( $csv ) ) {
			wp_die( esc_html( $csv->get_error_message() ), '', [ 'response' => 400 ] );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="core-blueprint-work-vat-rates-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentional CSV download body from bounded Base transport.
		exit;
	}

	public static function inspect(): never {
		self::guard_ajax();

		$input = self::uploaded_csv();
		if ( is_wp_error( $input ) ) {
			self::send_error( $input );
		}

		$inspection = Mapper::inspect_csv( $input );
		if ( is_wp_error( $inspection ) ) {
			self::send_error( $inspection );
		}

		wp_send_json_success( [
			'fields'       => $inspection['fields'],
			'record_count' => $inspection['record_count'],
			'delimiter'    => $inspection['delimiter'],
		] );
	}

	public static function preview(): never {
		self::guard_ajax();

		$prepared = self::mapped_envelope();
		if ( is_wp_error( $prepared ) ) {
			self::send_error( $prepared );
		}

		$preview = Engine::preview_json(
			$prepared['envelope'],
			Foundation::MODE_CREATE_UPDATE
		);
		if ( is_wp_error( $preview ) ) {
			self::send_error( $preview );
		}

		$valid = true === ( $preview['valid'] ?? false );
		wp_send_json_success( [
			'validation' => [
				'valid'       => $valid,
				'message'     => $valid
					? __( 'Preview ready. Review the changes, then apply the import.', 'core-blueprint-work' )
					: __( 'The import preview contains errors and cannot be applied.', 'core-blueprint-work' ),
				'counts'      => [
					'records' => (int) ( $preview['record_count'] ?? 0 ),
					'create'  => (int) ( $preview['counts'][ Foundation::OP_CREATE ] ?? 0 ),
					'update'  => (int) ( $preview['counts'][ Foundation::OP_UPDATE ] ?? 0 ),
					'skip'    => (int) ( $preview['counts'][ Foundation::OP_SKIP ] ?? 0 ),
				],
				'errors'      => isset( $preview['errors'] ) && is_array( $preview['errors'] ) ? $preview['errors'] : [],
				'fingerprint' => $valid && is_string( $preview['fingerprint'] ?? null ) ? $preview['fingerprint'] : '',
			],
		] );
	}

	public static function apply(): never {
		self::guard_ajax();

		$fingerprint = isset( $_POST['fingerprint'] )
			? strtolower( trim( wp_unslash( (string) $_POST['fingerprint'] ) ) )
			: '';
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) ) {
			self::send_error( new WP_Error( 'work_data_exchange_invalid_fingerprint', __( 'Preview the import again before applying it.', 'core-blueprint-work' ) ) );
		}

		$prepared = self::mapped_envelope();
		if ( is_wp_error( $prepared ) ) {
			self::send_error( $prepared );
		}

		$result = Engine::apply_json(
			$prepared['envelope'],
			Foundation::MODE_CREATE_UPDATE,
			$fingerprint
		);
		if ( is_wp_error( $result ) ) {
			self::send_error( $result, 409 );
		}

		$status = is_string( $result['status'] ?? null ) ? $result['status'] : 'failed';
		if ( 'complete' !== $status ) {
			$error = isset( $result['error'] ) && is_array( $result['error'] ) ? $result['error'] : [];
			self::send_validation_error(
				is_string( $error['code'] ?? null ) ? $error['code'] : 'work_data_exchange_apply_failed',
				is_string( $error['message'] ?? null ) ? $error['message'] : __( 'The VAT import stopped before all changes could be applied.', 'core-blueprint-work' ),
				[
					'records' => (int) ( $result['record_count'] ?? 0 ),
					'applied' => (int) ( $result['applied_count'] ?? 0 ),
					'skipped' => (int) ( $result['skipped_count'] ?? 0 ),
				],
				409
			);
		}

		wp_send_json_success( [
			'validation' => [
				'valid'   => true,
				'message' => __( 'VAT import completed.', 'core-blueprint-work' ),
				'counts'  => [
					'records' => (int) ( $result['record_count'] ?? 0 ),
					'applied' => (int) ( $result['applied_count'] ?? 0 ),
					'skipped' => (int) ( $result['skipped_count'] ?? 0 ),
				],
				'errors'  => [],
			],
		] );
	}

	private static function guard_ajax(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			self::send_validation_error(
				'work_data_exchange_forbidden',
				__( 'You do not have permission to manage Work VAT imports.', 'core-blueprint-work' ),
				[],
				403
			);
		}
		if ( false === check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			self::send_validation_error(
				'work_data_exchange_invalid_nonce',
				__( 'The VAT import request expired. Reload the page and try again.', 'core-blueprint-work' ),
				[],
				403
			);
		}
		if ( ! self::available() ) {
			self::send_validation_error(
				'work_data_exchange_unavailable',
				__( 'Data Exchange is unavailable in the installed Core Blueprint Base build.', 'core-blueprint-work' ),
				[],
				409
			);
		}
	}

	private static function enqueue_script(): void {
		$file = CB_WORK_DIR . 'assets/data-exchange-tax-rates.js';
		if ( ! is_file( $file ) ) {
			return;
		}

		$modified = filemtime( $file );
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CB_WORK_URL . 'assets/data-exchange-tax-rates.js',
			[],
			false === $modified ? CB_WORK_VERSION : (string) $modified,
			true
		);
		wp_localize_script( self::SCRIPT_HANDLE, 'cbWorkTaxRateDataExchange', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'actions' => [
				'inspect' => 'cb_work_tax_rate_mapper_inspect',
				'preview' => 'cb_work_tax_rate_mapper_preview',
				'apply'   => 'cb_work_tax_rate_mapper_apply',
			],
			'labels'  => [
				'inspecting'    => __( 'Inspecting CSV…', 'core-blueprint-work' ),
				'fileReady'     => __( 'CSV ready for mapping.', 'core-blueprint-work' ),
				'validating'    => __( 'Building server preview…', 'core-blueprint-work' ),
				'applying'      => __( 'Applying VAT import…', 'core-blueprint-work' ),
				'validate'      => __( 'Validate import', 'core-blueprint-work' ),
				'apply'         => __( 'Apply import', 'core-blueprint-work' ),
				'requestFailed' => __( 'The VAT import request could not be completed.', 'core-blueprint-work' ),
			],
		] );
	}

	/** @return array{envelope:string}|WP_Error */
	private static function mapped_envelope(): array|WP_Error {
		$input = self::uploaded_csv();
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$inspection = Mapper::inspect_csv( $input );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}

		$mapping = self::mapping_from_request();
		if ( is_wp_error( $mapping ) ) {
			return $mapping;
		}

		$entity         = new TaxRateEntity();
		$schema_version = $entity->schema_version();
		$target_fields  = Mapper::entity_schema( $entity, $schema_version );
		if ( is_wp_error( $target_fields ) ) {
			return $target_fields;
		}

		$mapped = Mapper::map_records(
			$inspection['records'],
			$inspection['fields'],
			$target_fields,
			$mapping
		);
		if ( is_wp_error( $mapped ) ) {
			return $mapped;
		}

		$envelope = Mapper::exchange_json(
			Suite::EXTENSION_ID,
			DataExchangeIntegration::TAX_RATE_ENTITY,
			$schema_version,
			$mapped
		);
		return is_wp_error( $envelope ) ? $envelope : [ 'envelope' => $envelope ];
	}

	/** @return list<array<string,mixed>>|WP_Error */
	private static function mapping_from_request(): array|WP_Error {
		$raw = isset( $_POST['mapping'] ) ? wp_unslash( (string) $_POST['mapping'] ) : '';
		if ( '' === $raw || strlen( $raw ) > Foundation::MAX_INPUT_BYTES ) {
			return new WP_Error( 'work_data_exchange_invalid_mapping', __( 'The VAT field mapping is missing or too large.', 'core-blueprint-work' ) );
		}

		try {
			$mapping = json_decode( $raw, true, 32, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			unset( $exception );
			return new WP_Error( 'work_data_exchange_invalid_mapping', __( 'The VAT field mapping is invalid.', 'core-blueprint-work' ) );
		}
		return is_array( $mapping ) && array_is_list( $mapping )
			? $mapping
			: new WP_Error( 'work_data_exchange_invalid_mapping', __( 'The VAT field mapping is invalid.', 'core-blueprint-work' ) );
	}

	private static function uploaded_csv(): string|WP_Error {
		$file = $_FILES['file'] ?? null;
		if ( ! is_array( $file ) ) {
			return new WP_Error( 'work_data_exchange_missing_file', __( 'Choose a CSV file to continue.', 'core-blueprint-work' ) );
		}

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		$size  = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$name  = isset( $file['name'] ) && is_string( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$tmp   = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'work_data_exchange_invalid_upload', __( 'The CSV upload could not be verified.', 'core-blueprint-work' ) );
		}
		if ( 'csv' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'work_data_exchange_invalid_file_type', __( 'VAT imports accept CSV files only.', 'core-blueprint-work' ) );
		}
		if ( $size <= 0 || $size > Foundation::MAX_INPUT_BYTES ) {
			return new WP_Error( 'work_data_exchange_invalid_file_size', __( 'The CSV file is empty or exceeds the Data Exchange size limit.', 'core-blueprint-work' ) );
		}

		$contents = file_get_contents( $tmp );
		if ( false === $contents || strlen( $contents ) !== $size || strlen( $contents ) > Foundation::MAX_INPUT_BYTES ) {
			return new WP_Error( 'work_data_exchange_unreadable_file', __( 'The CSV file could not be read safely.', 'core-blueprint-work' ) );
		}
		return $contents;
	}

	private static function send_error( WP_Error $error, int $status = 400 ): never {
		self::send_validation_error(
			(string) $error->get_error_code(),
			(string) $error->get_error_message(),
			[],
			$status
		);
	}

	/** @param array<string,int> $counts */
	private static function send_validation_error( string $code, string $message, array $counts, int $status ): never {
		wp_send_json_error( [
			'validation' => [
				'valid'   => false,
				'message' => sanitize_text_field( $message ),
				'counts'  => $counts,
				'errors'  => [
					[
						'code'    => sanitize_key( $code ),
						'message' => sanitize_text_field( $message ),
					],
				],
			],
		], $status );
	}

	private static function render_error( string $message ): void {
		echo Notice::render( [
			'variant' => Notice::ERROR,
			'title'   => __( 'VAT Data Exchange', 'core-blueprint-work' ),
			'message' => sanitize_text_field( $message ),
		] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
	}

	private function __construct() {}
}
