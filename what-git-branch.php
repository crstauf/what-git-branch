<?php
/*
Plugin Name: What Git Branch?
Plugin URI:
Description:
Version: 0.0.1
Author: Caleb Stauffer
Author URI: http://develop.calebstauffer.com
*/

new cssllc_what_git_branch;
add_action('wp_ajax_check_git_branch',array('cssllc_what_git_branch','ajax'));

class cssllc_what_git_branch {

	public static $is_repo = false; // false or key of $paths where git repo was found
	public static $branch = false;
	public static $commit = false;

	private static $paths = array();

	function __construct() {
		self::$paths = array(
			ABSPATH . '.git',
			ABSPATH . 'wp-content/.git',
			plugin_dir_path(__FILE__) . '.git', // primarily for testing purposes
		);
		
		foreach (self::$paths as $i => $path)
			if (
				file_exists($path) &&
				is_dir($path) &&
				file_exists($path . '/HEAD')
			)
				self::$is_repo = $i;

		add_action('admin_bar_menu',array(__CLASS__,'bar'),99999999999999);
		add_action('admin_footer',	array(__CLASS__,'heartbeat_js'));
		add_action('wp_footer',		array(__CLASS__,'heartbeat_js'));
	}

	public static function bar($bar) {
		wp_enqueue_script('heartbeat');
		$args = array(
			'id' => 'what-git-branch',
			'title' => '<span class="code" style="display: inline-block; background-image: url(' . plugin_dir_url(__FILE__) . 'git.png); background-size: auto 50%; background-repeat: no-repeat; background-position: 7px center; background-color: #32373c; padding: 0 7px 0 27px; font-family: Consolas,Monaco,monospace;">' . self::get_branch() . '</span>',
			'href' => '#',
			'parent' => false,
		);
		$bar->add_node($args);
	}

	public static function get_branch() {
		if (false === self::$is_repo) return false;
		if (false !== ($file = file_get_contents(self::$paths[self::$is_repo] . '/HEAD'))) {
			$pos = strripos($file,'/');
			return self::$branch = trim(substr($file,($pos + 1)));
		}
		return false;
	}

	public static function get_commit() {
		if (false === self::$is_repo || false === self::$branch) return false;
		if (file_exists(self::$paths[self::$is_repo] . '/refs/heads/' . self::get_branch()))
			return self::$commit = trim(file_get_contents(self::$paths[self::$is_repo] . '/refs/heads/' . self::$branch));
		return 'UNKNOWN';
	}

	public static function heartbeat_js() {
		?>

		<script>

			(function($) {

				$(document).on('heartbeat-send',function(e,data) {
					if ("undefined" !== typeof HBMonitor_time)
						HBMonitor_time("Checking Git branch");
					$.post("<?php echo admin_url('admin-ajax.php') ?>",{action: 'check_git_branch'},function(response) {
						if ("undefined" !== typeof HBMonitor_time)
							HBMonitor_time("Git branch: " + response);
						$("#wp-admin-bar-what-git-branch > a > span").html(response);
					});
				});

			}(jQuery));

		</script>

		<?php
	}

	public static function ajax() {
		wp_die(self::get_branch());
	}

}
