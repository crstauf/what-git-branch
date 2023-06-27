<?php declare( strict_types=1 );

namespace What_Git_Branch;

/**
 * @property-read string $name
 * @property-read string $path
 * @property-read string $external_file
 * @property-read string $head_ref
 * @proeprty-read string $branch
 * @property-read bool $is_primary
 */
class Repository {

	public const EXTERNAL_FILE = '.what-git-branch';
	public const HEAD_PREFIX   = 'ref: refs/heads/';

	/**
	 * @var null|string
	 */
	protected $name;

	/**
	 * @var null|string
	 */
	protected $path;

	/**
	 * @var null|string
	 */
	protected $external_file;

	/**
	 * @var null|string
	 */
	protected $head_ref;

	/**
	 * @var null|string
	 */
	protected $branch;

	/**
	 * @var bool
	 */
	protected $is_primary = false;

	/**
	 * Construct.
	 *
	 * @param string $path
	 */
	public function __construct( string $path ) {
		$this->path = $path;

		/**
		 * Set repository name.
		 *
		 * @param string $name
		 */
		$this->name = apply_filters( 'what-git-branch/repository/name', basename( $path ) );
	}

	/**
	 * Getter.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( string $key ) {
		return $this->$key;
	}

	/**
	 * Get key to identify repository.
	 *
	 * @return string
	 */
	public function key() : string {
		return wp_hash( $this->path );
	}

	/**
	 * Set this repo as the primary.
	 *
	 * @return void
	 */
	public function set_primary() : void {
		$this->is_primary = true;
	}

	/**
	 * Set head reference from external file or git.
	 *
	 * @uses $this->set_head_ref_from_external()
	 * @uses $this->set_head_ref_from_git()
	 *
	 * @return void
	 */
	public function set_head_ref() : void {
		$this->head_ref = null;
		$this->branch   = null;

		$this->set_head_ref_from_external();

		if ( ! empty( $this->head_ref ) ) {
			return;
		}

		$this->set_head_ref_from_git();
	}

	/**
	 * Set head reference from external file.
	 *
	 * @return void
	 */
	protected function set_head_ref_from_external() : void {
		$path = trailingslashit( $this->path ) . self::EXTERNAL_FILE;

		if ( ! file_exists( $path ) ) {
			return;
		}

		$this->external_file = $path;

		$external_file = file_get_contents( $path );

		if ( ! is_string( $external_file ) ) {
			return;
		}

		$external_file = sanitize_text_field( $external_file );

		if ( empty( $external_file ) ) {
			return;
		}

		$this->head_ref = $external_file;
	}

	/**
	 * Set head reference from git.
	 *
	 * @return void
	 */
	protected function set_head_ref_from_git() : void {
		$path = trailingslashit( $this->path ) . '.git/';

		if ( ! file_exists( $path ) || ! is_dir( $path ) || ! file_exists( $path . 'HEAD' ) ) {
			return;
		}

		$path .= 'HEAD';
		$git   = file_get_contents( $path );

		if ( false === $git ) {
			return;
		}

		$git = sanitize_text_field( $git );

		if ( empty( $git ) ) {
			return;
		}

		$this->head_ref = $git;
	}

	/**
	 * Get head reference.
	 *
	 * @uses $this->set_head_ref()
	 * @uses $this->get_branch()
	 *
	 * @return string
	 */
	public function get_head_ref() : string {
		if ( empty( $this->head_ref ) ) {
			$this->set_head_ref();
		}

		$branch = $this->get_branch();

		if ( ! empty( $branch ) ) {
			return $branch;
		}

		$head_ref = substr( $this->head_ref, 0, 7 );

		/**
		 * Change head reference for repository.
		 *
		 * @param string $head_ref
		 * @param string $raw_head_ref
		 */
		return apply_filters( 'what-git-branch/repository/get_head_ref()/commit', $head_ref, $this->head_ref );
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

		$branch = trim( str_replace( self::HEAD_PREFIX, '', $this->head_ref ) );

		/**
		 * Set branch name.
		 *
		 * @param string $branch
		 * @param self $repo
		 */
		$branch = apply_filters( 'what-git-branch/repository/get_branch()/$branch', $branch, $this );

		$this->branch = $branch;

		return $this->branch;
	}

	/**
	 * Check if head reference is a branch.
	 *
	 * If external file in use, always return true.
	 *
	 * @uses $this->set_head_ref()
	 *
	 * @return bool
	 */
	public function is_branch() : bool {
		if ( empty( $this->head_ref ) ) {
			$this->set_head_ref();
		}

		if ( ! empty( $this->external_file ) ) {
			return true;
		}

		return false !== stripos( $this->head_ref, self::HEAD_PREFIX );
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
	public function get_github_url() : string {
		$github_repo = '';

		if ( $this->is_primary && defined( 'WHATGITBRANCH_PRIMARY_GITHUB_REPO' ) ) {
			$github_repo = constant( 'WHATGITBRANCH_PRIMARY_GITHUB_REPO' );
		}

		/**
		 * Set GitHub URL for repository.
		 *
		 * @param string $github_repo
		 * @param string $directory_path
		 */
		$github_repo = apply_filters( 'what-git-branch/repository/get_github_url()/$github_repo', $github_repo, $this->path );

		if ( empty( $github_repo ) || ! is_string( $github_repo ) ) {
			return '';
		}

		return sprintf(
			'https://github.com/%s/tree/%s',
			sanitize_text_field( $github_repo ),
			sanitize_text_field( $this->get_head_ref() )
		);
	}

}