<?php 
/*
Plugin Name: Simple Slider
Plugin URI:	http://daniellanger.com/simpleslider
Description: A basic image slider. Attach images to a post, and then use a shortcode to display them. Their thumbnails (at a configurable size) will be shown in a jQuery Cycle slideshow, and each will be a link to the full-sized version.
Version: 1.0
Author: Daniel Langer
Author URI: http://www.daniellanger.com
License: BSD
*/

define("SIMPLESLIDER_VERSION", "1.0");

add_shortcode( 'list_images', 'list_images_handler' );
add_action( 'wp_enqueue_scripts', 'load_js' );

function load_js() {
	// Don't want any of this if we're on an admin page
	if ( is_admin() ) {
		return;
	}
	
	// Load jQuery Cycle Lite locally, and jQuery from the Google CDN
	wp_deregister_script( 'jquery' );
	wp_register_script( 'jquery', 'https://ajax.googleapis.com/ajax/libs/' . 
		'jquery/1.6.0/jquery.min.js', false, '1.6.0', false );
	wp_register_script( 'mini_cycle', plugins_url( 
		'jquery.cycle.lite.1.1.min.js', __FILE__ ), array( 'jquery' ), 
		'1.1', false );
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'mini_cycle' );
}

function list_images_handler( $atts ) {
	extract( shortcode_atts( array( 
		'size' => 'medium',
		'link_click' => true,
		'link_to' => 'direct'
		), $atts ) );
		
	// If the size specified in the argument to the shortcode isn't one 
	// WordPress recognizes, default to the 'medium' size.	
	if ( ! in_array($size, get_intermediate_image_sizes(), true))
		$size = 'medium';	
		
	$images =& get_children( 'post_type=attachment&post_mime_type=' .
								'image&post_parent=' . get_the_ID() ); 	
	
	// Don't load anything or start processing if there are no 
	// images to display.
	if( empty($images) ) {
		return '';
	}
	
	// Figure out the maximum size of the images being displayed so we can 
	// set the smallest possible fixed-size container to Cycle in.
	$thumb_w = $thumb_h = 0;
	foreach ( $images as $image_id => $image_data ) {
		$info = wp_get_attachment_metadata( $image_id );
		$thumb_w = max ( $thumb_w, $info[ 'sizes' ][ $size ][ 'width' ] );
		$thumb_h = max ( $thumb_h, $info[ 'sizes' ][ $size ][ 'height' ] );
	}
	
	$slider_show_id = 'simpleslider_show_' . get_the_ID();
	
	// JS to set up the Cycle for this instance
	$resp = '<script>' .
				'$(document).ready(function(){$(\'#' . $slider_show_id . 
				'\').cycle({timeout: 0, speed: 500, next: \'#' . 
				$slider_show_id . '_next\', prev: \'#' . $slider_show_id .
				'_prev\'}); ' .
				'$(\'#' . $slider_show_id. ' > a > img\').' .
				'removeAttr(\'title\'); ' .
				'$(\'#' . $slider_show_id . 
				' > div\').css(\'display\', \'block\');});';
	$resp .= '</script>' . "\n";
	
	$resp .= '<div class="simpleslider_show" id="' . $slider_show_id . '" ' . 
				'style="height: ' . $thumb_h . 'px; width: ' .
				$thumb_w . 'px; margin: 10px auto;">' . "\n";
	
	$first = true;
	foreach ( $images as $image_id => $image_data ) {
		// To prevent flash of unstyled content, we set all slideshow
		// elements aside from the first one to be hidden. JS
		// in document.onready then sets them back to block once
		// Cycle has done its setup and set the opacities.
		$display_style = $first ? 'block' : 'none'; 
		$first = false;
		
		$resp .= '<div style="display: ' . $display_style . '">';
		
		if ( true == $link_click){	
			if ('direct' == $link_to ) {
				$resp .= '<a href="' . wp_get_attachment_url( $image_id ) . 
							'" target="_new">' .
							wp_get_attachment_image( $image_id, $size ) . '</a>';
			} else {
				$resp .= '<a href="' . get_attachment_link( $image_id ) . 
							'" target="_new">' .
							wp_get_attachment_image( $image_id, $size ) . '</a>';
			}
		} else { 
			$resp .= wp_get_attachment_image( $image_id, $size );
		}
					
		$resp .= "</div>\n";
	}	
	
	$resp .= "</div>\n";
	
	// @TODO - style these controls a bit

	// @todo - admin control panel
	
	// Controls
	$resp .= '<div style="width: ' . $thumb_w . 'px; margin: 10px auto; ' .
				'text-align: center">';
	$resp .= '<a href="#" id="' . $slider_show_id . '_prev">prev</a> &nbsp;' .
				'&nbsp; <a href="#" id="' . $slider_show_id . 
				'_next">next</a>';
	$resp .= "</div>\n";

	return $resp;
}
?>