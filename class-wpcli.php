<?php declare( strict_types=1 );

namespace What_Git_Branch;

use \WP_CLI;

class WPCLI {

	protected $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * List all repositories.
	 */
	public function list( array $args, array $assoc = array() ) {
		$this->plugin->set_repos();

		$default_fields = array( 'name', 'ref', 'path' );

		$format = WP_CLI\Utils\get_flag_value( $assoc, 'format', 'table' );
		$fields = WP_CLI\Utils\get_flag_value( $assoc, 'fields', $default_fields );
		$rows   = array();

		if ( is_string( $fields ) ) {
			$fields = explode( ',', $fields );
			$fields = array_map( 'trim', $fields );
		}

		$fields = array_intersect( $default_fields, $fields );

		foreach ( $this->plugin->repos as $repo ) {
			$rows[] = array(
				'name' => $repo->name,
				'ref'  => $repo->get_head_ref(),
				'path' => str_replace( ABSPATH , './', $repo->path ),
			);
		}

		$names = array_column( $rows, 'name' );
		array_multisort( $names, SORT_ASC, SORT_NATURAL|SORT_FLAG_CASE, $rows );

		WP_CLI\Utils\format_items( $format, $rows, $fields );
	}

	public function directories( array $args ) {
		$this->directories_scan( $args );
		$this->directories_clear_cache( $args );

		WP_CLI::error( 'Unrecognized subcommand' );
	}

	/**
	 * Scan filesystem for directories.
	 */
	protected function directories_scan( $args ) {
		if ( 'scan' !== $args[0] ) {
			return;
		}

		WP_CLI::debug( 'Checking if scanning in WP CLI is permitted' );

		if ( ! $this->plugin->can_scan() ) {
			WP_CLI::error( 'Scanning from WP CLI prevented.' );
		}

		WP_CLI::debug( 'Scanning from WP CLI permitted' );
		WP_CLI::debug( 'Scanning for directories' );

		$dirs   = $this->plugin->get_dirs();
		$count  = count( $dirs );
		$format = _n( 'Found %d directory.', 'Found %d directories.', $count );

		WP_CLI::success( sprintf( $format, count( $dirs ) ) );
		exit;
	}

	protected function directories_clear_cache( $args ) {
		if ( 'clear-cache' !== $args[0] ) {
			return;
		}

		if ( 'option' === $this->plugin->cache_store() ) {
			$result = delete_option( Plugin::CACHE_KEY );
		} else {
			$result = delete_transient( Plugin::CACHE_KEY );
		}

		if ( ! $result ) {
			WP_CLI::error( 'Unable to clear directories cache' );
			exit;
		}

		WP_CLI::success( 'Cleared directories cache.' );
		exit;
	}

	public function primary( array $args ) {
		if ( is_null( $this->plugin->primary() ) ) {
			WP_CLI::warning( 'No primary repository.' );
			exit;
		}

		$this->plugin->primary()->set_head_ref();

		$this->primary_identify( $args );
		$this->primary_set( $args );
		$this->primary_reset( $args );

		WP_CLI::error( 'Unrecognized subcommand' );
	}

	protected function primary_identify( array $args ) {
		if ( 'identify' !== $args[0] ) {
			return;
		}

		if ( empty( $args[1] ) || 'ref' === $args[1] ) {
			WP_CLI::line( $this->plugin->primary()->get_head_ref() );
			exit;
		}

		if ( 'path' === $args[1] ) {
			WP_CLI::line( $this->plugin->primary()->path );
			exit;
		}

		WP_CLI::error( 'Unrecognized subcommand' );
	}

	protected function primary_set( array $args ) {
		if ( 'set' !== $args[0] ) {
			return;
		}

		$filepath = $this->plugin->primary()->path . Repository::EXTERNAL_FILE;

		WP_CLI::debug( sprintf( 'Saving text to file: %s', $filepath ) );

		$result = file_put_contents( $filepath, sanitize_text_field( $args[1] ) );

		if ( empty( $result ) ) {
			WP_CLI::error( 'Unable to write file' );
		}

		WP_CLI::debug( sprintf( 'Wrote %d bytes to file', $result ) );

		WP_CLI::success( 'Set primary repository external file.' );
		exit;
	}

	protected function primary_reset( array $args ) {
		if ( 'reset' !== $args[0] ) {
			return;
		}

		if ( is_null( $this->plugin->primary()->external_file ) ) {
			WP_CLI::warning( 'Primary repository using git head ref; cannot reset.' );
			exit;
		}

		WP_CLI::debug( sprintf( 'Checking file exists: %s', $this->plugin->primary()->external_file ) );

		if ( ! file_exists( $this->plugin->primary()->external_file ) ) {
			WP_CLI::warning( 'Primary repository using git head ref; cannot reset.' );
			exit;
		}

		WP_CLI::debug( 'Deleting external file' );

		$result = unlink( $this->plugin->primary()->external_file );

		if ( ! $result ) {
			WP_CLI::error( 'Unable to delete external file.' );
		}

		WP_CLI::success( 'Deleted primary repository external file.' );
		exit;
	}

}