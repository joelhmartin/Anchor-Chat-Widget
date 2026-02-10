<?php
/**
 * Plugin Name: Anchor Corps Chat Widget
 * Description: Adds a floating chat widget that renders the [anchor_chatbot] output inside a toggle panel on every page.
 * Author: Anchor Corps
 * Version: 2.2.9
 * Requires at least: 5.2
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Helper to read environment variables.
 *
 * Prefer $_ENV (populated by phpdotenv) and fall back to getenv().
 *
 * @param string $name
 * @return string|null
 */
function accw_get_env( $name ) {
	if ( isset( $_ENV[ $name ] ) && '' !== $_ENV[ $name ] ) {
		return (string) $_ENV[ $name ];
	}

	$value = getenv( $name );
	if ( false !== $value && '' !== $value ) {
		return (string) $value;
	}

	return null;
}

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
$token = accw_get_env( 'GITHUB_ACCESS_TOKEN' )
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

	// Business context settings.
	register_setting(
		'accw_settings',
		'accw_business_name',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'accw_settings',
		'accw_business_location',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'accw_settings',
		'accw_business_phone',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'accw_settings',
		'accw_business_email',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => '',
		)
	);

	register_setting(
		'accw_settings',
		'accw_business_context',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_kses_post',
			'default'           => '',
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

	// Logo URL.
	register_setting(
		'accw_settings',
		'accw_logo_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);

	// Accent color.
	register_setting(
		'accw_settings',
		'accw_accent_color',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#6c63ff',
		)
	);

	// Custom CSS.
	register_setting(
		'accw_settings',
		'accw_custom_css',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_strip_all_tags',
			'default'           => '',
		)
	);

	// Display visibility settings.
	register_setting(
		'accw_settings',
		'accw_display_mode',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'disabled',
		)
	);

	register_setting(
		'accw_settings',
		'accw_display_pages',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'accw_register_settings' );

/**
 * Enqueue Select2 on the plugin settings page.
 *
 * @param string $hook The current admin page hook.
 */
