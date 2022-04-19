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

