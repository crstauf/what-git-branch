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
	 * Scan filesystem for directories.
	 */
	public function scan() {

	}

	/**
	 * Access repository info.
	 */
	public function repo( array $args ) {
		if ( 'set' === $args[0] ) {
			$this->repo_set( $args );
		}

		if ( 'get' === $args[0] ) {
			$this->repo_get( $args );
		}
	}

	/**
	 * Set info for repository.
	 *
	 * For repository, set:
	 * - head reference (creates .what-git-branch file)
	 * - as primary (shown in admin bar)
	 */
	protected function repo_set( array $args ) {

	}

	/**
	 * Get info for repository.
	 *
	 * For repository, get:
	 * - head reference (from .git or .what-git-branch)
	 * - path
	 */
	protected function repo_get( array $args ) {

	}

}