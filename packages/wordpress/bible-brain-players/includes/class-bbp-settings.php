<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBPlay_Settings {

	const OPT = 'bbplay_settings';

	public static function blank_default() {
		return array(
			'country'  => '',
			'language' => '',
			'bible'    => '',
			'fileset'  => '',
		);
	}

	public static function defaults() {
		return array(
			'api_key'           => '',
			'cache_hours'       => 12,
			'accent'            => '#7E0E2A',
			'theme'             => 'auto',
			'allowed_countries' => '',
			'share'             => 1,
			'reflect'           => 1,
			'listen'            => 1,
			'cdn_hls'           => 1,
			'reflect_prompts'   => "What did you hear? Tell it back in your own words.\nWhat does this passage show me about God and about people?\nWhat will I do about it, and who will I share it with?",
			'defaults'          => array(
				'reader' => self::blank_default(),
				'audio'  => self::blank_default(),
				'video'  => self::blank_default(),
			),
		);
	}

	public static function get( $key = null ) {
		$saved = get_option( self::OPT, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$opts = array_replace_recursive( self::defaults(), $saved );
		if ( null === $key ) {
			return $opts;
		}
		return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
	}

	private static function clean_id( $v, $max = 24 ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $v ), 0, $max );
	}

	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$old = self::get();
		$out = $old;

		$out['api_key'] = isset( $in['api_key'] ) ? self::clean_id( trim( $in['api_key'] ), 128 ) : $old['api_key'];

		$hours              = isset( $in['cache_hours'] ) ? (int) $in['cache_hours'] : (int) $old['cache_hours'];
		$out['cache_hours'] = max( 1, min( 168, $hours ) );

		$accent        = isset( $in['accent'] ) ? sanitize_hex_color( $in['accent'] ) : '';
		$out['accent'] = $accent ? $accent : '#7E0E2A';

		$theme        = isset( $in['theme'] ) ? (string) $in['theme'] : 'auto';
		$out['theme'] = in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';

		$codes = array();
		if ( ! empty( $in['allowed_countries'] ) ) {
			foreach ( preg_split( '/[\s,;]+/', strtoupper( (string) $in['allowed_countries'] ) ) as $c ) {
				if ( preg_match( '/^[A-Z]{2}$/', $c ) ) {
					$codes[] = $c;
				}
			}
		}
		$out['allowed_countries'] = implode( ',', array_unique( $codes ) );

		foreach ( array( 'share', 'reflect', 'listen', 'cdn_hls' ) as $flag ) {
			$out[ $flag ] = empty( $in[ $flag ] ) ? 0 : 1;
		}

		$prompts = isset( $in['reflect_prompts'] ) ? sanitize_textarea_field( $in['reflect_prompts'] ) : $old['reflect_prompts'];
		$lines   = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $prompts ) ) );
		$out['reflect_prompts'] = implode( "\n", array_slice( $lines, 0, 6 ) );

		foreach ( array( 'reader', 'audio', 'video' ) as $type ) {
			$d  = isset( $in['defaults'][ $type ] ) && is_array( $in['defaults'][ $type ] ) ? $in['defaults'][ $type ] : array();
			$cc = isset( $d['country'] ) ? strtoupper( self::clean_id( $d['country'], 2 ) ) : '';
			$out['defaults'][ $type ] = array(
				'country'  => preg_match( '/^[A-Z]{2}$/', $cc ) ? $cc : '',
				'language' => isset( $d['language'] ) ? self::clean_id( $d['language'], 16 ) : '',
				'bible'    => isset( $d['bible'] ) ? self::clean_id( $d['bible'], 24 ) : '',
				'fileset'  => isset( $d['fileset'] ) ? self::clean_id( $d['fileset'], 40 ) : '',
			);
		}

		if ( $out['api_key'] !== $old['api_key'] ) {
			BBPlay_API::flush();
		}
		return $out;
	}
}
