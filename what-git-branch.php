<?php
/*
Plugin Name: What Git Branch?
Plugin URI:
Description:
Version: 0.0.2
Author: Caleb Stauffer
Author URI: http://develop.calebstauffer.com
*/

if (!defined('ABSPATH') || !function_exists('add_filter')) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

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

		add_action('init',array(__CLASS__,'action_init'));
	}

	public static function action_init() {
		if (!is_admin_bar_showing() || !current_user_can('manage_options')) return;

		add_action('wp_enqueue_scripts',			array(__CLASS__,'action_enqueue_scripts'));
		add_action('admin_enqueue_scripts',			array(__CLASS__,'action_enqueue_scripts'));
		add_action('admin_bar_menu',				array(__CLASS__,'bar'),99999999999999);
		add_action('admin_head-plugins.php',		array(__CLASS__,'action_admin_head_plugins'));
		add_filter('manage_plugins_columns',		array(__CLASS__,'filter_manage_plugins_columns'));
		add_action('manage_plugins_custom_column',	array(__CLASS__,'action_manage_plugins_custom_column'),10,3);
		add_action('admin_footer',					array(__CLASS__,'heartbeat_js'));
		add_action('wp_footer',						array(__CLASS__,'heartbeat_js'));
	}

	public static function action_enqueue_scripts() {
		wp_enqueue_script('heartbeat');
	}

	public static function bar($bar) {
		$args = array(
			'id' => 'what-git-branch',
			'title' => '<span class="code" style="display: inline-block; background-image: url(' . plugin_dir_url(__FILE__) . 'git.png); background-size: auto 50%; background-repeat: no-repeat; background-position: 7px center; background-color: #32373c; padding: 0 7px 0 27px; font-family: Consolas,Monaco,monospace;" title="' . esc_attr(str_replace('.git','',self::$paths[self::$is_repo])) . '">' . self::get_branch() . '</span>',
			'href' => '#',
			'parent' => false,
		);
		$bar->add_node($args);
	}

	public static function action_admin_head_plugins() {
		?>
		<style type="text/css">.column-git { width: 20%; }</style>
		<?php
	}

	public static function filter_manage_plugins_columns($columns) {
		return array_merge($columns,array('git' => 'Git Info'));
	}

	public static function action_manage_plugins_custom_column($column,$file,$data) {
		if ('git' !== $column) return false;
		$git_path = dirname(WP_PLUGIN_DIR . '/' . $file) . '/.git/';
		if (file_exists($git_path) && is_dir($git_path)) {
			if (false !== ($file = file_get_contents($git_path . 'HEAD'))) {
				$pos = strripos($file,'/');
				echo 'Branch <span class="code">' . esc_attr(trim(substr($file,($pos + 1)))) . '</span>';
			} else
				echo '&mdash;';
		} else
			echo '&mdash;';
	}

	public static function get_repo() {
		if (false === self::$is_repo) return false;
		return json_encode(array(
			'branch' => self::get_branch(),
			'path' => esc_attr(str_replace(ABSPATH,'/',str_replace('.git','',self::$paths[self::$is_repo]))),
		));
	}

	public static function get_branch() {
		if (false === self::$is_repo) return false;
		if (false !== ($file = file_get_contents(self::$paths[self::$is_repo] . '/HEAD'))) {
			$pos = strripos($file,'/');
			return self::$branch = esc_attr(trim(substr($file,($pos + 1))));
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
					$("#wp-admin-bar-what-git-branch > a > span").html('?');
					console.time('check-git-branch');
					$.post("<?php echo admin_url('admin-ajax.php') ?>",{action: 'check_git_branch'},function(response) {
						var data = $.parseJSON(response);
						if ("undefined" !== typeof HBMonitor)
							HBMonitor("Git branch: " + data.branch,'','Repo: ' + data.path,'','','Check git branch');
						$("#wp-admin-bar-what-git-branch > a > span").html(data.branch);
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
