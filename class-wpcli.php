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
	public function list() {

	}

	/**
	 * Display branch/path for root repository.
	 */
	public function identify( array $args, array $assoc_args = array() ) {
		if ( empty( $this->plugin->plugin->root_repo ) ) {
			WP_CLI::error( 'No root repository found or set.' );
		}

		WP_CLI::debug( sprintf( 'Found root repository: %s', $this->plugin->plugin->root_repo->path ) );

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'dir', false ) ) {
			WP_CLI::line( $this->plugin->plugin->root_repo->path );
			return;
		}

		WP_CLI::line( $this->plugin->plugin->root_repo->get_head_ref() );
	}

	/**
	 * Set head ref for root repository.
	 */
	public function set( array $args, array $assoc_args = array() ) : void {
		if ( empty( $this->plugin->root_repo ) ) {
			WP_CLI::error( 'No root repository found or set.' );
		}

		WP_CLI::debug( sprintf( 'Found root repository: %s', $this->plugin->root_repo->path ) );

		$ref = ( string ) $args[0];
		$ref = trim( $ref );

		if ( empty( $ref ) ) {
			WP_CLI::error( 'Branch name is required.' );
		}

		$this->plugin->root_repo->set_head_ref();

		WP_CLI::debug( 'Set root repository head reference' );

		$external_filepath = $this->plugin->root_repo->external_file;

		if ( empty( $external_filepath ) ) {
			$external_filepath = $this->plugin->root_repo->path . Repository::EXTERNAL_FILE;
		}

		WP_CLI::debug( sprintf( 'Checking for external file: %s', $external_filepath ) );

		if ( empty( $external_filepath ) || ! file_exists( $external_filepath ) ) {
			WP_CLI::warning( 'Root repository head ref is from git.' );
			WP_CLI::confirm( 'Proceed with manually setting head ref?', $assoc_args );
		}

		WP_CLI::debug( sprintf( 'Writing to file: %s', $external_filepath ) );

		$result = file_put_contents( $external_filepath, $ref );

		if ( false === $result ) {
			WP_CLI::error( 'Unable to write to file.' );
		}

		WP_CLI::debug( sprintf( 'Bytes written: %d', $result ) );

		WP_CLI::success( 'Set head ref for root repository.' );
	}

	/**
	 * Remove external file for root repository.
	 */
	public function reset( array $args ) : void {
		if ( empty( $args ) ) {
			$args = array( 'both' );
		}

		switch ( $args[0] ) {

			case 'root':
				$this->reset_root();
				break;

			case 'cache':
			case 'transient':
				$this->reset_cache();
				break;

			case 'both':
			default:
				$this->reset_cache();
				$this->reset_root();
				break;

		}

	}

	protected function reset_root() : void {
		if ( empty( $this->plugin->root_repo ) ) {
			WP_CLI::error( 'No root repository found or set.', false );
			return;
		}

		WP_CLI::debug( sprintf( 'Found root repository: %s', $this->plugin->root_repo->path ) );

		$this->plugin->root_repo->set_head_ref();

		WP_CLI::debug( 'Set root repository head reference.' );

		$external_filepath = $this->plugin->root_repo->external_file;

		if ( empty( $external_filepath ) ) {
			$external_filepath = $this->plugin->root_repo->path . Repository::EXTERNAL_FILE;
		}

		WP_CLI::debug( sprintf( 'Checking for external file: %s', $external_filepath ) );

		if ( empty( $external_filepath ) || ! file_exists( $external_filepath ) ) {
			WP_CLI::error( 'Root repository head ref is from git; cannot reset.', false );
			return;
		}

		WP_CLI::debug( sprintf( 'Head ref from file: %s', $this->plugin->root_repo->get_head_ref() ) );
		WP_CLI::debug( 'Deleting external file' );

		$result = unlink( $external_filepath );

		if ( empty( $result ) ) {
			WP_CLI::error( sprintf( 'Unable to delete external file: %s', $external_filepath ), false );
			return;
		}

		WP_CLI::debug( 'Getting head ref from git' );
		$this->plugin->root_repo->set_head_ref();

		WP_CLI::line( sprintf( 'Head ref from git: %s', $this->plugin->root_repo->get_head_ref() ) );
		WP_CLI::success( 'Deleted external file of root repository.' );
	}

	protected function reset_cache() : void {
		$cache = get_transient( Plugin::TRANSIENT_KEY );

		if ( empty( $cache ) ) {
			WP_CLI::error( 'No repositories cache found.', false );
			return;
		}

		WP_CLI::debug( sprintf( 'Repositories in cache: %d', count( $cache ) ) );

		$result = delete_transient( Plugin::TRANSIENT_KEY );

		if ( ! $result ) {
			WP_CLI::error( 'Unable to clear repositories cache.', false );
			return;
		}

		WP_CLI::success( 'Repositories cache cleared.' );
	}

}