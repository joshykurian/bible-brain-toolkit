<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBPlay_Shortcodes {

	private static $counter = array();

	public static function init() {
		add_shortcode( 'bb_reader', array( __CLASS__, 'reader' ) );
		add_shortcode( 'bb_audio', array( __CLASS__, 'audio' ) );
		add_shortcode( 'bb_video', array( __CLASS__, 'video' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'bbplay-frontend', BBPLAY_URL . 'assets/css/frontend.css', array(), BBPLAY_VERSION );

		$deps = array();
		if ( BBPlay_Settings::get( 'cdn_hls' ) || file_exists( BBPLAY_DIR . 'assets/vendor/hls.min.js' ) ) {
			$local = file_exists( BBPLAY_DIR . 'assets/vendor/hls.min.js' );
			$src   = $local ? BBPLAY_URL . 'assets/vendor/hls.min.js' : 'https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js';
			wp_register_script( 'bbplay-hls', $src, array(), '1.5.17', true );
		}
		wp_register_script( 'bbplay-frontend', BBPLAY_URL . 'assets/js/frontend.js', $deps, BBPLAY_VERSION, true );
		wp_localize_script( 'bbplay-frontend', 'BBP_I18N', self::strings() );
	}

	private static function strings() {
		return array(
			'country'      => __( 'Country', 'bible-brain-players' ),
			'language'     => __( 'Language', 'bible-brain-players' ),
			'version'      => __( 'Version', 'bible-brain-players' ),
			'recording'    => __( 'Edition', 'bible-brain-players' ),
			'book'         => __( 'Book', 'bible-brain-players' ),
			'chapter'      => __( 'Chapter', 'bible-brain-players' ),
			'loading'      => __( 'Loading...', 'bible-brain-players' ),
			'error'        => __( 'Something went wrong. Please try again.', 'bible-brain-players' ),
			'noversions'   => __( 'No versions were found for this selection.', 'bible-brain-players' ),
			'noconfig'     => __( 'No default version has been set for this player.', 'bible-brain-players' ),
			'prev'         => __( 'Previous', 'bible-brain-players' ),
			'next'         => __( 'Next', 'bible-brain-players' ),
			'smaller'      => __( 'Smaller text', 'bible-brain-players' ),
			'larger'       => __( 'Larger text', 'bible-brain-players' ),
			'speed'        => __( 'Speed', 'bible-brain-players' ),
			'autoplay'     => __( 'Play the next chapter automatically', 'bible-brain-players' ),
			'autoclip'     => __( 'Play the next clip automatically', 'bible-brain-players' ),
			'listen'       => __( 'Listen to this chapter', 'bible-brain-players' ),
			'clips'        => __( 'Clips', 'bible-brain-players' ),
			'nowplaying'   => __( 'Now playing', 'bible-brain-players' ),
			'reflect'      => __( 'Reflect and respond', 'bible-brain-players' ),
			'share'        => __( 'Share this', 'bible-brain-players' ),
			'copylink'     => __( 'Copy link', 'bible-brain-players' ),
			'copied'       => __( 'Link copied', 'bible-brain-players' ),
			'whatsapp'     => __( 'WhatsApp', 'bible-brain-players' ),
			'facebook'     => __( 'Facebook', 'bible-brain-players' ),
			'telegram'     => __( 'Telegram', 'bible-brain-players' ),
			'poweredby'    => __( 'Scripture content provided by Bible Brain (Faith Comes By Hearing).', 'bible-brain-players' ),
			'chapterlabel' => __( 'Chapter', 'bible-brain-players' ),
			'play'         => __( 'Play', 'bible-brain-players' ),
			'pause'        => __( 'Pause', 'bible-brain-players' ),
			'back'         => __( 'Back 15 seconds', 'bible-brain-players' ),
			'forward'      => __( 'Forward 15 seconds', 'bible-brain-players' ),
			'seek'         => __( 'Seek', 'bible-brain-players' ),
		);
	}

	public static function reader( $atts ) {
		return self::render( 'reader', $atts );
	}

	public static function audio( $atts ) {
		return self::render( 'audio', $atts );
	}

	public static function video( $atts ) {
		return self::render( 'video', $atts );
	}

	private static function yes( $v, $fallback ) {
		if ( '' === (string) $v ) {
			return (bool) $fallback;
		}
		return in_array( strtolower( (string) $v ), array( '1', 'yes', 'true', 'on' ), true );
	}

	private static function render( $type, $atts ) {
		$s    = BBPlay_Settings::get();
		$atts = shortcode_atts(
			array(
				'country'  => '',
				'language' => '',
				'bible'    => '',
				'fileset'  => '',
				'book'     => '',
				'chapter'  => '',
				'selector' => 'yes',
				'share'    => '',
				'reflect'  => '',
				'listen'   => '',
				'theme'    => '',
				'title'    => '',
			),
			$atts,
			'bb_' . $type
		);

		if ( '' === BBPlay_API::key() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="bbp-notice">' . wp_kses_post(
					sprintf(
						/* translators: %s: settings URL */
						__( 'Bible Brain Players needs an API key. <a href="%s">Add your free key in Settings</a>. Only administrators see this message.', 'bible-brain-players' ),
						esc_url( admin_url( 'options-general.php?page=bible-brain-players' ) )
					)
				) . '</div>';
			}
			return '';
		}

		wp_enqueue_style( 'bbplay-frontend' );
		wp_enqueue_script( 'bbplay-frontend' );
		if ( 'video' === $type || 'audio' === $type ) {
			if ( wp_script_is( 'bbplay-hls', 'registered' ) ) {
				wp_enqueue_script( 'bbplay-hls' );
			}
		}

		$d = $s['defaults'][ $type ];
		foreach ( array( 'country', 'language', 'bible', 'fileset' ) as $k ) {
			if ( '' === trim( (string) $atts[ $k ] ) ) {
				$atts[ $k ] = $d[ $k ];
			}
		}

		if ( ! isset( self::$counter[ $type ] ) ) {
			self::$counter[ $type ] = 0;
		}
		self::$counter[ $type ]++;

		$theme = in_array( $atts['theme'], array( 'light', 'dark', 'auto' ), true ) ? $atts['theme'] : $s['theme'];
		$clean = function ( $v, $max ) {
			return substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $v ), 0, $max );
		};

		$prompts = array_values( array_filter( array_map( 'trim', explode( "\n", (string) $s['reflect_prompts'] ) ) ) );

		$cfg = array(
			'type'     => $type,
			'uid'      => self::$counter[ $type ],
			'rest'     => esc_url_raw( rest_url( 'bible-brain-players/v1/' ) ),
			'country'  => strtoupper( $clean( $atts['country'], 2 ) ),
			'language' => strtolower( $clean( $atts['language'], 16 ) ),
			'bible'    => $clean( $atts['bible'], 24 ),
			'fileset'  => $clean( $atts['fileset'], 40 ),
			'book'     => strtoupper( $clean( $atts['book'], 4 ) ),
			'chapter'  => max( 0, (int) $atts['chapter'] ),
			'selector' => self::yes( $atts['selector'], true ),
			'share'    => self::yes( $atts['share'], $s['share'] ),
			'reflect'  => self::yes( $atts['reflect'], $s['reflect'] ),
			'listen'   => 'reader' === $type ? self::yes( $atts['listen'], $s['listen'] ) : false,
			'prompts'  => $prompts,
			'title'    => sanitize_text_field( $atts['title'] ),
		);

		$accent = sanitize_hex_color( $s['accent'] );
		$style  = $accent ? '--bbp-accent:' . $accent . ';' : '';

		return sprintf(
			'<div class="bbp bbp-%1$s" data-theme="%2$s" style="%3$s" data-bbp="%4$s"><div class="bbp-loading" role="status">%5$s</div><noscript>%6$s</noscript></div>',
			esc_attr( $type ),
			esc_attr( $theme ),
			esc_attr( $style ),
			esc_attr( wp_json_encode( $cfg ) ),
			esc_html__( 'Loading...', 'bible-brain-players' ),
			esc_html__( 'Please enable JavaScript to use this Scripture player.', 'bible-brain-players' )
		);
	}
}
