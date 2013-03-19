<?php
//error_reporting( E_ALL | E_STRICT );
/*
Plugin Name: Players
Description: Feature media using various content players. Each player can be customized with many options and effects. This plugin creates a custom post type called Players which integrates with the Media Library. To embed a player into a post or page, simply insert <code>[player id=""]</code> anywhere in the post or page content.
Version: 1.0.0
Author: Joel Kuzmarski
Author URI: http://www.github.com/leoj3n
License: GPL2

    Copyright 2013  Joel KUZMARSKI  (email : leoj3n at gmail dot com)

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

// Deactivate Plugin If PHP Version Is Less Than 5.2
if( version_compare( PHP_VERSION, '5.2', '<' ) ) {
	if( is_admin() && !(defined( 'DOING_AJAX' ) || DOING_AJAX) ) {
		require_once admin_url( 'includes/plugin.php' );
		deactivate_plugins( PLAYERS_PATH );
		wp_die( __( 'Players requires PHP 5.2 or higher, as does WordPress 3.2 and higher. The plugin has now disabled itself.', 'players' ) . 
			' <a href="javascript:history.go( -1 );">' . __( 'go back', 'players' ) . ' &rarr;</a>' );
	} else {
		return;
	}
}

define( 'PLAYERS_PATH', apply_filters( 'players_path', 'players/players.php' ) );
define( 'PLAYERS_CONTROLLERS_PATH', apply_filters( 'players_controllers_path', 'players/controllers/*' ) );
define( 'PLAYERS_ASSETS_PATH', apply_filters( 'players_assets_path', 'players/assets/*' ) );

// Include Lazy Load Plugin
if (!function_exists( 'lazyload_template_redirect' )) 
	include 'assets/lazyload/lazyload.php'; // @TODO: move to init?

// Include Default Controllers
include 'controllers/jwplayer.php';
include 'controllers/colorbox.php';
include 'controllers/nivo.php';
include 'controllers/flex.php';

// Define Globals
$players_video_icon = false;
$players_units = $players_used_ids = $players_footerjs = array();
$players_controllers = apply_filters( 'players_controllers', array() );
$players_interface = apply_filters( 'players_interface', array( 
	'sizes' 			=> array( 
						'max' 	=> __( 'Largest Image', 'players' ), 
						'min' 	=> __( 'Smallest Image', 'players' ), 
						'custom' 	=> __( 'Custom', 'players' ) ),
	'link_text' 		=> 'http://',
	'color' 			=> '#FFFFFF',
	'media_width' 		=> 300,
	'media_height' 	=> 188,
	'filter_width'		=> 60,
	'filter_height' 	=> 60 ) );
$players_timthumb_path = apply_filters( 'players_timthumb_path', plugins_url( 'assets/timthumb/timthumb.php', PLAYERS_PATH ) );
$players_options = apply_filters( 'players_options', 'players_options' );
$players_options_page = apply_filters( 'players_options_page', 'options' );
$file_version = '1.0.0'; // should match the "Version" definition in the header of this file
//delete_option( 'players_options' ); // hack for testing: resets version
//players_install();
$user_version = players_get_option( 'version' );

// First Run Function On Plugin Activation
function players_install() {
	players_update_option( 'automatic', 'enabled' );
	players_update_option( 'screenshot', 50 );
	players_update_option( 'sequence', 0 );
	players_update_option( 'debug', false );
	players_update_option( 'sample', false );
	players_update_option( 'ffmpeg_path', 'ffmpeg' );
	players_update_option( 'flvtool2_path', 'flvtool2' );
	players_update_option( 'qtfaststart_path', 'qt-faststart' );
	
	players_update_option( 'ffmpeg_queue', array() );
}
register_activation_hook( PLAYERS_PATH, 'players_install' );

// Updgrade Function
function players_upgrade() {
	global $file_version, $user_version;
	
	/*
	if( version_compare( $user_version, '2.0.0', '<' ) ) {
		// coming soon...
	}
	*/
	players_update_option( 'version', $file_version ); // update version to be up-to-date
}
if (version_compare( $user_version, $file_version, '<' )) players_upgrade();

/************************************************************************************************
 *									BEGIN VIDEO								                    *
 ************************************************************************************************/
 
 // Verify That Exec Is Supported
 function players_verify_exec() {
	return function_exists( 'exec' );
 }
 
 // Uses exec() But Return A String Like players_shell_exec() Would
 function players_shell_exec( $command ) {
	if (!players_verify_exec()) return '';
	
	$output = array();
	exec( $command, $output );
	return implode( "\n", $output );
 }
 
// Determine FFMPEG Capabilities
function players_get_ffmpeg_capabilities() {
	$ffmpeg_help = players_shell_exec( ($ffmpeg_path = players_get_option( 'ffmpeg_path' )) . ' -h' ); // should work on all versions
	$ffmpeg_filters = players_shell_exec( $ffmpeg_path . ' -filters' );
	
	$capabilities = array();	
	$capabilities['crop_filter'] = players_tf( stristr( $ffmpeg_help, 'Removed, use the crop filter instead' ) ); // are -crop* arguments deprecated?
	$capabilities['new_crop_syntax'] = players_tf( stristr( $ffmpeg_filters, 'width:height:x:y' ) ); // is crop filter syntax the old x:y:w:h or the new w:h:x:y?
	$capabilities['fpre'] = players_tf( stristr( $ffmpeg_help, '-fpre' ) ); // supports -fpre?
	
	return $capabilities; // return version type and string
}

// Verify That FFMPEG Is Working
function players_verify_ffmpeg() {
	if ($ffmpeg_path = players_get_option( 'ffmpeg_path' ))
		return players_string_between( players_shell_exec( $ffmpeg_path . ' -version' ), 'ffmpeg ', "\n", true ); // return version string or false
	else
		return false;
}

// Verify That FLVTOOL2 Is Working
function players_verify_flvtool2() {
	if ($flvtool2_path = players_get_option( 'flvtool2_path' ))
		return players_string_between( players_shell_exec( $flvtool2_path . ' -H' ), 'flvtool2 ', "\n", true ); // return version string or false
	else
		return false;
}

// Verify That QT-FASTSTART Is Working
function players_verify_qtfaststart() {
	if ($qtfaststart_path = players_get_option( 'qtfaststart_path' ))
		return players_tf( stristr( players_shell_exec( $qtfaststart_path ), 'Usage:' ) ); // return true or false
	else
		return false;
}

// Determine Video Duration
function players_get_video_duration( $video ) {
	if( file_exists( $video ) ) {
		$stats = players_shell_exec( players_get_option( 'ffmpeg_path' ) . ' -i ' . $video . ' -vstats 2>&1' );
		
		preg_match_all( '/Duration: ([0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9])/', $stats, $matches, PREG_PATTERN_ORDER );
		
		$duration = $matches[1][0];
		
		// ffmpeg returns length as 00:00:31.1
		$hmsmm = explode( ":", $duration );
		$tmp = explode( ".", $hmsmm[2] );
		$seconds = $tmp[0];
		$hours = $hmsmm[0];
		$minutes = $hmsmm[1];
		
		return $seconds + ($hours * 3600) + ($minutes * 60);
	} else {
		return false;
	}
}

// Determine Video Dimensions (Returns Array)
function players_get_video_dimensions( $video ) {
	if( file_exists( $video ) ) {
		$stats = players_shell_exec( players_get_option( 'ffmpeg_path' ) . ' -i ' . $video . ' -vstats 2>&1' );
		
		$stats = str_replace( basename( $video ), '', $stats ); // remove filename from result incase of dimension string in filename
		
		preg_match( '/[0-9]?[0-9][0-9][0-9]x[0-9][0-9][0-9][0-9]?/', $stats, $matches );
		
		if( isset( $matches[0] ) ) {
			$vals = explode( 'x', $matches[0] );
			$width = $vals[0] ? $vals[0] : NULL;
			$height = $vals[1] ? $vals[1] : NULL;
			
			return array( 
				'width' 	=> $width, 
				'height' 	=> $height );
		} else {
			return false;
		}
	} else {
		return false;
	}
}

//players_update_option( 'ffmpeg_queue', array() );
/* TEMPORARY TESTING FUNCTION(S) - delete me
function players_temp() {
	echo '<pre>';
	var_dump( players_get_option( 'ffmpeg_queue' ) );
	echo '</pre>';
}
add_action( 'admin_notices', 'players_temp' );*/

// Convert Time (HH:MM:SS.MS) To Seconds
function players_time_to_seconds( $time ) {
	$time = explode( ':', $time, 3 );
	return ((int)$time[0] * 3600) + ((int)$time[1] * 60) + (int)substr( $time[2], 0, 2 );
}

// Check For And Execute Any Enqueued Encodes
function players_do_queue() {
	$debug = players_tf( players_get_option( 'debug' ) );
	$queue = players_get_option( 'ffmpeg_queue' );
	
	if (empty( $queue ) || !is_array( $queue )) return players_update_option( 'ffmpeg_queue', array() ); // don't go any further
	
	$index = -1;
	foreach( $queue as $num => $item ) {
		if( $item['progress'] > -1.0 ) { // if encoding in progress
			$index = $num; // continue encoding this item
			break;
		}
	}
	
	if( $index == -1 ) { // nothing being encoded, kick encoding off	
		players_update_option( 'ffmpeg_process', players_bg_exec( $queue[0]['command'] ) );		
		$queue[0]['progress'] = 0.0;
	} else {
		if( $log = @file_get_contents( $queue[$index]['log'] ) ) {
			$time_parts = explode( 'time=', $log );
			$time_parts = explode( ' ', array_pop( $time_parts ) );
			$time = $time_parts[0];
			$time = ($time == '10000000000.00' ? '0.01' : $time); // fix for an ffmpeg quirk
			$time = (stristr( $time, ':' ) ? players_time_to_seconds( $time ) : $time); // newer versions use hh:mm:ss.ms
			
			if( !players_process_exists( players_get_option( 'ffmpeg_process' ) ) ) {
				if (!$debug) @unlink( $queue[$index]['log'] ); // delete the log file used to snag times if not debugging
				
				$cleanup = $queue[$index]['cleanup'];
				if (!strstr( $log, 'Could not' ) && !empty( $cleanup )) call_user_func_array( array_shift( $cleanup ), $cleanup ); // run the cleanup command
				
				unset( $queue[$index] ); // this item is done
			} else {
				$queue[$index]['progress'] = (float)$time; // update progress
			}
		}
	}
	
	players_update_option( 'ffmpeg_queue', array_values( $queue ) ); // array_values() to fix keys after unset()
}
if (!isset( $_POST['do_action'] ) || $_POST['do_action'] !== 'cancel') players_do_queue(); // call this at every possible page load (sort of like a constant wp-cron)

