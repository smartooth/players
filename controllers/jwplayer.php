<?php
// Admin Init
function jwplayer_admin_init() {
	global $players_options, $players_options_page;
	
	register_setting( $players_options, 'jwplayer_options', 'jwplayer_options_validate' );
	add_settings_section( 'jwplayer_section', __( 'JW Player', 'players' ), 'jwplayer_options_section', $players_options_page );
	add_settings_field( 'jwplayer_licensed_field', __( 'Licensed player', 'players' ), 'jwplayer_licensed_field', $players_options_page, 'jwplayer_section', array( 'label_for' => 'jwplayer_licensed' ) );
	add_settings_field( 'jwplayer_crossdomain_field', __( 'Crossdomain XML', 'players' ), 'jwplayer_crossdomain_field', $players_options_page, 'jwplayer_section', array( 'label_for' => 'jwplayer_crossdomain' ) );
}
add_action( 'admin_init', 'jwplayer_admin_init' );

// "JW Player" Options Page Section
function jwplayer_options_section() {
	// could echo a description if necessary...
}

// JW Player Options Page Admin Notices
function jwplayer_options_admin_notices() {
	if (!players_verify_post_type() || !isset( $_GET['page'] ) || ($_GET['page'] !== 'options') || !isset( $_GET['settings-updated'] )) return;
	
	$options = get_option( 'jwplayer_options' );
	
	// "Licensed player" error/success messages
	if( !empty( $options['licensed_path'] ) ) {
		if ((substr( $options['licensed_path'], -4, 4 ) == '.swf') && file_exists( $options['licensed_path'] ))
			echo '<div class="updated"><p>' . __( 'An swf file was successfully detected at licensed player path', 'players' ) . ' <code>' . $options['licensed_path'] . '</code></p></div>';
		else
			echo '<div class="error"><p>' . __( 'The current licensed player path is invalid', 'players' ) . ' &mdash; ' . __( 'The default player.swf will be used.', 'players' ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'jwplayer_options_admin_notices' );

// Validate JW Player Options
function jwplayer_options_validate( $input ) {
	$options = get_option( 'jwplayer_options' );
	$options['licensed_path'] = esc_attr( players_path( $input['licensed_path'] ) );
	return $options;
}

// Handle JW Player "licensed" Options Page Field
function jwplayer_licensed_field() {
	$options = get_option( 'jwplayer_options' );
	?>
	<input name="jwplayer_options[licensed_path]" type="text" id="jwplayer_licensed" value="<?php echo esc_attr( $options['licensed_path'] ); ?>" class="regular-text" />
	<span class="description"><?php _e( 'Path to a licensed version of player.swf', 'players' ); ?> (<?php _e( 'leave blank if none', 'players' ); ?>).</span>
<?php }

// Handle JW Player "crossdomain" Options Page Field @TODO: setup ajax request
function jwplayer_crossdomain_field() {	?>
	<span id="jwplayer_no_crossdomain"><?php _e( 'No', 'players' ); ?> <code>crossdomain.xml</code> <?php _e( 'file located in server root.', 'players' ); ?></span>
	<a class="button" id="jwplayer_crossdomain" href="javascript:void(0);"><span id="jwplayer_crossdomain_action"></span> crossdomain.xml</a>
	<span class="description"><?php _e( 'This file allows other sites to embed your videos.', 'players' ); ?> <a href="http://www.longtailvideo.com/support/jw-player/jw-player-for-flash-v5/12541/crossdomain-file-loading-restrictions" target="_blank"><?php _e( 'more info', 'players' ); ?></a></span>
<?php }

// Add Javascript To Options Page Admin Head
function jwplayer_options_admin_head() { ?>
	<script type="text/javascript">
		;(function($) {
			$(document).ready(function() {
				function jwplayer_crossdomain_action( action ) {
					if( action == 'create' ) {
						action = '<?php _e( 'Create', 'players' ); ?>';
						$('#jwplayer_no_crossdomain').show();
					} else {
						action = '<?php _e( 'Delete', 'players' ); ?>';
						$('#jwplayer_no_crossdomain').hide();
					}
					$('#jwplayer_crossdomain_action').text( action );
				}
				jwplayer_crossdomain_action( '<?php echo (file_exists( $_SERVER['DOCUMENT_ROOT'] . '/crossdomain.xml' ) ? 'delete' : 'create'); ?>' );
				
				$('#jwplayer_crossdomain').click( function() {
					$.post( ajaxurl, { action: 'jwplayer_crossdomain' }, function( response ) {
						if (response == 'error')
							alert( '<?php _e( 'Unable to modify crossdomain.xml, you may have to modify it by hand.', 'players' ); ?>' );
						else
							jwplayer_crossdomain_action( response );
					});
				});
			});
		})(jQuery);
	</script>
<?php }
add_action( 'admin_head-player_page_options', 'jwplayer_options_admin_head' );

// Handle Ajax Request To Create Or Delete The Crossdomain File
function jwplayer_crossdomain() {
	if (!file_exists( $file = ($_SERVER['DOCUMENT_ROOT'] . '/crossdomain.xml') ))
		die( (copy( plugins_url( 'jwplayer/crossdomain.xml', __FILE__ ), $file ) ? 'delete' : 'error') );
	else
		die( (@unlink( $file ) ? 'create' : 'error') );
}
add_action( 'wp_ajax_jwplayer_crossdomain', 'jwplayer_crossdomain' );

// Necessary Adding Of Script To Head
function jwplayer_template_redirect() {
	wp_enqueue_script( 'jwplayerjs', plugins_url( 'jwplayer/jwplayer.js', __FILE__ ) ); // jwplayer fails in IE if this script is added using lazy load
}
add_action( 'template_redirect', 'jwplayer_template_redirect' );

// Filter default player controller array
add_filter( 'players_controllers', 'jwplayer_filter_controllers', 12 );
function jwplayer_filter_controllers( $defaults ) {
	$defaults['jwplayer'] = array( 
		'nicename'	=> 'JW Player v1.7',
		'website'		=> 'http://www.longtailvideo.com/players/jw-flv-player/',
		'description'	=> __( 'A video playlist that uses JW Player which uses Flash if available but falls back to HTML5 for mobile browsers.', 'players' ),
		'mime_types' 	=> array( 'video/flv', 'video/mp4' ),
		'shortcode_cb'	=> 'players_jwplayer_shortcode',
		'options'		=> array(
						'section1'		=> array( 
											'type'		=> 'section',
											'title'		=> __( 'General Options', 'players' ) ),
						'listposition'		=> array( 
											'type'		=> 'select',
											'label'		=> __( 'Playlist Position', 'players' ),
											'options'		=> array(
															'top'	=> __( 'Top', 'players' ),
															'bottom'	=> __( 'Bottom', 'players' ),
															'left'	=> __( 'Left', 'players' ),
															'right'	=> __( 'Right', 'players' ) ),
											'default'		=> 'bottom' ),
						'listsize'		=> array( 
											'type'		=> 'input',
											'label'		=> __( 'Playlist Size', 'players' ),
											'hint'		=> __( 'Increase this value for left or right positioned playlists.', 'players' ),
											'size'		=> 4,
											'units'		=> __( 'px', 'players' ),
											'default'		=> 100,
											'validate'	=> 'numbers' ),
						'bcolor'			=> array( 
											'type'		=> 'input',
											'label'		=> __( 'Back Color', 'players' ),
											'size'		=> 8,
											'default'		=> '#FFFFFF' ),
						'fcolor'			=> array( 
											'type'		=> 'input',
											'label'		=> __( 'Front Color', 'players' ),
											'size'		=> 8,
											'default'		=> '#000000' ),
						'lcolor'			=> array( 
											'type'		=> 'input',
											'label'		=> __( 'Light Color', 'players' ),
											'size'		=> 8,
											'default'		=> '#000000' ),
						'scolor'			=> array( 
											'type'		=> 'input',
											'label'		=> __( 'Screen Color', 'players' ),
											'size'		=> 8,
											'default'		=> '#000000' ),
						'section2'		=> array( 
											'type'		=> 'section',
											'title'		=> __( 'HTML5', 'players' ) ),
						'forcehtml5'		=> array( 
											'type'		=> 'checkbox',
											'label'		=> __( 'Force HTML5 (and fallback to Flash)', 'players' ) ) ) );
		
	return $defaults; // don't forget to return after filtering
}

// JW Player Callback
function players_jwplayer_shortcode( $uid, $title, $dimensions, $options, $attachments ) {
	$is_playlist = (count( $attachments ) > 1 ? true : false);
	$result = $playlist = $extra = '';
	$licensed = players_get_option( 'jw_licensed' );
	$mime_type = 'video/mp4';
	
	foreach( $attachments as $num => $attachment ) {
		if (!($encodes = players_get_encodes( $attachment->ID, $mime_type ))) continue;
		$encode = players_select_encode_by_dimensions( $encodes, $dimensions['width'], $dimensions['height'] );
		$screenshots = players_get_screenshots( $attachment->ID );
		
		if ($num == 0) $result .= '<div class="pwrapper"><div id="id_' . $uid . '">Loading video...</div></div>';
		
		if( $is_playlist ) {
			$playlist .= '
				{ "duration": ' . $encode['duration'] . ',
				"file": "' . $encode['mime_types'][$mime_type]['url'] . '",
				"hd.file": "' . $encodes['source']['mime_types'][$mime_type]['url'] . '",
				"image": "' . $screenshots[0]['url'] . '",
				"title": "' . $attachment->post_title . '",
				"description": "' . $attachment->post_content . '",
				"provider": "http" },';
		} else {
			$extra .= ',
				"file": "' . $encode['mime_types'][$mime_type]['url'] . '",
				"hd.file": "' . $encodes['source']['mime_types'][$mime_type]['url'] . '",
				"image": "' . $screenshots[0]['url'] . '",
				"provider": "http"';
		}
	}
	
	$extra .= ($is_playlist ? ',
		"playlist": [' . $playlist . '],
		"playlist.position": "' . $options['listposition'] . '",
		"playlist.size": ' . $options['listsize'] : '');
		
	$extra .= (players_tf( $options['forcehtml5'] ) ? ',
			"modes": [
				{ "type": "html5" },
				{ "type": "flash" },
				{ "type": "download" }
			]' : '');
	
	players_footerjs( 'id_' . $uid,
		'jwplayer( "id_' . $uid . '" ).setup({
			"flashplayer": "' . plugins_url( 'jwplayer/player.swf', __FILE__ ) . '",
			"width": "' . $dimensions['width'] . '",
			"height": "' . $dimensions['height'] . '",
			"backcolor": "' . jwplayer_color( $options['bcolor'] ) . '",
			"frontcolor": "' . jwplayer_color( $options['fcolor'] ) . '", 
			"lightcolor": "' . jwplayer_color( $options['lcolor'] ) . '", 
			"screencolor": "' . jwplayer_color( $options['scolor'] ) . '", 
			"plugins": {
				"viral-2": { "allowmenu": false, "oncomplete": false, "onpause": false, "functions": "" },
				"hd-2": {}
			}' 
			. $extra . '
		});' );
	
	return $result; // finally, output the resulting code
}

function jwplayer_color( $string ) {
	return (strpos($string, '#') === 0 ? substr( $string, 1, 6 ) : $string);
}
?>