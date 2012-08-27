<?php
/*
Plugin Name: Lazy Load
Description: Allow mid-page enqueue of scripts or styles. Such lazy load functionality is usually needed by shortcodes who, without it, clutter the head with unecessary includes.
Version: 1.0.0
Author: Joel Kuczmarski
Author URI: http://www.joelak.com
License: GPL2

    Copyright 2011  Joel KUCZMARSKI  (email : leoj3n at gmail dot com)

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

/* EXAMPLE OF HOW TO BIND TO scriptsLoaded:

	<script type="text/javascript">
	;(function($) { // so the $ sign can be used within WordPress
		$(document).ready(function() { // standard jQuery ready
			$(document).bind('scriptsLoaded', function() { // bind to scriptsLoaded
				// javascript that relies on lazy loaded scripts/styles goes here			
			});
		});
	})(jQuery);
	</script>
*/

// Add lazyload
function lazyload_template_redirect() {
	wp_enqueue_script( 'jquery' ); // lazyload.js is jquery agnostic but jquery is used to wait for the document/window to be ready and to trigger scriptsLoaded
	wp_enqueue_script( 'lazyload', plugins_url( 'lazyload/lazyload-min.js', PLAYERS_ASSETS_PATH ) );
}
add_action( 'template_redirect', 'lazyload_template_redirect' );

// Add Javascript/CSS To Footer On Front End Only
function lazyload_footer() {	
	global $wp_styles, $wp_scripts;
	
	// lazy load styles
	if( is_a( $wp_styles, 'WP_Styles' ) ) {
		$wp_styles->all_deps( $wp_styles->queue );
		$i = 0;
		$end = count( $wp_styles->to_do );
		foreach( $wp_styles->to_do as $key => $handle ) {
			if( !in_array( $handle, $wp_styles->done ) && isset( $wp_styles->registered[$handle] ) ) {
				if (NULL === $wp_styles->registered[$handle]->ver)
					$ver = '';
				else
					$ver = $wp_styles->registered[$handle]->ver ? $wp_styles->registered[$handle]->ver : $wp_styles->default_version;
		
				if (isset($wp_styles->args[$handle])) $ver = $ver ? $ver . '&amp;' . $wp_styles->args[$handle] : $wp_styles->args[$handle];
					
				if (isset( $wp_styles->registered[$handle]->args ))
					$media = esc_attr( $wp_styles->registered[$handle]->args );
				else
					$media = 'all';
					
				$src = $wp_styles->_css_href( $wp_styles->registered[$handle]->src, $ver, $handle );
				
				if ($i++ == 0) : ?>
				<script type="text/javascript">
				LazyLoad.css([
				<?php endif; ?>
				
					'<?php echo $src; ?>', // media: '<?php echo $media; ?>'
					
				<?php if ($i == $end) : ?>
				]);
				</script>
				<?php endif;
				$wp_styles->done[] = $handle;
				unset( $wp_styles->to_do[$key] );
			} else {
				$end--;
			}
		}
	}
	
	// lazy load scripts
	if (!is_a( $wp_scripts, 'WP_Scripts' )) $wp_scripts = new WP_Scripts();
	$wp_scripts->all_deps( $wp_scripts->queue );
	$i = 0;
	$end = count( $wp_scripts->to_do );
	foreach( $wp_scripts->to_do as $key => $handle ) {
		if( !in_array( $handle, $wp_scripts->done ) && isset( $wp_scripts->registered[$handle] ) ) {
			if (NULL === $wp_scripts->registered[$handle]->ver)
				$ver = '';
			else
				$ver = $wp_scripts->registered[$handle]->ver ? $wp_scripts->registered[$handle]->ver : $wp_scripts->default_version;
	
			if (isset( $wp_scripts->args[$handle] )) $ver = $ver ? $ver . '&amp;' . $wp_scripts->args[$handle] : $wp_scripts->args[$handle];
	
			$src = $wp_scripts->registered[$handle]->src;
	
			$wp_scripts->print_scripts_l10n( $handle ); // <script> tags are echoed by this function
			
			if (!preg_match( '|^https?://|', $src ) && !($wp_scripts->content_url && 0 === strpos( $src, $wp_scripts->content_url )))
				$src = $wp_scripts->base_url . $src;
	
			if (!empty( $ver )) $src = add_query_arg( 'ver', $ver, $src );
			
			$src = esc_url( apply_filters( 'script_loader_src', $src, $handle ) );
			
			if ($i++ == 0) : ?>
			<script type="text/javascript">
			;(function($) {
				$(window).load(function() {
					LazyLoad.js([
				<?php endif; ?>
				
					'<?php echo $src; ?>', 	
					
				<?php if ($i == $end) : ?>
					], function() {
						$(document).trigger( 'scriptsLoaded' );
					});
				});
			})(jQuery);
			</script>
			<?php endif;
		
			$wp_scripts->done[] = $handle;
			unset( $wp_scripts->to_do[$key] );
		} else {
			$end--;
		}
	}
	
	if ($end === 0) echo '<script type="text/javascript">;(function($) { $(window).load(function() { $(document).trigger( "scriptsLoaded" ); }); })(jQuery);</script>';
	
	echo "\n\n";
}
add_action( 'wp_footer', 'lazyload_footer' );
?>