// Initiate Encoding And Update Video Metadata
function players_video_metadata( $data, $post_id ) {
	global $pagenow;
	
	$post = get_post( $post_id );
	
	if( isset( $post ) && players_is_video( $post->post_mime_type ) ) { // only execute the following for video files
		// do some location checking
		switch( !empty( $_POST['id'] ) && is_numeric( $_POST['id'] ) ) {
			case false: // from media-new.php
				if (players_get_option( 'automatic' ) !== 'enabled') return $data; // cancel encode
				break;
			case true: // from uploads.php, Encode/Re-Encode/Delete 
				players_video_delete( $post_id );
				if( $_POST['do_action'] == 'delete' ) {
					unset( $data['encodes'] );
					unset( $data['screenshots'] );
					unset( $data['path'] );
					return $data;
				}
				break;
		}
		
		$source = array(
			'path'	=> players_path( get_attached_file( $post_id ) ),
			'url'	=> wp_get_attachment_url( $post_id ) ); // path and url to source video
		
		if( $dimensions = players_get_video_dimensions( $source['path'] ) ) {
			extract( $dimensions ); // source width and height
			
			if( is_numeric( $width ) && is_numeric( $height ) ) {
				$browser = array( 'video/flv' ); // browsers only need flv
				$mobile = array( 'video/mp4' ); // mobile only needs mp4
				
				// begin enqueued profiles
				$queue = array(
					'source'	=> array( 
						'width'		=> $width, 
						'height'		=> $height,
						'mime_types'	=> $mobile ) );
									
				$profiles = apply_filters( 'players_profiles', (($width / $height) < 1.5 ? array( 'standard' ) : array( 'widescreen' )) ); // default profiles
				
				if( in_array( 'standard', $profiles ) ) { // 4:3 profile
					$queue = array_merge( $queue, array( 
						'oldyt'	=> array( 
							'width'		=> 320, 
							'height'		=> 240,
							'mime_types'	=> $mobile ),
						'iphone'	=> array( 
							'width'		=> 480, 
							'height'		=> 360,
							'mime_types'	=> $mobile ),
						'youtube'	=> array( 
							'width'		=> 640, 
							'height'		=> 480,
							'mime_types'	=> $mobile ) ) );
				}
				
				if( in_array( 'widescreen', $profiles ) ) { // 16:9 profile				
					$queue = array_merge( $queue, array( 
						'iphone'	=> array( 
							'width'		=> 480, 
							'height'		=> 272,
							'mime_types'	=> $mobile ),
						'youtube'	=> array( 
							'width'		=> 640, 
							'height'		=> 360,
							'mime_types'	=> $mobile ),
						'480p'	=> array( 
							'width'		=> 852, 
							'height'		=> 480,
							'mime_types'	=> $mobile ) ) );
				}
				
				// define a bunch of variables
				$queue = apply_filters( 'players_queue', $queue ); // use this filter to modify or add profiles
				$ffmpeg_path = players_get_option( 'ffmpeg_path' );
				$flvtool2_path = players_get_option( 'flvtool2_path' );
				$capabilities = players_get_option( 'ffmpeg_capabilities' );
				$preset = players_get_option( 'ffmpeg_preset' );
				$duration = players_get_video_duration( $source['path'] ); // source duration
				$screenshot = ($duration * (players_get_option( 'screenshot' ) / 100));
				$sequence = players_get_option( 'sequence' );
				$pathinfo = pathinfo( $source['path'] );
				$urlinfo = pathinfo( $source['url'] );
				$path = $data['directory'] = players_path( trailingslashit( $pathinfo['dirname'] ) . trailingslashit( $pathinfo['filename'] ) );
				$url = trailingslashit( $urlinfo['dirname'] ) . trailingslashit( $pathinfo['filename'] );
				$data['encodes'] = $data['screenshots'] = array();
				$debug = players_tf( players_get_option( 'debug' ) );
				$d_log = $path . 'debug.log'; // set debug log file
				$sample = players_tf( players_get_option( 'sample' ) );
				
				clearstatcache();
				if (!is_dir( $path )) mkdir( $path ); // make directory if needed
				if (!file_exists( $path . 'index.html' )) file_put_contents( $path . 'index.html', '' ); // hide directory contents
				
				// prepend correct prefix to preset
				if ($capabilities['fpre'])
					$preset = (file_exists( $preset ) ? '-fpre' : '-vpre') . ' "' . $preset . '"';
				else
					$preset = '-vpre "' . $preset . '"';
				
				// image encoding from source
				$ss_cmd = $ffmpeg_path . ' -i "' . $source['path'] . '" -vframes 1 -ss %s -f image2 -vcodec mjpeg -y "%s"';
				$ss_filename = '%sscreenshot%d.jpg';
				$ss_exec = array();
				for( $i = 0; $i <= $sequence; $i++ ) {
					if ($i == 0)
						$percentage = $screenshot;
					else
						$percentage = ($offset = ($duration / 10)) + (($duration - ($offset * 2)) / (($sequence + 1) - $i));
						
					$data['screenshots'][$i] = array( 'path' => sprintf( $ss_filename, $path, $i ), 'url' => sprintf( $ss_filename, $url, $i ) );
					
					$ss_exec[] = sprintf( $ss_cmd, number_format( $percentage, 3 ), $data['screenshots'][$i]['path'] );
				}
				if( $pagenow == 'async-upload.php' ) {
					players_debug_log( $d_log, $ss_exec[0] );
					players_shell_exec( array_shift( $ss_exec ) ); // wait for first image to exist
				}
				if( !empty( $ss_exec ) ) {
					players_debug_log( $d_log, ($ss_cmd = implode( ' && ', $ss_exec )) ); // this could be a race condition with ffmpeg_process but haven't seen any evidence yet...
					players_bg_exec( $ss_cmd ); // do the rest
				}
				
				// loop through each enqueued profile
				foreach( $queue as $task => $opts ) {
					// validate important variables
					if (!is_numeric( $opts['width'] ) || !is_numeric( $opts['height'] ) 
						|| $opts['width'] < 1.0 || $opts['height'] < 1.0						// invalid width or height
						|| $opts['width'] > $width || $opts['height'] > $height) continue;	// don't encode if task width or height is greater than source
					if(empty( $opts['bitrate'] )) $opts['bitrate'] = ceil( ($opts['width'] + $opts['height']) / 3.0 ); // guess best bitrate
					$opts['duration'] = ($sample ? 30.0 : $opts['duration']); // force duration to be 30 seconds duration if sampling
					if (empty( $opts['duration'] ) || $opts['duration'] > $duration) $opts['duration'] = $duration; // don't encode for longer than duration of source
					
					// force integers
					settype( $opts['width'], 'integer' );
					settype( $opts['height'], 'integer' );
					settype( $opts['duration'], 'integer' );
					settype( $opts['bitrate'], 'integer' );
					
					$options = array();
					$ratio = number_format( $width / $height, 4 );
					$output_ratio = number_format( $opts['width'] / $opts['height'], 4 );
					// set sizing
					if( $output_ratio != $ratio && $width && $height ) {		
						if( $sizing == 'crop' ) {			
							if ( $ratio > $output_ratio ) { // match height, crop left/right
								$end_width = $width - ($height - $opts['height']);
								$end_height = $opts['height'];
								$crop1 = $crop2 = floor( ($end_width - $opts['width']) / 2 );
								if( $crop1 % 2 !== 0 ) {
									$crop1++;
									$crop2--;
								}
								if( $capabilities['crop_filter'] ) {
									if ($capabilities['new_crop_syntax'])
										$options[] = '-vf crop=in_w-' . ($crop1 + $crop2) . ':in_h';
									else
										$options[] = '-vf crop=0:0:in_w-' . ($crop1 + $crop2) . ':in_h';
								} else {
									$options[] = '-aspect ' . $output_ratio;
									$options[] = '-cropleft ' . $crop1;
									$options[] = '-cropright ' . $crop2;
								}
							} else { // match width, crop top/bottom
								$end_height = $height - ($width - $opts['width']);
								$end_width = $opts['width'];
								$crop1 = $crop2 = floor( ($end_height - $opts['height']) / 2 );
								if( $crop1 % 2 !== 0 ) {
									$crop1++;
									$crop2--;
								}
								if( $capabilities['crop_filter'] ) {
									if ($capabilities['new_crop_syntax'])
										$options[] = '-vf crop=in_w:in_h-' . ($crop1 + $crop2);
									else
										$options[] = '-vf crop=0:0:in_w:in_h-' . ($crop1 + $crop2);
								} else {
									$options[] = '-aspect ' . $output_ratio;
									$options[] = '-croptop ' . $crop1;
									$options[] = '-cropbottom ' . $crop2;
								}
							}
							
							$ext = $opts['width'] . 'x' . $opts['height'] . '_crop';
						} else {
							if( $ratio < $output_ratio ) {
								$end_width = $opts['height'] * $ratio;
								$end_height = $opts['height'];
							} else {
								$end_height = $opts['width'] / $ratio;
								$end_width = $opts['width'];
							}
							
							$end_width = (round( $end_width ) % 2 == 0 ? round( $end_width ) : round( $end_width ) + 1);
							$end_height = (round( $end_height ) % 2 == 0 ? round( $end_height ) : round( $end_height ) + 1);
							
							$ext = $end_width . 'x' . $end_height;
						}
						
						$sizing = $end_width . 'x' . $end_height;
					} else {
						$sizing = $ext = $opts['width'] . 'x' . $opts['height'];
					}
					array_unshift( $options, '-s ' . $sizing ); // add sizing to the beginning
					if ($opts['duration'] !== $duration) $options[] = '-t ' . $opts['duration']; // only add -t if necessary
					$options[] = ' -b:v ' . $opts['bitrate'] . 'k';
					$options = implode( ' ', $options ); // finish options
					$ext .= '_' . $opts['bitrate'] . 'kbits_' . $opts['duration'] . 'secs'; // finish ext
					
					// this array will be saved to the attachment's metadata
					$encode_data = array( 
						'width'		=> $opts['width'],
						'height'		=> $opts['height'],
						'bitrate'		=> $opts['bitrate'],
						'duration'	=> $opts['duration'],
						'mime_types'	=> array() );
					
					$encode_queue = array( 
						'video/mp4'	=> array( 
										'extension'	=> 'mp4',
										'commands'	=> array( 
														'-an -pass 1 -threads 2 -vcodec libx264',
														'-acodec copy -pass 2 -threads 2 -vcodec libx264' ) ),
						'video/flv'	=> array( 
										'extension'	=> 'flv',
										'commands'	=> array( '-acodec libmp3lame -ar 44100 -vcodec flv -f flv' ) ) );
					
					$ffmpeg_queue = players_get_option( 'ffmpeg_queue' );
					
					foreach( $encode_queue as $mime_type => $encode ) {
						if (!empty( $opts['mime_types'] ) && !in_array( $mime_type, $opts['mime_types'] )) continue; // don't do this mime type
						
						extract( $encode ); // extension and command
						
						$encode_data['mime_types'][$mime_type] = array( 
							'path'	=> $path . $ext . '.' . $extension,
							'url'	=> $url . $ext . '.' . $extension );
						
						$file_path = $encode_data['mime_types'][$mime_type]['path'];
						
						$count = count( $encode['commands'] );
						
						foreach( $encode['commands'] as $index => $command ) {
							$out_file = ($count > 1 ? ' -f rawvideo ' . (players_is_windows() ? 'nul' : '/dev/null') : '"' . $file_path . '"');
							
							$command = sprintf( '%s -i "%s" %s %s -y %s', $ffmpeg_path, $source['path'], $options, $command, $out_file );
							
							$cleanup = ''; // initial cleanup
							if( $index == $count-- ) {
								switch( $mime_type ) {
									case 'video/mp4':
										if (players_verify_qtfaststart())
											$cleanup = array( 'players_qtfaststart', $file_path, $d_log ); // use qt-faststart
										else
											$cleanup = array( 'players_moovrelocator', $file_path, $d_log ); // fallback to moovrelocator
										break;
									
									case 'video/flv':
										// add flvtool2 cleanup command (and output to debug file if debugging)
										if (players_verify_flvtool2()) $cleanup = array( 'players_bg_exec', $flvtool2_path . ' -UP "' . $file_path . ($debug ? '" >> "' . $d_log . '"' : '"') );
										break;
								}
							}
							
							$f_log = $path . $ext . '_' . $extension . '.ffmpeg'; // WxH_bitrate_seconds_type.ffmpeg, e.g. */640x480_0.6_4_flv.ffmpeg
							$command .= ' 2> "' . $f_log . '"'; // add log file to command
							
							$to_add = array( 
								'id'			=> $post_id,
								'command'		=> $command, 
								'cleanup'		=> $cleanup,
								'log'		=> $f_log, 
								'progress'	=> -1,
								'duration'	=> $opts['duration'] );
							
							$dont_add = false;
							foreach( $ffmpeg_queue as $item ) {
								$intersect = array_intersect_assoc( $to_add, $item );
								if( isset( $intersect['command'] ) ) $dont_add = true;
							}
							
							if( $dont_add || ($exists = file_exists( $file_path )) ) { // command already enqueued or file already exists, for instance when source matches a profile
								players_debug_log( $d_log, 'Skipped: ' . $file_path . 
									', already enqueued: ' . players_tf( $dont_add, 'string' ) . ', file exists: ' . players_tf( $exists, 'string' ) );
							} else {
								players_debug_log( $d_log, $command . ($cleanup == '' ? '' : "\n\n" . print_r( $cleanup, true )) );
								array_push( $ffmpeg_queue, $to_add );
							}
						}
					} // end $encode_queue foreach
					
					players_update_option( 'ffmpeg_queue', $ffmpeg_queue ); // update the ffmpeg queue
					
					$data['encodes'][$task] = $encode_data; // add to encode metadata
				} // end $queue foreach
				
				// set default wp metadata for source
				$data['width'] = $width;
				$data['height'] = $height;
			}
		}
	}
	
	return $data; // set attachment metadata
}
add_filter( 'wp_update_attachment_metadata', 'players_video_metadata', 10, 2 );

// Add Debug Log Entry
function players_debug_log( $d_log, $contents, $append = FILE_APPEND ) {
	if (!players_tf( players_get_option( 'debug' ) )) return false; // debug disabled
	
	$log_divider = "\n\n" . str_repeat( '-', 80 ) . "\n\n";
	
	file_put_contents( $d_log, $contents . $log_divider, $append );
	
	return true;
}

// Do qt-faststart
function players_qtfaststart( $file_path, $d_log ) {
	$qtfaststart_path = players_get_option( 'qtfaststart_path' );
	$debug = players_get_option( 'debug' );
	
	$pathinfo = pathinfo( $file_path );
	$temp_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '_qtfs' . $pathinfo['extension'];
	
	players_shell_exec( sprintf( '%s "%s" "%s"', $qtfaststart_path, $file_path, $temp_path ) . ($debug ? ' >> "' . $d_log . '"' : '') );
	
	@unlink( $file_path );
	rename( $temp_path, $file_path );
}

// Moov Relocator
function players_moovrelocator( $file_path, $d_log ) {
	require_once 'assets/moovrelocator/Moovrelocator.class.php';
	
	$moovrelocator = Moovrelocator::getInstance(); // Instantiate Moovrelocator
	
	$failed = __( 'Failed to relocate moov for', 'players' ) . ' ' . $file_path . ': ';	
	if (($result = $moovrelocator->setInput( $file_path )) !== true) return players_debug_log( $d_log, $failed . $result ); // read file, preprocess (parse atoms/boxes)
	if (($result = $moovrelocator->setOutput( $file_path )) !== true) return players_debug_log( $d_log, $failed . $result ); // set the output filename and path
	if (($result = $moovrelocator->fix()) !== true) // moov positioning fix
		 return players_debug_log( $d_log, $failed . $result );
	else
		 return players_debug_log( $d_log, __( 'Moov successfully relocated for', 'players' ) . ' ' . $file_path );
}

// Delete All Conversions And Screenshots
function players_video_delete( $id ) {
	// remove from queue and terminate process if being encoded
	if (players_video_dequeue( $id )) players_terminate_process( players_get_option( 'ffmpeg_process' ) );
	
	clearstatcache();
	
	if( ($directory = players_get_directory( $id )) && is_dir( $directory ) ) {
		foreach( scandir( $directory ) as $item ) {
			if ($item == '.' || $item == '..') continue;
			@unlink( trailingslashit( $directory ) . $item );
		}		
		@rmdir( $directory ); // delete encode directory
		
		return true;
	}
	
	return false;
}
add_filter( 'delete_attachment', 'players_video_delete', 10, 1 );

// Return Encode Directory For A Video Attachment
function players_get_directory( $id ) {
	$metadata = wp_get_attachment_metadata( $id );
	return ((!empty( $metadata['directory'] ) && is_string( $metadata['directory'] )) ? $metadata['directory'] : false);
}

// Return Screenshots For A Video Attachment
function players_get_screenshots( $id ) {
	$metadata = wp_get_attachment_metadata( $id );		
	return (is_array( $metadata['screenshots'] ) ? $metadata['screenshots'] : false);
}

