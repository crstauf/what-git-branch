<?php declare( strict_types=1 );
/*
Plugin Name: What Git Branch?
Plugin URI: https://github.com/crstauf/what-git-branch
Version: 2.0.0
Author: Caleb Stauffer
Author URI: https://develop.calebstauffer.com
*/

namespace What_Git_Branch;

if ( ! defined( 'WPINC' ) || ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

class Plugin {

	public const HEARTBEAT_KEY = 'what_git_branch';

	protected $repos = array();
	protected $root_repo;

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() : void {
		static $init = false;

		if ( $init ) {
			return;
		}

		$init     = true;
		$instance = new self;

		$instance->include_files();
		$instance->set_repos();
		$instance->set_root_repo();

		Admin::init( $instance->repos, $instance->root_repo );
	}

	protected function __construct() {}

	protected function include_files() : void {
		require_once 'class-repository.php';
		require_once 'class-admin.php';
	}

	protected function recursive_glob( $pattern ) : array {
		$files = glob( $pattern );

		foreach ( glob( dirname( $pattern ) . '/*', GLOB_ONLYDIR|GLOB_NOSORT ) as $dir ) {
			$files = array_merge( $files, $this->recursive_glob( trailingslashit( $dir ) . basename( $pattern ) ) );
		}

		return $files;
	}

	/**
	 * @uses $this->recursive_glob()
	 *
	 * @return void
	 */
	protected function set_repos() : void {
		if ( ! empty( $this->repos ) ) {
			return;
		}

		$dirs     = array();
		$git_dirs = $this->recursive_glob( trailingslashit( WP_CONTENT_DIR ) . '**/.git/' );
		$ext_dirs = $this->recursive_glob( trailingslashit( WP_CONTENT_DIR . '**/' . Repository::EXTERNAL_FILE ) );

		$additional_paths = apply_filters( 'what-git-branch/set_repos/$additional_paths', array(
			trailingslashit( ABSPATH ) . '.git/',
			trailingslashit( ABSPATH ) . Repository::EXTERNAL_FILE,
			trailingslashit( WP_CONTENT_DIR ) . '.git/',
			trailingslashit( WP_CONTENT_DIR ) . Repository::EXTERNAL_FILE,
		) );

		foreach ( $additional_paths as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}

			$dirs[] = $path;
		}

		$dirs = array_merge( $dirs, $git_dirs, $ext_dirs );
		$dirs = array_unique( $dirs );

		$repos = array_map( 'dirname', $dirs );
		$repos = array_map( 'trailingslashit', $repos );
		$repos = array_map( static function ( $repo_path ) {
			return new Repository( $repo_path );
		}, $repos );

		$this->repos = $repos;
	}

	/**
	 * return void
	 */
	protected function set_root_repo() : void {
		if ( ! is_null( $this->root_repo ) ) {
			return;
		}

		$this->set_repos();

		$pre = ( string ) apply_filters( 'what-git-branch/set_root_repo/pre', '' );

		if ( ! empty( $pre ) ) {
			foreach ( $this->repos as $repo ) {
				if ( $pre !== $repo->path ) {
					continue;
				}

				$this->root_repo = &$repo;
				$this->root_repo->set_as_root();

				return;
			}

			trigger_error( sprintf( 'Manually setting root repository failed: %s', $pre ), E_USER_WARNING );
			return;
		}

		$dirs = array();

		foreach ( $this->repos as $repo ) {
			$dirs[ $repo->path ] = count( explode( '/', $repo->path ) );
		}

		$min  = min( $dirs );
		$dirs = array_filter( $dirs, static function ( $count ) use ( $min ) {
			return $count <= $min;
		} );

		if ( 1 !== count( $dirs ) ) {
			$this->root_repo = false;
			return;
		}

		$dirs = array_keys( $dirs );
		$root = array_pop( $dirs );

		foreach ( $this->repos as $repo ) {
			if ( $root !== $repo->path ) {
				continue;
			}

			$this->root_repo = &$repo;
			$this->root_repo->set_as_root();
		}
	}

}

add_action( 'init', static function() : void {
	if (
		'production' === wp_get_environment_type()
		|| ! current_user_can( 'manage_options' )
		|| ! is_admin()
		|| ! is_admin_bar_showing()
	) {
		return;
	}

	Plugin::init();

} );