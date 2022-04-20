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
 * @todo add log of branch changes to dashboard widget
 */
class CSSLLC_What_Git_Branch {

	public const HEARTBEAT_KEY = 'what_git_branch';
	public const EXTERNAL_FILE = '.what-git-branch';

	protected $search_paths = array();
	protected $git_dir = '';
	protected $external_file = '';
	protected $head_ref = '';
	protected $branch = '';

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
	 * @uses $this->set_head_ref()
	 */
	protected function __construct() {
		$this->set_search_paths();
		$this->set_head_ref();

		if ( empty( $this->head_ref ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts',    array( $this, 'action__wp_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action__admin_enqueue_scripts' ) );
		add_action( 'wp_dashboard_setup',    array( $this, 'action__wp_dashboard_setup' ) );
		add_action( 'admin_bar_menu',        array( $this, 'action__admin_bar_menu' ), 5000 );

		if ( empty( $this->git_dir ) ) {
			return;
		}

		add_action( 'heartbeat_received', array( $this, 'action__heartbeat_received' ), 10, 2 );
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
	 * Set head reference.
	 * 
	 * @uses $this->set_head_ref_by_file()
	 * @uses $this->set_head_ref_by_repo()
	 * 
	 * @return void
	 */
	protected function set_head_ref() : void {
		$this->set_head_ref_by_file();

		if ( ! empty( $this->head_ref ) ) {
			return;
		}
		
		$this->set_head_ref_by_repo();
	}
	
	/**
	 * Set head reference from file in searchable paths.
	 * 
	 * @return void
	 */
	protected function set_head_ref_by_file() : void {
		if ( empty( $this->search_paths ) ) {
			return;
		}

		foreach ( $this->search_paths as $path ) {
			$path .= self::EXTERNAL_FILE;

			if ( ! file_exists( $path ) ) {
				continue;
			}

			$this->external_file = $path;
			$external_file = file_get_contents( $path );

			break;
		}

		if ( empty( $external_file ) ) {
			return;
		}

		$this->head_ref = sanitize_text_field( $external_file );
	}

	/**
	 * Set head reference from git repository data.
	 *
	 * @uses $this->find_repo_dir()
	 * 
	 * @return void
	 */
	protected function set_head_ref_by_repo() : void {
		$this->find_repo_dir();

		if ( empty( $this->git_dir ) ) {
			return;
		}

		$head_ref = file_get_contents( $this->git_dir . '.git/HEAD' );

		if ( false === $head_ref ) {
			return;
		}

		$this->head_ref = sanitize_text_field( $head_ref );
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
			
			break; // only one git repo
		}
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
	 * 
	 * @uses $this->get_head_ref()
	 * @uses $this->get_github_url()
	 * 
	 * @return void
	 */
	public function callback__dashboard_widget() : void {
		$head_ref = $this->get_head_ref();

		if ( empty( $head_ref ) ) {
			echo '<p>Unable to determine current branch.</p>';

			return;
		}

		echo '<div style="order: -1">'
			. '<h3>Head</h3>'
			. '<p>'
				. '<code class="what-git-branch">' . esc_html( $head_ref ) . '</code>';

				if ( ! empty( $this->get_github_url() ) ) { 
					echo '<a class="what-git-branch-github" href="' . esc_url( $this->get_github_url() ) . '">View on GitHub</a>';
				}
			
			echo '</p>'
		. '</div>';

		if ( empty( $this->git_dir ) ) {
			echo '<div>'
				. '<h3>External File</h3>'
				. '<p><code>' . esc_html( str_replace( ABSPATH , '/', $this->external_file ) ) . '</code></p>'
			. '</div>';

			return;
		}

		echo '<div>'
			. '<h3>Git directory</h3>'
			. '<p><code>' . esc_html( str_replace( ABSPATH , '/', $this->git_dir ) ) . '</code></p>'
		. '</div>';
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

	protected function add_inline_style__dashboard() : string {
		ob_start();
		?>

		#dashboard-widgets-wrap #what-git-branch .inside {
			display: flex;
			gap: 1rem;
			align-items: stretch;
		}

		#dashboard-widgets-wrap #what-git-branch .inside::before {
			content: '';
			width: 0;
			border: 0.5px solid #d3d3d3;
		}

		#dashboard-widgets-wrap #what-git-branch .inside > div {
			width: 50%;
			order: 1;
		}

		#dashboard-widgets-wrap #what-git-branch .inside a {
			float: right;
			font-size: 0.8em;
		}

		#dashboard-widgets-wrap #what-git-branch .inside code {
			display: block;
			user-select: all;
			white-space: nowrap;
			overflow: hidden;
			text-align: left;
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
		if ( empty( $this->git_dir ) ) {
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

				document.querySelectorAll( '.what-git-branch' ).forEach( function( el ) {
					el.innerText = heartbeat_data.head_ref;
				} );

				document.querySelectorAll( '#wp-admin-bar-what-git-branch > a, a.what-git-branch-github' ).forEach( function( el ) {
					el.setAttribute( 'href', heartbeat_data.github_url );
				} );
			} );

		} () );

		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Get head reference.
	 * 
	 * @uses $this->set_head_ref()
	 * @uses $this->is_branch()
	 * @uses $this->get_branch()
	 * 
	 * @return string
	 */
	public function get_head_ref() : string {
		if ( empty( $this->head_ref ) ) {
			$this->set_head_ref();
		}

		if ( $this->is_branch() ) {
			return apply_filters( 'what_git_branch/branch', $this->get_branch() );
		}

		return apply_filters( 'what_git_branch/commit', substr( $this->head_ref, 0, 7 ), $this->head_ref );
	}

	/**
	 * Get branch name.
	 * 
	 * @uses $this->is_branch()
	 * 
	 * @return string
	 */
	protected function get_branch() : string {
		if ( ! $this->is_branch() ) {
			return '';
		}

		if ( ! empty( $this->branch ) ) {
			return $this->branch;
		}

		$pos = strripos( $this->head_ref, '/' );

		$this->branch = trim( substr( $this->head_ref, ( $pos + 1 ) ) );

		return $this->branch;
	}

	/**
	 * Check if head reference is a branch.
	 * 
	 * @return bool
	 */
	public function is_branch() : bool {
		return false !== strripos( $this->head_ref, '/' );
	}

	/**
	 * Check if head reference is a commit.
	 * 
	 * @uses $this->is_branch()
	 * 
	 * @return bool
	 */
	public function is_commit() : bool {
		return ! $this->is_branch();
	}

	/**
	 * Get URL to head reference on GitHub.
	 * 
	 * @uses $this->get_head_ref()
	 * 
	 * @return string
	 */
	protected function get_github_url() : string {
		$github_repo = '';

		if ( defined( 'WHAT_GIT_BRANCH_GITHUB_REPO' ) ) {
			$github_repo = constant( 'WHAT_GIT_BRANCH_GITHUB_REPO' );
		}

		$github_repo = apply_filters( 'what_git_branch/github_repo', $github_repo );

		if ( empty( $github_repo ) ) {
			return '';
		}

		return sprintf( 
			'https://github.com/%s/tree/%s',
			sanitize_text_field( $github_repo ),
			sanitize_text_field( $this->get_head_ref() )
		);
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
	 * @param WP_Admin_Bar $bar (reference)
	 * 
	 * @uses $this->get_head_ref()
	 * @uses $this->get_github_url()
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
				esc_html( $this->get_head_ref() ) 
			),
			'parent' => false,
			'meta'   => array(
				'onclick' => 'if ( navigator.clipboard) { navigator.clipboard.writeText( "' . $this->get_head_ref() . '" ).then( () => { alert( "Copied branch name." ) } ) }',
			),
		);

		$github_url = $this->get_github_url();

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
	 * 
	 * @uses $this->get_head_ref()
	 * @uses $this->get_github_url()
	 * 
	 * @return array
	 */
	public function action__heartbeat_received( $response, array $data ) {
		if ( empty( $data[ self::HEARTBEAT_KEY ] ) ) {
			return $response;
		}

		$response[ self::HEARTBEAT_KEY ] = array(
			'head_ref'   => $this->get_head_ref(),
			'github_url' => $this->get_github_url(),
		);

		return $response;
	}

}

add_action( 'init', static function() : void {
	if ( 
		'production' === wp_get_environment_type() 
		|| ! current_user_can( 'manage_options' ) 
	) {
		return;
	}

	CSSLLC_What_Git_Branch::init();
} );