// Return All Encode Data For A Video Attachment
function players_get_encodes( $id, $mime_types = array() ) {
	$metadata = wp_get_attachment_metadata( $id );
	if (!is_array( $encodes = $metadata['encodes'] )) return false;
	
	if( !empty( $mime_types ) ) { // filter allowed mime types
		foreach( $encodes as $index => $encode ) {
			if (!players_verify_mime_types( array_keys( $encode['mime_types'] ), $mime_types )) unset( $encodes[$index] );
		}
	}
	
	return (empty( $encodes ) ? false : $encodes);
}

// Return Encode That Is Closest To Passed Dimensions
function players_select_encode_by_dimensions( $encodes, $width = 0, $height = 0 ) {
	$closest_match = array();
	
	foreach( $encodes as $encode ) {
		if (empty( $closest_match ) && ($closest_match = $encode))  continue; // nothing to test against yet, set first match
		
		if( $width > 0 && $height > 0 ) {
			$wh = $width + $height;
			$closest_wh = $closest_match['width'] + $closest_match['height'];
			$encode_wh = $encode['width'] + $encode['height'];
		} else if( $width > 0 ) {
			$wh = $width;
			$closest_wh = $closest_match['width'];
			$encode_wh = $encode['width'];
		} else {
			$wh = $height;
			$closest_wh = $closest_match['height'];
			$encode_wh = $encode['height'];
		}
		
		if (abs( $wh - $closest_wh ) > abs( $wh - $encode_wh ))
			$closest_match = $encode; // greater difference in current closest match, so set to new encode
	}
	
	return $closest_match;
}

// Return Encode That Is Closest To Passed Bitrate @TODO make this function...
function players_select_encode_by_bitrate( $encodes, $bitrate ) {
	$closest_match = array();
	
	foreach( $encodes as $encode ) {		
		if (empty( $closest_match ) && ($closest_match = $encode))  continue; // nothing to test against yet, set first match
	}
	
	return $closest_match;
}

// Return Encoded Video Thumbnail If It Exists
function players_encoded_icon( $orig, $type ) {
	global $post, $players_video_icon;
	
	if ($players_video_icon) return $orig;
		
	$id = (isset( $post ) ? $post->ID : $_GET['attachment_id']);
		
	$screenshots = players_get_screenshots( $id );
	
	if( !empty( $screenshots ) ) {
		switch( $type ) {
			case 'icon':
				$orig = $screenshots[0]['url'];
				break;
			case 'dir_uri':
				$orig = dirname( $screenshots[0]['url'] );
				break;
			case 'dir_path':
				$orig = dirname( $screenshots[0]['path'] );
				break;
		}
	}
	
	return $orig;
}

// Filter Thumbnail/Icon On Uploads Page
function players_attachment_image_attributes( $attr, $attachment ) {
	if( players_is_video( $attachment->post_mime_type ) ) {
		$icon = players_encoded_icon( '', 'icon' );
		
		if( $icon != '' ) {
			$attr['src'] = players_timthumb( $icon, 66, 60 );
			$attr['class'] .= ' encoded';
		} else {
			$attr['class'] .= ' not-encoded';
		}
	}
	
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'players_attachment_image_attributes', 10, 2 );

// Add To Head On Uploads Page
function players_upload_admin_head() {
?>
	<style type="text/css">
		.encoded, .not-encoded, .encoding {
			padding-left:14px;
			background:url(<?php echo plugins_url( 'assets/images/encoded.png', PLAYERS_PATH ); ?>) 3px center no-repeat;
		}
		.encoded { width:66px; background-color:#C7F464 !important; }
		.not-encoded { background-color:#FF6B6B !important; }
		.encoding { background-color:#FF0 !important; }
	</style>
    <script type="text/javascript">
	var a_ids = new Array();
	var c_ids = new Array();
	var wait = false;
	;(function($) {
		function update_progress( initial ) {
			if (!wait) add_ids();
			if (!wait) cancel_ids();
			if( wait ) {				
				setTimeout( update_progress, 100 );
				return false;
			}
			
			$.post( ajaxurl, { action: 'encode_progress' }, function( response ) {
				//console.log( response ); // for debugging
				
				response = $.parseJSON( response );
				
				$('#the-list tr').each( function( i, v ) {
					var obj = $(this);
					var imgobj = $('td.column-icon a img.attachment-80x60', obj);
					var id = parseInt( obj.attr( 'id' ).replace( 'post-', '' ) );
					var prog = dur = num = 0;
					
					if( response ) {
						$(response).each( function( index, value ) {
							if( parseInt( value.id ) == parseInt( id ) ) {
								if( value.progress > 0 ) {
									dur = value.duration;
									prog = value.progress
								}
								
								num++;
							}
						});
					}
					
					if( num > 0 ) {
						if( ($.inArray( id, a_ids ) == -1) && ($.inArray( id, c_ids ) == -1) ) {
							if (prog == 0)
								$('.status', obj).text( '<?php _e( 'Waiting for turn...', 'players' ); ?>' ).show();
							else
								$('.status', obj).text( Math.round((prog / dur) * 100) + '% encoded (' + num + ' job' + (num > 1 ? 's' : '') + ' remaining)' ).show();
							
							$('.cancel', obj).show();
							$('.new, .complete', obj).hide();
							imgobj.attr( 'src', '<?php echo players_video_icon(); ?>' ).width( 46 ).height( 60 ).removeClass( 'not-encoded encoded' ).addClass( 'encoding' );
						}
					} else {
						if( imgobj.hasClass( 'encoding' ) || imgobj.hasClass( 'not-encoding' ) ) {							
							$.get( ajaxurl, { action: 'encode_icon', attachment_id: id }, function( response ) {							
								imgobj.attr( 'src', response ).width( 66 ).height( 60 ).removeClass( 'not-encoded encoding' ).addClass( 'encoded' );
								$('.new, .cancel', obj).hide();
								$('.complete', obj).show();
								$('.status', obj).text( '<?php _e( 'Encoding complete!', 'players' ); ?>' ).show();
							});
						} else if( initial && imgobj.hasClass( 'encoded' ) ) {
							$('.status', obj).hide();
							$('.complete', obj).show();
						}
					}
							
				});
					
				setTimeout( update_progress, 1000 );
			});
			
			return true;
		}
		
		function extract_id( obj ) {
			return parseInt( $(obj).parents( 'tr' ).attr( 'id' ).replace( 'post-', '' ) );
		}
		
		function cancel_ids() {			
			var id = c_ids.shift();
			if (typeof id !== 'number') return;
			var obj = $('#post-' + id);
			
			wait = true;
			$.post( ajaxurl, { action: 'encode_from_upload', id: id, do_action: 'cancel' }, function( response ) {					
				$('.complete, .cancel', obj).hide();
				$('.new', obj).show();
				$('.status', obj).text( response ).show();
				kill_icon( obj );
				
				wait = false;
			});
		}
		
		function add_ids() {			
			var id = a_ids.shift();
			if (typeof id !== 'number') return;
			var obj = $('#post-' + id);
			var imgobj = $('td.column-icon a img.attachment-80x60', obj);
			
			wait = true;
			$.post( ajaxurl, { action: 'encode_from_upload', id: id, do_action: 'add' }, function( response ) {
				$('.status', obj).text( response ).show();
				imgobj.attr( 'src', '<?php echo players_video_icon(); ?>' ).width( 46 ).height( 60 ).removeClass( 'not-encoded encoded' ).addClass( 'encoding' );
				
				wait = false;
			});
		}
		
		function kill_icon( obj ) {
			var imgobj = $('td.column-icon a img.attachment-80x60', obj);
			
			imgobj.attr( 'src', '<?php echo players_video_icon(); ?>' );
			imgobj.width( 46 ).height( 60 ).removeClass( 'encoded encoding' ).addClass( 'not-encoded' );
		}
		
		$(document).ready(function() {
			update_progress( true ); // kick off progress update
			
			$('.encode_add, .encode_cancel').click( function() {
				var id = extract_id( this );
				var obj = $('#post-' + id);
				
				$('.new, .complete, .cancel', obj).hide();
				$('.status', obj).text( '<?php _e( 'Sending request...', 'players' ); ?>' ).show();
				
				if ($(this).hasClass( 'encode_add' ))
					a_ids.push( id );
				else
					c_ids.push( id );
			});
			
			$('.encode_delete').click( function() {				
				var id = extract_id( this );
				var obj = $('#post-' + id);
				
				$.post( ajaxurl, { action: 'encode_from_upload', id: id, do_action: 'delete' }, function( response ) {
					$('.complete', obj).hide();
					$('.new', obj).show();
					$('.status', obj).text( response ).show();
					
					kill_icon( obj );
				});
			});
		});
	})(jQuery);
	</script>
<?php
}
add_action( 'admin_head-upload.php', 'players_upload_admin_head' );

// Filter Attachment Icon
function players_icon_dir( $dir ) {
	return players_encoded_icon( $dir, 'dir_path' );
}
add_filter( 'icon_dir', 'players_icon_dir' );

// Filter Attachment Icon
function players_icon_dir_uri( $dir ) {
	return players_encoded_icon( $dir, 'dir_uri' );
}
add_filter( 'icon_dir_uri', 'players_icon_dir_uri' );

// Filter Attachment Icon
function players_wp_mime_type_icon( $icon, $mime, $post_id ) {
	return players_encoded_icon( $icon, 'icon' );
}
add_filter( 'wp_mime_type_icon', 'players_wp_mime_type_icon', 10, 3 );

// Video Mime Icon Fix
function players_video_icon() {
	global $players_video_icon;
	 
	$players_video_icon = true;
	
	$return = wp_mime_type_icon( 'video' );
	
	$players_video_icon = false;
	
	return $return;
}

// Initiate Custom Columns
function players_add_upload_columns( $columns ) {
	$new_columns = array();
	
	$i = 0;	
	foreach( $columns as $key => $value ) {
		$new_columns[$key] = $value;
		
		if ($i++ == 2) $new_columns['encode'] = __( 'Encode', 'players' );
	}
	
	return $new_columns;
}
add_filter( 'manage_media_columns', 'players_add_upload_columns' );

// Handle Custom Columns
function players_do_upload_columns( $column, $post_id )
{
	switch( $column ) {
		case 'encode':
			$post = get_post( $post_id );
			if( players_is_video( $post->post_mime_type ) ) {
				$encodes = players_tf( players_get_encodes( $post_id ) );
				$encode_possible = players_verify_ffmpeg();
				echo '<div class="complete hide-if-js">';
				if ($encode_possible) echo '<a href="javascript:void(0);" class="encode_add">' . __( 'Re-Encode', 'players' ) . '</a> | ';
				echo '<a href="javascript:void(0);" class="encode_delete">' . __( 'Delete Encodes', 'players' ) . '</a></div><div class="new' . ($encodes ? ' hide-if-js' : '') . '">';
				if ($encode_possible)
					echo '<a href="javascript:void(0);" class="encode_add">' . __( 'Encode', 'players' ) . '</a>';
				else 
					echo __( 'The current path to', 'players' ) . ' FFMPEG ' . __( 'is invalid', 'players' );
				echo '</div><div><span class="status' . ($encodes ? '' : ' hide-if-js') . '">' 
					. __( 'Loading...', 'players' ) . '</span> <span class="cancel hide-if-js"><a href="javascript:void(0);" class="encode_cancel">' . __( 'cancel', 'players' ) . '</a></span></div>';
			}
			break;
	}
}
add_action( 'manage_media_custom_column', 'players_do_upload_columns', 1, 2 );

// Clear Items With Passed ID From Queue
function players_video_dequeue( $id ) {
	$queue = players_get_option( 'ffmpeg_queue' );
	
	if (!is_array( $queue )) return 0; // stop here if not an array

	$result = ($queue[0]['id'] == $id); // was currently being encoded
	
	foreach( $queue as $index => $item ) {
		if ($item['id'] == $id) unset( $queue[$index] );
	}
	
	players_update_option( 'ffmpeg_queue', array_values( $queue ) );
	
	return $result;
}

// Encode From Media
function players_encode_from_upload() {
	$post_id = $_POST['id'];
	$action = $_POST['do_action'];
	$process = players_get_option( 'ffmpeg_process' );
	
	switch( $action ) {
		case 'cancel':
			players_video_dequeue( $post_id ); // clear any items with the id from the queue
			
			players_terminate_process( $process ); // terminate ffmpeg process
			
			$_POST['do_action'] = 'delete'; // set flag to delete files
			
			$result = __( 'Encoding canceled.', 'players' );
			break;
		case 'add':
			$result = __( 'Added to queue...', 'players' );
			break;
		case 'delete':
			$result = __( 'Encodings deleted.', 'players' );
			break;
	}
	
	wp_update_attachment_metadata( $post_id, wp_get_attachment_metadata( $post_id ) );
	
	die( $result );
}
add_action( 'wp_ajax_encode_from_upload', 'players_encode_from_upload' );

// Encode Progress
function players_encode_progress() {
	die( json_encode( players_get_option( 'ffmpeg_queue' ) ) );
}
add_action( 'wp_ajax_encode_progress', 'players_encode_progress' );

// Encode Icon
function players_encode_icon() {
	$attr = players_attachment_image_attributes( array(), (object)array( 'post_mime_type' => 'video' ) );
	die( $attr['src'] );
}
add_action( 'wp_ajax_encode_icon', 'players_encode_icon' );

// Return Streamer Link
function players_streamer( $file_path ) {
	return plugins_url( 'assets/streamer.php?file_path=', PLAYERS_PATH ) . $file_path;
}

/************************************************************************************************
 *									END VIDEO									 *
 ************************************************************************************************/

// Utility: Verify PHP OS
function players_is_windows() {
	return (strpos( PHP_OS, 'WIN' ) === 0);
}

// Utility: Exec In Background On Both Windows And *nix (returns pid)
function players_bg_exec( $cmd ) {
	$cmd = players_path( $cmd );
	
	if( players_is_windows() ) {
		pclose( popen( 'start /B "bg" ' . players_nullify_command( $cmd ), 'r') );
		
		// get the pid
		$imagename = substr( $cmd, 0, strpos( $cmd, ' ' ) );
		if (stristr( $imagename, '/' )) $imagename = substr( $imagename, (strrpos( $imagename, '/' ) - strlen( $imagename ) + 1) );
		exec( 'TASKLIST /NH /FO "CSV" /FI "imagename eq ' . $imagename . '" /FI "cputime eq 00:00:00"', $output );
		$pid = explode( '","', array_pop( $output ) );
		
		return (is_numeric( $pid[1] ) ? (int)$pid[1] : -1); // return pid or -1
	} else {	
		return exec( players_nullify_command( $cmd ) . ' & echo $! & disown' ); // @TODO make sure this returns the pid
	}
}

// Utility: Set stdout And stderr To Null If Not Set
function players_nullify_command( $cmd ) {
	$null = (players_is_windows() ? 'nul' : '/dev/null');
	return $cmd . (!stristr( $cmd, '2>' ) ? ' 2> ' . $null : '') . (!stristr( str_replace( '2>', '', $cmd ), '>' ) ? ' > ' . $null : '');
}

// Utility: Verify A Process Exists
function players_process_exists( $pid ) {
	if (!is_numeric( $pid )) return false;
	
	$pid = (int)$pid;
	
	if( players_is_windows() ) {
		if (!stristr( players_shell_exec( 'TASKLIST /NH /FO "CSV" /FI "pid eq ' . $pid . '"' ), 'INFO: No task' )) return true;
	} else {
		exec( 'ps ' . $pid, $process_state );
		if (count( $process_state ) >= 2) return true;
	}
	
	return false;
}

// Utility: Terminate A Process
function players_terminate_process( $pid ) {
	if (!players_process_exists( $pid )) return false;

	$pid = (int)$pid;
	
	if( players_is_windows() ) {			
		players_shell_exec( 'TASKKILL /F /PID ' . $pid );
	} else {
		players_shell_exec( 'kill -KILL ' . $pid );
	}
	
	sleep( 1 ); // allow time for process to be closed
	
	return true;
}

// Utility: Verify Post Type
function players_verify_post_type() {
	global $post_type;
	
	if (isset( $_GET['post_type'] ) && $_GET['post_type'] == 'player'
		|| isset( $post_type ) && $post_type == 'player') return true; // return true if on a player page
		
	return false;
}

// Utility: Generate Shortcode Input
function players_embed_input( $post_id ) {
	return '<input class="text urlfield" readonly="readonly" 
		value="[player id=&quot;' . $post_id . '&quot;]" type="text">';
}

// Utility: Sort By Order
function players_sort_order( $a, $b ) {
	if ($a['order'] == $b['order']) return 0;
	return ($a['order'] < $b['order'] ? -1 : 1);
}

// Utility: Verify Player ID
function players_verify_id( $id ) {
	global $players_used_ids;
	
	while (in_array( $id, $players_used_ids )) $id++;
	
	array_push( $players_used_ids, $id );
	
	return $id;
}

// Utility: Verify A URL
function players_validate_url( $url ) {
	return (bool)preg_match( "/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i", $url );
}

// Utility: Escape Path
function players_path( $path ) {
	return str_replace( '\\', '/', trim( $path ) );
}

// Utility: Return String Between
function players_string_between( $string, $start, $end, $case_insensitive = false ) {
	if( $case_insensitive ) {
		$string = strtolower( $string );
		$start = strtolower( $start );
		$end = strtolower( $end );
	}
	
	$parts = explode( $start, $string );
	
	if( isset( $parts[1] ) ) {
		$parts = explode( $end, $parts[1] );
		return $parts[0];
	}
	
	return false;
}

// Utility: Return True Or False
function players_tf( $val, $type = 'int' ) {
	switch( $type ) {
		case 'int':
			return (empty( $val ) ? 0 : 1);
			break;
		case 'string':
			return (empty( $val ) ? 'false' : 'true');
			break;
		case 'bool':
			return (empty( $val ) ? false : true);
			break;
	}
}

// Utility: Generate A Checkbox
function players_checkbox( $value, $label, $name, $checked = '', $class ) {
	return '<p class="less-margin' . (!empty( $class ) ? ' ' . $class : '') . '">
		<input type="hidden" name="' . $name . '[' . $value . ']" value="0" />
		<input type="checkbox" name="' . $name . '[' . $value . ']" id="' . $name . '[' . $value . ']" 
		value="' . $value . '"' . ($value == $checked ? 'checked="checked"' : '') . ' /> 
		<label for="' . $name . '[' . $value . ']">' . $label . '</label></p>';
}

// Utility: Generate A Radio
function players_radio( $value, $key, $name, $checked = '' ) {
	return '<p class="less-margin"><input type="radio" name="' . $name . '" id="' . $name . '[' . $value . ']" 
		value="' . $value . '"' . ($value == $checked ? 'checked="checked"' : '') . ' /> 
		<label for="' . $name . '[' . $value . ']">' . $key . '</label></p>';
}

// Utility: Resize Image With Timthumb
function players_timthumb( $url, $width, $height, $align = 'c', $zc = 1, $q = 100 ) {
	global $players_timthumb_path;
	
	if( is_numeric( $url ) ) {
		$attachment_image_src = wp_get_attachment_image_src( $url, 'full' );
		$url = $attachment_image_src[0];
	}
	
	return $players_timthumb_path . '?src=' . $url . '&amp;w=' . $width . '&amp;h=' . $height . 
		'&amp;a=' . $align . '&amp;zc=' . $zc . '&amp;q=' . $q;
}

// Plugin Initialization
function players_init() {
	// Add custom post type
	register_post_type( 'player', array(
		'labels' 				=> array(
									'name' 				=> __( 'Players', 'players' ),
									'singular_name' 		=> __( 'Player', 'players' ),
									'add_new' 			=> __( 'Add New', 'players' ),
									'add_new_item' 		=> __( 'Add New Player', 'players' ),
									'edit_item' 			=> __( 'Edit Player', 'players' ),
									'new_item' 			=> __( 'New Player', 'players' ),
									'view_item' 			=> __( 'View Player', 'players' ),
									'search_items' 		=> __( 'Search Players', 'players' ),
									'not_found' 			=> __( 'No players found', 'players' ),
									'not_found_in_trash' 	=> __( 'No players found in Trash', 'players' ), 
									'parent_item_colon' 	=> '' ),
		'label' 				=> __( 'Players', 'players' ),
		'singular_label' 		=> __( 'Player', 'players' ),
		'public' 				=> true,
		'exclude_from_search' 	=> true,
		'show_ui' 			=> true,
		'capability_type' 		=> 'post',
		'hierarchical' 		=> false,
		'rewrite' 			=> array( 
								'slug' 		=> 'players', 
								'with_front' 	=> false ),
		'query_var' 			=> true,
		'supports' 			=> array( 'title' ),
		'menu_position' 		=> 5,
		'show_in_nav_menus' 	=> true,
		'register_meta_box_cb' 	=> 'players_meta_box_callback' ) );
}
add_action( 'init', 'players_init' );

// Admin Init
function players_admin_init() {
	global $players_options, $players_options_page;
	
	// setup options page
	register_setting( $players_options, $players_options, 'players_options_validate' );
	$section = $players_options . '_s1';
	add_settings_section( $section, __( 'Video Encoding', 'players' ), 'players_encoding_options_section', $players_options_page );
	$callback = 'players_options_field';
	add_settings_field( $section . '_f1', __( 'Auto encode after upload', 'players' ), $callback, $players_options_page, $section, array( 'label_for' => 'automatic' ) );
	add_settings_field( $section . '_f2', __( 'Screenshot percentage', 'players' ), $callback, $players_options_page, $section, array( 'label_for' => 'screenshot' ) );
	add_settings_field( $section . '_f3', __( 'Screenshot sequence', 'players' ), $callback, $players_options_page, $section, array( 'label_for' => 'sequence' ) );
	add_settings_field( $section . '_f4', __( 'Debug', 'players' ), $callback, $players_options_page, $section, array( 'label_for' => 'debug' ) );
	add_settings_field( $section . '_f5', __( 'Sample', 'players' ), $callback, $players_options_page, $section, array( 'label_for' => 'sample' ) );
	add_settings_field( $section . '_f6', __( 'Path to FFMPEG', 'players' ), $callback, $players_options_page, $section, array( 'label_for' => 'ffmpeg_path' ) );
	add_settings_field( $section . '_f7', __( 'Path to FFMPEG preset', 'players' ), $callback, $players_options_page, $section, array( 'label_for' => 'ffmpeg_preset' ) );
	add_settings_field( $section . '_f8', __( 'Path to FLVTOOL2', 'players' ), $callback, $players_options_page, $section, array( 'label_for' => 'flvtool2_path' ) );
	add_settings_field( $section . '_f9', __( 'Path to QT-FASTSTART', 'players' ), $callback, $players_options_page, $section, array( 'label_for' => 'qtfaststart_path' ) );
}
add_action( 'admin_init', 'players_admin_init', 9 );

// Add To Admin Menu
function players_admin_menu() {
	global $players_options_page;
	add_submenu_page( 'edit.php?post_type=player', __( 'Options', 'players' ), __( 'Options', 'players' ), 'manage_options', $players_options_page, 'players_options_page' );
}
add_action( 'admin_menu', 'players_admin_menu' );

// Add "Settings" Link To Plugins Page
function players_action_links( $links, $file ) {
	global $players_options_page;
	
	if ($file == plugin_basename( PLAYERS_PATH )) $links[] = '<a href="edit.php?post_type=player&page=' . $players_options_page . '">' . __( 'Settings' ) . '</a>';

	return $links;
}
add_filter( 'plugin_action_links', 'players_action_links', 10, 2 );

// Add To Admin Head
function players_admin_head() { ?>
	<style type="text/css">
		/* icons */
		#menu-posts-player .wp-menu-image {
			background:url(<?php echo plugins_url( 'assets/images/icon.png', PLAYERS_PATH ); ?>) no-repeat 6px -33px !important;
		}
		#menu-posts-player:hover .wp-menu-image,
		#menu-posts-player.wp-has-current-submenu .wp-menu-image {
			background-position:6px -1px !important;
		}
		#icon-edit.icon32-posts-player {
			background: url(<?php echo plugins_url( 'assets/images/icon32.png', PLAYERS_PATH ); ?>) no-repeat;
		}
	</style>
<?php }
add_action( 'admin_head', 'players_admin_head' );

