<?php
// Filter default player controller array
add_filter( 'sb_player_controllers', 'colorbox_filter_controllers', 12 );
function colorbox_filter_controllers( $defaults ) {
	$defaults['colorbox'] = array( 
		'nicename'	=> 'Colorbox',
		'website'		=> 'http://colorpowered.com/colorbox/',
		'description'	=> __( 'The title given to the player will be inserted into the post or page as a link that, when clicked, will spawn an image slideshow in a lightbox window.', 'startbox' ),
		'mime_types' 	=> array( 'image/jpeg', 'image/png', 'image/gif' ),
		'shortcode_cb'	=> 'sb_player_colorbox_shortcode',
		'options'		=> array(
							'section0'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Pause Timer', 'startbox' ),
												'desc'		=> __( 'How long, in milliseconds, to pause on an item before transitioning.', 'startbox' ) ),
							'pause'			=> array( 
												'type'		=> 'input',
												'size'		=> 4,
												'units'		=> __( 'milliseconds', 'startbox' ),
												'default'		=> 3000,
												'validate'	=> 'numbers' ),
							'section1'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'General Options', 'startbox' ) ),
							'autoplay'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Auto Play', 'startbox' ),
												'default'	=> 1 ),
							'overlayclose'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Overlay Close', 'startbox' ),
												'hint'		=> __( 'If checked, the lightbox will close when the overlay is clicked.' ),
												'default'		=> 1 ),
							'section2'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Style Options', 'startbox' ) ),
							'theme'			=> array( 
												'type'		=> 'select',
												'label'		=> __( 'Theme', 'startbox' ),
												'options'		=> array(
																'minimalblack'		=> __( 'Minimal Black', 'startbox' ),
																'minimalwhite'		=> __( 'Minimal White', 'startbox' ),
																'fullwhite'		=> __( 'Full White', 'startbox' ),
																'white'			=> __( 'White', 'startbox' ),
																'gray'			=> __( 'Gray', 'startbox' ) ),
												'default'		=> 'minimalblack' ),
							'transition'		=> array( 
												'type'		=> 'select',
												'label'		=> __( 'Transition Effect', 'startbox' ),
												'options'		=> array(
																'none'	=> 'None',
																'fade'	=> 'Fade' ),
												'default'		=> 'fade' ),
							'opacity'			=> array( 
												'type'		=> 'input',
												'label'		=> __( 'Overlay Opacity', 'startbox' ),
												'size'		=> 4,
												'units'		=> __( '%', 'startbox' ),
												'default'		=> 85,
												'validate'	=> 'numbers' ) ) );
		
	return $defaults; // don't forget to return after filtering
}

// Cycle Callback
function sb_player_colorbox_shortcode( $uid, $title, $dimensions, $options, $slides ) {
	global $wp_styles;
	
	// lazy load
	wp_enqueue_script( 'colorbox' );
	// colorbox doesn't support multiple embeds with different themes on one page... all embeds will inherit the first theme
	if (!strstr( implode( ' ', array_merge( $wp_styles->done, $wp_styles->queue ) ), 'colorbox-' ))
		wp_enqueue_style( 'colorbox-' . $options['theme'] );
	
	$result = '';
	
	foreach( $slides as $num => $slide ) {		
		$post_title = ($num == 0 ? $title : $slide->post_content);
		
		$post_image = sb_post_image( $dimensions['width'], $dimensions['height'], NULL, 1, array( 
			'image_id' 	=> $slide->ID, 
			'crop'		=> $options['cropfrom'], 
			'title' 		=> $slide->post_content,
			'alt' 		=> $slide->post_content,
			'echo' 		=> false ) );
		
		// hackish, need timthumb function
		$post_image = str_replace( '<img', '<a rel="slider-' . $uid . '"' . ($num > 0 ? ' style="display:none;"' : ''), $post_image );
		$post_image = str_replace(  'src="', 'href="', $post_image );
		$post_image = str_replace( ' />', '>' . $post_title . '</a>', $post_image );
		
		$result .= $post_image;
	}
	
	// re-init colorbox if it was initialized before styles were appended to head
	sb_player_footerjs( 'cb_init',
		'var elems = $("#cboxOverlay, #colorbox");
		if( elems.length ) {
			elems.remove();
			$.colorbox.init();
		}' );
	
	sb_player_footerjs( 'id_' . $uid,
		'$("a[rel=\'slider-' . $uid . '\']").colorbox({
			transition:"' . $options['transition'] . '", 
			innerWidth:"' . $dimensions['width'] . 'px", 
			innerHeight:"' . $dimensions['height'] . 'px",
			opacity:0.' . $options['opacity'] . ',
			photo:true,
			slideshow:true,
			slideshowSpeed:' . $options['pause'] . ',
			slideshowAuto:' . sb_player_tf( $options['autoplay'] ) . ',
			overlayClose:' . sb_player_tf( $options['overlayclose'] ) . '
		});' );
	
	return $result; // finally, output the resulting code
}
?>