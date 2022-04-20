<?php
/*
Plugin Name: What Git Branch?
Plugin URI:
Description:
Version: 1.0.0
Author: Caleb Stauffer
Author URI: https://develop.calebstauffer.com
*/

if ( ! defined( 'WPINC' ) || ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * @todo add dashboard widget
 * @todo add log of branch changes to dashboard widget
 */
class CSSLLC_What_Git_Branch {

	public const HEARTBEAT_KEY = 'what_git_branch';
	public const EXTERNAL_FILE = '.what-git-branch';

	protected $search_paths = array();
	protected $git_dir = '';
	protected $current_branch = '';

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

		$init = true;

		new self;
	}

	/**
	 * Construct.
	 *
	 * @uses $this->set_search_paths()
	 * @uses $this->set_current_branch()
	 */
	protected function __construct() {
		$this->set_search_paths();
		$this->set_current_branch();

		if ( empty( $this->current_branch ) ) {
			return;
		}

		add_action( 'init', array( $this, 'action__init' ) );
	}

	/**
	 * Set paths to search for git repo.
	 * 
	 * @return void
	 */
	protected function set_search_paths() : void {
		$paths = array(
			trailingslashit( ABSPATH ),
			trailingslashit( WP_CONTENT_DIR ),
		);

		$paths = apply_filters( 'what_git_branch/paths', $paths );
		$paths = array_map( 'trailingslashit', $paths );
		$paths = array_unique( $paths );

		$this->search_paths = $paths;
	}
	
	/**
	 * Set current branch.
	 * 
	 * @uses $this->set_branch_by_file()
	 * @uses $this->set_branch_by_repo()
	 * 
	 * @return void
	 */
	protected function set_current_branch() : void {
		$this->set_branch_by_file();

		if ( ! empty( $this->current_branch ) ) {
			return;
		}

		$this->set_branch_by_repo();
	}
	
	/**
	 * Set branch from file in searchable paths.
	 * 
	 * @return void
	 */
	protected function set_branch_by_file() : void {
		if ( empty( $this->search_paths ) ) {
			return;
		}

		foreach ( $this->search_paths as $path ) {
			$path .= self::EXTERNAL_FILE;

			if ( ! file_exists( $path ) ) {
				continue;
			}

			$external_file = file_get_contents( $path );

			break;
		}

		if ( empty( $external_file ) ) {
			return;
		}

		$this->current_branch = sanitize_text_field( $external_file );
	}

	/**
	 * Set branch from git repository data.
	 *
	 * @uses $this->find_repo_dir()
	 * 
	 * @return void
	 */
	protected function set_branch_by_repo() : void {
		$this->find_repo_dir();

		if ( empty( $this->git_dir ) ) {
			return;
		}

		$head = file_get_contents( $this->git_dir . '.git/HEAD' );
		$head = sanitize_text_field( $head );

		if ( false === $head ) {
			return;
		}

		$pos = strripos( $head, '/' );
		$this->current_branch = trim( substr( $head, ( $pos + 1 ) ) );
	}

	/**
	 * Find repository directory.
	 * 
	 * @return void
	 */
	protected function find_repo_dir() : void {
		if ( ! empty( $this->git_dir ) ) {
			return;
		}

		if ( empty( $this->search_paths ) ) {
			return;
		}

		foreach ( $this->search_paths as $path ) {
			$path .= '.git/';

			if (
				   ! file_exists( $path )
				|| ! is_dir( $path )
				|| ! file_exists( $path . 'HEAD' )
			) {
				continue;
			}

			$this->git_dir = trailingslashit( dirname( $path ) );
			
			break; // supports one git repo
		}
	}

	/**
	 * Enqueue assets.
	 * 
	 * @uses $this->add_inline_style__admin_bar()
	 * @uses $this->add_inline_script__heartbeat()
	 * 
	 * @return void
	 */
	protected function enqueue_assets() : void {
		wp_add_inline_style( 'admin-bar', $this->add_inline_style__admin_bar() );
		
		if ( empty( $this->git_dir ) ) {
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

		#wp-admin-bar-what-git-branch .what-git-branch {
			display: inline-block;
			padding: 0 7px 0 27px;
			background-image: url( <?php echo plugin_dir_url( __FILE__ ) ?>git.png );
			background-position: 7px center;
			background-repeat: no-repeat;
			background-size: auto 50%;
			font-family: Consolas, Monaco, monospace;
			line-height: 30px;
		}

		<?php
		return trim( ob_get_clean() );
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

			jQuery( document ).on( 'heartbeat-send', function ( ev, data ) {
				data[ heartbeat_key ] = true;
			} );

			jQuery( document ).on( 'heartbeat-tick', function( ev, data ) {
				if ( ! data[ heartbeat_key ] ) {
					return;
				}

				document.querySelectorAll( '.what-git-branch' ).forEach( function( el ) {
					el.innerText = data[ heartbeat_key ]
				} );
			} );

		} () );

		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Get branch name.
	 * 
	 * @uses $this->set_current_branch()
	 * 
	 * @return string
	 */
	public function get_current_branch() : string {
		if ( empty( $this->current_branch ) ) {
			$this->set_current_branch();
		}

		return apply_filters( 'what_git_branch/current_branch', $this->current_branch );
	}

	/**
	 * Action: init
	 * 
	 * @return void
	 */
	public function action__init() : void {
		if ( 
			'init' !== current_action() 
			|| ! current_user_can( 'manage_options' )
		) {
			return;
		}

		if ( 
			   ! is_admin_bar_showing() 
			&& ! wp_doing_ajax() 
		) {
			return;
		}

		add_action( 'wp_enqueue_scripts',    array( $this, 'action__wp_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action__admin_enqueue_scripts' ) );
		add_action( 'admin_bar_menu',        array( $this, 'action__admin_bar_menu' ), 5000 );

		if ( empty( $this->git_dir ) ) {
			return;
		}

		add_action( 'heartbeat_received', array( $this, 'action__heartbeat_received' ), 10, 2 );
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
	 * Action: admin_bar_menu
	 * 
	 * @param WP_Admin_Bar $bar (reference)
	 * 
	 * @uses $this->get_current_branch()
	 * 
	 * @return void
	 */
	public function action__admin_bar_menu( WP_Admin_Bar $bar ) : void {
		if ( 'admin_bar_menu' !== current_action() ) {
			return;
		}

		$args = array(
			'id'     => 'what-git-branch',
			'title'  => sprintf( 
				'<span class="what-git-branch" title="%s">%s</span>', 
				esc_html( $this->git_dir ),
				esc_html( $this->get_current_branch() ) 
			),
			'parent' => false,
			'meta'   => array(
				'onclick' => 'if ( navigator.clipboard) { navigator.clipboard.writeText( "' . $this->get_current_branch() . '" ).then( () => { alert( "Copied branch name." ) } ) }',
			),
		);

		$github_repo = '';

		if ( defined( 'WHAT_GIT_BRANCH_GITHUB_REPO' ) ) {
			$github_repo = constant( 'WHAT_GIT_BRANCH_GITHUB_REPO' );
		}

		$github_repo = apply_filters( 'what_git_branch/github_repo', $github_repo );

		if ( ! empty( $github_repo ) ) {
			unset( $args['meta']['onclick'] );

			$args['href'] = sprintf( 
				'https://github.com/%s/tree/%s',
				sanitize_text_field( $github_repo ),
				sanitize_text_field( $this->get_current_branch() )
			);
		}

		$bar->add_node( $args );
	}

	/**
	 * Action: heartbeat_received
	 * 
	 * @param mixed $response
	 * @param array $data
	 * 
	 * @uses $this->get_current_branch()
	 * 
	 * @return array
	 */
	public function action__heartbeat_received( $response, array $data ) {
		if ( empty( $data[ self::HEARTBEAT_KEY ] ) ) {
			return $response;
		}

		$response[ self::HEARTBEAT_KEY ] = $this->get_current_branch();

		return $response;
	}

}

CSSLLC_What_Git_Branch::init();