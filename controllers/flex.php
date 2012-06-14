<?php
// Filter default player controller array
add_filter( 'sb_player_controllers', 'flex_filter_controllers', 11 );
function flex_filter_controllers( $defaults ) {
	$defaults['flex'] = array( 
		'nicename'	=> 'Flex Slider',
		'website'		=> 'http://flex.madebymufffin.com/',
		'description'	=> __( 'A responsive image slider with simple transitions and mobile touch gesture support', 'startbox' ),
		'mime_types' 	=> array( 'image/jpeg', 'image/png', 'image/gif' ),
		'shortcode_cb'	=> 'sb_player_flex_shortcode',
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
												'title'		=> __( 'Show', 'startbox' ) ),
							'arrows'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Show Overlay Arrows', 'startbox' ),
												'default'		=> 1 ),
							'arrhide'			=> array( 
												'type'		=> 'checkbox',
												'sub'		=> 1,
												'label'		=> __( 'only on hover', 'startbox' ) ),
							'navigation'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Show Bottom Navigation', 'startbox' ),
												'default'		=> 1 ),
							'keyboard'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Allow Keyboard Navigation', 'startbox' ),
												'default'		=> 1 ),
							'touchswipe'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Enable Mobile Touch Gestures', 'startbox' ),
												'default'		=> 1 ),
							'section2'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Image Cropping', 'startbox' ) ),
							'align'			=> array( 
												'type'		=> 'select',
												'label'		=> __( 'Crop From', 'startbox' ),
												'options' 	=> array(
																't'	=> __( 'Top', 'startbox' ),
																'tl'	=> __( 'Top Left', 'startbox' ),
																'tr'	=> __( 'Top Right', 'startbox' ),
																'c'	=> __( 'Center', 'startbox' ),
																'l'	=> __( 'Left', 'startbox' ),
																'r'	=> __( 'Right', 'startbox' ),
																'b'	=> __( 'Bottom', 'startbox' ),
																'bl'	=> __( 'Bottom Left', 'startbox' ),
																'br'	=> __( 'Bottom Right', 'startbox' ) ),
												'default'		=> 'c' ),
							'section3'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Play Options', 'startbox' ) ),
							'autoplay'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Auto Play', 'startbox' ),
												'default'		=> 1 ),
							'hover'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Pause On Hover', 'startbox' ),
												'default'		=> 1 ),
							'section4'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Transition Effects', 'startbox' ),
												'desc'		=> __( 'If no transition effect is checked, all effects will be used at random.', 'startbox' ) ),
							'slide'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Slide', 'startbox' ),
												'group' 		=> 'transition' ),
							'fade'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Fade', 'startbox' ),
												'group'		=> 'transition',
												'default'		=> 1 ),
							'speed'			=> array( 
												'type'		=> 'input',
												'label'		=> __( 'Transition Speed', 'startbox' ),
												'size'		=> 4,
												'units'		=> __( 'milliseconds', 'startbox' ),
												'default'		=> 500,
												'validate'	=> 'numbers' ) ) );
	
	return $defaults; // don't forget to return after filtering
}

