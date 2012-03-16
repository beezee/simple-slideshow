<?php 

/*
Plugin Name: Simple Slideshow
Plugin URI:	http://daniellanger.com/blog/simple-slideshow
Description: An easy-to use jQuery+Cycle slideshow - just attach the images to your post using Wordpress' built-in media uploader, and add <code>[simple_slideshow]</code> to the body of your post where you'd like the slideshow to be.
Version: 1.2
Author: Daniel Langer
Author URI: http://www.daniellanger.com
Text Domain: simple_slideshow
License: FreeBSD
*/

require_once 'simpleslider-validate.php';

register_activation_hook( __FILE__, 'sss_activation' );
register_uninstall_hook( __FILE__, 'sss_uninstall' ); 
add_shortcode( 'simple_slideshow', 'sss_handle_shortcode' );
add_action( 'init', 'sss_load_externals' );
//add option to create multiple slideshows per post
add_filter('attachment_fields_to_edit', 'sss_edit_attachment_slideshow_id', 10, 2);
add_filter('attachment_fields_to_save', 'sss_save_attachment_slideshow_id', 10, 2);


if( is_admin() ){
	require_once 'simpleslider-admin.php';
}

function sss_activation() {
	// When activating don't clobber any existing settings, but load 
	// defaults if none are present
	if( ! get_option( 'sss_settings') ) 
		update_option( 'sss_settings', sss_settings_defaults( NULL, true ) );	
}

function sss_uninstall() {
	delete_option( 'sss_settings' );
}

function sss_load_externals() {
	if ( is_admin() ) {

		wp_enqueue_script( 'jquery' );
		wp_register_script( 'jquery-ui-custom', plugins_url( 
			'jquery-ui-1.8.15.custom.min.js', __FILE__ ), array( 'jquery' ),
			'1.8.15', false );
		wp_enqueue_script( 'jquery-ui-custom' );
		wp_register_style( 'simpleslider_admin', plugins_url(
			'simpleslider-admin.css', __FILE__ ), false, 1.1);
		wp_enqueue_style( 'simpleslider_admin');
		return;
	}
	
	$defaults = get_option( 'sss_settings' );
	if( ! $defaults ) 
		$defaults = sss_settings_defaults(NULL, true);
		
	if( $defaults[ 'cycle_version' ] == 'lite' ) 
		wp_register_script( 'cycle', plugins_url( 
		'jquery.cycle.lite.1.3.min.js', __FILE__ ), array( 'jquery' ), 
		'1.3', true );
	else 
		wp_register_script( 'cycle', plugins_url( 
		'jquery.cycle.all.2.88.min.js', __FILE__ ), array( 'jquery' ), 
		'2.88', true );
	
	wp_enqueue_script( 'cycle' );
	wp_register_script( 'simpleslider', plugins_url( 
		'simpleslider.js', __FILE__ ), array( 'jquery', 'cycle' ), 1.0, false);
	wp_register_style( 'simpleslider_css', plugins_url( 
		'simpleslider.css', __FILE__ ), false, 1.0);
	wp_enqueue_style( 'simpleslider_css' ); 
	wp_enqueue_script( 'simpleslider' );		
	wp_enqueue_script( 'jquery' );
	
	load_plugin_textdomain( 'simple_slideshow', false, basename( 
		dirname( __FILE__ ) ) );
}

function sss_get_slideshow_include_list($post_id, $gallery_id)
{
	global $wpdb;
	$attachments_meta = $wpdb->get_results("SELECT {$wpdb->prefix}posts.ID, {$wpdb->prefix}postmeta.meta_key, {$wpdb->prefix}postmeta.meta_value FROM {$wpdb->prefix}posts LEFT JOIN {$wpdb->prefix}postmeta on {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id WHERE post_type = 'attachment' AND post_parent = $post_id");
	$attachments = array();
	foreach ($attachments_meta as $attachment)
	{
		if (!isset($attachments[$attachment->ID])) $attachments[$attachment->ID] = array();
		$attachments[$attachment->ID][$attachment->meta_key] = $attachment->meta_value;
	}
	$include_list = array();
	foreach($attachments as $id => $attachment)
	{
		if ($gallery_id == 1)
		{
			if ($attachment['sss_slideshow_id'] == 1 or !isset($attachment['sss_slideshow_id'])) $include_list[] = $id;
		}
		else
		{
			if ($attachment['sss_slideshow_id'] == $gallery_id) $include_list[] = $id;
		}
	}
	return $include_list;
}


