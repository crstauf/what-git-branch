<?php
/*
Plugin Name: What Git Repo?
Plugin URI:
Description:
Version: 0.0.1
Author: Caleb Stauffer
Author URI: http://develop.calebstauffer.com
*/

new cssllc_what_git_repo;
add_action('wp_ajax_check_git_repo',array('cssllc_what_git_repo','ajax'));

class cssllc_what_git_repo {

	public static $is_repo = false;
	public static $repo = false;
	public static $commit = false;

	function __construct() {
		if (
			file_exists(ABSPATH . '.git') &&
			is_dir(ABSPATH . '.git') &&
			file_exists(ABSPATH . '.git/HEAD')
		)
			self::$is_repo = true;

		add_action('admin_bar_menu',array(__CLASS__,'bar'),99999999999999);
		add_action('admin_footer',	array(__CLASS__,'heartbeat_js'));
		add_action('wp_footer',		array(__CLASS__,'heartbeat_js'));
	}

	public static function bar($bar) {
		wp_enqueue_script('heartbeat');
		$args = array(
			'id' => 'what-git-repo',
			'title' => '<span class="code" style="display: inline-block; background-image: url(' . plugin_dir_url(__FILE__) . 'git.png); background-size: auto 50%; background-repeat: no-repeat; background-position: 7px center; background-color: #32373c; padding: 0 7px 0 27px; font-family: Consolas,Monaco,monospace;">' . self::get_repo() . '</span>',
			'href' => '#',
			'parent' => false,
		);
		$bar->add_node($args);
	}

	public static function get_repo() {
		if (!self::$is_repo) return false;
		if (false !== ($file = file_get_contents(ABSPATH . '.git/HEAD'))) {
			$pos = strripos($file,'/');
			return self::$repo = trim(substr($file,($pos + 1)));
		}
		return false;
	}

	public static function get_commit() {
		if (!self::$is_repo || false === self::$repo) return false;
		if (file_exists(ABSPATH . '.git/refs/heads/' . self::get_repo()))
			return self::$commit = trim(file_get_contents(ABSPATH . '.git/refs/heads/' . self::$repo));
		return 'UNKNOWN';
	}

	public static function heartbeat_js() {
		?>

		<script>

			(function($) {

				$(document).on('heartbeat-send',function(e,data) {
					if ("undefined" !== typeof HBMonitor_time)
						HBMonitor_time("Checking Git repo");
					$.post("<?php echo admin_url('admin-ajax.php') ?>",{action: 'check_git_repo'},function(response) {
						if ("undefined" !== typeof HBMonitor_time)
							HBMonitor_time("Git repo: " + response);
						$("#wp-admin-bar-what-git-repo > a > span").html(response);
					});
				});

			}(jQuery));

		</script>

		<?php
	}

	public static function ajax() {
		wp_die(self::get_repo());
	}

}
