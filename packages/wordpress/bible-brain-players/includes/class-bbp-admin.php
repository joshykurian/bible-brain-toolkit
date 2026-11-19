<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBPlay_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_bbplay_flush', array( __CLASS__, 'handle_flush' ) );
	}

	public static function menu() {
		add_options_page(
			__( 'Bible Brain Players', 'bible-brain-players' ),
			__( 'Bible Brain Players', 'bible-brain-players' ),
			'manage_options',
			'bible-brain-players',
			array( __CLASS__, 'page' )
		);
	}

	public static function register() {
		register_setting(
			'bbplay_group',
			BBPlay_Settings::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'BBPlay_Settings', 'sanitize' ),
				'default'           => BBPlay_Settings::defaults(),
			)
		);
	}

	public static function assets( $hook ) {
		if ( 'settings_page_bible-brain-players' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'bbplay-admin', BBPLAY_URL . 'assets/css/admin.css', array(), BBPLAY_VERSION );
		wp_enqueue_script( 'bbplay-admin', BBPLAY_URL . 'assets/js/admin.js', array(), BBPLAY_VERSION, true );
		wp_localize_script(
			'bbplay-admin',
			'BBP_ADMIN',
			array(
				'rest'  => esc_url_raw( rest_url( 'bible-brain-players/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'choose'   => __( 'Choose...', 'bible-brain-players' ),
					'loading'  => __( 'Loading...', 'bible-brain-players' ),
					'none'     => __( 'Nothing found for this selection.', 'bible-brain-players' ),
					'testing'  => __( 'Testing...', 'bible-brain-players' ),
					'error'    => __( 'Request failed.', 'bible-brain-players' ),
					'selected' => __( 'Saved when you press Save Changes.', 'bible-brain-players' ),
				),
			)
		);
	}

	public static function handle_flush() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bible-brain-players' ) );
		}
		check_admin_referer( 'bbplay_flush' );
		BBPlay_API::flush();
		wp_safe_redirect( add_query_arg( array( 'page' => 'bible-brain-players', 'bbplay_flushed' => 1 ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	private static function card( $type, $title, $desc, $s ) {
		$d = $s['defaults'][ $type ];
		$n = BBPlay_Settings::OPT . '[defaults][' . $type . ']';
		?>
		<div class="bbp-card bbp-default-card" data-media="<?php echo esc_attr( 'reader' === $type ? 'text' : $type ); ?>"
			data-country="<?php echo esc_attr( $d['country'] ); ?>"
			data-language="<?php echo esc_attr( $d['language'] ); ?>"
			data-bible="<?php echo esc_attr( $d['bible'] ); ?>"
			data-fileset="<?php echo esc_attr( $d['fileset'] ); ?>">
			<h3><?php echo esc_html( $title ); ?></h3>
			<p class="description"><?php echo esc_html( $desc ); ?></p>
			<label><span><?php esc_html_e( 'Country', 'bible-brain-players' ); ?></span><select class="bbp-s-country"></select></label>
			<label><span><?php esc_html_e( 'Language', 'bible-brain-players' ); ?></span><select class="bbp-s-language"></select></label>
			<label><span><?php esc_html_e( 'Version', 'bible-brain-players' ); ?></span><select class="bbp-s-bible"></select></label>
			<label><span><?php esc_html_e( 'Edition', 'bible-brain-players' ); ?></span><select class="bbp-s-fileset"></select></label>
			<input type="hidden" class="bbp-h-country" name="<?php echo esc_attr( $n ); ?>[country]" value="<?php echo esc_attr( $d['country'] ); ?>">
			<input type="hidden" class="bbp-h-language" name="<?php echo esc_attr( $n ); ?>[language]" value="<?php echo esc_attr( $d['language'] ); ?>">
			<input type="hidden" class="bbp-h-bible" name="<?php echo esc_attr( $n ); ?>[bible]" value="<?php echo esc_attr( $d['bible'] ); ?>">
			<input type="hidden" class="bbp-h-fileset" name="<?php echo esc_attr( $n ); ?>[fileset]" value="<?php echo esc_attr( $d['fileset'] ); ?>">
			<p class="bbp-card-status" aria-live="polite"></p>
		</div>
		<?php
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = BBPlay_Settings::get();
		$opt = BBPlay_Settings::OPT;
		?>
		<div class="wrap bbp-admin">
			<h1><?php esc_html_e( 'Bible Brain Players', 'bible-brain-players' ); ?></h1>
			<p class="bbp-lead"><?php esc_html_e( 'Place an audio-first Bible Reader, Audio Player and Video Player anywhere on your site. Visitors pick their country, language and version.', 'bible-brain-players' ); ?></p>

			<?php if ( isset( $_GET['bbplay_flushed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cached catalog data was cleared.', 'bible-brain-players' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'bbplay_group' ); ?>

				<h2><?php esc_html_e( '1. Connect to Bible Brain', 'bible-brain-players' ); ?></h2>
				<div class="bbp-card">
					<ol class="bbp-steps">
						<li>
							<?php
							printf(
								/* translators: %s: link to key request page */
								esc_html__( 'Request your free API key at %s.', 'bible-brain-players' ),
								'<a href="https://4.dbt.io/api_key/request" target="_blank" rel="noopener noreferrer">4.dbt.io/api_key/request</a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Paste the key below and save.', 'bible-brain-players' ); ?></li>
						<li><?php esc_html_e( 'Press Test connection.', 'bible-brain-players' ); ?></li>
					</ol>
					<label class="bbp-wide"><span><?php esc_html_e( 'API key', 'bible-brain-players' ); ?></span>
						<input type="password" autocomplete="off" class="regular-text code" name="<?php echo esc_attr( $opt ); ?>[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>">
					</label>
					<p>
						<button type="button" class="button" id="bbp-test"><?php esc_html_e( 'Test connection', 'bible-brain-players' ); ?></button>
						<span id="bbp-test-result" aria-live="polite"></span>
					</p>
					<p class="description"><?php esc_html_e( 'The key stays on your server. Visitors never see it. Please follow the Bible Brain license and copyright terms for the content you display.', 'bible-brain-players' ); ?></p>
				</div>

				<h2><?php esc_html_e( '2. Choose default content', 'bible-brain-players' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Each player opens with this version. Visitors can change it when the selector is shown. Pick country, language, version and edition for each player.', 'bible-brain-players' ); ?></p>
				<div class="bbp-grid">
					<?php
					self::card( 'reader', __( 'Bible Reader', 'bible-brain-players' ), __( 'Text, with optional listen-while-you-read.', 'bible-brain-players' ), $s );
					self::card( 'audio', __( 'Audio Player', 'bible-brain-players' ), __( 'Audio Bible, chapter by chapter.', 'bible-brain-players' ), $s );
					self::card( 'video', __( 'Video Player', 'bible-brain-players' ), __( 'Gospel films and other video Scripture.', 'bible-brain-players' ), $s );
					?>
				</div>

				<h2><?php esc_html_e( '3. Look, reach and engagement', 'bible-brain-players' ); ?></h2>
				<div class="bbp-card">
					<label><span><?php esc_html_e( 'Accent color', 'bible-brain-players' ); ?></span>
						<input type="color" name="<?php echo esc_attr( $opt ); ?>[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>"></label>
					<label><span><?php esc_html_e( 'Theme', 'bible-brain-players' ); ?></span>
						<select name="<?php echo esc_attr( $opt ); ?>[theme]">
							<option value="auto" <?php selected( $s['theme'], 'auto' ); ?>><?php esc_html_e( 'Follow the visitor device', 'bible-brain-players' ); ?></option>
							<option value="light" <?php selected( $s['theme'], 'light' ); ?>><?php esc_html_e( 'Always light', 'bible-brain-players' ); ?></option>
							<option value="dark" <?php selected( $s['theme'], 'dark' ); ?>><?php esc_html_e( 'Always dark', 'bible-brain-players' ); ?></option>
						</select></label>
					<label class="bbp-wide"><span><?php esc_html_e( 'Limit the country list (optional)', 'bible-brain-players' ); ?></span>
						<input type="text" class="regular-text" placeholder="IN, NP, BD, BT, LK, MV" name="<?php echo esc_attr( $opt ); ?>[allowed_countries]" value="<?php echo esc_attr( $s['allowed_countries'] ); ?>">
						<em class="description"><?php esc_html_e( 'Two-letter ISO country codes, separated by commas. Leave empty to show every country. Useful for a regional focus.', 'bible-brain-players' ); ?></em>
					</label>
					<label class="bbp-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[share]" value="1" <?php checked( $s['share'], 1 ); ?>> <?php esc_html_e( 'Show share buttons (WhatsApp, Facebook, Telegram, copy link)', 'bible-brain-players' ); ?></label>
					<label class="bbp-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[listen]" value="1" <?php checked( $s['listen'], 1 ); ?>> <?php esc_html_e( 'Reader: offer "Listen to this chapter" with verse highlighting when audio exists', 'bible-brain-players' ); ?></label>
					<label class="bbp-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[reflect]" value="1" <?php checked( $s['reflect'], 1 ); ?>> <?php esc_html_e( 'Show reflection prompts after listening or watching', 'bible-brain-players' ); ?></label>
					<label class="bbp-wide"><span><?php esc_html_e( 'Reflection prompts (one per line, up to 6)', 'bible-brain-players' ); ?></span>
						<textarea rows="4" class="large-text" name="<?php echo esc_attr( $opt ); ?>[reflect_prompts]"><?php echo esc_textarea( $s['reflect_prompts'] ); ?></textarea>
						<em class="description"><?php esc_html_e( 'Simple questions that move people from hearing to understanding to obeying and sharing. Adapt them to your context and language.', 'bible-brain-players' ); ?></em>
					</label>
					<label><span><?php esc_html_e( 'Cache catalog data (hours)', 'bible-brain-players' ); ?></span>
						<input type="number" min="1" max="168" name="<?php echo esc_attr( $opt ); ?>[cache_hours]" value="<?php echo esc_attr( $s['cache_hours'] ); ?>"></label>
					<label class="bbp-check"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[cdn_hls]" value="1" <?php checked( $s['cdn_hls'], 1 ); ?>> <?php esc_html_e( 'Load the hls.js video library from jsDelivr when not bundled (needed for video in Chrome, Firefox and Edge)', 'bible-brain-players' ); ?></label>
				</div>

				<?php submit_button( __( 'Save changes', 'bible-brain-players' ) ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bbp-inline">
				<input type="hidden" name="action" value="bbp_flush">
				<?php wp_nonce_field( 'bbplay_flush' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Clear cached catalog data', 'bible-brain-players' ); ?></button>
			</form>

			<h2><?php esc_html_e( 'Shortcodes', 'bible-brain-players' ); ?></h2>
			<div class="bbp-card">
				<p><code>[bb_reader]</code> &nbsp; <code>[bb_audio]</code> &nbsp; <code>[bb_video]</code></p>
				<p class="description"><?php esc_html_e( 'Optional attributes: country="IN" language="hin" bible="HINDOV" fileset="HINDOVN2DA" book="JHN" chapter="3" selector="no" share="no" reflect="no" listen="no" theme="dark" title="Listen to John".', 'bible-brain-players' ); ?></p>
				<p class="description"><?php esc_html_e( 'With selector="no" the player is locked to the version you set, which suits a campaign page in one language. Every player also creates shareable links that reopen the same chapter or clip.', 'bible-brain-players' ); ?></p>
			</div>

			<h2><?php esc_html_e( 'Diagnostics', 'bible-brain-players' ); ?></h2>
			<div class="bbp-card">
				<p class="description"><?php esc_html_e( 'Inspect a raw Bible Brain response using your saved key. Useful if a country or version looks incomplete.', 'bible-brain-players' ); ?></p>
				<label><span><?php esc_html_e( 'API path', 'bible-brain-players' ); ?></span><input type="text" id="bbp-raw-path" class="regular-text code" value="/bibles"></label>
				<label><span><?php esc_html_e( 'Query', 'bible-brain-players' ); ?></span><input type="text" id="bbp-raw-query" class="regular-text code" value="country=IN&amp;limit=2"></label>
				<p><button type="button" class="button" id="bbp-raw-go"><?php esc_html_e( 'Fetch response', 'bible-brain-players' ); ?></button></p>
				<pre id="bbp-raw-out" class="bbp-raw" hidden></pre>
			</div>

			<h2><?php esc_html_e( 'About and links', 'bible-brain-players' ); ?></h2>
			<div class="bbp-card">
				<p class="description"><?php esc_html_e( 'Bible Brain Players is a free plugin developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. Scripture content is provided by Bible Brain, a project of Faith Comes By Hearing. Please follow the license and copyright terms of every Bible version you display.', 'bible-brain-players' ); ?></p>
				<ul class="bbp-links">
					<?php
					$bbp_links = array(
						__( 'Bible Brain', 'bible-brain-players' )            => 'https://www.faithcomesbyhearing.com/audio-bible-resources/bible-brain',
						__( 'Developer documentation', 'bible-brain-players' ) => 'https://www.faithcomesbyhearing.com/bible-brain/developer-documentation',
						__( 'API reference', 'bible-brain-players' )          => 'https://www.faithcomesbyhearing.com/bible-brain/api-reference',
						__( 'Request a free API key', 'bible-brain-players' ) => 'https://4.dbt.io/api_key/request',
						__( 'Available content', 'bible-brain-players' )      => 'https://www.faithcomesbyhearing.com/bible-brain-available-content',
						__( 'License', 'bible-brain-players' )                => 'https://www.faithcomesbyhearing.com/bible-brain/license',
						__( 'Terms and conditions', 'bible-brain-players' )   => 'https://www.faithcomesbyhearing.com/bible-brain/terms-conditions',
						__( 'Copyright policy', 'bible-brain-players' )       => 'https://www.faithcomesbyhearing.com/copyright-policy',
					);
					foreach ( $bbp_links as $bbp_label => $bbp_url ) {
						printf( '<li><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></li>', esc_url( $bbp_url ), esc_html( $bbp_label ) );
					}
					?>
				</ul>
				<p class="description"><?php esc_html_e( 'Questions about your key or about content licences: support@digitalbibleplatform.com', 'bible-brain-players' ); ?></p>
			</div>
		</div>
		<?php
	}
}
