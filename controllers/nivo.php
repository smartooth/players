<?php
// Filter default player controller array
add_filter( 'players_controllers', 'nivo_filter_controllers', 11 );
function nivo_filter_controllers( $defaults ) {
	$defaults['nivo'] = array( 
		'nicename'	=> 'Nivo Slider',
		'website'		=> 'http://nivo.dev7studios.com/',
		'description'	=> __( 'An image slider with impressive transitions', 'players' ),
		'mime_types' 	=> array( 'image/jpeg', 'image/png', 'image/gif' ),
		'shortcode_cb'	=> 'players_nivo_shortcode',
		'options'		=> array(
							'section0'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Pause Timer', 'players' ),
												'desc'		=> __( 'How long, in milliseconds, to pause on an item before transitioning.', 'players' ) ),
							'pause'			=> array( 
												'type'		=> 'input',
												'size'		=> 4,
												'units'		=> __( 'milliseconds', 'players' ),
												'default'		=> 3000,
												'validate'	=> 'numbers' ),
							'section2'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Show', 'players' ) ),
							'arrows'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Show Overlay Arrows', 'players' ),
												'default'		=> 1 ),
							'arrhide'			=> array( 
												'type'		=> 'checkbox',
												'sub'		=> 1,
												'label'		=> __( 'only on hover', 'players' ) ),
							'navigation'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Show Bottom Navigation', 'players' ),
												'default'		=> 1 ),
							'navthumbs'		=> array( 
												'type'		=> 'checkbox',
												'sub'		=> 1,
												'label'		=> __( 'as thumbnails', 'players' ) ),
							'thumbsize'		=> array( 
												'type'		=> 'input',
												'label'		=> __( 'Thumbnail Size', 'players' ),
												'size'		=> 4,
												'units'		=> __( 'px', 'players' ),
												'default'		=> 50,
												'validate'	=> 'numbers',
												'hint'		=> __( 'Enter <code>0</code> for size-to-fit thumbnails.' ) ),
							'section3'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Image Cropping', 'players' ) ),
							'align'			=> array( 
												'type'		=> 'select',
												'label'		=> __( 'Crop From', 'players' ),
												'options' 	=> array(
																't'	=> __( 'Top', 'players' ),
																'tl'	=> __( 'Top Left', 'players' ),
																'tr'	=> __( 'Top Right', 'players' ),
																'c'	=> __( 'Center', 'players' ),
																'l'	=> __( 'Left', 'players' ),
																'r'	=> __( 'Right', 'players' ),
																'b'	=> __( 'Bottom', 'players' ),
																'bl'	=> __( 'Bottom Left', 'players' ),
																'br'	=> __( 'Bottom Right', 'players' ) ),
												'default'		=> 'c' ),
							'section4'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Play Options', 'players' ) ),
							'autoplay'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Auto Play', 'players' ),
												'default'		=> 1 ),
							'hover'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Pause On Hover', 'players' ),
												'default'		=> 1 ),
							'section5'		=> array( 
												'type'		=> 'section',
												'title'		=> __( 'Transition Effects', 'players' ),
												'desc'		=> __( 'If no transition effect is checked, all effects will be used at random.', 'players' ) ),
							'sliceUp'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Slice Up', 'players' ),
												'group' 		=> 'transition' ),
							'sliceDown'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Slice Down', 'players' ),
												'group' 		=> 'transition' ),
							'sliceUpDown'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Slice Up &amp; Down', 'players' ),
												'group' 		=> 'transition' ),
							'slideIn'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Slide In', 'players' ),
												'group' 		=> 'transition' ),
							'fold'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Fold', 'players' ),
												'group' 		=> 'transition' ),
							'fade'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Fade', 'players' ),
												'group'		=> 'transition',
												'default'		=> 1 ),
							'boxRandom'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Box Random', 'players' ),
												'group'		=> 'transition' ),
							'boxRain'			=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Box Rain', 'players' ),
												'group'		=> 'transition' ),
							'boxRainGrow'		=> array( 
												'type'		=> 'checkbox',
												'label'		=> __( 'Box Rain Grow', 'players' ),
												'group'		=> 'transition' ),
							'slices'			=> array( 
												'type'		=> 'input',
												'label'		=> __( 'Transition Slices', 'players' ),
												'hint'		=> '<em>' . __( 'Slice Up', 'players' ) . '</em>, <em> ' . __( 'Slice Down', 'players' ) . '</em> ' . __( 'and', 'players' ) . ' <em>' . __( 'Fold', 'players' ) . '</em> ' . __( 'are the only transition effects with slices.', 'players' ),
												'size'		=> 4,
												'units'		=> __( 'slices', 'players' ),
												'default'		=> 10,
												'validate'	=> 'numbers' ),
							'speed'			=> array( 
												'type'		=> 'input',
												'label'		=> __( 'Transition Speed', 'players' ),
												'size'		=> 4,
												'units'		=> __( 'milliseconds', 'players' ),
												'default'		=> 500,
												'validate'	=> 'numbers' ) ) );
	
	return $defaults; // don't forget to return after filtering
}

