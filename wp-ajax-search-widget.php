<?php
/*
Plugin Name: WP Ajax Search Widget
Plugin URI: https://github.com/cftp/wp-ajax-search-widget
Description: Search your WordPress site using this inline ajax search widget
Version: 1.0
Author: Scott Evans (Code For The People)
Author URI: http://codeforthepeople.com
Text Domain: wpasw
Domain Path: /assets/languages/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright © 2013 Code for the People ltd

                _____________
               /      ____   \
         _____/       \   \   \
        /\    \        \___\   \
       /  \    \                \
      /   /    /          _______\
     /   /    /          \       /
    /   /    /            \     /
    \   \    \ _____    ___\   /
     \   \    /\    \  /       \
      \   \  /  \____\/    _____\
       \   \/        /    /    / \
        \           /____/    /___\
         \                        /
          \______________________/


This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

*/

/**
 * wpasw_widget
 *
 * Register the widget
 *
 * @return void
 */
function wpasw_widget() {
	register_widget('wpasw_widget');
}
add_action('widgets_init', 'wpasw_widget');

class wpasw_widget extends WP_Widget {

	function wpasw_widget() {
		$widget_ops = array( 'classname' => 'wpasw-widget', 'description' => __('Search form with AJAX results', 'wpasw') );
		$this->WP_Widget( 'wpasw-widget', __('AJAX Search', 'wpasw'), $widget_ops );

		add_action( 'init', array( $this, 'wpasw_register_script' ) ) ;

		// only load scripts when an instance is active
		if ( is_active_widget( false, false, $this->id_base ) && !is_admin())
			add_action( 'wp_footer', array( $this, 'wpasw_print_script' ) );

		add_action('wp_ajax_wpasw', array( $this, 'wpasw_ajax') );
		add_action('wp_ajax_nopriv_wpasw', array( $this, 'wpasw_ajax') );

	}

	function widget($args, $instance) {

		extract($args, EXTR_SKIP);

		$title = empty($instance['title']) ? '' : apply_filters('widget_title', $instance['title']);
		$username = empty($instance['username']) ? '' : $instance['username'];
		$limit = empty($instance['number']) ? 10 : $instance['number'];

		echo $before_widget;
		if (!empty($title)) { echo $before_title . $title . $after_title; };

			get_search_form( true );
			?><div class="wpasw-results"></div><?php

		echo $after_widget;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => __('Search', 'wpasw'), 'number' => 10 ) );
		$title = esc_attr($instance['title']);
		$number = absint($instance['number']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'wpasw'); ?>: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Results Limit', 'wpasw'); ?>:</label>
			<select id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" class="widefat">
				<option value="-1" <?php selected('-1', $number) ?>><?php _e('All', 'wpasw'); ?></option>
				<option value="5" <?php selected('5', $number) ?>><?php _e('5', 'wpasw'); ?></option>
				<option value="10" <?php selected('10', $number) ?>><?php _e('10', 'wpasw'); ?></option>
				<option value="15" <?php selected('15', $number) ?>><?php _e('15', 'wpasw'); ?></option>
				<option value="20" <?php selected('20', $number) ?>><?php _e('20', 'wpasw'); ?></option>
			</select>
		</p>
		<?php

	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = !absint($new_instance['number']) ? 9 : $new_instance['number'];
		return $instance;
	}

	/**
	 * wpasw_register_script
	 *
	 * Register the JS for ajax request
	 *
	 * @return void
	 */
	function wpasw_register_script() {
		wp_register_script( 'wpasw', plugins_url('/assets/js/wpasw.js', __FILE__), array('jquery'), '1.0', true);

		wp_localize_script('wpasw','wpasw', array(
			'ajax_url' => add_query_arg(array('action' => 'wpasw','_wpnonce' => wp_create_nonce( 'wpasw' )), untrailingslashit(admin_url('admin-ajax.php'))),
		));
	}

	/**
	 * wpasw_print_script
	 *
	 * Output JS only when widget in use on page
	 *
	 * @return void
	 */
	function wpasw_print_script() {
		wp_print_scripts( 'wpasw' );
	}

	/**
	 * ajax
	 *
	 * Handle the search request
	 *
	 * @return string
	 */
	function wpasw_ajax() {

		// verify the nonce
		if (wp_verify_nonce($_REQUEST['_wpnonce'], 'wpasw')) {

			// clean up the query
			$s = trim(stripslashes($_POST['s']));

			// cancel if no search term is set
			if ( !$s ) die();

			// get the settings for this widget instance
			$instance = $this->get_settings();
			if ( array_key_exists( $this->number, $instance ) ) {
				$instance = $instance[$this->number];
			}

			// slowly
			sleep(1);

			// set the query limit
			$limit = empty($instance['number']) ? 10: $instance['number'];

			$query_args = array('s' => $s, 'post_status' => 'publish', 'posts_per_page' => $limit );

			$search = new WP_Query($query_args);

			if ( $search->have_posts() ) :

				?><ul class="wpasw-result-list"><?php
				while ( $search->have_posts() ) : $search->the_post();

					if ( locate_template('parts/widget-ajax-search-result.php') != '' ) {
						get_template_part( 'parts/widget-ajax-search-result' );
					} else {
						?>
						<li <?php post_class(); ?>>
							<h5 class="entry-title"><a href="<?php the_permalink() ?>" rel="bookmark" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h5>
							<div class="entry-date"><time class="published" datetime="<?php the_time('Y-m-d\TH:i:s') ?>"><?php the_date(); ?></time></div>
						</li>
						<?php
					}
				endwhile;
				?></ul><?php

			else:

				// no results
				if ( locate_template('parts/widget-ajax-search-fail.php') != '' ) {
					get_template_part( 'parts/widget-ajax-search-fail' );
				} else {
					?><div class="alert alert-info"><?php _e('No results found.', 'wpasw'); ?></div><?php
				}

			endif;

			wp_reset_postdata();
		}

		die();
	}
}

?>