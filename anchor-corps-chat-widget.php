<?php
/**
 * Plugin Name: Anchor Corps Chat Widget
 * Description: Adds a floating chat widget that renders the [anchor_chatbot] output inside a toggle panel on every page.
 * Author: Anchor Corps
 * Version: 2.0.7
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
 * Register plugin settings for the admin settings page.
 */
function accw_register_settings() {
	// Core connection settings.
	register_setting(
		'accw_settings',
		'accw_api_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);

	register_setting(
		'accw_settings',
		'accw_forward_transcript_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);

	register_setting(
		'accw_settings',
		'accw_client_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'accw_settings',
		'accw_forward_token',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'anchor_forward_token_v1',
		)
	);

	// Optional UI text settings.
	register_setting(
		'accw_settings',
		'accw_header_title',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Chat with us',
		)
	);

	register_setting(
		'accw_settings',
		'accw_header_subtitle',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'We are here to help',
		)
	);

	register_setting(
		'accw_settings',
		'accw_helper_text',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Hi, how can we help?',
		)
	);

	register_setting(
		'accw_settings',
		'accw_position',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'bottom-right',
		)
	);
}
add_action( 'admin_init', 'accw_register_settings' );

/**
 * Add settings page under "Settings".
 */
function accw_add_settings_page() {
	add_options_page(
		'Anchor Chat Widget',
		'Anchor Chat Widget',
		'manage_options',
		'accw-settings',
		'accw_render_settings_page'
	);
}
add_action( 'admin_menu', 'accw_add_settings_page' );

/**
 * Render the settings page content.
 */