// Nivo Callback
function players_nivo_shortcode( $uid, $title, $dimensions, $options, $slides ) {
	// lazy load
	wp_enqueue_style( 'nivocss', plugins_url( 'nivo/nivo-slider.css', __FILE__ ) );
	wp_enqueue_style( 'nivocustomcss', plugins_url( 'nivo/custom.css', __FILE__ ) );
	wp_enqueue_style( 'nivodefaultcss', plugins_url( 'nivo/themes/default/default.css', __FILE__ ) );
	wp_enqueue_script( 'nivojs', plugins_url( 'nivo/jquery.nivo.slider.js', __FILE__ ) );
	
	$result = '';
	
	// Handle a player with only one item
	if( count( $slides ) == 1 ) {
			$valid_url = players_validate_url( $slides[0]->post_excerpt );
			if ($valid_url) $result .= '<a href="' . $slides[0]->post_excerpt . '">';
			$result .= '<img src="' . 
				players_timthumb( $slides[0]->ID, $dimensions['width'], $dimensions['height'], $options['align'] ) . 
				'" alt="' . $slides[0]->post_content . '" title="' . $slides[0]->post_content . '" />';
			if ($valid_url) $result .= '</a>';
			
			return $result; // stop here, no need to go any further
	}
	
	$controlNav = players_tf( $options['navigation'] );
	$controlNavThumbs = players_tf( $options['navthumbs'] );
	
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
		
		players_footerjs( 'ctrlnav', // will only be included once because identifier doesn't change
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
	
	players_footerjs( 'id_' . $uid,
		'$("#slider-' . $uid . '").nivoSlider({
			effect:"' . ($options['transition'] == '' ? 'random' : implode( ',', $options['transition'] )) . '",
			slices:' . $options['slices'] . ',
			animSpeed:' . $options['speed'] . ',
			pauseTime:' . $options['pause'] . ',
			directionNav:' . players_tf( $options['arrows'] ) . ',
			directionNavHide:' . players_tf( $options['arrhide'] ) . ',
			controlNav:' . $controlNav . ',' . $controljs . '
			pauseOnHover:' . players_tf( $options['hover'] ) . ',
			manualAdvance:' . (1 - players_tf( $options['autoplay'] )) . '
		});' );
	
	// create the html for the player
	$result .= '<div class="slider-wrapper' . ($controlNav ? ' with-controlNav' : '') . ($controlNavThumbs ? ' with-controlNavThumbs' : '') . 
		'" style="width:' . $dimensions['width'] . 'px;"><div class="slider theme-default" id="slider-' . $uid . '">';

	foreach($slides as $slide) {
		$valid_url = players_validate_url( $slide->post_excerpt );
		if ($valid_url) $result .= '<a href="' . $slides[0]->post_excerpt . '">';
		$result .= '<img src="' . 
			players_timthumb( $slide->ID, $dimensions['width'], $dimensions['height'], $options['align'] ) . 
			'" alt="' . $slides[0]->post_content . '" title="' . $slides[0]->post_content . '" />';
		if ($valid_url) $result .= '</a>';
	}
	$result .= '</div></div>';
	
	return $result; // finally, return the resulting code
}
?>