// Nivo Callback
function sb_player_flex_shortcode( $uid, $title, $dimensions, $options, $slides ) {
	// lazy load
	wp_enqueue_style( 'flexcss', plugins_url( 'flex/flexslider.css', __FILE__ ) );
	wp_enqueue_style( 'flexcustomcss', plugins_url( 'flex/custom.css', __FILE__ ) );
	wp_enqueue_script( 'flexjs', plugins_url( 'flex/jquery.flexslider-min.js', __FILE__ ) );
	
	$result = '';
	
	// Handle a player with only one item
	if( count( $slides ) == 1 ) {
			$valid_url = sb_player_validate_url( $slides[0]->post_excerpt );
			if ($valid_url) $result .= '<a href="' . $slides[0]->post_excerpt . '">';
			$result .= '<img src="' . 
				sb_player_timthumb( $slides[0]->ID, $dimensions['width'], $dimensions['height'], $options['align'] ) . 
				'" alt="' . $slides[0]->post_content . '" title="' . $slides[0]->post_content . '" />';
			if ($valid_url) $result .= '</a>';
			
			return $result; // stop here, no need to go any further
	}
	
	$controlNav = sb_player_tf( $options['navigation'] );
	
	if( $controlNav ) {
		$controljs = '';
		if( $controlNavThumbs ) {
			if( $options['thumbsize'] == 0 ) {
				$options['thumbsize'] = intval( ($dimensions['width']) / count( $slides ) );
				if ($options['thumbsize'] < 10) $options['thumbsize'] = 10; // thumbnails become unclickable below this size
			}
			
			$controljs .= '
				controlNavThumbs:' . $controlNavThumbs . ',
				controlNavThumbsSearch:"w=' . $dimensions['width'] . '&h=' . $dimensions['height'] . '",
				controlNavThumbsReplace:"w='. $options['thumbsize'] . '&h='. $options['thumbsize'] . '",';
		}
		$controljs .= '
			afterLoad: function() { controlNav($("#slider-' . $uid . '"), ' . count( $slides ) . ', '. $options['thumbsize'] . ') },';
		
		sb_player_footerjs( 'ctrlnav', // will only be included once because identifier doesn't change
			'function controlNav(obj, numslides, thumbsize) {
				var i = 1;
				var ctrlnav = $(".nivo-controlNav", obj);
				if ( !ctrlnav.length ) return; // element not found
				if( $(".with-controlNavThumbs").length ) {
					$("img", ctrlnav).load(function() {
						if( i++ == numslides ) {
							var nava = $("a", ctrlnav);
							var margin = parseInt( nava.not( ".active" ).css( "margin-right" ), 10 ) * 2;
							thumbsize -= margin;
							$("img", nava).width( thumbsize ).height( thumbsize );
							nava.width( thumbsize ).height( thumbsize );
							centerNav();
							var newbtm = nava.outerHeight() * (ctrlnav.outerHeight() / nava.outerHeight());
							ctrlnav.css( "bottom", -newbtm );
							$(obj).parent().css( "margin-bottom", newbtm );
						}
					});
				} else {
					centerNav();
				}
				function centerNav() {
					ctrlnav.css("left", ($(obj).width() - ctrlnav.width()) / 2); // center control nav
				}
			}', 11 );
	}
	
	sb_player_footerjs( 'id_' . $uid,
		'$("#slider-' . $uid . '").nivoSlider({
			effect:"' . ($options['transition'] == '' ? 'random' : implode( ',', $options['transition'] )) . '",
			slices:' . $options['slices'] . ',
			animSpeed:' . $options['speed'] . ',
			pauseTime:' . $options['pause'] . ',
			directionNav:' . sb_player_tf( $options['arrows'] ) . ',
			directionNavHide:' . sb_player_tf( $options['arrhide'] ) . ',
			controlNav:' . $controlNav . ',' . $controljs . '
			pauseOnHover:' . sb_player_tf( $options['hover'] ) . ',
			manualAdvance:' . (1 - sb_player_tf( $options['autoplay'] )) . '
		});' );
	
	// create the html for the player
	$result .= '<div class="slider-wrapper' . ($controlNav ? ' with-controlNav' : '') . ($controlNavThumbs ? ' with-controlNavThumbs' : '') . 
		'" style="width:' . $dimensions['width'] . 'px;"><div class="slider theme-default" id="slider-' . $uid . '">';

	foreach($slides as $slide) {
		$valid_url = sb_player_validate_url( $slide->post_excerpt );
		if ($valid_url) $result .= '<a href="' . $slides[0]->post_excerpt . '">';
		$result .= '<img src="' . 
			sb_player_timthumb( $slide->ID, $dimensions['width'], $dimensions['height'], $options['align'] ) . 
			'" alt="' . $slides[0]->post_content . '" title="' . $slides[0]->post_content . '" />';
		if ($valid_url) $result .= '</a>';
	}
	$result .= '</div></div>';
	
	return $result; // finally, return the resulting code
}
?>