function accw_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = accw_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Anchor Corps Chat Widget', 'anchor-corps-chat-widget' ); ?></h1>
		<p>
			<?php esc_html_e( 'Configure the Cloud Run endpoints and client identifiers used by the chat widget.', 'anchor-corps-chat-widget' ); ?>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'accw_settings' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="accw_api_url"><?php esc_html_e( 'Chat API URL (Cloud Run /chat)', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="url" class="regular-text" id="accw_api_url" name="accw_api_url"
								   value="<?php echo esc_attr( get_option( 'accw_api_url', '' ) ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Full URL to your Cloud Run /chat endpoint.', 'anchor-corps-chat-widget' ); ?>
							</p>
							<?php if ( getenv( 'ACCW_API_URL' ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'Note: ACCW_API_URL is set in the server environment and will override this value.', 'anchor-corps-chat-widget' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_forward_transcript_url"><?php esc_html_e( 'Transcript URL (Cloud Run /transcript)', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="url" class="regular-text" id="accw_forward_transcript_url" name="accw_forward_transcript_url"
								   value="<?php echo esc_attr( get_option( 'accw_forward_transcript_url', '' ) ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Full URL to your Cloud Run /transcript endpoint.', 'anchor-corps-chat-widget' ); ?>
							</p>
							<?php if ( getenv( 'ACCW_FORWARD_TRANSCRIPT_URL' ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'Note: ACCW_FORWARD_TRANSCRIPT_URL is set in the server environment and will override this value.', 'anchor-corps-chat-widget' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_client_id"><?php esc_html_e( 'Client Account ID', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_client_id" name="accw_client_id"
								   value="<?php echo esc_attr( get_option( 'accw_client_id', '' ) ); ?>" />
							<p class="description">
								<?php esc_html_e( 'CTM account id that Cloud Run uses to route transcripts and activity.', 'anchor-corps-chat-widget' ); ?>
							</p>
							<?php if ( getenv( 'ACCW_CLIENT_ID' ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'Note: ACCW_CLIENT_ID is set in the server environment and will override this value.', 'anchor-corps-chat-widget' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_forward_token"><?php esc_html_e( 'Forward Token', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_forward_token" name="accw_forward_token"
								   value="<?php echo esc_attr( get_option( 'accw_forward_token', 'anchor_forward_token_v1' ) ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Shared secret token that Cloud Run validates before forwarding a transcript to CTM.', 'anchor-corps-chat-widget' ); ?>
							</p>
							<?php if ( getenv( 'ACCW_FORWARD_TOKEN' ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'Note: ACCW_FORWARD_TOKEN is set in the server environment and will override this value.', 'anchor-corps-chat-widget' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_header_title"><?php esc_html_e( 'Header title', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_header_title" name="accw_header_title"
								   value="<?php echo esc_attr( get_option( 'accw_header_title', 'Chat with us' ) ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_header_subtitle"><?php esc_html_e( 'Header subtitle', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_header_subtitle" name="accw_header_subtitle"
								   value="<?php echo esc_attr( get_option( 'accw_header_subtitle', 'We are here to help' ) ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_helper_text"><?php esc_html_e( 'Helper text', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_helper_text" name="accw_helper_text"
								   value="<?php echo esc_attr( get_option( 'accw_helper_text', 'Hi, how can we help?' ) ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_position"><?php esc_html_e( 'Widget position', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<select id="accw_position" name="accw_position">
								<?php
								$current_position = get_option( 'accw_position', 'bottom-right' );
								$options = array(
									'bottom-right' => __( 'Bottom right', 'anchor-corps-chat-widget' ),
									'bottom-left'  => __( 'Bottom left', 'anchor-corps-chat-widget' ),
								);
								foreach ( $options as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_position, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Derive widget settings from environment variables, options, and defaults.
 *
 * Env vars take precedence over stored options so you can override per environment.
 *
 * @return array<string,string>
 */
function accw_get_settings() {
	static $settings = null;

	if ( null !== $settings ) {
		return $settings;
	}

	$settings = array(
		'headerTitle'          => getenv( 'ACCW_HEADER_TITLE' )
			?: get_option( 'accw_header_title', 'Chat with us' ),
		'headerSubtitle'       => getenv( 'ACCW_HEADER_SUBTITLE' )
			?: get_option( 'accw_header_subtitle', 'We are here to help' ),
		'helperText'           => getenv( 'ACCW_HELPER_TEXT' )
			?: get_option( 'accw_helper_text', 'Hi, how can we help?' ),
		'apiUrl'               => getenv( 'ACCW_API_URL' )
			?: get_option( 'accw_api_url', '' ),
		'apiAuthToken'         => getenv( 'ACCW_API_AUTH_TOKEN' )
			?: '', // keep token in env only
		'forwardTranscriptUrl' => getenv( 'ACCW_FORWARD_TRANSCRIPT_URL' )
			?: get_option( 'accw_forward_transcript_url', '' ),
		'clientId'             => getenv( 'ACCW_CLIENT_ID' )
			?: get_option( 'accw_client_id', '' ),
		'forwardToken'         => getenv( 'ACCW_FORWARD_TOKEN' )
			?: get_option( 'accw_forward_token', 'anchor_forward_token_v1' ),
		'position'             => getenv( 'ACCW_POSITION' )
			?: get_option( 'accw_position', 'bottom-right' ),
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
		'helperText'    => $settings['helperText'],
		'headerTitle'   => $settings['headerTitle'],
		'headerSubtitle'=> $settings['headerSubtitle'],
		'ariaLabelOpen' => $settings['ariaLabelOpen'],
	);

	$config = array(
		'headerTitle'         => $settings['headerTitle'],
		'headerSubtitle'      => $settings['headerSubtitle'],
		'helperText'          => $settings['helperText'],
		'apiUrl'              => $settings['apiUrl'],
		'apiAuthToken'        => $settings['apiAuthToken'],
		'forwardTranscriptUrl'=> $settings['forwardTranscriptUrl'],
		'clientId'            => $settings['clientId'],
		'forwardToken'        => $settings['forwardToken'],
		'position'            => $settings['position'],
	);

	$inline  = 'window.ACCW_STRINGS = ' . wp_json_encode( $strings ) . ';';
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

	$chatbody = do_shortcode( '[anchor_chatbot]' );
	if ( empty( $chatbody ) && current_user_can( 'manage_options' ) ) {
		$chatbody = '<div class="accw-chatbot-warning">' . esc_html__( 'The [anchor_chatbot] shortcode did not output any markup. Verify the plugin is active.', 'anchor-corps-chat-widget' ) . '</div>';
	}
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
 * Register [anchor_chatbot] if nothing else has.
 */
function accw_register_shortcodes() {
	if ( shortcode_exists( 'anchor_chatbot' ) ) {
		return;
	}

	add_shortcode( 'anchor_chatbot', 'accw_render_chatbot_shortcode' );
}
add_action( 'init', 'accw_register_shortcodes' );

/**
 * Default [anchor_chatbot] implementation that renders the live chat UI.
 *
 * @param array<string,string> $atts
 * @return string
 */
function accw_render_chatbot_shortcode( $atts = array() ) {
	$settings = accw_get_settings();
	$atts     = shortcode_atts(
		array(
			'intro' => $settings['helperText'],
		),
		$atts,
		'anchor_chatbot'
	);

	if ( empty( $settings['apiUrl'] ) ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<div class="accw-chatbot-warning">' . esc_html__( 'Set ACCW_API_URL (Chatbot API URL) or configure the Chat API URL in Settings → Anchor Chat Widget to enable the chat experience.', 'anchor-corps-chat-widget' ) . '</div>';
		}
		return '<div class="accw-chatbot-offline">' . esc_html__( 'Chat is unavailable right now.', 'anchor-corps-chat-widget' ) . '</div>';
	}

	$uid = uniqid( 'accw_', false );

	ob_start();
	?>
	<div class="accw-chatbot" data-accw-chatbot="<?php echo esc_attr( $uid ); ?>" data-accw-intro="<?php echo esc_attr( $atts['intro'] ); ?>">
		<div class="accw-chatbot__messages" data-accw-messages role="log" aria-live="polite" aria-busy="false"></div>
		<div class="accw-chatbot__status" data-accw-status></div>
		<form class="accw-chatbot__form" data-accw-form novalidate>
			<label class="accw-visually-hidden" for="accwInput-<?php echo esc_attr( $uid ); ?>">
				<?php esc_html_e( 'Type your message', 'anchor-corps-chat-widget' ); ?>
			</label>
			<textarea
				id="accwInput-<?php echo esc_attr( $uid ); ?>"
				class="accw-chatbot__input"
				data-accw-input
				rows="2"
				placeholder="<?php echo esc_attr__( 'Ask us anything about your care...', 'anchor-corps-chat-widget' ); ?>"
			></textarea>
			<div class="accw-chatbot__actions">
				<button type="submit" class="accw-btn accw-btn-primary" data-accw-send><?php esc_html_e( 'Send', 'anchor-corps-chat-widget' ); ?></button>
				<button type="button" class="accw-btn accw-btn-secondary" data-accw-end><?php esc_html_e( 'End chat', 'anchor-corps-chat-widget' ); ?></button>
			</div>
		</form>
	</div>
	<?php

	$output = ob_get_clean();

	/**
	 * Filter the rendered chatbot markup.
	 *
	 * @param string $output
	 * @param array  $settings
	 * @param array  $atts
	 */
	return apply_filters( 'accw_chatbot_markup', $output, $settings, $atts );
}

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