// Get Option
function players_get_option( $option ) {
	global $players_options;
	
	$options = get_option( $players_options );

	return (is_string( $options[$option] ) ? stripslashes( $options[$option] ) : $options[$option]);
}

// Set Option
function players_update_option( $option, $value ) {
	global $players_options;
	
	$options = get_option( $players_options );
	
	$options[$option] = $value;
	
	return update_option( $players_options, $options );
}

// Options Page Admin Notices
function players_options_admin_notices() {
	if (!players_verify_post_type() || !isset( $_GET['page'] ) || ($_GET['page'] !== 'options')) return;
	
	players_update_option( 'ffmpeg_capabilities', $capabilities = players_get_ffmpeg_capabilities() );
	if (in_array( 0, $capabilities )) $outdated = '<strong>' . __( 'but is outdated and should be updated if possible.', 'players' ) . '</strong>';
	
	// queue error/success messages
	$error = $success = array();
	
	if ($ffmpeg_version = players_verify_ffmpeg())
		array_push( $success, 'FFMPEG ' . __( 'version', 'players' ) . ' ' . $ffmpeg_version . ' '  . 
			__( 'successfully detected using path', 'players' ) . 
			' <code>' . players_get_option( 'ffmpeg_path' ) . '</code>' . (isset( $outdated ) ? ' ' . $outdated : '') );
	else
		array_push( $error, __( 'The current path to', 'players' ) . ' FFMPEG ' . 
			__( 'is invalid', 'players' ) . ' &mdash; ' . __( 'Videos will not be encoded.', 'players' ) );
		
	if ($flvtool2_version = players_verify_flvtool2())
		array_push( $success, 'FLVTOOL2 ' . __( 'version', 'players' ) . ' ' . $flvtool2_version . ' ' . 
			__( 'successfully detected using path', 'players' ) . 
			' <code>' . players_get_option( 'flvtool2_path' ) . '</code>' );
	else
		array_push( $error, __( 'The current path to', 'players' ) . ' FLVTOOL2 ' . 
			__( 'is invalid', 'players' ) . ' &mdash; ' . __( 'Metadata will not be inserted into FLV encodes.', 'players' ) );
			
	if (players_verify_qtfaststart())
		array_push( $success, 'QT-FASTSTART ' . 
			__( 'successfully detected using path', 'players' ) . 
			' <code>' . players_get_option( 'qtfaststart_path' ) . '</code>' );
	else
		array_push( $error, __( 'The current path to', 'players' ) . ' QT-FASTSTART ' . 
			__( 'is invalid', 'players' ) . ' &mdash; ' . __( 'Will fallback to the less optimal moovrelocator script.', 'players' ) );
	
	// if exec not supported, override all error messages with this one
	if (!players_verify_exec())
		$error = array( __( 'Video encoding will not work because' ) . ' <code>exec()</code> ' . __( 'has been disabled on this server. PHP Safe Mode most likely needs to be turned OFF. Contact your hosting company.', 'players' ) );
	
	// output error/success messages
	foreach ($error as $message) echo '<div class="error"><p>' . $message . '</p></div>';
	
	if( isset( $_GET['settings-updated'] ) ) {
		foreach ($success as $message) echo '<div class="updated"><p>' . $message . '</p></div>';
	}
}
add_action( 'admin_notices', 'players_options_admin_notices', 9 );

