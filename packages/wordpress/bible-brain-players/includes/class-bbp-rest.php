<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public, read-only REST proxy (namespace bible-brain-players/v1). Keeps the API key server-side,
 * caches catalog data, and throttles abusive clients.
 */
class BBPlay_Rest {

	const NS = 'bible-brain-players/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		$public = array(
			'countries'  => 'countries',
			'catalog'    => 'catalog',
			'books'      => 'books',
			'text'       => 'text',
			'audio'      => 'audio',
			'video'      => 'video',
			'timestamps' => 'timestamps',
			'copyright'  => 'copyright',
		);
		foreach ( $public as $route => $method ) {
			register_rest_route(
				self::NS,
				'/' . $route,
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'h_' . $method ),
					'permission_callback' => '__return_true',
				)
			);
		}
		register_rest_route(
			self::NS,
			'/admin/test',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'h_admin_test' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
		register_rest_route(
			self::NS,
			'/admin/raw',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'h_admin_raw' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/* ---------------- helpers ---------------- */

	private static function throttle() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$k   = 'bbplay_rl_' . md5( $ip );
		$d   = get_transient( $k );
		$now = time();
		if ( ! is_array( $d ) ) {
			$d = array( 'n' => 0, 't' => $now );
		}
		$d['n']++;
		if ( $d['n'] > 300 ) {
			return new WP_Error( 'bbp_rate', __( 'Too many requests. Please wait a few minutes.', 'bible-brain-players' ), array( 'status' => 429 ) );
		}
		set_transient( $k, $d, max( 1, 300 - ( $now - (int) $d['t'] ) ) );
		return true;
	}

	private static function id( WP_REST_Request $r, $key, $max = 40 ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $r->get_param( $key ) ), 0, $max );
	}

	private static function guard( $cb ) {
		$t = self::throttle();
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$res = call_user_func( $cb );
		return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
	}

	private static function bad( $msg ) {
		return new WP_Error( 'bbp_bad_request', $msg, array( 'status' => 400 ) );
	}

	/* ---------------- handlers ---------------- */

	public static function h_countries( WP_REST_Request $r ) {
		return self::guard(
			function () {
				$list = BBPlay_API::countries();
				if ( is_wp_error( $list ) ) {
					return $list;
				}
				$allowed = (string) BBPlay_Settings::get( 'allowed_countries' );
				if ( '' !== $allowed ) {
					$allowed = explode( ',', $allowed );
					$list    = array_values(
						array_filter(
							$list,
							function ( $c ) use ( $allowed ) {
								return in_array( $c['code'], $allowed, true );
							}
						)
					);
				}
				return array( 'countries' => $list );
			}
		);
	}

	public static function h_catalog( WP_REST_Request $r ) {
		return self::guard(
			function () use ( $r ) {
				$country = strtoupper( self::id( $r, 'country', 2 ) );
				$media   = (string) $r->get_param( 'media' );
				if ( ! preg_match( '/^[A-Z]{2}$/', $country ) || ! in_array( $media, array( 'text', 'audio', 'video' ), true ) ) {
					return self::bad( __( 'Invalid country or media type.', 'bible-brain-players' ) );
				}
				return BBPlay_API::catalog( $country, $media );
			}
		);
	}

	public static function h_books( WP_REST_Request $r ) {
		return self::guard(
			function () use ( $r ) {
				$fileset = self::id( $r, 'fileset' );
				if ( '' === $fileset ) {
					return self::bad( __( 'Missing fileset.', 'bible-brain-players' ) );
				}
				return BBPlay_API::books( $fileset, self::id( $r, 'type' ), self::id( $r, 'asset' ), self::id( $r, 'bible' ), self::id( $r, 'size', 4 ) );
			}
		);
	}

	private static function chapter_args( WP_REST_Request $r ) {
		$fileset = self::id( $r, 'fileset' );
		$book    = strtoupper( self::id( $r, 'book', 4 ) );
		$chapter = (int) $r->get_param( 'chapter' );
		if ( '' === $fileset || '' === $book || $chapter < 1 || $chapter > 200 ) {
			return false;
		}
		return array( $fileset, $book, $chapter );
	}

	public static function h_text( WP_REST_Request $r ) {
		return self::guard(
			function () use ( $r ) {
				$a    = self::chapter_args( $r );
				$type = self::id( $r, 'type' );
				if ( ! $a || ! in_array( $type, array( 'text_plain', 'text_format' ), true ) ) {
					return self::bad( __( 'Invalid request.', 'bible-brain-players' ) );
				}
				return BBPlay_API::text( $a[0], $a[1], $a[2], $type );
			}
		);
	}

	public static function h_audio( WP_REST_Request $r ) {
		return self::guard(
			function () use ( $r ) {
				$a = self::chapter_args( $r );
				if ( ! $a ) {
					return self::bad( __( 'Invalid request.', 'bible-brain-players' ) );
				}
				return BBPlay_API::audio( $a[0], $a[1], $a[2], self::id( $r, 'type' ), self::id( $r, 'asset' ) );
			}
		);
	}

	public static function h_timestamps( WP_REST_Request $r ) {
		return self::guard(
			function () use ( $r ) {
				$a = self::chapter_args( $r );
				if ( ! $a ) {
					return self::bad( __( 'Invalid request.', 'bible-brain-players' ) );
				}
				return BBPlay_API::timestamps( $a[0], $a[1], $a[2] );
			}
		);
	}

	public static function h_video( WP_REST_Request $r ) {
		return self::guard(
			function () use ( $r ) {
				$fileset = self::id( $r, 'fileset' );
				if ( '' === $fileset ) {
					return self::bad( __( 'Missing fileset.', 'bible-brain-players' ) );
				}
				return BBPlay_API::video( $fileset, 'video_stream', self::id( $r, 'asset' ) );
			}
		);
	}

	public static function h_copyright( WP_REST_Request $r ) {
		return self::guard(
			function () use ( $r ) {
				$bible = self::id( $r, 'bible' );
				if ( '' === $bible ) {
					return self::bad( __( 'Missing Bible id.', 'bible-brain-players' ) );
				}
				return BBPlay_API::copyright( $bible );
			}
		);
	}

	/* ---------------- admin only ---------------- */

	public static function h_admin_test( WP_REST_Request $r ) {
		if ( '' === BBPlay_API::key() ) {
			return self::bad( __( 'Save your API key first, then test the connection.', 'bible-brain-players' ) );
		}
		$res = BBPlay_API::request( '/countries', array( 'limit' => 5 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$n = isset( $res['data'] ) && is_array( $res['data'] ) ? count( $res['data'] ) : 0;
		return rest_ensure_response(
			array(
				'ok'      => $n > 0,
				'message' => $n > 0 ? __( 'Connected. Your API key works.', 'bible-brain-players' ) : __( 'The key was accepted but no data came back. Check the diagnostics below.', 'bible-brain-players' ),
			)
		);
	}

	public static function h_admin_raw( WP_REST_Request $r ) {
		$path = (string) $r->get_param( 'path' );
		if ( '' === $path || '/' !== $path[0] || false !== strpos( $path, '..' ) || ! preg_match( '#^/[A-Za-z0-9_\-/\.]*$#', $path ) ) {
			return self::bad( __( 'Enter an API path such as /bibles or /countries.', 'bible-brain-players' ) );
		}
		$params = array();
		wp_parse_str( (string) $r->get_param( 'query' ), $params );
		$params = array_map( 'sanitize_text_field', array_filter( $params, 'is_scalar' ) );
		$res    = BBPlay_API::request( $path, $params );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$json = wp_json_encode( $res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( strlen( $json ) > 40000 ) {
			$json = substr( $json, 0, 40000 ) . "\n... (truncated)";
		}
		return rest_ensure_response( array( 'json' => $json ) );
	}
}
