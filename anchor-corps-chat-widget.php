<?php
/**
 * Plugin Name: Anchor Corps Chat Widget
 * Description: Adds a floating chat widget that renders the [anchor_chatbot] output inside a toggle panel on every page.
 * Author: Anchor Corps
 * Version: 2.0.1
 * Requires at least: 5.2
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Load .env if present.
if ( file_exists( __DIR__ . '/.env' ) ) {
	$dotenv = Dotenv::createImmutable( __DIR__ );
	$dotenv->safeLoad();
}

// Build the update checker so this plugin auto-updates from GitHub.
$updateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/joelhmartin/Anchor-Chat-Widget/',
	__FILE__,
	'anchor-corps-chat-widget'
);
$updateChecker->setBranch( 'main' );

// Auth token from environment, with fallbacks.
$token = $_ENV['GITHUB_ACCESS_TOKEN']
	?? getenv( 'GITHUB_ACCESS_TOKEN' )
	?: ( defined( 'GITHUB_ACCESS_TOKEN' ) ? GITHUB_ACCESS_TOKEN : null );

if ( $token ) {
	$updateChecker->setAuthentication( $token );
}

// Prefer GitHub release assets when they exist.
$vcs_api = method_exists( $updateChecker, 'getVcsApi' ) ? $updateChecker->getVcsApi() : null;
if ( $vcs_api && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
	$vcs_api->enableReleaseAssets();
}

// Optional: verbose logs when updating.
add_filter(
	'upgrader_pre_download',
	function ( $reply, $package ) {
		error_log( '[UPGRADER] pre_download package=' . $package );
		return $reply;
	},
	10,
	2
);
add_filter(
	'upgrader_source_selection',
	function ( $source ) {
		error_log( '[UPGRADER] source_selection source=' . $source );
		return $source;
	},
	10,
	1
);

define( 'ACCW_PLUGIN_VERSION', '1.0.0' );
define( 'ACCW_PLUGIN_FILE', __FILE__ );
define( 'ACCW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACCW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Derive widget settings from environment variables and defaults.
 *
 * @return array<string,string>
 */
function accw_get_settings() {
	static $settings = null;

	if ( null !== $settings ) {
		return $settings;
	}

	$settings = array(
		'headerTitle'          => getenv( 'ACCW_HEADER_TITLE' ) ?: 'Chat with us',
		'headerSubtitle'       => getenv( 'ACCW_HEADER_SUBTITLE' ) ?: 'We are here to help',
		'helperText'           => getenv( 'ACCW_HELPER_TEXT' ) ?: 'Hi, how can we help?',
		'apiUrl'               => getenv( 'ACCW_API_URL' ) ?: '',
		'apiAuthToken'         => getenv( 'ACCW_API_AUTH_TOKEN' ) ?: '',
		'forwardTranscriptUrl' => getenv( 'ACCW_FORWARD_TRANSCRIPT_URL' ) ?: '',
		'clientId'             => getenv( 'ACCW_CLIENT_ID' ) ?: '',
		'forwardToken'         => getenv( 'ACCW_FORWARD_TOKEN' ) ?: 'anchor_forward_token_v1',
		'position'             => getenv( 'ACCW_POSITION' ) ?: 'bottom-right',
		'ariaLabelOpen'        => 'Open chat',
	);

	$settings['headerTitle']    = apply_filters( 'accw_header_title', $settings['headerTitle'] );
	$settings['headerSubtitle'] = apply_filters( 'accw_header_subtitle', $settings['headerSubtitle'] );
	$settings['helperText']     = apply_filters( 'accw_helper_text', $settings['helperText'] );
	$settings['ariaLabelOpen']  = apply_filters( 'accw_aria_label_open', $settings['ariaLabelOpen'] );

	/**
	 * Allow filtering the full settings array before it is passed into JS.
	 *
	 * @param array $settings
	 */
	$settings = apply_filters( 'accw_settings', $settings );

	return $settings;
}

/**
 * Enqueue styles and scripts globally on the front end.
 */
function accw_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}
	$settings = accw_get_settings();
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

	// Pass tweakable strings to JS and expose the config for the widget logic.
	$strings = array(
		'helperText' => $settings['helperText'],
		'headerTitle' => $settings['headerTitle'],
		'headerSubtitle' => $settings['headerSubtitle'],
		'ariaLabelOpen' => $settings['ariaLabelOpen'],
	);

	$config = array(
		'headerTitle' => $settings['headerTitle'],
		'headerSubtitle' => $settings['headerSubtitle'],
		'helperText' => $settings['helperText'],
		'apiUrl' => $settings['apiUrl'],
		'apiAuthToken' => $settings['apiAuthToken'],
		'forwardTranscriptUrl' => $settings['forwardTranscriptUrl'],
		'clientId' => $settings['clientId'],
		'forwardToken' => $settings['forwardToken'],
		'position' => $settings['position'],
	);

	$inline = 'window.ACCW_STRINGS = ' . wp_json_encode( $strings ) . ';';
	$inline .= 'window.ACCW_CONFIG = ' . wp_json_encode( $config ) . ';';
	wp_add_inline_script( 'accw-chat-widget', $inline, 'before' );
}
add_action( 'wp_enqueue_scripts', 'accw_enqueue_assets', 5 );

/**
 * Output the widget markup in the footer on all front end pages.
 */
function accw_render_widget() {
	if ( is_admin() ) {
		return;
	}
	$settings = accw_get_settings();

	$logo_url = apply_filters(
		'accw_logo_url',
		'https://tmjsleepkc.com/wp-content/uploads/2025/09/Small_Logo_SVG_TMJ_INT@2x.webp'
	);

	// Render the shortcode output directly so it works inside the widget.
	$chatbody = do_shortcode( '[anchor_chatbot]' );
	?>
	<div class="chat-widget-container" id="accwContainer" aria-live="polite">
		<div class="chat-helper" id="chatHelper"></div>
		<button class="chat-button" id="chatToggle" aria-label="<?php echo esc_attr( $settings['ariaLabelOpen'] ); ?>">
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