// Validate Players Options
function players_options_validate( $input ) {
	global $players_options;
	
	$options = array_merge( get_option( $players_options ), $input );
	
	$options['automatic'] = esc_attr( $input['automatic'] );
	
	$screenshot = esc_attr( $input['screenshot'] );
	if( is_numeric( $screenshot ) && $screenshot > 0 && $screenshot <= 100 ) {
		$options['screenshot'] = (int)$screenshot;
	} else {
		$options['screenshot'] = 50;
		//array_push( $error, __( 'Invalid screenshot percentage. Defaulting to 50.', 'players' ) );
	}
	
	$options['sequence'] = ((is_numeric( $sequence = esc_attr( $input['sequence'] ) ) && $sequence > 1) ? (int)$sequence : 0);
	
	$options['debug'] = esc_attr( (isset( $input['debug'] ) ? $input['debug'] : '') );
	$options['sample'] = esc_attr( (isset( $input['sample'] ) ? $input['sample'] : '') );
	
	$options['ffmpeg_path'] = esc_attr( players_path( $input['ffmpeg_path'] ) );
	$options['ffmpeg_preset'] = esc_attr( players_path( $input['ffmpeg_preset'] ) );
	
	$options['flvtool2_path'] = esc_attr( players_path( $input['flvtool2_path'] ) );
	
	$options['qtfaststart_path'] = esc_attr( players_path( $input['qtfaststart_path'] ) );
	
	return $options;
}

// "Video Encoding" Options Page Section
function players_encoding_options_section() {
	// could echo a description if necessary...
	if (!empty( $_GET['ffmpeg_info'] )) echo '<pre>' . players_shell_exec( players_get_option( 'ffmpeg_path' ) . ' -version' ) . '</pre>';
}

// Handle Options Page Setting Fields
function players_options_field( $field ) {
	global $players_options;
	
	$options = get_option( $players_options );
	
	switch( $field['label_for'] ) {
		case 'automatic': ?>
			<select name="<?php echo $players_options; ?>[automatic]" id="automatic">
				<option value="enabled"<?php selected( $options['automatic'], 'enabled' ); ?>><?php _e( 'Enabled', 'players' ); ?></option>
				<option value="disabled"<?php selected( $options['automatic'], 'disabled' ); ?>><?php _e( 'Disabled', 'players' ); ?></option>
			</select>
			<span class="description">
				<?php _e( 'Videos can always be encoded by hand after uploading from the', 'players' ); ?> 
				<a href="<?php echo get_admin_url(); ?>/upload.php"><?php _e( 'Media Library', 'players' ); ?></a> 
				<?php _e( 'page.', 'players' ); ?>
			</span>
			<?php break;
			case 'screenshot': ?>
				<input name="<?php echo $players_options; ?>[screenshot]" type="text" id="screenshot" value="<?php echo esc_attr( $options['screenshot'] ); ?>" class="small-text" /><code>%</code>
				<span class="description"><?php _e( 'A primary screenshot will be taken at this percentage through the video. Enter a number between 1 and 100.', 'players' ); ?></span>
				<?php break;
			case 'sequence': ?>
				<input name="<?php echo $players_options; ?>[sequence]" type="text" id="sequence" value="<?php echo esc_attr( $options['sequence'] ); ?>" class="small-text" />
				<span class="description"><?php _e( 'This number of secondary screenshots will be taken evenly throughout the video. Anything less than 2 will disable this feature.', 'players' ); ?></span>
				<?php break;
			case 'debug': ?>
				<input name="<?php echo $players_options; ?>[debug]" type="checkbox" id="debug" value="1" <?php checked( '1', $options['debug'] ); ?> />
				<span class="description"><?php _e( 'Output debug files to the encodes directory of the attachment', 'players' ); ?> (<?php _e( 'located at', 'players' ); ?> <code>/<?php $upload_dir = wp_upload_dir(); echo str_replace( ABSPATH, '', $upload_dir['basedir'] ); ?>/[<?php _e( 'year', 'players' ); ?>]/[<?php _e( 'month', 'players' ); ?>]/[<?php _e( 'attachment_filename', 'players' ); ?>]/</code>).</span>
				<?php break;
			case 'sample': ?>
				<input name="<?php echo $players_options; ?>[sample]" type="checkbox" id="sample" value="1" <?php checked( '1', $options['sample'] ); ?> />
				<span class="description"><?php _e( 'Only encode a short sample (the first 30 seconds).', 'players' ); ?></span>
				<?php break;
			case 'ffmpeg_path': ?>
				<input name="<?php echo $players_options; ?>[ffmpeg_path]" type="text" id="ffmpeg_path" value="<?php echo esc_attr( $options['ffmpeg_path'] ); ?>" class="regular-text" />
				<span class="description"><?php _e( 'Will most likely be', 'players' ) ?> <code>/usr/bin/ffmpeg</code> <?php _e( 'or', 'players' ) ?> <code>ffmpeg</code>.</span>
				<?php break;
			case 'ffmpeg_preset': ?>
				<input name="<?php echo $players_options; ?>[ffmpeg_preset]" type="text" id="ffmpeg_preset" value="<?php echo esc_attr( $options['ffmpeg_preset'] ); ?>" class="regular-text" />
				<span class="description"><?php _e( 'Preset name or path to an FFMPEG preset file. Default is <code>hq</code>.', 'players' ); ?></span>
				<?php break;
			case 'flvtool2_path': ?>
				<input name="<?php echo $players_options; ?>[flvtool2_path]" type="text" id="flvtool2_path" value="<?php echo esc_attr( $options['flvtool2_path'] ); ?>" class="regular-text" />
				<span class="description"><?php _e( 'Will most likely be <code>/usr/bin/flvtool2</code> or <code>flvtool2</code>.', 'players' ); ?></span>
				<?php break;
			case 'qtfaststart_path': ?>
				<input name="<?php echo $players_options; ?>[qtfaststart_path]" type="text" id="qtfaststart_path" value="<?php echo esc_attr( $options['qtfaststart_path'] ); ?>" class="regular-text" />
				<span class="description"><?php _e( 'Will most likely be <code>/usr/bin/qt-faststart</code> or <code>qt-faststart</code>.', 'players' ); ?></span>
				<?php break;
	}
}


// Options Admin Page
function players_options_page() {
	global $players_options, $players_options_page;
	?>
	<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php esc_html_e( 'Options', 'players' ); ?></h2>
	
	<form action="options.php" method="post">
	<?php settings_fields( $players_options );
	do_settings_sections( $players_options_page ); ?>
	<?php submit_button(); ?>
	</form>
	
	<h3 class="title">Add Your Own Controllers</h3>
	
	<p>There are three default player controllers but you are not limited to these. It is possible to add your own controller using a filter and a callback function from within a plugin or functions.php file.</p>
	
	<p>The filter <code>players_controllers</code> passes the array of default controllers. Manipulate this array to add, remove or modify controllers.</p>
	
	<p>Each controller in the array of default controllers must define a <code>shortcode_cb</code> that references a callback function. This callback will be passed five variables:</p>
	
	<ol>
		<li><code>unique ID</code> The unique ID should be used to identify each player being added to a page so that multiple players can be embedded multiple times on one page.</li>
		<li><code>title</code> The title given to the player.</li>
		<li><code>dimensions</code> The dimensions variable is an array with two associative keys: <code>width</code> and <code>height</code>.</li>
		<li><code>options</code> The options variable is an array of the options specified for the selected controller, again from the edit page.</li>
		<li><code>attachments</code> Finally, the attachments variable is an ordered array of standard WordPress attachment objects. Use <code>var_dump()</code> to explore the objects. Every player will need to use a loop to iterate through this variable.</li>
	</ol>
	
	<?php
	$directory = dirname( PLAYERS_PATH ) . '/controllers/';
	$glob = glob( $directory . '*.php' );
	$count = count( $glob );
	$files = '';
	foreach( $glob as $file ) 
		$files .= '<code>' . basename( $file ) . '</code>' . ($count-- == 2 ? ' ' . __( 'and', 'players' ) . ' ' : ($count > 1 ? ', ' : ''));
	?>
	
	<p>For examples, look at the default controller files <?php echo $files; ?> in <code><?php echo $directory; ?></code>.</p>
	
	</div><!-- .wrap -->
	<?php	
}

// Callback From register_post_type In players_init
function players_meta_box_callback() {
	global $wpdb, $post, $players_interface, $players_units, $players_controllers;
	
	$player = get_post_meta( $post->ID, 'player', true );
	
	// make sure the current controller exists, set it to first available controller if not
	if( !isset( $player['controller'] ) || !isset( $players_controllers[$player['controller']] ) ) {
		$controller_keys = array_keys( $players_controllers );
		$player['controller'] = $controller_keys[0];
		update_post_meta( $post->ID, 'player', $player );
	}
	
	$players_units = array_merge( $players_units, players_units( $post->ID ) ); // add new units
	usort( $players_units, 'players_sort_order' ); // order the units
	
	// Add custom meta boxes to custom post type
	add_meta_box( 'players_units', __( 'Media', 'players' ), 'players_units_meta', 'player', 'normal', 'low' );
	add_meta_box( 'players_library', __( 'Media Library', 'players' ), 'players_library_meta', 'player', 'normal', 'low' );
	add_meta_box( 'players_shortcode', __( 'Shortcode', 'players' ), 'players_shortcode_meta', 'player', 'side', 'low' );
	add_meta_box( 'players_controller', __( 'Controller', 'players' ), 'players_controller_meta', 'player', 'side', 'low' );
	add_meta_box( 'players_options', __( 'Options', 'players' ), 'players_options_meta', 'player', 'side', 'low' );
}

// Output "units" Meta Box
function players_units_meta() {
	echo '<input type="hidden" name="players_noncename" id="players_noncename" 
		value="' . wp_create_nonce( plugin_basename( PLAYERS_PATH ) ) . '" />'; // security nonce
		
	players_meta_box( 'units' );
}

// Output "Media Library" Meta Box
function players_library_meta() {
	global $players_interface, $players_units;
	
	$mime_types = $years = $months = array();
	foreach( $players_units as $unit ) {
		if( is_array( $type = $unit['attachment']['type'] ) ) {
			$mime_types = array_unique( array_merge( $type, $mime_types ) );
		} else {
			if (!in_array( $type = $unit['attachment']['type'], $mime_types )) array_push( $mime_types, $type );
		}
		if (!in_array( $year = $unit['attachment']['year'], $years )) array_push( $years, $year );
		if (!in_array( $month = $unit['attachment']['month'], $months )) array_push( $months, $month );
		if (!isset( $month_years[$month] )) $month_years[$month] = array();
		if (!in_array( $year = 'y' . $year, $month_years[$month] )) array_push( $month_years[$month], $year );
	}
	
	sort( $years, SORT_NUMERIC );
	sort( $months, SORT_NUMERIC );
	
	players_meta_box( 'library' );
?>
	<div id="libraryFilters">
		<div> <em><?php _e( 'Library Filters', 'players' ); ?></em> </div>
		<div class="filterGroup"> <a href="#" id="clear-filters"><?php _e( 'Clear', 'players' ); ?></a> </div>
		<div class="filterGroup">
			<label for="filter_year"> <?php _e( 'Year', 'players' ); ?>: </label>
			<select id="filter_year">
				<option value="all">&infin;&nbsp;</option>
				<?php foreach ($years as $year) : ?>
				<option value="<?php echo $year; ?>"><?php echo $year; ?></option>
				<?php endforeach; ?>
			</select>
			<label for="filter_month">&nbsp;<?php _e( 'Month', 'players' ); ?>: </label>
			<select id="filter_month">
				<option value="all">&infin;&nbsp;</option>
				<?php foreach ($months as $month) : ?>
				<option value="<?php echo $month; ?>" class="<?php echo implode( ' ', $month_years[$month] ); ?>"><?php echo $month; ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="filterGroup">
			<label for="filter_type"> <?php _e( 'Media Type', 'players' ); ?>: </label>
			<select id="filter_type">
				<option value="all">&infin;&nbsp;</option>
				<?php foreach ($mime_types as $type) : ?>
				<option value="<?php echo $type; ?>"><?php echo $type; ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="filterGroup">
			<label for="filter_width_ltgt"> <?php _e( 'Width', 'players' ); ?>: </label>
			<select id="filter_width_ltgt">
				<option value="gt"><?php _e( 'greater than', 'players' ); ?>&nbsp;</option>
				<option value="lt"><?php _e( 'less than', 'players' ); ?></option>
			</select>
			<input type="text" id="filter_width" class="pixelfield" 
				value="<?php echo $players_interface['filter_width']; ?>" /><code>px</code>
			<label for="filter_height_ltgt">&nbsp;<?php _e( 'Height', 'players' ); ?>: </label>
			<select id="filter_height_ltgt">
				<option value="gt"><?php _e( 'greater than', 'players' ); ?>&nbsp;</option>
				<option value="lt"><?php _e( 'less than', 'players' ); ?></option>
			</select>
			<input type="text" id="filter_height" class="pixelfield" 
				value="<?php echo $players_interface['filter_height']; ?>" /><code>px</code>
		</div>
	</div>
	<div id="uploaddiv" class="hide-if-no-js"> <a title="Upload a file" id="uploadlink" class="button" href="#"><?php _e( 'Add New', 'players' ); ?></a> <span id="uploadresult"></span> </div>
<?php
}

// Output "Shortcode" Meta Box
function players_shortcode_meta() {
	global $post;
	echo players_embed_input( $post->ID );
}

