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
	public const TRANSIENT_KEY = 'what-git-branch-repos';

	protected $repos = array();
	protected $root_repo;

	public function __get( $key ) {
		return $this->$key;
	}

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

		require_once 'class-repository.php';

		$instance->set_repos();
		$instance->set_root_repo();
		$instance->hooks();
	}

	public static function init_cli() : void {
		static $init = false;

		if ( $init ) {
			return;
		}

		$init     = true;
		$instance = new self;

		require_once 'class-repository.php';
		require_once 'class-wpcli.php';

		$instance->set_repos();
		$instance->set_root_repo();

		$cli = new WPCLI( $instance );

		\WP_CLI::add_command( 'whatgitbranch', $cli );
	}

	protected function __construct() {}

	protected function recursive_glob( $pattern ) : array {
		$files = glob( $pattern );

		foreach ( glob( dirname( $pattern ) . '/*', GLOB_ONLYDIR|GLOB_NOSORT ) as $dir ) {
			$files = array_merge( $files, $this->recursive_glob( trailingslashit( $dir ) . basename( $pattern ) ) );
		}

		return $files;
	}

	protected function scan_for_dirs() : bool {
		return apply_filters( 'what-git-branch/scan_for_dirs()', true );
	}

	/**
	 * @uses $this->recursive_glob()
	 *
	 * @return void
	 */
	public function set_repos() : void {error_log( __METHOD__ );
		if ( ! empty( $this->repos ) ) {
			return;
		}
error_log( '94' );
		$this->set_repos_from_filter();

		if ( ! empty( $this->repos ) ) {
			return;
		}
error_log( '100' );
		$this->set_repos_from_cache();

		if ( ! empty( $this->repos ) ) {
			return;
		}
error_log( '106' );
		if ( ! $this->scan_for_dirs() ) {
			return;
		}
error_log( '111' );
		do_action( 'qm/start', 'what-git-branch/set_repos()' );

		$dirs     = array();
		$git_dirs = $this->recursive_glob( trailingslashit( WP_CONTENT_DIR ) . '**/.git/' );

		do_action( 'qm/lap', 'what-git-branch/set_repos()', '$git_dirs' );

		$ext_dirs = $this->recursive_glob( trailingslashit( WP_CONTENT_DIR ) . '**/' . Repository::EXTERNAL_FILE );

		do_action( 'qm/lap', 'what-git-branch/set_repos()', '$ext_dirs' );

		$additional_paths = apply_filters( 'what-git-branch/set_repos/$additional_paths', array(
			trailingslashit( ABSPATH ),
			trailingslashit( WP_CONTENT_DIR ),
		) );

		foreach ( $additional_paths as $path ) {
			if (
				   ! file_exists( $path . '.git/' )
				&& ! file_exists( $path . Repository::EXTERNAL_FILE )
			) {
				continue;
			}

			$dirs[] = $path;
		}

		do_action( 'qm/lap', 'what-git-branch/set_repos()', '$additional_paths' );

		$git_dirs = array_map( 'dirname', $git_dirs );
		$ext_dirs = array_map( 'dirname', $ext_dirs );

		$repos = array_merge( $dirs, $git_dirs, $ext_dirs );
		$repos = array_unique( $repos );
		$repos = array_map( 'trailingslashit', $repos );
		$repos = array_map( static function ( $repo_path ) {
			return new Repository( $repo_path );
		}, $repos );

		$this->repos = $repos;

		set_transient( self::TRANSIENT_KEY, $this->repos, DAY_IN_SECONDS );

		do_action( 'qm/lap', 'what-git-branch/set_repos()', '$this->repos' );
		do_action( 'qm/stop', 'what-git-branch/set_repos()' );
	}

	protected function set_repos_from_filter() {
		$pre = apply_filters( 'what-git-branch/set_repos()/$pre', null );

		if ( empty( $pre ) || ! is_array( $pre ) ) {
			return;
		}

		$pre = array_filter( 'file_exists', $pre );
		$pre = array_filter( 'is_dir', $pre );
		$pre = array_unique( $pre );
		$pre = array_map( 'trailingslashit', $pre );
		$pre = array_map( static function ( $repo_path ) {
			return new Repository( $repo_path );
		}, $pre );

		$this->repos = $pre;
	}

	protected function set_repos_from_cache() {
		$cache = get_transient( self::TRANSIENT_KEY );

		if ( empty( $cache ) ) {
			return;
		}

		$this->repos = $cache;
	}

	/**
	 * return void
	 */
	protected function set_root_repo() : void {
		if ( ! is_null( $this->root_repo ) ) {
			return;
		}

		if ( apply_filters( 'what-git-branch/set_root_repo/disable', false ) ) {
			return;
		}

		$this->set_repos();

		$pre = ( string ) apply_filters( 'what-git-branch/set_root_repo/pre', '' );

		if ( ! empty( $pre ) && file_exists( $pre ) && is_dir( $pre ) ) {
			if ( ! $this->scan_for_dirs() ) {
				$this->root_repo = new Repository( $pre );
				$this->root_repo->set_as_root();

				if ( empty( $this->repos ) ) {
					$this->repos[] = &$this->root_repo;
				}

				return;
			}

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

		if ( ! $this->scan_for_dirs() ) {
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

			break;
		}
	}

	protected function hooks() : void {
		add_action( 'admin_enqueue_scripts', array( $this, 'action__admin_enqueue_scripts' ) );
		add_action( 'wp_dashboard_setup',    array( $this, 'action__wp_dashboard_setup' ) );

		if ( empty( $this->root_repo ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'action__wp_enqueue_scripts' ) );
		add_action( 'admin_bar_menu',     array( $this, 'action__admin_bar_menu' ), 5000 );
		add_action( 'heartbeat_received', array( $this, 'action__heartbeat_received' ), 10, 2 );
	}

	/**
	 * Register Dashboard widget.
	 *
	 * @uses wp_add_dashboard_widget()
	 *
	 * @return void
	 */
	protected function register_dashboard_widget() : void {
		wp_add_dashboard_widget( 'what-git-branch', 'What Git Branch?', array( $this, 'callback__dashboard_widget' ) );
	}

	/**
	 * Callback: wp_add_dashboard_widget()
	 *
	 * @see $this->register_dashboard_widget()
	 * @return void
	 */
	public function callback__dashboard_widget() : void {
		if ( empty( $this->repos ) ) {
			echo '<p>No repositories found.</p>';
			return;
		}

		$sort__directories = array();

		foreach ( $this->repos as $repo ) {
			$sort__directories[] = basename( $repo->path );
		}

		array_multisort( $sort__directories, SORT_ASC, SORT_NATURAL|SORT_FLAG_CASE, $this->repos );

		echo '<table cellpadding="0" cellspacing="0" width="100%">';

		foreach ( $this->repos as $repo ) {
			if ( apply_filters( 'what-git-branch/callback__dashboard_widget/foreach/continue', false, $repo->path ) ) {
				continue;
			}

			$attr__class = '';
			$github_link = '';
			$github_url  = $repo->get_github_url();

			$name = apply_filters( 'what-git-branch/callback__dashboard_widget/foreach/name', basename( $repo->path ), $repo );

			if ( $repo->is_root || ( ! empty( $this->root_repo ) && $repo->path === $this->root_repo->path ) ) {
				$attr__class = ' class="is-root"';
			}

			if ( ! empty( $github_url ) ) {
				$github_link = sprintf(
					'<a data-wgb-key="%s" class="wgb-only-link" href="%s"><span class="dashicons dashicons-external"></span></a>',
					esc_attr( $repo->key() ),
					esc_url( $github_url )
				);
			}

			printf(
				'<tr valign="top"%s><th scope="row" title="%s">%s</th><td><code data-wgb-key="%s">%s</code>%s</td>',
				$attr__class,
				esc_attr( $repo->path ),
				esc_html( $name ),
				esc_attr( $repo->key() ),
				esc_html( $repo->get_head_ref() ),
				$github_link
			);
		}

		echo '</table>';
	}

	/**
	 * Enqueue assets.
	 *
	 * @uses $this->add_inline_style__admin_bar()
	 * @uses $this->add_inline_style__dashboard()
	 * @uses $this->needs_heartbeat()
	 * @uses $this->add_inline_script__heartbeat()
	 *
	 * @return void
	 */
	protected function enqueue_assets() : void {
		if ( is_admin_bar_showing() ) {
			wp_add_inline_style( 'admin-bar', $this->add_inline_style__admin_bar() );
		}

		if (
			function_exists( 'get_current_screen' )
			&& is_a( get_current_screen(), \WP_Screen::class )
			&& 'dashboard' === get_current_screen()->id
		) {
			wp_add_inline_style( 'dashboard', $this->add_inline_style__dashboard() );
		}

		if ( ! $this->needs_heartbeat() ) {
			return;
		}

		   wp_enqueue_script( 'heartbeat' );
		wp_add_inline_script( 'heartbeat', $this->add_inline_script__heartbeat(), 'before' );
	}

	/**
	 * Add inline styles to admin-bar stylesheet.
	 *
	 * @return string
	 */
	protected function add_inline_style__admin_bar() : string {
		ob_start();
		?>

		#wp-admin-bar-what-git-branch [data-wgb-key] {
			display: inline-block;
			padding: 0 7px 0 27px;
			background-image: url( data:image/png;base64,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 );
			background-position: 7px center;
			background-repeat: no-repeat;
			background-size: auto 50%;
			font-family: Consolas, Monaco, monospace;
			line-height: 30px;
		}

		<?php
		return trim( ob_get_clean() );
	}

	protected function add_inline_style__dashboard() : string {
		ob_start();
		?>

		#dashboard-widgets-wrap #what-git-branch .postbox-header .hndle {
			justify-content: flex-start;
		}

		#dashboard-widgets-wrap #what-git-branch .postbox-header .hndle::before {
			content: '';
			display: inline-block;
			width: 20px;
			height: 20px;
			margin-right: 5px;
			background-image: url( data:image/png;base64,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 );
			background-repeat: no-repeat;
			background-position: center;
			background-size: auto 80%;
		}

		#dashboard-widgets-wrap #what-git-branch .inside :is( th, td ) {
			padding: 5px 10px;
			font-weight: normal;
			text-align: left;
		}

		#dashboard-widgets-wrap #what-git-branch .inside tr.is-root :is( th, td ) {
			color: #FFF;
		}

		#dashboard-widgets-wrap #what-git-branch .inside tr:nth-child( odd ):not( .is-root ) :is( th, td ) {
			background-color: #eee;
		}

		#dashboard-widgets-wrap #what-git-branch .inside tr.is-root {
			position: relative;
			z-index: 1;
		}

		#dashboard-widgets-wrap #what-git-branch .inside tr.is-root th::before {
			content: '';
			position: absolute;
			left: -13px;
			top: 0;
			z-index: -1;
			display: block;
			width: calc( 100% + 13px + 13px );
			height: 100%;
			background-color: #f14e32;
		}

		#dashboard-widgets-wrap #what-git-branch .inside a {
			float: right;
			text-decoration: none;
		}

		#dashboard-widgets-wrap #what-git-branch .inside a:not( :hover ) {
			color: #999;
		}

		#dashboard-widgets-wrap #what-git-branch .inside tr.is-root a {
			color: #FFF;
		}

		#dashboard-widgets-wrap #what-git-branch .inside a .dashicons {
			margin-top: 2px;
			font-size: 15px;
		}

		#dashboard-widgets-wrap #what-git-branch .inside code {
			user-select: all;
			white-space: nowrap;
			background-color: transparent;
			overflow: hidden;
			text-align: left;
			font-size: 12px;
			direction: rtl;
		}

		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Check if Heartbeat API is needed.
	 *
	 * Heartbeat API is used to periodically check the git branch,
	 * and update the branch name in the admin bar and Dashboard widget.
	 *
	 * @return bool
	 */
	protected function needs_heartbeat() : bool {
		if ( empty( $this->root_repo ) ) {
			return false;
		}

		if ( is_admin_bar_showing() ) {
			return true;
		}

		return function_exists( 'get_current_screen' ) && 'dashboard' === get_current_screen()->id;
	}

	/**
	 * Add inline script to heartbeat script.
	 *
	 * @return string
	 */
	protected function add_inline_script__heartbeat() : string {
		ob_start();
		?>

		( function() {

			var heartbeat_key = <?php echo json_encode( self::HEARTBEAT_KEY ) ?>;
			var heartbeat_data;

			jQuery( document ).on( 'heartbeat-send', function ( ev, data ) {
				data[ heartbeat_key ] = true;
			} );

			jQuery( document ).on( 'heartbeat-tick', function( ev, data ) {
				if ( ! data[ heartbeat_key ] ) {
					return;
				}

				heartbeat_data = data[ heartbeat_key ];

				document.querySelectorAll( '[data-wgb-key]:not( .wgb-only-link )' ).forEach( function( el ) {
					el.innerText = heartbeat_data[ el.dataset.wgbKey ]['head_ref'];
				} );

				document.querySelectorAll( 'a[data-wgb-key]' ).forEach( function( el ) {
					el.setAttribute( 'href', heartbeat_data[ el.dataset.wgbKey ]['github_url'] );
				} );

				document.querySelector( '#wp-admin-bar-what-git-branch > a' ).setAttribute( 'href', heartbeat_data['root']['github_url'] );
			} );

		} () );

		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Action: wp_enqueue_scripts
	 *
	 * @uses $this->enqueue_assets()
	 *
	 * @return void
	 */
	public function action__wp_enqueue_scripts() : void {
		if ( 'wp_enqueue_scripts' !== current_action() ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Action: admin_enqueue_scripts
	 *
	 * @uses $this->enqueue_assets()
	 *
	 * @return void
	 */
	public function action__admin_enqueue_scripts() : void {
		if ( 'admin_enqueue_scripts' !== current_action() ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Action: wp_dashboard_setup
	 *
	 * @uses $this->register_dashboard_widget()
	 *
	 * @return void
	 */
	public function action__wp_dashboard_setup() : void {
		if ( 'wp_dashboard_setup' !== current_action() ) {
			return;
		}

		$this->register_dashboard_widget();
	}

	/**
	 * Action: admin_bar_menu
	 *
	 * @param \WP_Admin_Bar $bar (reference)
	 * @return void
	 */
	public function action__admin_bar_menu( \WP_Admin_Bar $bar ) : void {
		if ( 'admin_bar_menu' !== current_action() ) {
			return;
		}

		$repo = $this->root_repo;
		$args = array(
			'id'     => 'what-git-branch',
			'title'  => sprintf(
				'<span data-wgb-key="%s" title="%s">%s</span>',
				esc_attr( $repo->key() ),
				esc_attr( $repo->path ),
				esc_html( $repo->get_head_ref() )
			),
			'parent' => false,
			'meta'   => array(
				'onclick' => 'if ( navigator.clipboard) { navigator.clipboard.writeText( "' . $repo->get_head_ref() . '" ).then( () => { alert( "Copied branch name." ) } ) }',
			),
		);

		$github_url = $repo->get_github_url();

		if ( ! empty( $github_url ) ) {
			$args['href'] = $github_url;

			unset( $args['meta']['onclick'] );
		}

		$bar->add_node( $args );
	}

	/**
	 * Action: heartbeat_received
	 *
	 * @param mixed $response
	 * @param array $data
	 * @return array
	 */
	public function action__heartbeat_received( $response, array $data ) {
		if ( empty( $data[ self::HEARTBEAT_KEY ] ) ) {
			return $response;
		}

		$repos = array();

		foreach ( $this->repos as $repo ) {
			$repos[ $repo->key() ] = array(
				'head_ref'   => $repo->get_head_ref(),
				'github_url' => $repo->get_github_url(),
			);

			if ( ! $repo->is_root ) {
				continue;
			}

			$repos['root'] = &$repos[ $repo->key() ];
		}

		$response[ self::HEARTBEAT_KEY ] = $repos;

		return $response;
	}

}

add_action( 'init', static function() : void {
	if ( 'production' === wp_get_environment_type() ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! is_admin() && ! is_admin_bar_showing() ) {
		return;
	}

	Plugin::init();
} );

if ( ! defined( 'WP_CLI' ) || ! constant( 'WP_CLI' ) || ! file_exists( __DIR__ . '/class-wpcli.php' ) ) {
	return;
}

Plugin::init_cli();