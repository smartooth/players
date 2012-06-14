<?php
// Filter default player controller array
add_filter( 'sb_player_controllers', 'nivo_filter_controllers', 11 );
function nivo_filter_controllers( $defaults ) {
	$defaults['nivo'] = array( 
		'nicename'	=> 'Nivo Slider',
		'website'		=> 'http://nivo.dev7studios.com/',
		'description'	=> __( 'An image slider with impressive transitions', 'startbox' ),
		'mime_types' 	=> array( 'image/jpeg', 'image/png', 'image/gif' ),
		'shortcode_cb'	=> 'sb_player_nivo_shortcode',
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
							'section2'		=> array( 
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
							'navthumbs'		=> array( 
												'type'		=> 'checkbox',
												'sub'		=> 1,
												'label'		=> __( 'as thumbnails', 'startbox' ) ),
							'thumbsize'		=> array( 
												'type'		=> 'input',
												'label'		=> __( 'Thumbnail Size', 'startbox' ),
												'size'		=> 4,
												'units'		=> __( 'px', 'startbox' ),
												'default'		=> 50,
												'validate'	=> 'numbers',
												'hint'		=> __( 'Enter <code>0</code> for size-to-fit thumbnails.' ) ),
							'section3'		=> array( 
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
							'section4'		=> array( 
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
							'section5'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Transition Effects', 'startbox' ),
												'desc'		=> __( 'If no transition effect is checked, all effects will be used at random.', 'startbox' ) ),
							'sliceUp'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Slice Up', 'startbox' ),
												'group' 		=> 'transition' ),
							'sliceDown'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Slice Down', 'startbox' ),
												'group' 		=> 'transition' ),
							'sliceUpDown'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Slice Up &amp; Down', 'startbox' ),
												'group' 		=> 'transition' ),
							'slideIn'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Slide In', 'startbox' ),
												'group' 		=> 'transition' ),
							'fold'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Fold', 'startbox' ),
												'group' 		=> 'transition' ),
							'fade'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Fade', 'startbox' ),
												'group'		=> 'transition',
												'default'		=> 1 ),
							'boxRandom'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Box Random', 'startbox' ),
												'group'		=> 'transition' ),
							'boxRain'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Box Rain', 'startbox' ),
												'group'		=> 'transition' ),
							'boxRainGrow'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Box Rain Grow', 'startbox' ),
												'group'		=> 'transition' ),
							'slices'			=> array( 
												'type'		=> 'input',
												'label'		=> __( 'Transition Slices', 'startbox' ),
												'hint'		=> '<em>' . __( 'Slice Up', 'startbox' ) . '</em>, <em> ' . __( 'Slice Down', 'startbox' ) . '</em> ' . __( 'and', 'startbox' ) . ' <em>' . __( 'Fold', 'startbox' ) . '</em> ' . __( 'are the only transition effects with slices.', 'startbox' ),
												'size'		=> 4,
												'units'		=> __( 'slices', 'startbox' ),
												'default'		=> 10,
												'validate'	=> 'numbers' ),
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
function sb_player_nivo_shortcode( $uid, $title, $dimensions, $options, $slides ) {
	// lazy load
	wp_enqueue_style( 'nivocss', plugins_url( 'nivo/nivo-slider.css', __FILE__ ) );
	wp_enqueue_style( 'nivocustomcss', plugins_url( 'nivo/custom.css', __FILE__ ) );
	wp_enqueue_style( 'nivodefaultcss', plugins_url( 'nivo/themes/default/default.css', __FILE__ ) );
	wp_enqueue_script( 'nivojs', plugins_url( 'nivo/jquery.nivo.slider.js', __FILE__ ) );
	
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
	$controlNavThumbs = sb_player_tf( $options['navthumbs'] );
	
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
			afterLoad: function() { controlNav( $("#slider-' . $uid . '"), ' . count( $slides ) . ', '. $options['thumbsize'] . ' ) },';
		
		sb_player_footerjs( 'ctrlnav', // will only be included once because identifier doesn't change
			'function controlNav( obj, numslides, thumbsize ) {
				var ctrlnav = $(".nivo-controlNav", obj);
				
				if (!ctrlnav.length) return; // element not found
				
				var nava = $("a", ctrlnav).not( ".active" );
				var horiz_margin = parseInt( nava.css( "margin-left" ), 10 ) + parseInt( nava.css( "margin-right" ), 10 );
				
				if( $(obj).parent( ".with-controlNavThumbs" ).length ) {
					thumbsize -= horiz_margin;
					$("a, img", ctrlnav).width( thumbsize ).height( thumbsize );
					
					var i = 1;
					$("img", ctrlnav).load(function() {
						if (i++ == numslides) place_navigation();
					});
				} else {
					place_navigation( 8 );
				}
				
				var awidth = (nava.outerWidth( true ) * numslides);
				ctrlnav.css( "padding-left", ($(obj).width() - awidth) / 2 ); // center control nav
				
				function place_navigation( extra ) {
					extra = (extra == undefined ? 0 : extra);
					var newbtm = (nava.outerHeight() * (ctrlnav.outerHeight() / nava.outerHeight())) + extra;
					ctrlnav.css( "bottom", -newbtm );
					$(obj).parent().css( "margin-bottom", newbtm );
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