// Output "Controller" Meta Box
function players_controller_meta() {
	global $post, $players_controllers;
	
	$player = get_post_meta( $post->ID, 'player', true );
?>
	<p>
		<strong>Choose a Controller</strong>
	</p>
	<p>
		<select name="player[controller]" id="controller">
<?php
		$description = '';
		foreach ($players_controllers as $controller => $info) :
			$description .= '<div class="controller-' . $controller . ' players_controller">
				<p><strong>' . __( 'Description', 'players' ) . '</strong></p>
				<p>' . $info['description'] . '</p>' . 
				(isset( $info['website'] ) ? '<p><a href="' . $info['website'] . '" 
					alt="' . __( 'More Information', 'players' ) . '" 
					title="' . __( 'More Information', 'players' ) . '" 
					target="_blank">' . $info['website'] . '</a></p>' : '')
				. '<p><strong>' . __( 'Supports', 'players' ) . '</strong></p>
				<p>' . implode( ', ', $info['mime_types'] ) . '</p></div>';
?>
			<option value="<?php echo $controller; ?>"<?php echo ($controller == $player['controller'] ? ' selected="selected"' : ''); ?>><?php echo $info['nicename']; ?></option>
<?php
		endforeach;
?>
		</select>
		<?php echo $description; ?>
	</p>
<?php
}

// Output "Options" Meta Box
function players_options_meta() {
	global $post, $pagenow, $players_interface, $players_controllers;
	
	// get various options stored as post meta records	
	$player = get_post_meta( $post->ID, 'player', true );
	$controller = get_post_meta( $post->ID, 'controller', true );
?>
	<p><strong><?php _e( 'Player Size', 'players' ); ?></strong></p>
	<div id="size_radio_buttons">
<?php
	foreach ($players_interface['sizes'] as $key => $value) 
		echo players_radio( $key, $value, 'player[size][selected]', (empty( $player['size']['selected'] ) ? 'min' : $player['size']['selected']) );
?>
	</div>
    <p id="custom_size" class="hide-if-js">
	<label for="custom_width"><?php _e( 'Width', 'players' ); ?></label> 
		<input type="text" id="custom_width" class="pixelfield" name="player[size][custom][width]" 
			value="<?php echo $player['size']['custom']['width']; ?>" /><?php players_tag( __( 'px', 'players' ), 'code' ); ?>
	<label for="custom_height"><?php _e( 'Height', 'players' ); ?></label> 
		<input type="text" id="custom_height" class="pixelfield" name="player[size][custom][height]" 
			value="<?php echo $player['size']['custom']['height']; ?>" /><?php players_tag( __( 'px', 'players' ), 'code' ); ?>
	</p>

<?php
	foreach ($players_controllers as $controller_name => $info) :
		if (empty( $info['options'] )) continue;
?>
		<div class="controller-<?php echo $controller_name; ?> players_controller">
<?php 
			foreach( $info['options'] as $key => $opts ) {
				$controller_string = 'controller[' . $controller_name . ']';
				
				if ($pagenow != 'post-new.php') unset( $opts['default'] ); // only set default values if this is a new post
				
				switch( $opts['type'] ) {
					case 'checkbox':
						$group = (isset( $opts['group'] ) ? '[' . $opts['group'] . ']' : '' );
						//$test = $controller[$controller_name][$opts['group']];
						$checked = (!empty( $controller ) && ($group == '' ? isset( $controller[$controller_name][$key] ) : isset( $controller[$controller_name][$opts['group']][$key] ))  
							|| isset( $opts['default'] ) ? $key : '');
						if (!empty( $opts['desc'] )) players_tag( $opts['desc'] );
						echo players_checkbox( $key, $opts['label'], $controller_string . $group, $checked, (isset( $opts['sub'] ) ? 'subopt' : '') );
						if (!empty( $opts['hint'] )) players_tag( $opts['hint'], 'p', 'hint' );
						break;
					case 'select':
						if (!is_array( $options = $opts['options'] )) break;
						$selected = (!empty( $controller ) && isset( $controller[$controller_name][$key] ) ? $controller[$controller_name][$key] : $opts['default']);
						$controller_string .= '[' . $key . ']';
						if (!empty( $opts['desc'] )) players_tag( $opts['desc'] );
						echo '<p>';
						if (isset( $opts['label'] )) echo '<label for="' . $controller_string . '">' . $opts['label'] . '</label> ';
						echo '<select name="' . $controller_string . '" id="' . $controller_string . '">';
						foreach( $options as $value => $nicename ) 
							echo '<option value="' . $value . '"' . ($value == $selected ? ' selected="selected"' : '') . '>' . $nicename . '</option>';
						echo '</select>';
						if (!empty( $opts['units'] )) players_tag( $opts['units'], 'code' );
						echo '</p>';
						if (!empty( $opts['hint'] )) players_tag( $opts['hint'], 'p', 'hint' );
						break;
					case 'input':
						$default = (isset( $opts['default'] ) ? $opts['default'] : '');
						$value = (!empty( $controller ) && isset( $controller[$controller_name][$key] ) ? $controller[$controller_name][$key] : $default);
						$controller_string .= '[' . $key . ']';
						$size = (isset( $opts['size'] ) ? ' size="' . $opts['size'] . '"' : '');
						$class = (isset( $opts['validate'] ) && $opts['validate'] == 'numbers' ? ' class="numbers-only"' : '');
						if (!empty( $opts['desc'] )) players_tag( $opts['desc'] );
						echo '<p>';
						if (isset( $opts['label'] )) echo '<label for="' . $controller_string . '">' . $opts['label'] . '</label> ';
						echo '<input type="text"' . $size . $class . ' id="' . $controller_string . '" name="' . $controller_string . '" value="' . $value . '" />';
						if (!empty( $opts['units'] )) players_tag( $opts['units'], 'code' );
						echo '</p>';
						if (!empty( $opts['hint'] )) players_tag( $opts['hint'], 'p', 'hint' );
						break;
					case 'section':
						players_tag( players_tag( $opts['title'], 'strong', '', false ) );
						if (!empty( $opts['desc'] )) players_tag( $opts['desc'] );
						if (!empty( $opts['hint'] )) players_tag( $opts['hint'], 'p', 'hint' );
						break;
				}
			}
			?>
		</div>		
<?php
	endforeach;
}

// Wrap Passed Text In Passed Tag
function players_tag( $string, $tag = 'p', $class = '', $echo = true ) {
	if (!empty( $class )) $class = ' class="' . $class . '"';
	
	$result = '<' . $tag . $class . '>' . $string . '</' . $tag . '>';
	
	if ($echo) echo $result;
	
	return $result;
}

// Output "Units" OR "Media Library" Meta Boxes
function players_meta_box( $box ) {
	global $players_units;
?>
	<div class="scrollingContainer">
		<div class="scrollingHotSpotLeft"></div><div class="scrollingHotSpotRight"></div>
		<ul class="connectedSortable">
<?php 
		foreach( $players_units as $index => $unit ) {
			if ($unit['box'] == $box) players_sortable_item( $unit, $index );
		}
?>
		</ul>
	</div>
<?php
}

// Utility: Get Mime Type
function players_get_mime_types( $id ) {
	$attachment = get_post( $id );
	$mime_types = array( $attachment->post_mime_type );
	
	// if an encoded video, add all encoded formats
	if( players_is_video( $mime_types[0] ) && players_tf( $encodes = players_get_encodes( $id ) ) ) {
		$mime_types = array(); // strip out source mime type
		foreach( $encodes as $encode ) {
			foreach( $encode['mime_types'] as $mime_type => $data ) array_push( $mime_types, $mime_type );
		}
	}
	
	return array_unique( $mime_types );
}

// Utility: Verify Mime Type
function players_verify_mime_types( $mime_types, $allowed_types = array() ) {
	global $players_controllers;
	
	if( empty( $allowed_types ) ) { // get mime types supported by all controllers
		foreach ($players_controllers as $controller) $allowed_types = array_merge( $allowed_types, $controller['mime_types'] );
		$allowed_types = array_unique( $allowed_types );
	}
	
	$intersect = array_intersect( (array)$mime_types, (array)$allowed_types ); // check given against allowed
	
	return (empty( $intersect ) ? false : true);
}

function players_is_video( $mime_types ) {
	return strstr( implode( '', (array)$mime_types ), 'video' );
}

// Push Attachments Onto Global Unit Array And Return Added Units
function players_units( $post_id, $limit = -1 ) {
	global $wpdb, $players_interface;
	
	$units = get_post_meta( $post_id, 'unit', false ); // set "single" (third parameter) to false to pull ALL records with key "unit"
		
	$attachments = get_posts( array( 
		'post_type'		=> 'attachment',
		'order_by'		=> 'post_date',
		'order'			=> 'DESC',
		'numberposts'	=> $limit ) );
	
	// push all image attachments info into array
	$new_units = array();
	foreach( $attachments as $attachment ) {
		$mime_typez = $attachment->post_mime_type; // "z" because images have a single mime type while videos can have multiple
		
		if( $is_video = players_is_video( $mime_typez ) ) {
			$mime_typez = players_get_mime_types( $attachment->ID );
			if (empty( $mime_typez )) continue; // must be encoded
		}
		
		if (!players_verify_mime_types( $mime_typez )) continue; // must be supported
		
		$box = 'library';
		$order = '';
		foreach( $units as $unit ) {
			if( $unit['attachment_id'] == $attachment->ID ) {
				$box = 'units'; 
				$order = $unit['order'];
				break;
			}
		}
		
		$metadata = wp_get_attachment_metadata( $attachment->ID );
		
		$image_url = $attachment->ID;
		if( $is_video ) {
			$screenshots = players_get_screenshots( $attachment->ID );
			$image_url = $screenshots[0]['url'];
		}
		$image = players_timthumb( $image_url, $players_interface['media_width'], $players_interface['media_height'] );
		
		array_push( $new_units, array(
			'box' 		=> $box,
			'order'		=> $order,
			'attachment'	=> array(
								'id' 	=> $attachment->ID,
								'year' 	=> mysql2date( 'Y', $attachment->post_date ),
								'month' 	=> mysql2date( 'm', $attachment->post_date ),
								'width'	=> $metadata['width'],
								'height' 	=> $metadata['height'],
								'type' 	=> $mime_typez,
								'link'	=> $attachment->post_excerpt,
								'content'	=> $attachment->post_content ),
			'image' 		=>  $image ) );
	}
	
	return $new_units;
}

// Output A Sortable Item To Be Used In "Units" OR "Media Library"
function players_sortable_item( $unit, $index ) {
	global $players_interface;
	
	$attachment = $unit['attachment'];
	
	if( $is_video = players_is_video( $attachment['type'] ) ) {
		if (!players_tf( players_get_encodes( $attachment['id'] ) )) return; // make sure encodes exist
	}
	
	$post = get_post( $attachment['id'] );
?>
	<li id="attachment_id-<?php echo $post->ID; ?>" class="players_<?php echo $unit['box']; ?> players_item">
		<div class="players_right">
			<a href="#" class="move-to-library"><?php _e( 'Remove', 'players' ); ?></a>
			<a href="#" class="move-to-units"><?php _e( 'Add', 'players' ); ?></a>
		</div>
		<div class="not-supported">
			<strong class="controller-name">Controller</strong> doesn't support<br /><strong class="attachment-type"><?php echo implode( ', ', (array)$attachment['type'] ); ?></strong>
            <br />This item will be ignored
		</div>
		<?php echo '<img src="' . $unit['image'] . '" />'; ?>
        <?php if ($is_video) : ?>
        <div><label for="unit-title-<?php echo $index; ?>" class="title-label"><?php _e( 'Title:', 'players' ); ?></label>
        <input type="text" class="unit-title" name="unit[<?php echo $index; ?>][post_title]" id="unit-title-<?php echo $index; ?>" 
			value="<?php echo ($post->post_title != '' ? $post->post_title : ''); ?>" /></div>
        <?php endif; ?>
		<div style="clear:both;"><textarea name="unit[<?php echo $index; ?>][post_content]"><?php echo $post->post_content; ?></textarea></div>
        <?php if (!$is_video) : ?>
		<div><label for="unit-link-<?php echo $index; ?>" class="link-label"><?php _e( 'Link to:', 'players' ); ?></label>
		<input type="text" class="unit-link" name="unit[<?php echo $index; ?>][post_excerpt]" id="unit-link-<?php echo $index; ?>" 
			value="<?php echo ($post->post_excerpt != '' ? $post->post_excerpt : $players_interface['link_text']); ?>" /></div>
        <?php endif; ?>
		<div><input type="hidden" name="unit[<?php echo $index; ?>][attachment_id]" 
			value="<?php echo $post->ID; ?>" /></div>
		<div><input type="hidden" name="unit[<?php echo $index; ?>][box]" class="players_box" 
			value="<?php echo $unit['box']; ?>" /></div>
		<div><input type="hidden" name="unit[<?php echo $index; ?>][order]" class="players_order" 
			value="<?php echo $unit['order']; ?>" /></div>
	</li>
<?php
}

// Save Custom Data
function players_save( $post_id ) {
	global $players_interface, $players_controllers;
	
	if (!isset( $_POST['players_noncename'] ) || !wp_verify_nonce( $_POST['players_noncename'], plugin_basename( PLAYERS_PATH ) ) // security check
		|| defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE // avoid auto save routine
		|| 'page' == $_POST['post_type'] && !(current_user_can( 'edit_page', $post_id ) || current_user_can( 'edit_post', $post_id )) // check permissions
		|| !players_verify_post_type()) return $post_id; // verify post type
	
	delete_post_meta( $post_id, 'unit' ); // flush all post meta entries with "unit" as their key
	
	// initialize default min and max image sizes
	$image_size = array( 
		'min' 	=> array( 
					'width' 	=> 9999, 
					'height' 	=> 9999 ),
		'max' 	=> array( 
					'width' 	=> 0, 
					'height' 	=> 0 ) );
	
	foreach( $_POST['unit'] as $unit ) {
		// update attachment details for any unit
		wp_update_post( array( 
			'ID' 		=> $unit['attachment_id'], 
			'post_title'	=> apply_filters( 'link_title', (isset( $unit['post_title'] ) ? $unit['post_title'] : '') ),
			'post_excerpt' => apply_filters( 'pre_link_url', 
							(isset( $unit['post_excerpt'] ) && $unit['post_excerpt'] !== $players_interface['link_text'] ? $unit['post_excerpt'] : '') ),
			'post_content'	=> apply_filters( 'link_description', (isset( $unit['post_content'] ) ? $unit['post_content'] : '') ) ) );
		
		if ($unit['box'] == 'library') continue; // stop here if unit is from library
		
		// create a post meta record with key "unit" to hold this unit's details
		add_post_meta( $post_id, 'unit', array( 
			'attachment_id' 	=> $unit['attachment_id'], 
			'order' 			=> $unit['order'] ) );
		
		// from here on is just doing some checks to figure out the max and min image heights and widths
		
		$settings = $players_controllers[$_POST['player']['controller']]; // get chosen controller settings
		if (!players_verify_mime_types( players_get_mime_types( $unit['attachment_id'] ), $settings['mime_types'] ))
			continue; // skip if this unit won't be supported by the controller
		
		$md = wp_get_attachment_metadata( $unit['attachment_id'] ); // attachment metadata
		
		// min
		if ($md['width'] < $image_size['min']['width']) $image_size['min']['width'] = $md['width'];
		if ($md['height'] < $image_size['min']['height']) $image_size['min']['height'] = $md['height'];
			
		// max
		if ($md['width'] > $image_size['max']['width']) $image_size['max']['width'] = $md['width'];
		if ($md['height'] > $image_size['max']['height']) $image_size['max']['height'] = $md['height'];
	}
	
	// update post meta records
	$options = $_POST['player'];
	$options['size'] = array_merge( $_POST['player']['size'], $image_size );
	if (empty( $options['size']['custom']['width'] )) $options['size']['custom']['width'] = $image_size['min']['width'];
	if (empty( $options['size']['custom']['height'] )) $options['size']['custom']['height'] = $image_size['min']['height'];
	update_post_meta( $post_id, 'player', $options );
	update_post_meta( $post_id, 'controller', $_POST['controller'] );
	
	return $post_id;
}
add_action( 'save_post', 'players_save' );

// Handle Shortcode
function players_shortcode( $atts, $content = NULL ) {
	global $players_controllers;
	
	extract( shortcode_atts( array( 'id' => 0 ), $atts ) );
	
	// set up some error variables
	$lnk = '<a href="' . get_admin_url() . 'post.php?post=' . $id . '&amp;action=edit">' . $id . '</a>';
	$err = '<strong>' . __( 'Error', 'players' ) . ':</strong>';
	
	// get various options stored as post meta records	
	$player = get_post_meta( $id, 'player', true );
	if ($player == '') return sprintf( __( '%s Player %s does not exist.', 'players' ), $err, $lnk );
	$controller = get_post_meta( $id, 'controller', true );
	
	// set up some general purpose variables
	$settings = $players_controllers[$player['controller']];
	if (empty( $settings ))
		return sprintf( __( '%s Player %s is asking for %s, a controller that does not exist.', 'players' ), $err, $lnk, '<strong>' . $player['controller'] . '</strong>' );
	$current_controller = $controller[$player['controller']];
	$dimensions = $player['size'][$player['size']['selected']];
	
	// get and sort all units stored as post meta records
	$units = get_post_meta( $id, 'unit', false ); // set single (third parameter) to false to pull ALL records with key "unit"
	usort( $units, 'players_sort_order' ); // order the elements of the array
	
	$attachments = array();
	foreach( $units as $unit ) {
		$attachment = get_post( $unit['attachment_id'] );
		
		if (!players_verify_mime_types( players_get_mime_types( $attachment->ID ), $settings['mime_types'] ))
			continue; // ignore if mime type not supported
		
		array_push( $attachments, $attachment );
	}
	
	if (empty( $attachments )) return sprintf( __( '%s Player %s contains no useable content.', 'players' ), $err, $lnk );
	
	return call_user_func( $players_controllers[$player['controller']]['shortcode_cb'], 
		players_verify_id( $id ), get_the_title( $id ), $dimensions, $current_controller, $attachments );
}
add_shortcode( 'player', 'players_shortcode' );

// Footer JS
function players_footerjs( $index, $string, $priority = 10 ) {
	global $players_footerjs;
	
	$players_footerjs[$index] = array( 'js' => $string, 'order' => $priority );
}

// Add Custom Management Columns
function players_columns( $columns ) {
	unset( $columns['date'] ); // remove date column
	$columns['controller'] = __( 'Controller', 'players' );
	$columns['id'] = __( 'ID', 'players' );
	$columns['shortcode'] = __( 'Shortcode', 'players' );
	$columns['date'] = __( 'Date', 'players' ); // add date column back, at the end
	
	return $columns;
}
add_filter( 'manage_edit-player_columns', 'players_columns', 10, 1 );

// Handle Custom Management Columns
function players_custom_columns( $column, $post_id ) {	
	switch( $column ) {
		case 'controller':
			global $players_controllers;
			$player = get_post_meta( $post_id, 'player', true );
			$controller = $players_controllers[$player['controller']]['nicename'];
			echo (empty( $controller ) ? $player['controller'] . '<div style="color:red;">' . __( '(missing)', 'players' ) . '</div>' : $controller);
		break;
		case 'id':
			echo $post_id;
		break;
		case 'shortcode':
			echo players_embed_input( $post_id );
		break;
	}
}
add_action( 'manage_player_posts_custom_column', 'players_custom_columns', 10, 2 );

// Filter Player Post Content (for when visiting a players public URL)
function players_content_filter( $content ) {
	global $post, $pagenow;
	
	if (players_verify_post_type()) {
		if ($pagenow == 'edit.php')
			return count( get_post_meta( $post->ID, 'unit', false ) ) . __( ' items', 'players' );
		else
			return '[player id="' . $post->ID . '"]';
	} else {
		return $content;
	}
}
add_filter( 'the_content', 'players_content_filter' );

// Add Javascript To Footer On Front End Only
function players_footer_javascript() {	
	global $players_footerjs;
	
	if (empty( $players_footerjs )) return; // don't add unnecessary javascript to footer
?>
	<script type="text/javascript">
	;(function($) {
		$(document).ready( function() {
			$(document).bind( 'scriptsLoaded', function() {
<?php
			usort( $players_footerjs, 'players_sort_order' );
			
			// output formatted javascript
			foreach ($players_footerjs as $js) 
				echo "\n\t\t\t" . str_replace( "\n", "\n\t\t\t", str_replace( "\t", '', $js['js'] ) ) . "\n";
?>
			});
		});
	})(jQuery);
	</script>
<?php
}
add_action( 'wp_footer', 'players_footer_javascript' );

// Add Plugin Scripts To Head On Back End Post Pages
function players_post_admin_print_scripts() {
	// enqueue scripts
	wp_enqueue_script( 'jquery-ui-core' );
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_script( 'jquery-ui-widget' );
	wp_enqueue_script( 'jquery-ajaxuploader', plugins_url( 'assets/ajaxupload.js', PLAYERS_PATH ) );
}
add_action( 'admin_print_scripts-post.php', 'players_post_admin_print_scripts' );
add_action( 'admin_print_scripts-post-new.php', 'players_post_admin_print_scripts' );

// Add Plugin Javascript And Style To Head On Back End Post Pages
function players_post_admin_head() {
	if (!players_verify_post_type()) return; // verify post type
	
	global $post, $players_interface, $players_controllers, $players_units;
	
	$attachments = array();
	foreach ($players_units as $unit) array_push( $attachments, $unit['attachment'] );
?>
	<script type="text/javascript">
		//<![CDATA[
		;(function( $ ) {
			$(document).ready( function(){
				var attachments = <?php echo json_encode( $attachments ); ?>;
				var controllers = <?php echo json_encode( $players_controllers ); ?>;
				// sortable container resize
				function container_resize() {
					$.each( ['#players_units', '#players_library'], function( index, obj ) {
						$(obj).removeClass( 'closed' ); // make sure meta box is opened before calculating width
						var sortable = $('ul.connectedSortable', obj);
						sortable.outerWidth( ($('li.players_item:visible', sortable).length + 1) * $('li.players_item:visible:first', sortable).outerWidth() );
						$('div.scrollingHotSpotLeft, div.scrollingHotSpotRight', obj).outerHeight( sortable.outerHeight() );
					});
					
					checkScrollPos();
				}
				container_resize(); // initial sizing
				
				// update hidden sortable inputs
				function hidden_inputs() {
					$('#players_units ul.connectedSortable li').each( function( index ) {
						$('.players_order', this).val( index );
					});
					$('#players_units ul.connectedSortable li .players_box').val( 'units' );
					$('#players_library ul.connectedSortable li .players_box').val( 'library' );
				}
				
				// prepend an item to a box
				function prepend_to( location, players_item ) {
					var sortable = $('#players_' + location + ' ul.connectedSortable');
					sortable.parent().stop().animate({ scrollLeft: 0 }, 'slow'); // alternative: sortable.outerWidth() - $(this).scrollLeft()
					players_item = $(players_item); // force object
					players_item.fadeOut( 'fast', function() {
						players_item.prependTo( sortable ); // alternative: append
						players_item.fadeIn('slow');
						filter_library();
						container_resize();
						hidden_inputs();
					});
				}
				
				// move sortable item via add/remove link
				$('.move-to-library').live( 'click', function() {
					prepend_to( 'library', $(this).parent('div.players_right').parent('li.players_item') );
					return false;
				});
				$('.move-to-units').live( 'click', function() {
					prepend_to( 'units', $(this).parent('div.players_right').parent('li.players_item') );
					return false;
				});
	
				// connected sortable lists
				$('#players_units ul.connectedSortable, #players_library ul.connectedSortable').sortable({
					connectWith: '.connectedSortable',
					handle: 'img',
					forceHelperSize: false,
					forcePlaceholderSize: true,
					helper: function( ev, el ) { return $('img', el).clone().width( <?php 
						echo floor( $players_interface['media_width'] / 3 ); ?>).height( <?php 
						echo floor( $players_interface['media_height'] / 3 ); ?>); },
					placeholder: 'placeholder',
					revert: true,
					cursor: 'move',
					cursorAt: { top: -1, left: <?php echo floor( $players_interface['media_height'] / 4.2 ); ?> },
					containment: '#normal-sortables',
					scrollSensitivity: 0,
					tolerance: 'pointer'
				});
				$('#players_units ul.connectedSortable, #players_library ul.connectedSortable').bind( 'sortreceive', function( ev, ui ) {
					filter_library();
					container_resize();
				});
				$('#players_units ul.connectedSortable').bind('sortupdate', function( ev, ui ) {
					hidden_inputs();
				});
				
				// sortable list scrolling
				var interval_id = 0;
				$('div.scrollingHotSpotLeft, div.scrollingHotSpotRight').mouseenter( function() {
					var scrollDiv = $(this).parent();
					var scrollAmount = $('li.players_item:first', scrollDiv).outerWidth() * 2;
					scrollAmount *= ($(this).attr('class') == 'scrollingHotSpotLeft' ? -1 : 1);
						
					if( interval_id == 0 ) {
						interval_id = setInterval( function() {
							scrollDiv.stop().animate({ scrollLeft: scrollDiv.scrollLeft() + scrollAmount }, 280);
						}, 400);									
					}
				});
				$('div.scrollingHotSpotLeft, div.scrollingHotSpotRight').mouseleave( function() {
					if( interval_id != 0 ) {
						clearInterval( interval_id );
						interval_id = 0;
					}
				});
				$('div.scrollingContainer').scroll( function() {
					checkScrollPos();
				});
				function checkScrollPos() {
					$('div.scrollingContainer').each( function() {
						var scrollLeft = $(this).scrollLeft();
						var left = $('div.scrollingHotSpotLeft', this);
						var right = $('div.scrollingHotSpotRight', this);
						var sortableW = $('ul.connectedSortable', this).outerWidth();
						var containerW = $(this).outerWidth();
						
						if( sortableW > containerW ) {
							if( scrollLeft == 0 )
								left.fadeOut('slow');
							else
								left.fadeIn('slow');
							
							if( scrollLeft == (sortableW - containerW) )
								right.fadeOut('slow');
							else
								right.fadeIn('slow');						
						} else {
							left.fadeOut('slow');
							right.fadeOut('slow');
						}
					});
				}
				
				// numbers only
				function numbers_only( obj ) {
					var oldVal = $(obj).val();
					var newVal = (!isNaN( parseInt( oldVal, 10 ) ) ? parseInt( oldVal ) : '');	
					newVal = String( newVal ).replace( /[^0-9]/g, '' );
					
					if( newVal == '0' || oldVal.slice( 0, 1 ) == '0' )
						$(obj).val( 0 ); 
					else
						$(obj).val( newVal );
					
					if (oldVal == '' || oldVal.slice( 0, -1 ) != newVal) return true;
					
					return false;
				}
				$('input.numbers-only').keyup( function() {					
					numbers_only( this );
				});
				
				// library filtering
				$('div#libraryFilters input').keypress( function( e ) {
					if( e.which == 13 ) {
						e.preventDefault();					
						return false;
					}
				});
				$('div#libraryFilters input').keyup( function() {					
					if (numbers_only( this )) filter_library();
				});
				$('div#libraryFilters select').not('select#filter_year').change( function() {
					filter_library();
				});
				
				// show/hide filter months on year change
				function filterMonthToggle( className ) {
					var className = (className != 'all' ? ', option.y' + className : '');
					$('select#filter_month option').removeAttr( 'selected' ).removeAttr( 'disabled' ).not('option:first' + className).attr( 'disabled', 'disabled' );					
					$('select#filter_month option:first').attr( 'selected', 'selected' );
				}
				$('select#filter_year').change( function() {
					filterMonthToggle( $(this).val() );
					filter_library();
				});
				filterMonthToggle( $('select#filter_year').val() ); // initial month input value
				var stopFiltering;
				function filter_library() {
					stopFiltering = true;
					
					var year = $('#filter_year').val();
					var month = $('#filter_month').val();
					var type = $('#filter_type').val();
					var width_ltgt = $('#filter_width_ltgt').val();
					var width = parseInt( $('#filter_width').val() );
					var height_ltgt = $('#filter_height_ltgt').val();
					var height = parseInt( $('#filter_height').val() );
				
					$(attachments).each(function( index, attachment ) {
						if( index == 0 ) {
							stopFiltering = false;
						} else {
							if (stopFiltering) return false; // stop if mid-way through when a new filter is initiated
						}
											
						unit = $('#players_library ul.connectedSortable li#attachment_id-' + attachment.id);
						
						if( unit.length != 0 ) { // check if unit exists
							var hide = false;
							
							if (year != 'all' && attachment.year != year) hide = true;
							
							if (month != 'all' && attachment.month != month) hide = true;
							
							if (type != 'all' && !verify_mime_type( type, attachment.type )) hide = true;
							
							attachment.width = parseInt( attachment.width );
							if( typeof width == 'number' && typeof attachment.width == 'number' ) {
								if (width_ltgt == 'lt' && attachment.width > width) hide = true;
								if (width_ltgt == 'gt' && attachment.width < width) hide = true;
							}
							
							attachment.height = parseInt( attachment.height );
							if( typeof height == 'number' && typeof attachment.height == 'number' ) {
								if (height_ltgt == 'lt' && attachment.height > height) hide = true;
								if (height_ltgt == 'gt' && attachment.height < height) hide = true;
							}
							
							if (hide) unit.hide(); else unit.show();
						}
						
						if (index == ($(attachments).length - 1) ) container_resize(); // resize container when finished filtering
					});
				}
				filter_library(); // initial filtering
				
				// clear filters
				$('#clear-filters').click( function() {
					$('div#libraryFilters select').each( function( i, v ) {
						$('option:selected', this).removeAttr( 'selected' );
						$('option:first', this).attr( 'selected', 'selected' );
					});
					$('div#libraryFilters input#filter_width').val( <?php echo $players_interface['filter_width']; ?> );
					$('div#libraryFilters input#filter_height').val( <?php echo $players_interface['filter_height']; ?> );
					
					filter_library();
					
					$(this).blur();
					
					return false;
				});
				
				// file upload
				var fid = 'userfile';
				new AjaxUpload( $('a#uploadlink'), {
					action: ajaxurl,
					name: fid,
					data: { action: 'players_handle_upload_ajax', _ajax_nonce: '<?php echo wp_create_nonce( plugin_basename( PLAYERS_PATH ) ); ?>', file_id: fid },
					responseType: 'json',
					onSubmit: function( file , ext ) {
						if( ext && /^(jpg|png|jpeg|gif)$/.test( ext ) ) {
							$('span#uploadresult').html( 'Uploading ' + file + '...' );
						} else {
							// extension is not allowed
							$('span#uploadresult').html( 'Error: Only images are allowed.' );
							return false;	// cancel upload			
						}
					},
					onComplete: function( file, response ) {
						if( response.error != '' ) {
							$('span#uploadresult').html( response.error ); // show user the error
						} else {
							$('span#uploadresult').html( '<a href="' + response.full + '" target="_blank" class="previewlink">' + file + '</a> has been uploaded!' );
							
							var offset = $(attachments).length;
							$.post(ajaxurl, { action: 'players_upload', id: '<?php echo $post->ID; ?>', index_offset: offset }, function(response) {								
								attachments[offset] = $.parseJSON( response.object ); // append to attachments array
								addToSelect( 'type', attachments[offset]['type'], null ); // add mime type to filter area if not already present
								
								// add year and month to filter if not already present
								var year = attachments[offset]['year'];									
								addToSelect( 'year', year, null );
								addToSelect( 'month', attachments[offset]['month'], year );
														
								$('select#filter_month option').sort( sortNum ).appendTo( 'select#filter_month' ); // sort the months to make sure they are in order	
								filterMonthToggle( $('select#filter_year').val() ); // enable/disable months based on year value
								prepend_to( 'library', response.html ); // prepend to library
								change_controller(); // show "controller will ignore" overlay for item if necessary
							}, 'json' );
						}
					}
				});
				
				// this is retarded but it works for now
				function addToSelect( key, text, year ) {
					sel = 'select#filter_' + key;
					
					var kill = false;
					$(sel + ' option').each( function( i, v ) {
						if ($(this).val() == text) {
							if (year != null) $(this).addClass( 'y' + year );
							
							kill = true; // not able to add to select, option already exists
						}
					});
					
					if (kill) return false;
										
					if (year != null)
						$('<option>').val( text ).text( text ).addClass('y' + year).appendTo( sel );
					else
						$('<option>').val( text ).text( text ).appendTo( sel );
					
					return true; // option added to select
				}
				
				// custom number sorting funtion
				function sortNum( a, b ){
					if ($(a).val() == 'all') return -1;
					return $(a).val() > $(b).val() ? 1 : -1;
				};
				
				// show/hide custom size
				$('#size_radio_buttons input[type="radio"]').change( function() {
					if( $(this).val() == 'custom' )
						$('#custom_size').show();
					else
						$('#custom_size').hide();
				});
				$('#size_radio_buttons input[type="radio"]').each( function() {
					if ($(this).val() == 'custom' && $(this).is(':checked')) $('#custom_size').show();
				});
				
				// player title is required
				$('input#publish, input#save-post, a#post-preview').mousedown( function(e) {
					if( $('input#title').val() == '' ) {
						e.preventDefault();
						$('input#title').focus();
						alert( 'You must enter a title for this player!' );
						return false;
					}
				});
				
				// clear default link
				$('input.unit-link').live( 'focus', function() {
					if ($(this).val() == '<?php echo $players_interface['link_text']; ?>') $(this).val( '' );
				});
				$('input.unit-link').live( 'blur', function() {
					if ($(this).val() == '') $(this).val( '<?php echo $players_interface['link_text']; ?>' );
				});
				
				function verify_mime_type( val, arr ) {
					if( $.isArray( arr ) && arr.length > 1 ) {
						if ($.inArray( val, arr ) > -1) return true;
					} else {
						if (val == String( arr )) return true;
					}
					
					return false;
				}
				
				// show controller info
				function change_controller() {
					// info and options
					$('div.players_controller').hide();
					var select_val = $('select#controller').val();
					var theClass = $('.controller-' + select_val);
					theClass.show();
					
					// not-supported
					$(attachments).each( function( index, attachment ) {
						var in_array = false;
						
						$(controllers[select_val].mime_types).each( function( index, type ) {							
							if (verify_mime_type( type, attachment.type )) in_array = true;
						});
						
						var div = $('li#attachment_id-' + attachment.id + ' div.not-supported');
						if (in_array) div.hide(); else div.show();
					});
					$('div.not-supported .controller-name').text( $('select#controller option:selected').text() );
				}
				change_controller(); // initial controller show/hide
				// controller select change
				$('select#controller').change( function() {
					change_controller();
				});
			});
		})(jQuery);	
		//]]>
	</script>
	
	<style type="text/css">
		/* units and library */
		#players_units div.inside,
		#players_library div.inside { position:relative; }
		div.scrollingContainer {
			overflow:auto;
			white-space:nowrap;
			margin:0;
		}
		ul.connectedSortable { width:9999px; overflow:auto; }
		ul.connectedSortable li {
			float:left;
			display:inline;
			position:relative;
			margin:0;
		}
		ul.connectedSortable img { cursor:move;	}
		ul.connectedSortable li,
		.ui-sortable-helper { padding:5px; }
		.placeholder { background:#257DA6 !important; }
		div.players_right { position:absolute; top:1px; right:2px; padding:5px 10px; }
		div.players_right a { text-decoration:none; }		
		
		/* units */
		#players_units ul.connectedSortable { 
			min-width:<?php echo $players_interface['media_width'] * 2; ?>px; 
			min-height:<?php echo $players_interface['media_height'] + 87; ?>px; 
		}
		#players_units.closed ul.connectedSortable { min-height:0; }
		#players_units ul.connectedSortable img { 
			margin-bottom:5px;
			width:<?php echo $players_interface['media_width']; ?>px; 
			height:<?php echo $players_interface['media_height']; ?>px; 
		}
		#players_units .placeholder { 
			width:<?php echo $players_interface['media_width'] + 2; ?>px; 
			height:<?php echo $players_interface['media_height'] + 77; ?>px; 
		}
		#players_units ul.connectedSortable textarea {
			width:<?php echo $players_interface['media_width']; ?>px;
			height:50px;
			display:block;
		}
		#players_units label.title-label,
		#players_units label.link-label { float:left; width:45px; line-height:32px; }
		#players_units label.link-label { width:55px; }
		#players_units input.unit-link,
		#players_units input.unit-title {
			height:25px;
			display:block;
			margin-top:5px;
			width:<?php echo $players_interface['media_width'] - 54; ?>px;
		}
		#players_units input.unit-title { 
			margin-top:0;
			margin-bottom:5px;
			width:<?php echo $players_interface['media_width'] - 44; ?>px;
		}
		#players_units div.players_right a.move-to-units { display:none; }
		div.not-supported { 
			position:absolute; 
			top:<?php echo $players_interface['media_height'] / 2; ?>px; 
			text-align:center; 
			width:<?php echo $players_interface['media_width']; ?>px;
			white-space:normal;
		}
		
		/* library */
		#players_library ul.connectedSortable { 
			min-width:<?php echo $players_interface['media_width'] * 2; ?>px; 
			min-height:<?php echo ($players_interface['media_height'] / 3) + 10; ?>px; 
		}
		#players_library ul.connectedSortable img,
		#players_library .placeholder {
			width:<?php echo floor($players_interface['media_width'] / 3); ?>px;
			height:<?php echo floor($players_interface['media_height'] / 3); ?>px;
		}
		#players_library ul.connectedSortable textarea,
		#players_library ul.connectedSortable label,
		#players_library input.unit-link,
		#players_library input.unit-title,
		#players_library div.players_right a.move-to-library,
		#players_library div.not-supported { display:none !important; }
		
		/* scrolling hotspots */
		div.scrollingHotSpotLeft,
		div.scrollingHotSpotRight {
			display:none;
			width:50px;
			height:100%;
			top:0;
			position:absolute;
			z-index:200;
			background-position:center center;
			background-repeat:no-repeat;
			background-color:#F9F9F9;
			opacity:0.35;
			-moz-opacity:0.35;
			filter:alpha(opacity = 35);
			zoom:1; /* trigger "hasLayout" in Internet Explorer 6 or older */
		}
		div.scrollingHotSpotLeft	{
			left: 0;
			background-image: url(<?php echo plugins_url( 'assets/images/arrow_left.gif', PLAYERS_PATH ); ?>);
			cursor: url(<?php echo plugins_url( 'assets/images/cursors/cursor_arrow_left.cur', PLAYERS_PATH ); ?>), w-resize;
		}
		div.scrollingHotSpotRight {
			right: 0;
			background-image: url(<?php echo plugins_url( 'assets/images/arrow_right.gif', PLAYERS_PATH ); ?>);
			cursor: url(<?php echo plugins_url( 'assets/images/cursors/cursor_arrow_right.cur', PLAYERS_PATH ); ?>), w-resize;
		}
		
		/* interface */
		div.scrollingContainer, 
		.ui-sortable-helper, 
		div.players_right, 
		div.not-supported,
		ul.connectedSortable li {
			background:<?php echo $players_interface['color']; ?>;
		}
		input.pixelfield { width:44px; }
		input.urlfield { width:155px; }
		#uploaddiv { position:absolute; top:-33px; left:110px; }
		#libraryFilters { margin-top:8px; text-align:left; overflow:auto; }
		#libraryFilters div {
			margin-top:6px;
			line-height:2em;
			white-space:nowrap;
			float:left;
			margin-left:10px;
		}
		#libraryFilters div em { line-height:2em; display:block; }
		div.filterGroup {
			padding-left:10px;
			border-left:1px solid #EAEAEA;
		}
		div.players_controller { display:none; }
		p.subopt {
			padding-left:11px;
			background: url(<?php echo plugins_url( 'assets/images/dr_arrow.png', PLAYERS_PATH ); ?>) no-repeat;
		}
		p.less-margin { margin-top:0 !important; margin-bottom:0 !important; }
		p.hint { border-left:1px solid #CCC; margin-left:8px !important; padding-left:3px; }
	</style>
<?php
}
add_action( 'admin_head-post.php', 'players_post_admin_head' );
add_action( 'admin_head-post-new.php', 'players_post_admin_head' );

