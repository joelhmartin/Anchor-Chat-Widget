<?php
/**
 * Plugin Name: Anchor Corps Chat Widget
 * Description: Adds a floating chat widget that renders the [anchor_chatbot] output inside a toggle panel on every page.
 * Author: Anchor Corps
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ACCW_PLUGIN_VERSION', '1.0.0' );
define( 'ACCW_PLUGIN_FILE', __FILE__ );
define( 'ACCW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACCW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueue styles and scripts globally on the front end.
 */
function accw_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}
	wp_enqueue_style(
		'accw-chat-widget',
		ACCW_PLUGIN_URL . 'assets/css/chat-widget.css',
		array(),
		ACCW_PLUGIN_VERSION
	);

	wp_enqueue_script(
		'accw-chat-widget',
		ACCW_PLUGIN_URL . 'assets/js/chat-widget.js',
		array(),
		ACCW_PLUGIN_VERSION,
		true
	);

	// Pass a few tweakable strings through JS, in case a theme wants to filter.
	$strings = array(
		'helperText' => apply_filters( 'accw_helper_text', 'Hi, how can we help?' ),
		'headerTitle' => apply_filters( 'accw_header_title', 'Chat with us' ),
		'headerSubtitle' => apply_filters( 'accw_header_subtitle', "We're here to help!" ),
		'ariaLabelOpen' => apply_filters( 'accw_aria_label_open', 'Open chat' ),
	);
	wp_add_inline_script( 'accw-chat-widget', 'window.ACCW_STRINGS = ' . wp_json_encode( $strings ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'accw_enqueue_assets', 5 );

/**
 * Output the widget markup in the footer on all front end pages.
 */
function accw_render_widget() {
	if ( is_admin() ) {
		return;
	}

	$logo_url = apply_filters(
		'accw_logo_url',
		'https://tmjsleepkc.com/wp-content/uploads/2025/09/Small_Logo_SVG_TMJ_INT@2x.webp'
	);

	// Render the shortcode output directly so it works inside the widget.
	$chatbody = do_shortcode( '[anchor_chatbot]' );
	?>
	<div class="chat-widget-container" id="accwContainer" aria-live="polite">
		<div class="chat-helper" id="chatHelper"></div>
		<button class="chat-button" id="chatToggle" aria-label="<?php echo esc_attr( apply_filters( 'accw_aria_label_open', 'Open chat' ) ); ?>">
			<svg class="chat-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
				<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
			</svg>
			<svg class="close-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
				<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
			</svg>
		</button>

		<div class="chat-window" id="chatWindow" role="dialog" aria-modal="false" aria-labelledby="accwHeaderTitle">
			<div class="chat-header">
				<div class="logo-container">
					<div class="logo-placeholder">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="" loading="lazy" decoding="async" />
					</div>
				</div>
				<div class="chat-header-text">
					<h3 id="accwHeaderTitle"></h3>
					<p id="accwHeaderSubtitle"></p>
				</div>
			</div>
			<div class="chat-body">
				<?php echo $chatbody; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'accw_render_widget', 100 );

/**
 * Basic safe defaults for CSS variables in case the theme does not set them.
 */
function accw_root_css_vars() {
	if ( is_admin() ) {
		return;
	}
	$css = ':root{--color-accent:#6c63ff;--color-dark:#1f1f1f;}';
	wp_add_inline_style( 'accw-chat-widget', $css );
}
add_action( 'wp_enqueue_scripts', 'accw_root_css_vars', 6 );
