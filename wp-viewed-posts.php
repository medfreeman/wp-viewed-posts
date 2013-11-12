<?php
/*
Plugin Name: Viewed Posts
Plugin URI: http://www.superposition.info
Description: Stores viewed posts in a cookie, supports getting list of viewed posts by type, via function get_viewed_posts, automatically cleared for specific type when all posts are seen.
Version: 1.0
Author: Mehdi Lahlou
Author URI: http://www.superposition.info
Author Email: mehdi.lahlou@free.fr
License: GPLv2

  Copyright 2013 Mehdi Lahlou (mehdi.lahlou@free.fr)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

define(VIEWED_POSTS_COOKIE, 'viewed_posts');

// git plugin updater
include_once('updater.php');

class ViewedPosts {
	
	private $viewed_posts_array;
	private $all_viewed;
	 
	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/
	
	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {
		$this->viewed_posts_array = array();
		$this->all_viewed = array();
		
		add_action( 'init', array($this, 'get_viewed_posts_cookie'), 1);
		add_action( 'wp', array($this, 'update_viewed_posts'), 97);
		add_filter( 'wpvp_clear_viewed_posts', array($this, 'clear_viewed_posts_by_type'), 10, 2);
		add_action( 'wp', array($this, 'clear_viewed_posts'), 98);
		add_action( 'wp', array($this, 'set_viewed_posts_cookie'), 99);
		
		if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin
			$config = array(
				'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
				'proper_folder_name' => 'wp-viewed-posts', // this is the name of the folder your plugin lives in
				'api_url' => 'https://api.github.com/repos/medfreeman/wp-viewed-posts', // the github API url of your github repo
				'raw_url' => 'https://raw.github.com/medfreeman/wp-viewed-posts/master', // the github raw url of your github repo
				'github_url' => 'https://github.com/medfreeman/wp-viewed-posts', // the github url of your github repo
				'zip_url' => 'https://github.com/medfreeman/wp-viewed-posts/zipball/master', // the zip url of the github repo
				'sslverify' => true, // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
				'requires' => '3.5', // which version of WordPress does your plugin require?
				'tested' => '3.5.2', // which version of WordPress is your plugin tested up to?
				'readme' => 'README.md', // which file to use as the readme for the version number
				'access_token' => '' // Access private repositories by authorizing under Appearance > Github Updates when this example plugin is installed
			);
			new WP_GitHub_Updater($config);
		}
		
	} // end constructor
	
	/**
    * Get viewed posts
    */
    
    function get_viewed_posts_cookie() {
		if (isset($_COOKIE[VIEWED_POSTS_COOKIE])) {
			$viewed_posts = unserialize(preg_replace('!s:(\d+):"(.*?)";!e', "'s:'.strlen('$2').':\"$2\";'", stripslashes($_COOKIE[VIEWED_POSTS_COOKIE])));
			if(is_array($viewed_posts)) {
				$this->viewed_posts_array = $viewed_posts;
			} else {
				setcookie(VIEWED_POSTS_COOKIE, false, time()+31536000, COOKIEPATH, COOKIE_DOMAIN, false, false);
			}
		}
	}
	
	/**
    * Update viewed posts when on single post of type
    */
    
	function update_viewed_posts() {
		if (is_single()) {
			global $post;
			$post_type = get_post_type($post);
			if ($post && $post_type) {
				if (isset($this->viewed_posts_array[$post_type])) {
					if(!in_array($post->ID, $this->viewed_posts_array[$post_type])) {
						array_push($this->viewed_posts_array[$post_type], $post->ID);
					}
				} else {
					$this->viewed_posts_array[$post_type] = array($post->ID);
				}
			}
		}
	}
	
	function clear_viewed_posts() {
		$this->viewed_posts_array = apply_filters('wpvp_clear_viewed_posts', $this->viewed_posts_array);
	}
	
	/**
    * Clear viewed posts when all have been seen and we're in archive page
    */
    
    function clear_viewed_posts_by_type($viewed_posts_array) {
		foreach($viewed_posts_array as $post_type => $posts) {
			if((is_home() || is_post_type_archive($post_type)) && sizeof($posts) == wp_count_posts($post_type)->publish) {
				$this->all_viewed[$post_type] = true;
				unset($viewed_posts_array[$post_type]);
			}
		}
		return $viewed_posts_array;
	}
	
	/**
    * Set a cookie with viewed posts
    */
    
	function set_viewed_posts_cookie() {
		$data = serialize($this->viewed_posts_array);
		setcookie(VIEWED_POSTS_COOKIE, $data, time()+43200, COOKIEPATH, COOKIE_DOMAIN, false, false);
	}
	
	/**
    * Get viewed posts of specific type 
    */
    
	function get_viewed_posts($post_type) {
		if($post_type && isset($this->viewed_posts_array[$post_type])) {
			return $this->viewed_posts_array[$post_type];
		}
		return array();
	}
	
	/**
    * Has viewed all posts of specific type
    */
    
	function has_viewed_all_posts($post_type) {
		if($post_type && isset($this->all_viewed[$post_type])) {
			return $this->all_viewed[$post_type];
		}
		return false;
	}
	
} // end class

global $viewed_posts;
$viewed_posts = new ViewedPosts();

if (!function_exists('get_viewed_posts')) {
	function get_viewed_posts($post_type = false) {
		global $viewed_posts;
		return $viewed_posts->get_viewed_posts($post_type);
	} 
}

if (!function_exists('has_viewed_all_posts')) {
	function has_viewed_all_posts($post_type = false) {
		global $viewed_posts;
		return $viewed_posts->has_viewed_all_posts($post_type);
	} 
}