// Handle Files Uploaded Via Ajax
function players_handle_upload_ajax() {
	check_ajax_referer( plugin_basename( PLAYERS_PATH ) ); // security
	
	$error = $result = '';
	if (!isset( $_REQUEST['file_id'] )) $error = 'No file_id found.';
	if (empty( $_FILES )) $error = 'No file received.';
	
	if( $error == '' ) {
		$id = media_handle_upload( $_REQUEST['file_id'], 0, array(), array( 'test_form' => false ) ); // returns attachment id
		
		if( is_numeric( $id ) ) {
			$result['thumb'] = array_shift( wp_get_attachment_image_src( $id, 'thumbnail' ) );
			$result['full'] = array_shift( wp_get_attachment_image_src( $id, 'full' ) );
			if ($result['thumb'] == '' || $result['full'] == '') $error = 'Could not retrieve uploaded image.';
		} else {
			$error = $id;
		}
	}
	
	$result['error'] = ($error !== '' ? 'Error: ' . $error : '');
	
	die( htmlspecialchars( json_encode( $result ), ENT_NOQUOTES ) ); 
}
add_action( 'wp_ajax_players_handle_upload_ajax', 'players_handle_upload_ajax' );

// Add Uploaded File To Library
function players_upload() {
	// create unit array for newest attachment
	$units = players_units( (int)$_POST['id'], 1 );
	$unit = $units[0];
	
	ob_start();
	players_sortable_item( $unit, (int)$_POST['index_offset'] );
	$html = ob_get_clean();

	die( json_encode( array( 
		'object' 	=> json_encode( $unit['attachment'] ), 
		'html' 	=> $html ) ) );
}
add_action( 'wp_ajax_players_upload', 'players_upload' );

// Add Plugin Style To Head On Back End Edit Page
function players_edit_admin_style() {
	if (!players_verify_post_type()) return; // verify post type
?>
	<style type="text/css">
		th.column-controller { width:100px; }
		th.column-id { width:50px; }
		th.column-shortcode { width:180px; }
		input.urlfield { width:155px; }
	</style>
<?php
}
add_action( 'admin_print_styles-edit.php', 'players_edit_admin_style' );
?>