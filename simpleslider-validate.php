<?php

function sss_settings_validate( $inp ) {
	$fields = array_keys( sss_settings_defaults(NULL, true) );
	$safe_inp = array();
	foreach( $fields as $field )
		$safe_inp[ $field ] = call_user_func( 'sss_settings_' . $field . 
			'_val', $inp[ $field ] );	
	return $safe_inp;
}

function sss_settings_defaults( $field, $return_all = false ){
	$defs = array('size' => 'medium',
					'transition_speed' => 400,
					'link_click' => 0,
					'link_target' => 'direct', 
					'show_counter' => 1, 
					'cycle_version' => 'lite',
					'transition' => 'fade'	
	);
	if( $return_all )
		return $defs;
	else
		return $defs[ $field ];
}

function sss_settings_transition_val( $inp ){
	// Validity of transition depends on the value of cycle_type
	// (if cycle_type == 'lite', the only valid value for 
	/// transition is 'fade'), so we look it up.
	$opts = get_option( 'sss_settings' );
	$cycle_type = sss_settings_defaults( 'cycle_type' );
		
	if( $opts and isset( $opts[ 'cycle_type' ] ) )
		$cycle_type = $opts[ 'cycle_type' ];
	
	if( 'lite' == $cycle_type )
		return 'fade';
	elseif( ctype_alpha( $inp ) )
		return $inp;
	else 
		return sss_settings_defaults( 'transition' );
}

function sss_settings_size_val( $inp ){
	return validate_in_list( $inp, 'size', get_intermediate_image_sizes() );
}

function sss_settings_cycle_version_val( $inp ){
	return validate_in_list( $inp, 'cycle_version', array( 'lite', 'all' ) );
}

function sss_settings_link_target_val( $inp ){
	return validate_in_list( $inp, 'link_target', 
		array( 'direct', 'attach' ) );
}

function sss_settings_transition_speed_val( $inp ){
	$safe_inp = ( int ) $inp;
	if( $safe_inp < 10 or $safe_inp > 1000 )
		return sss_settings_defaults( 'transition_speed' );
	else 
		return $safe_inp;
}

function sss_settings_link_click_val( $inp ){
	$safe_inp = ( int ) $inp;
	if( $safe_inp > 1 or $safe_inp < 0)
		return sss_settings_defaults( 'link_click' );
	else
		return $safe_inp;
}

function sss_settings_show_counter_val( $inp ){
	$safe_inp = ( int ) $inp;
	if( $safe_inp > 1 or $safe_inp < 0)
		return sss_settings_defaults( 'show_counter' );
	else
		return $safe_inp;
}

function validate_in_list( $inp, $field, $options ){
	if( in_array( $inp, $options, true ) )
		return $inp;
	else 
		return sss_settings_defaults( $field ); 
}

function validate_range( $inp, $field, $minval, $maxval ){
	$safe_inp = ( int ) $inp;
	if( $safe_inp < $minval or $safe_inp > $maxval )
		return sss_settings_defaults( $field );
	else 
		return $safe_inp;
}

?>