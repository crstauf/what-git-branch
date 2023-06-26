<?php declare( strict_types=1 );

namespace What_Git_Branch;

class Repository {

	public const EXTERNAL_FILE = '.what-git-branch';
	public const HEAD_PREFIX   = 'ref: refs/heads/';

	protected $name;
	protected $path;
	protected $external_file;
	protected $head_ref;
	protected $branch;
	protected $is_primary = false;

	public function __construct( $path ) {
		$this->path = $path;
		$this->name = apply_filters( 'what-git-branch/repository/name', basename( $path ) );
	}

	public function __get( $key ) {
		return $this->$key;
	}

	public function key() {
		return wp_hash( $this->path );
	}

	public function set_primary() {
		$this->is_primary = true;
	}

	public function set_head_ref() : void {
		$this->head_ref = null;
		$this->branch   = null;

		$this->set_head_ref_from_external();

		if ( ! empty( $this->head_ref ) ) {
			return;
		}

		$this->set_head_ref_from_git();
	}

	protected function set_head_ref_from_external() : void {
		$path = trailingslashit( $this->path ) . self::EXTERNAL_FILE;

		if ( ! file_exists( $path ) ) {
			return;
		}

		$this->external_file = $path;

		$external_file = file_get_contents( $path );
		$external_file = sanitize_text_field( $external_file );

		if ( empty( $external_file ) ) {
			return;
		}

		$this->head_ref = $external_file;
	}

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
	 * @uses $this->is_branch()
	 * @uses $this->get_branch()
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

		return apply_filters( 'what-git-branch/get_head_ref()/commit', $head_ref, $this->head_ref );
	}

	/**
	 * Get branch name.
	 *
	 * @uses $this->is_branch()
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
		$branch = apply_filters( 'what-git-branch/get_branch()/$branch', $branch, $this );

		$this->branch = $branch;

		return $this->branch;
	}

	/**
	 * Check if head reference is a branch.
	 *
	 * If external file in use, always return true.
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
	 * @return bool
	 */
	public function is_commit() : bool {
		return ! $this->is_branch();
	}

	/**
	 * Get URL to head reference on GitHub.
	 *
	 * @uses $this->get_head_ref()
	 * @return string
	 */
	public function get_github_url() : string {
		$github_repo = '';

		if ( $this->is_primary && defined( 'WHATGITBRANCH_PRIMARY_GITHUB_REPO' ) ) {
			$github_repo = constant( 'WHATGITBRANCH_PRIMARY_GITHUB_REPO' );
		}

		$github_repo = apply_filters( 'what-git-branch/get_github_url()/$github_repo', $github_repo, $this->path );

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