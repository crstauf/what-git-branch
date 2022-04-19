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
 * @todo introduce support for link to branch on GitHub
 */
class CSSLLC_What_Git_Branch {

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
	 */
	protected function __construct() {
		$this->set_search_paths();
		$this->find_repo_dir();

		if ( empty( $this->git_dir ) ) {
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
	 * Find repository directory.
	 * 
	 * @return void
	 */
	protected function find_repo_dir() : void {
		if ( empty( $this->search_paths ) ) {
			return;
		}

		foreach ( $this->search_paths as $i => $path ) {
			$path .= '.git/';

			if (
				   ! file_exists( $path )
				|| ! is_dir( $path )
				|| ! file_exists( $path . 'HEAD' )
			) {
				continue;
			}

			$this->git_dir = $this->search_paths[ $i ];
			
			break; // currently only supports one git repo
		}
	}

	/**
	 * Enqueue assets.
	 * 
	 * @return void
	 */
	protected function enqueue_assets() : void {
		wp_enqueue_script( 'heartbeat' );
		wp_add_inline_style( 'admin-bar', $this->add_inline_style__admin_bar() );
	}

	/**
	 * Add inline styles to admin-bar stylesheet.
	 * 
	 * @return string
	 */
	protected function add_inline_style__admin_bar() : string {
		ob_start();
		?>

		#wp-admin-bar-what-git-branch .code {
			display: inline-block;
			padding: 0 7px 0 27px;
			background-image: url( <?php echo plugin_dir_url( __FILE__ ) ?>git.png );
			background-position: 7px center;
			background-repeat: no-repeat;
			background-size: auto 50%;
			font-family: Consolas, Monaco, monospace;
			line-height: 30px;
		}

		#wpadminbar .ab-top-menu > li#wp-admin-bar-what-git-branch.hover > .ab-item, 
		#wpadminbar.nojq .quicklinks .ab-top-menu > li#wp-admin-bar-what-git-branch > .ab-item:focus, 
		#wpadminbar:not( .mobile ) .ab-top-menu > li#wp-admin-bar-what-git-branch:hover >.ab-item, 
		#wpadminbar:not( .mobile ) .ab-top-menu > li#wp-admin-bar-what-git-branch > .ab-item:focus {
			background: transparent;
			color: #f0f0f1;
		}

		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Get branch name.
	 * 
	 * @return string
	 */
	protected function get_current_branch() : string {
		if ( ! empty( $this->current_branch ) ) {
			return $this->current_branch;
		}

		$head = file_get_contents( $this->git_dir . '.git/HEAD' );

		if ( false === $head ) {
			return 'N/A';
		}

		$pos = strripos( $head, '/' );
		$this->current_branch = substr( $head, ( $pos + 1 ) );

		return $this->current_branch;
	}

	/**
	 * Action: init
	 * 
	 * @return void
	 */
	public function action__init() : void {
		if ( 'init' !== current_action() ) {
			return;
		}

		if (
			   ! current_user_can( 'manage_options' )
			|| ! is_admin_bar_showing()
		) {
			return;
		}

		add_action( 'wp_enqueue_scripts',    array( $this, 'action__wp_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action__admin_enqueue_scripts' ) );
		add_action( 'admin_bar_menu',        array( $this, 'action__admin_bar_menu' ), 5000 );
	}

	/**
	 * Action: wp_enqueue_scripts
	 * 
	 * @uses $this->enqueue_assets()
	 * 
	 * @return void
	 */
	public function action__wp_enqueue_scripts() : void {
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
		$this->enqueue_assets();
	}

	/**
	 * Action: admin_bar_menu
	 * 
	 * @param WP_Admin_Bar $bar (reference)
	 * 
	 * @return void
	 */
	public function action__admin_bar_menu( WP_Admin_Bar $bar ) : void {
		$args = array(
			'id'     => 'what-git-branch',
			'title'  => sprintf( 
				'<span class="code" title="%s">%s</span>', 
				esc_html( $this->git_dir ),
				esc_html( $this->get_current_branch() ) 
			),
			'href'   => false,
			'parent' => false,
		);

		$bar->add_node( $args );
	}

}

CSSLLC_What_Git_Branch::init();