function accw_admin_enqueue_scripts( $hook ) {
	if ( 'settings_page_accw-settings' !== $hook ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );

	wp_enqueue_style(
		'select2',
		'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
		array(),
		'4.1.0'
	);

	wp_enqueue_script(
		'select2',
		'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
		array( 'jquery' ),
		'4.1.0',
		true
	);

	wp_enqueue_script(
		'accw-rag-admin',
		ACCW_PLUGIN_URL . 'assets/js/rag-admin.js',
		array( 'jquery' ),
		ACCW_PLUGIN_VERSION,
		true
	);

	wp_localize_script( 'accw-rag-admin', 'accwRag', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'accw_rag_nonce' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'accw_admin_enqueue_scripts' );

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
							<label for="accw_client_id"><?php esc_html_e( 'Client Account ID', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_client_id" name="accw_client_id"
								   value="<?php echo esc_attr( get_option( 'accw_client_id', '' ) ); ?>" />
							<p class="description">
								<?php esc_html_e( 'CTM account id that Cloud Run uses to route transcripts and activity.', 'anchor-corps-chat-widget' ); ?>
							</p>
							<?php if ( accw_get_env( 'ACCW_CLIENT_ID' ) ) : ?>
								<p class="description">
									<?php esc_html_e( 'Note: ACCW_CLIENT_ID is set in the server environment and will override this value.', 'anchor-corps-chat-widget' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_business_name"><?php esc_html_e( 'Business name', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_business_name" name="accw_business_name"
								   value="<?php echo esc_attr( get_option( 'accw_business_name', '' ) ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_business_location"><?php esc_html_e( 'Business location', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_business_location" name="accw_business_location"
								   value="<?php echo esc_attr( get_option( 'accw_business_location', '' ) ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_business_phone"><?php esc_html_e( 'Business phone', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_business_phone" name="accw_business_phone"
								   value="<?php echo esc_attr( get_option( 'accw_business_phone', '' ) ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_business_email"><?php esc_html_e( 'Business email', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="email" class="regular-text" id="accw_business_email" name="accw_business_email"
								   value="<?php echo esc_attr( get_option( 'accw_business_email', '' ) ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row" valign="top">
							<label for="accw_business_context"><?php esc_html_e( 'Business context / instructions', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<textarea class="large-text code" rows="6" id="accw_business_context" name="accw_business_context"><?php echo esc_textarea( get_option( 'accw_business_context', '' ) ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Optional prompt additions such as insurance/financing info or special instructions.', 'anchor-corps-chat-widget' ); ?>
							</p>
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
							<label for="accw_logo_url"><?php esc_html_e( 'Logo image', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="accw_logo_url" name="accw_logo_url"
								   value="<?php echo esc_attr( get_option( 'accw_logo_url', '' ) ); ?>" />
							<button type="button" class="button" id="accw_logo_upload"><?php esc_html_e( 'Upload', 'anchor-corps-chat-widget' ); ?></button>
							<?php $logo_preview = get_option( 'accw_logo_url', '' ); ?>
							<div id="accw_logo_preview" style="margin-top:10px;">
								<?php if ( $logo_preview ) : ?>
									<img src="<?php echo esc_url( $logo_preview ); ?>" style="max-height:60px;" />
								<?php endif; ?>
							</div>
							<p class="description">
								<?php esc_html_e( 'Logo displayed in the chat header. Use Upload to pick from the media library, or paste a URL.', 'anchor-corps-chat-widget' ); ?>
							</p>
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

					<tr>
						<th scope="row">
							<label for="accw_accent_color"><?php esc_html_e( 'Accent color', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<input type="text" id="accw_accent_color" name="accw_accent_color"
								   value="<?php echo esc_attr( get_option( 'accw_accent_color', '#6c63ff' ) ); ?>"
								   data-default-color="#6c63ff" />
							<p class="description">
								<?php esc_html_e( 'Primary color used for the chat button, header, user bubbles, and send button.', 'anchor-corps-chat-widget' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row" valign="top">
							<label for="accw_custom_css"><?php esc_html_e( 'Custom CSS', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<textarea class="large-text code" rows="14" id="accw_custom_css" name="accw_custom_css"><?php
								$custom_css = get_option( 'accw_custom_css', '' );
								if ( '' === $custom_css ) {
									$custom_css = "/* Widget container (floating position) */\n/* .chat-widget-container { } */\n\n/* Chat toggle button */\n/* .chat-button { } */\n\n/* Chat window */\n/* .chat-window { } */\n\n/* Chat header */\n/* .chat-header { } */\n\n/* Messages area */\n/* .accw-chatbot__messages { } */\n\n/* User message bubble */\n/* .accw-message-user { } */\n\n/* Bot message bubble */\n/* .accw-message-bot { } */\n\n/* Send button */\n/* .accw-btn-primary { } */";
								}
								echo esc_textarea( $custom_css );
							?></textarea>
							<p class="description">
								<?php esc_html_e( 'Add custom CSS rules to adjust the widget appearance. Uncomment and edit the selectors above as needed.', 'anchor-corps-chat-widget' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="accw_display_mode"><?php esc_html_e( 'Display visibility', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<select id="accw_display_mode" name="accw_display_mode">
								<?php
								$current_mode = get_option( 'accw_display_mode', 'disabled' );
								$mode_options = array(
									'disabled'   => __( 'Disabled (widget hidden everywhere)', 'anchor-corps-chat-widget' ),
									'everywhere' => __( 'Enabled on all pages', 'anchor-corps-chat-widget' ),
									'only'       => __( 'Only on specific pages', 'anchor-corps-chat-widget' ),
									'except'     => __( 'Everywhere except specific pages', 'anchor-corps-chat-widget' ),
								);
								foreach ( $mode_options as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_mode, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Control where the chat widget appears on your site.', 'anchor-corps-chat-widget' ); ?>
							</p>
						</td>
					</tr>

					<tr id="accw_display_pages_row" style="<?php echo in_array( $current_mode, array( 'only', 'except' ), true ) ? '' : 'display:none;'; ?>">
						<th scope="row">
							<label for="accw_display_pages"><?php esc_html_e( 'Select pages', 'anchor-corps-chat-widget' ); ?></label>
						</th>
						<td>
							<?php
							$saved_pages = array_filter( array_map( 'trim', explode( "\n", get_option( 'accw_display_pages', '' ) ) ) );
							$all_pages   = get_posts(
								array(
									'post_type'      => array( 'page', 'post' ),
									'post_status'    => 'publish',
									'posts_per_page' => -1,
									'orderby'        => 'title',
									'order'          => 'ASC',
								)
							);
							?>
							<select id="accw_display_pages_select" multiple="multiple" style="width: 100%; max-width: 400px;">
								<?php foreach ( $all_pages as $page ) :
									$path = wp_parse_url( get_permalink( $page ), PHP_URL_PATH );
									$path = trailingslashit( $path );
									$selected = in_array( $path, $saved_pages, true ) ? 'selected' : '';
								?>
									<option value="<?php echo esc_attr( $path ); ?>" <?php echo $selected; ?>>
										<?php echo esc_html( $page->post_title ); ?> (<?php echo esc_html( $path ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<input type="hidden" id="accw_display_pages" name="accw_display_pages" value="<?php echo esc_attr( get_option( 'accw_display_pages', '' ) ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Search and select pages where the widget should appear (or be hidden).', 'anchor-corps-chat-widget' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<script>
			jQuery(document).ready(function($) {
				// Initialize color picker.
				$('#accw_accent_color').wpColorPicker();

				// Logo media uploader.
				$('#accw_logo_upload').on('click', function(e) {
					e.preventDefault();
					var frame = wp.media({ title: 'Select Logo', multiple: false, library: { type: 'image' } });
					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						$('#accw_logo_url').val(attachment.url);
						$('#accw_logo_preview').html('<img src="' + attachment.url + '" style="max-height:60px;" />');
					});
					frame.open();
				});

				// Initialize Select2 on the pages dropdown.
				$('#accw_display_pages_select').select2({
					placeholder: '<?php echo esc_js( __( 'Search for pages...', 'anchor-corps-chat-widget' ) ); ?>',
					allowClear: true,
					width: '100%'
				});

				// Sync Select2 selections to hidden input.
				$('#accw_display_pages_select').on('change', function() {
					var selected = $(this).val() || [];
					$('#accw_display_pages').val(selected.join("\n"));
				});

				// Toggle pages row visibility based on mode.
				var modeSelect = document.getElementById('accw_display_mode');
				var pagesRow = document.getElementById('accw_display_pages_row');
				if (modeSelect && pagesRow) {
					modeSelect.addEventListener('change', function() {
						pagesRow.style.display = (this.value === 'only' || this.value === 'except') ? '' : 'none';
					});
				}
			});
			</script>

			<?php submit_button(); ?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Knowledge Base', 'anchor-corps-chat-widget' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Upload documents (PDF, TXT, HTML, CSV, DOCX) to train the chat assistant with practice-specific knowledge. The assistant will use these documents to answer patient questions more accurately.', 'anchor-corps-chat-widget' ); ?>
		</p>

		<?php if ( empty( get_option( 'accw_client_id', '' ) ) ) : ?>
			<div class="notice notice-warning inline" style="margin:12px 0;">
				<p><?php esc_html_e( 'Set a Client Account ID above before using the Knowledge Base.', 'anchor-corps-chat-widget' ); ?></p>
			</div>
		<?php else : ?>

		<div id="accw-rag-app">
			<!-- Status -->
			<div id="accw-rag-status" style="margin:12px 0;">
				<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
				<?php esc_html_e( 'Checking knowledge base status…', 'anchor-corps-chat-widget' ); ?>
			</div>

			<!-- Enable / Disable buttons -->
			<div id="accw-rag-controls" style="margin:12px 0; display:none;">
				<button type="button" class="button button-primary" id="accw-rag-enable">
					<?php esc_html_e( 'Enable Knowledge Base', 'anchor-corps-chat-widget' ); ?>
				</button>
				<button type="button" class="button button-link-delete" id="accw-rag-disable" style="display:none;">
					<?php esc_html_e( 'Delete Knowledge Base', 'anchor-corps-chat-widget' ); ?>
				</button>
			</div>

			<!-- Upload form (visible only when corpus exists) -->
			<div id="accw-rag-upload-section" style="display:none; margin:16px 0;">
				<h3><?php esc_html_e( 'Upload Documents', 'anchor-corps-chat-widget' ); ?></h3>
				<form id="accw-rag-upload-form" enctype="multipart/form-data">
					<input type="file" id="accw-rag-file" name="file"
						   accept=".pdf,.txt,.html,.csv,.docx" multiple />
					<button type="submit" class="button button-primary" id="accw-rag-upload-btn">
						<?php esc_html_e( 'Upload & Import', 'anchor-corps-chat-widget' ); ?>
					</button>
					<span class="spinner" id="accw-rag-upload-spinner" style="float:none;margin:0 0 0 8px;"></span>
				</form>
				<p class="description">
					<?php esc_html_e( 'Max 10 MB per file. Supported: PDF, TXT, HTML, CSV, DOCX. Select multiple files to upload at once. Files are processed and indexed — this may take a minute.', 'anchor-corps-chat-widget' ); ?>
				</p>
				<div id="accw-rag-upload-status" style="margin:8px 0;"></div>
			</div>

			<!-- File list (visible only when corpus exists) -->
			<div id="accw-rag-files-section" style="display:none; margin:16px 0;">
				<h3><?php esc_html_e( 'Uploaded Documents', 'anchor-corps-chat-widget' ); ?></h3>
				<table class="wp-list-table widefat striped" id="accw-rag-files-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Document', 'anchor-corps-chat-widget' ); ?></th>
							<th><?php esc_html_e( 'Size', 'anchor-corps-chat-widget' ); ?></th>
							<th><?php esc_html_e( 'Added', 'anchor-corps-chat-widget' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'anchor-corps-chat-widget' ); ?></th>
						</tr>
					</thead>
					<tbody id="accw-rag-files-body">
						<tr><td colspan="4"><?php esc_html_e( 'Loading…', 'anchor-corps-chat-widget' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<?php endif; ?>
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
		'headerTitle'          => accw_get_env( 'ACCW_HEADER_TITLE' )
			?: get_option( 'accw_header_title', 'Chat with us' ),
		'headerSubtitle'       => accw_get_env( 'ACCW_HEADER_SUBTITLE' )
			?: get_option( 'accw_header_subtitle', 'We are here to help' ),
		'helperText'           => accw_get_env( 'ACCW_HELPER_TEXT' )
			?: get_option( 'accw_helper_text', 'Hi, how can we help?' ),
		// Fixed backend endpoints with optional env overrides.
		'apiUrl'               => accw_get_env( 'ACCW_API_URL' )
			?: 'https://ai-endpoint-kqikza7ska-ew.a.run.app/chat',
		'apiAuthToken'         => accw_get_env( 'ACCW_API_AUTH_TOKEN' )
			?: '', // keep token in env only
		'forwardTranscriptUrl' => accw_get_env( 'ACCW_FORWARD_TRANSCRIPT_URL' )
			?: 'https://ai-endpoint-kqikza7ska-ew.a.run.app/lead',
		'clientId'             => accw_get_env( 'ACCW_CLIENT_ID' )
			?: get_option( 'accw_client_id', '' ),
		// Forward token must match FORWARD_TOKEN on Cloud Run.
		'forwardToken'         => accw_get_env( 'ACCW_FORWARD_TOKEN' )
			?: 'anchor_forward_token_v1',
		'businessName'         => accw_get_env( 'ACCW_BUSINESS_NAME' )
			?: get_option( 'accw_business_name', '' ),
		'businessLocation'     => accw_get_env( 'ACCW_BUSINESS_LOCATION' )
			?: get_option( 'accw_business_location', '' ),
		'businessPhone'        => accw_get_env( 'ACCW_BUSINESS_PHONE' )
			?: get_option( 'accw_business_phone', '' ),
		'businessEmail'        => accw_get_env( 'ACCW_BUSINESS_EMAIL' )
			?: get_option( 'accw_business_email', '' ),
		'businessContext'      => accw_get_env( 'ACCW_BUSINESS_CONTEXT' )
			?: get_option( 'accw_business_context', '' ),
		'position'             => accw_get_env( 'ACCW_POSITION' )
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
 * Check if the widget should be displayed on the current page.
 *
 * @return bool
 */
function accw_should_display() {
	$mode = get_option( 'accw_display_mode', 'disabled' );

	// Disabled mode - never show.
	if ( 'disabled' === $mode ) {
		return false;
	}

	// Everywhere mode - always show.
	if ( 'everywhere' === $mode ) {
		return true;
	}

	// Get current request path.
	$current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '/';
	$current_path = trailingslashit( $current_path );

	// Parse the page paths setting.
	$pages_raw = get_option( 'accw_display_pages', '' );
	$pages     = array_filter( array_map( 'trim', explode( "\n", $pages_raw ) ) );

	if ( empty( $pages ) ) {
		// No pages specified: 'only' mode shows nothing, 'except' mode shows everywhere.
		return 'except' === $mode;
	}

	// Check if current path matches any pattern.
	$matches = false;
	foreach ( $pages as $pattern ) {
		$pattern = trailingslashit( trim( $pattern ) );

		// Convert wildcard pattern to regex.
		if ( false !== strpos( $pattern, '*' ) ) {
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
			if ( preg_match( $regex, $current_path ) ) {
				$matches = true;
				break;
			}
		} elseif ( $current_path === $pattern ) {
			$matches = true;
			break;
		}
	}

	// 'only' mode: show if matches. 'except' mode: show if doesn't match.
	return 'only' === $mode ? $matches : ! $matches;
}

/**
 * Enqueue styles and scripts globally on the front end.
 */
function accw_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}

	if ( ! accw_should_display() ) {
		return;
	}
	$settings = accw_get_settings();
	$css_ver = (string) filemtime( ACCW_PLUGIN_DIR . 'assets/css/chat-widget.css' );
	$js_ver  = (string) filemtime( ACCW_PLUGIN_DIR . 'assets/js/chat-widget.js' );

	wp_enqueue_style(
		'accw-chat-widget',
		ACCW_PLUGIN_URL . 'assets/css/chat-widget.css',
		array(),
		$css_ver
	);

	wp_enqueue_script(
		'accw-chat-widget',
		ACCW_PLUGIN_URL . 'assets/js/chat-widget.js',
		array(),
		$js_ver,
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
		'businessName'        => $settings['businessName'],
		'businessLocation'    => $settings['businessLocation'],
		'businessPhone'       => $settings['businessPhone'],
		'businessEmail'       => $settings['businessEmail'],
		'businessContext'     => $settings['businessContext'],
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

	if ( ! accw_should_display() ) {
		return;
	}
	$settings = accw_get_settings();

	$logo_url = get_option( 'accw_logo_url', '' );
	$logo_url = apply_filters( 'accw_logo_url', $logo_url );

	$chatbody = do_shortcode( '[anchor_chatbot]' );
	if ( empty( $chatbody ) && current_user_can( 'manage_options' ) ) {
		$chatbody = '<div class="accw-chatbot-warning">' . esc_html__( 'The [anchor_chatbot] shortcode did not output any markup. Verify the plugin is active.', 'anchor-corps-chat-widget' ) . '</div>';
	}
	?>
	<div class="chat-widget-container" id="accwContainer" aria-live="polite">
		<button class="chat-button" id="chatToggle" aria-label="<?php echo esc_attr( $settings['ariaLabelOpen'] ); ?>">
			<svg class="chat-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
				<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
			</svg>
			<svg class="close-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
				<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
			</svg>
		</button>

		<div class="chat-helper" id="chatHelper"></div>

		<div class="chat-window" id="chatWindow" role="dialog" aria-modal="false" aria-labelledby="accwHeaderTitle">
			<div class="chat-header">
				<?php if ( $logo_url ) : ?>
				<div class="logo-container">
					<div class="logo-placeholder">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="" loading="lazy" decoding="async" />
					</div>
				</div>
				<?php endif; ?>
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
add_action( 'wp_footer', 'accw_render_widget', 10 );

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
		<div class="accw-chatbot__messages" data-accw-messages role="log" aria-live="polite" aria-busy="false">
			<div class="accw-message accw-message-bot accw-lead-bubble" data-accw-lead-bubble>
				<p class="accw-lead__title"><?php esc_html_e( 'In case we get disconnected, can you share contact details?', 'anchor-corps-chat-widget' ); ?></p>
				<form class="accw-lead" data-accw-lead-form novalidate>
					<div class="accw-lead__row">
						<label class="accw-visually-hidden" for="accwLeadName-<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Name', 'anchor-corps-chat-widget' ); ?></label>
						<input type="text" id="accwLeadName-<?php echo esc_attr( $uid ); ?>" data-accw-lead-name placeholder="<?php esc_attr_e( 'Name', 'anchor-corps-chat-widget' ); ?>" />
					</div>
					<div class="accw-lead__row">
						<label class="accw-visually-hidden" for="accwLeadEmail-<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Email', 'anchor-corps-chat-widget' ); ?></label>
						<input type="email" id="accwLeadEmail-<?php echo esc_attr( $uid ); ?>" data-accw-lead-email placeholder="<?php esc_attr_e( 'Email', 'anchor-corps-chat-widget' ); ?>" />
					</div>
					<div class="accw-lead__row">
						<label class="accw-visually-hidden" for="accwLeadPhone-<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Phone', 'anchor-corps-chat-widget' ); ?></label>
						<input type="tel" id="accwLeadPhone-<?php echo esc_attr( $uid ); ?>" data-accw-lead-phone placeholder="<?php esc_attr_e( 'Phone', 'anchor-corps-chat-widget' ); ?>" />
					</div>
					<div class="accw-lead__actions">
						<button type="submit" class="accw-btn accw-btn-primary" data-accw-lead-submit><?php esc_html_e( 'Send', 'anchor-corps-chat-widget' ); ?></button>
						<div class="accw-lead__status" data-accw-lead-status></div>
					</div>
					<div class="accw-lead__call" data-accw-lead-call style="display:none;">
						<a href="#" rel="nofollow noopener"></a>
					</div>
				</form>
			</div>
		</div>
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
 * Output CSS custom properties and optional custom CSS.
 */
function accw_root_css_vars() {
	if ( is_admin() || ! accw_should_display() ) {
		return;
	}

	$accent = get_option( 'accw_accent_color', '#6c63ff' );
	if ( ! $accent || ! preg_match( '/^#[0-9a-fA-F]{3,6}$/', $accent ) ) {
		$accent = '#6c63ff';
	}

	// Darken the accent color by 25 % for --color-dark.
	$hex = ltrim( $accent, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$r = max( 0, (int) round( hexdec( substr( $hex, 0, 2 ) ) * 0.75 ) );
	$g = max( 0, (int) round( hexdec( substr( $hex, 2, 2 ) ) * 0.75 ) );
	$b = max( 0, (int) round( hexdec( substr( $hex, 4, 2 ) ) * 0.75 ) );
	$dark = sprintf( '#%02x%02x%02x', $r, $g, $b );

	$css = ':root{--color-accent:' . esc_attr( $accent ) . ';--color-dark:' . esc_attr( $dark ) . ';}';
	wp_add_inline_style( 'accw-chat-widget', $css );

	// Custom CSS.
	$custom_css = get_option( 'accw_custom_css', '' );
	if ( $custom_css ) {
		wp_add_inline_style( 'accw-chat-widget', wp_strip_all_tags( $custom_css ) );
	}
}
add_action( 'wp_enqueue_scripts', 'accw_root_css_vars', 6 );

// ── RAG Knowledge Base admin ────────────────────────────────────────

/**
 * Derive the Cloud Run base URL from the chat API URL.
 *
 * @return string Base URL without trailing slash, or empty string.
 */
function accw_cloud_run_base_url() {
	$settings = accw_get_settings();
	$api_url  = $settings['apiUrl'] ?? '';
	if ( ! $api_url ) {
		return '';
	}
	// Strip the /chat path to get base URL.
	$parsed = wp_parse_url( $api_url );
	if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
		return '';
	}
	return $parsed['scheme'] . '://' . $parsed['host'];
}

/**
 * Make an authenticated request to a Cloud Run RAG endpoint.
 *
 * @param string $method  HTTP method.
 * @param string $path    Path relative to base URL (e.g. "/rag/status").
 * @param array  $args    Query params for GET, body params for POST/DELETE.
 * @return array|WP_Error Decoded JSON response or WP_Error.
 */
function accw_rag_request( $method, $path, $args = array() ) {
	$base = accw_cloud_run_base_url();
	if ( ! $base ) {
		return new WP_Error( 'no_base_url', 'Cloud Run API URL is not configured.' );
	}

	$settings = accw_get_settings();
	$token    = $settings['forwardToken'] ?? '';

	$url      = $base . $path;
	$method   = strtoupper( $method );

	if ( 'GET' === $method ) {
		$args['token'] = $token;
		$url           = add_query_arg( $args, $url );
		$response      = wp_remote_get( $url, array( 'timeout' => 120 ) );
	} else {
		$args['token'] = $token;
		$response      = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'timeout' => 120,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $args ),
			)
		);
	}

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code >= 400 ) {
		$msg = isset( $body['error'] ) ? $body['error'] : "HTTP $code";
		return new WP_Error( 'rag_api_error', $msg, array( 'status' => $code ) );
	}

	return $body ?: array();
}

/**
 * Upload a file to the Cloud Run RAG endpoint via multipart POST.
 *
 * @param string $file_path     Temporary file path.
 * @param string $file_name     Original file name.
 * @param string $file_type     MIME type.
 * @param string $client_id     Client ID.
 * @return array|WP_Error
 */
function accw_rag_upload_file( $file_path, $file_name, $file_type, $client_id ) {
	$base = accw_cloud_run_base_url();
	if ( ! $base ) {
		return new WP_Error( 'no_base_url', 'Cloud Run API URL is not configured.' );
	}

	$settings = accw_get_settings();
	$token    = $settings['forwardToken'] ?? '';

	$boundary = wp_generate_password( 24, false );
	$body     = '';

	// token field.
	$body .= "--{$boundary}\r\n";
	$body .= "Content-Disposition: form-data; name=\"token\"\r\n\r\n";
	$body .= $token . "\r\n";

	// clientId field.
	$body .= "--{$boundary}\r\n";
	$body .= "Content-Disposition: form-data; name=\"clientId\"\r\n\r\n";
	$body .= $client_id . "\r\n";

	// file field.
	$body .= "--{$boundary}\r\n";
	$body .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . $file_name . "\"\r\n";
	$body .= "Content-Type: " . $file_type . "\r\n\r\n";
	$body .= file_get_contents( $file_path ) . "\r\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$body .= "--{$boundary}--\r\n";

	$response = wp_remote_post(
		$base . '/rag/files',
		array(
			'timeout' => 120,
			'headers' => array(
				'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
			),
			'body'    => $body,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code      = wp_remote_retrieve_response_code( $response );
	$resp_body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code >= 400 ) {
		$msg = isset( $resp_body['error'] ) ? $resp_body['error'] : "HTTP $code";
		return new WP_Error( 'rag_upload_error', $msg, array( 'status' => $code ) );
	}

	return $resp_body ?: array();
}

// ── AJAX handlers ───────────────────────────────────────────────────

/**
 * AJAX: Get RAG status for this client.
 */
function accw_ajax_rag_status() {
	check_ajax_referer( 'accw_rag_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	$client_id = get_option( 'accw_client_id', '' );
	if ( ! $client_id ) {
		wp_send_json_error( 'Client ID not configured.' );
	}

	$result = accw_rag_request( 'GET', '/rag/status', array( 'clientId' => $client_id ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_accw_rag_status', 'accw_ajax_rag_status' );

/**
 * AJAX: Create a RAG corpus for this client.
 */
function accw_ajax_rag_create_corpus() {
	check_ajax_referer( 'accw_rag_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	$client_id = get_option( 'accw_client_id', '' );
	$biz_name  = get_option( 'accw_business_name', 'Knowledge Base' );
	if ( ! $client_id ) {
		wp_send_json_error( 'Client ID not configured.' );
	}

	$result = accw_rag_request( 'POST', '/rag/corpus', array(
		'clientId' => $client_id,
		'name'     => $biz_name,
	) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_accw_rag_create_corpus', 'accw_ajax_rag_create_corpus' );

/**
 * AJAX: Delete the RAG corpus for this client.
 */
function accw_ajax_rag_delete_corpus() {
	check_ajax_referer( 'accw_rag_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	$client_id = get_option( 'accw_client_id', '' );
	if ( ! $client_id ) {
		wp_send_json_error( 'Client ID not configured.' );
	}

	$result = accw_rag_request( 'DELETE', '/rag/corpus', array(
		'clientId' => $client_id,
	) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_accw_rag_delete_corpus', 'accw_ajax_rag_delete_corpus' );

/**
 * AJAX: Upload a file to the RAG corpus.
 */
function accw_ajax_rag_upload_file() {
	check_ajax_referer( 'accw_rag_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	$client_id = get_option( 'accw_client_id', '' );
	if ( ! $client_id ) {
		wp_send_json_error( 'Client ID not configured.' );
	}

	if ( empty( $_FILES['file'] ) ) {
		wp_send_json_error( 'No file uploaded.' );
	}

	$file = $_FILES['file'];
	if ( $file['error'] !== UPLOAD_ERR_OK ) {
		wp_send_json_error( 'Upload error code: ' . $file['error'] );
	}

	$allowed = array(
		'application/pdf',
		'text/plain',
		'text/html',
		'text/csv',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	);
	if ( ! in_array( $file['type'], $allowed, true ) ) {
		wp_send_json_error( 'Unsupported file type: ' . $file['type'] );
	}

	if ( $file['size'] > 10 * 1024 * 1024 ) {
		wp_send_json_error( 'File too large. Maximum 10 MB.' );
	}

	$result = accw_rag_upload_file( $file['tmp_name'], $file['name'], $file['type'], $client_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_accw_rag_upload_file', 'accw_ajax_rag_upload_file' );

/**
 * AJAX: List files in the RAG corpus.
 */
function accw_ajax_rag_list_files() {
	check_ajax_referer( 'accw_rag_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	$client_id = get_option( 'accw_client_id', '' );
	if ( ! $client_id ) {
		wp_send_json_error( 'Client ID not configured.' );
	}

	$result = accw_rag_request( 'GET', '/rag/files', array( 'clientId' => $client_id ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_accw_rag_list_files', 'accw_ajax_rag_list_files' );

/**
 * AJAX: Delete a file from the RAG corpus.
 */
function accw_ajax_rag_delete_file() {
	check_ajax_referer( 'accw_rag_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	$client_id    = get_option( 'accw_client_id', '' );
	$rag_file_name = isset( $_POST['ragFileName'] ) ? sanitize_text_field( wp_unslash( $_POST['ragFileName'] ) ) : '';

	if ( ! $client_id ) {
		wp_send_json_error( 'Client ID not configured.' );
	}
	if ( ! $rag_file_name ) {
		wp_send_json_error( 'Missing ragFileName.' );
	}

	$result = accw_rag_request( 'DELETE', '/rag/files', array(
		'clientId'    => $client_id,
		'ragFileName' => $rag_file_name,
	) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_accw_rag_delete_file', 'accw_ajax_rag_delete_file' );