function sss_handle_shortcode( $attrs ) {
	$slideshow_id = ($attrs['slideshow_id']) ? $attrs['slideshow_id'] : 1;
	$include_list = sss_get_slideshow_include_list(get_the_ID(), $slideshow_id);
	$resp = '';
	$defaults = get_option( 'sss_settings' );
	if( ! $defaults ) 
		$defaults = sss_settings_defaults(NULL, true);

	$stored_cycle_version = $defaults[ 'cycle_version' ];

	// Stops a possible injection of cycle_version as shortcode argument
	unset( $defaults[ 'cycle_version' ] );
	extract( sss_settings_validate( shortcode_atts( $defaults, 
				$attrs), true ) );
	// Will always be 'lite' (so wrong) - use $stored_cycle_version instead
	unset( $cycle_version );
	
				
	$all_images =& get_children( 'post_type=attachment&post_mime_type=' .
								'image&post_parent=' . get_the_ID() .
								'&orderby=menu_order&order=ASC' ); 	
	foreach($all_images as $id => $image)
	{
		if (in_array($id, $include_list)) $images[$id] = $image;
	}
	
	// Don't load anything or start processing if there are no 
	// images to display.
	if( empty($images) ) 
		return '';
		
	if( ! empty( $attrs[ 'exclude' ] ) ) {
		$exclude = explode(',', $attrs[ 'exclude' ]);
		foreach ( $exclude as $image_id ) {
			unset( $images[ trim( $image_id ) ] );
		}
	}
	
	// Figure out the maximum size of the images being displayed so we can 
	// set the smallest possible fixed-size container to cycle in.
	$thumb_w = $thumb_h = 0;
	$captions = array();
	foreach ( $images as $image_id => $image_data ) {
		$image_props[ $image_id ] = wp_get_attachment_image_src( $image_id, $size );
		$thumb_w = max ( $thumb_w, $image_props[ $image_id ][ 1 ] );
		$thumb_h = max ( $thumb_h, $image_props[ $image_id ][ 2 ] );
		$captions[ $image_id ] = $image_data->post_excerpt;
	}

	$slider_show_id = 'simpleslider_show_' . get_the_ID();
	$slider_show_number = get_the_ID();
	
	$resp .= "<div class=\"simpleslider_show\" id=\"{$slider_show_id}\" " . 
				"style=\"height: {$thumb_h}px; width: {$thumb_w}px;\">\n";
	
	$first = true;
	foreach ( $images as $image_id => $image_data ) {
		$opacity = $first ? '1' : '0'; 
		$first = false;

		$image_prop = $image_props[ $image_id ];
		$image_tag = "<img src=\"${image_prop[ 0 ]}\" " .
						"width=\"${image_prop[ 1 ]}\" " .
						"height=\"${image_prop[ 2 ]}\" " .
						"alt=\"${captions[ $image_id ]}\">"; 
		
		$resp .= "<div style=\"opacity: {$opacity}\">";
		if ( 1 == $link_click ){	
			$link = ( 'direct' == $link_target ) ? 
							wp_get_attachment_url( $image_id ) : 
							get_attachment_link( $image_id );
							
			$resp .= '<a href="' . $link . '" class="simpleslider_image_link " .
						"simpleslider_link" target="_new">' . $image_tag . 
						'</a>';
		} else { 
			$resp .= $image_tag;
		}				
		$resp .= "</div>\n";
	}	
	
	$resp .= "</div>\n";
		
	if ( true == $show_counter ) 
		$image_counter = '<div class="simpleslider_counter"><span ' .
							"id=\"{$slider_show_id}" .
							'_count">1</span>/' . count($images) . '</div>';
	else
		$image_counter = '';	
	
	$resp .= '<div class="simpleslider_controls">';
	
	if ( true == $show_controls )
		$resp .= "<a href=\"#\" id=\"{$slider_show_id}_prev\" " . 
					"title=\"Previous Image\" class=\"simpleslider_link prev\">◄ " . 
					__( 'Prev.', 'simple_slideshow' ) . "</a> " .
					"&nbsp;"; 
				
	$resp .= ${image_counter};
				
	if ( true == $show_controls)
		$resp .= "&nbsp; <a href=\"#\" id=\"{$slider_show_id}_next\" " .
					"title=\"Next Image\" class=\"simpleslider_link next\">" .
					__( 'Next', 'simple_slideshow' ) . " ►</a>";
					
	$resp .= "</div>\n";
	
	
	// JavaScript
	$prefs = array('slides'=>count($images), 'transition_speed'=>$transition_speed);
	if( 'all' == $stored_cycle_version ) 
		$prefs['fx'] = $transition;
	if( 1 == $auto_advance ) 
		$prefs['auto_advance_speed'] = $auto_advance_speed;
	
	$resp .= "\n".'<script type="text/javascript">simpleslider_prefs['.$slider_show_number.'] = '.json_encode($prefs).'</script>';
	
	return $resp;
}

function sss_edit_attachment_slideshow_id($form_fields, $post)
{
	if(is_admin) {
		$value = get_post_meta($post->ID, 'sss_slideshow_id');
		$form_fields['sss_slideshow_id'] = array(
			'label' => 'Slideshow ID',
			'input' => 'text',
			'value' => get_post_meta($post->ID, 'sss_slideshow_id', true),
			'helps' => 'Set this if you want more than one slideshow per page. Defaults to 1 if left blank.'
		);
	}
	
	return $form_fields;
}

function sss_save_attachment_slideshow_id($post, $attachment)
{
	$slideshow_id = (isset($attachment['sss_slideshow_id'])) ? $attachment['sss_slideshow_id'] : 1;
	update_post_meta($post['ID'], 'sss_slideshow_id', $slideshow_id);
	return $post;
}
?>