<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin, cached client for the Bible Brain (Digital Bible Platform v4) API.
 * The API key never leaves the server; browsers talk to our REST proxy.
 */
class BBPlay_API {

	const BASE = 'https://4.dbt.io/api';

	public static function key() {
		return trim( (string) BBPlay_Settings::get( 'api_key' ) );
	}

	private static function ttl() {
		return max( 1, (int) BBPlay_Settings::get( 'cache_hours' ) ) * HOUR_IN_SECONDS;
	}

	public static function flush() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_bbp\_%' OR option_name LIKE '\_transient\_timeout\_bbp\_%'" );
	}

	/**
	 * Raw request. Returns decoded array or WP_Error.
	 */
	public static function request( $path, $params = array(), $ttl = 0 ) {
		$key = self::key();
		if ( '' === $key ) {
			return new WP_Error( 'bbp_no_key', __( 'No Bible Brain API key has been set yet.', 'bible-brain-players' ), array( 'status' => 400 ) );
		}

		$params = array_filter(
			$params,
			function ( $v ) {
				return '' !== $v && null !== $v;
			}
		);
		ksort( $params );

		$cache_key = '';
		if ( $ttl > 0 ) {
			$cache_key = 'bbplay_r_' . md5( $path . '?' . http_build_query( $params ) );
			$hit       = get_transient( $cache_key );
			if ( false !== $hit ) {
				return $hit;
			}
		}

		$params['key'] = $key;
		$params['v']   = 4;
		$url           = self::BASE . $path . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );

		$resp = wp_remote_get(
			$url,
			array(
				'timeout'    => 25,
				'headers'    => array( 'Accept' => 'application/json' ),
				'user-agent' => 'BibleBrainPlayers/' . BBPLAY_VERSION . '; ' . home_url(),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'bbp_unreachable', __( 'Could not reach Bible Brain. Please try again shortly.', 'bible-brain-players' ), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code >= 400 || ! is_array( $body ) ) {
			$msg = __( 'Bible Brain returned an error.', 'bible-brain-players' );
			if ( is_array( $body ) && isset( $body['error']['message'] ) && is_string( $body['error']['message'] ) ) {
				$msg = $body['error']['message'];
			}
			$status = ( 404 === $code ) ? 404 : 502;
			return new WP_Error( 'bbp_api_error', $msg, array( 'status' => $status, 'upstream' => $code ) );
		}

		if ( $ttl > 0 ) {
			set_transient( $cache_key, $body, $ttl );
		}
		return $body;
	}

	/**
	 * Follow v4 pagination and merge all "data" rows.
	 */
	public static function paged( $path, $params = array(), $max_pages = 80 ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore
		}
		$all  = array();
		$page = 1;
		$last = 1;
		do {
			$r = self::request( $path, array_merge( $params, array( 'page' => $page, 'limit' => 50 ) ) );
			if ( is_wp_error( $r ) ) {
				if ( 1 === $page ) {
					return $r;
				}
				break;
			}
			if ( isset( $r['data'] ) && is_array( $r['data'] ) ) {
				$all = array_merge( $all, array_values( $r['data'] ) );
			}
			$pg = isset( $r['meta']['pagination'] ) ? $r['meta']['pagination'] : array();
			if ( isset( $pg['total_pages'] ) ) {
				$last = (int) $pg['total_pages'];
			} elseif ( isset( $pg['last_page'] ) ) {
				$last = (int) $pg['last_page'];
			} else {
				$last = 1;
			}
			$page++;
		} while ( $page <= $last && $page <= $max_pages );
		return $all;
	}

	/* ------------------------------------------------------------------ */
	/* Countries                                                           */
	/* ------------------------------------------------------------------ */

	public static function countries() {
		$ck  = 'bbplay_countries';
		$hit = get_transient( $ck );
		if ( false !== $hit ) {
			return $hit;
		}
		$rows = self::paged( '/countries' );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$out = array();
		foreach ( $rows as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$code = '';
			if ( isset( $c['codes']['iso_a2'] ) ) {
				$code = $c['codes']['iso_a2'];
			} elseif ( isset( $c['code'] ) ) {
				$code = $c['code'];
			} elseif ( isset( $c['id'] ) ) {
				$code = $c['id'];
			}
			$code = strtoupper( (string) $code );
			$name = isset( $c['name'] ) ? (string) $c['name'] : '';
			if ( preg_match( '/^[A-Z]{2}$/', $code ) && '' !== $name ) {
				$out[ $code ] = array( 'code' => $code, 'name' => $name );
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);
		$out = array_values( $out );
		set_transient( $ck, $out, WEEK_IN_SECONDS );
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Catalog: country -> languages -> versions -> filesets               */
	/* ------------------------------------------------------------------ */

	private static function size_label( $size ) {
		$map = array(
			'C'   => __( 'Complete Bible', 'bible-brain-players' ),
			'NT'  => __( 'New Testament', 'bible-brain-players' ),
			'OT'  => __( 'Old Testament', 'bible-brain-players' ),
			'NTP' => __( 'New Testament (partial)', 'bible-brain-players' ),
			'OTP' => __( 'Old Testament (partial)', 'bible-brain-players' ),
			'P'   => __( 'Selected portions', 'bible-brain-players' ),
			'S'   => __( 'Stories', 'bible-brain-players' ),
		);
		$size = strtoupper( (string) $size );
		return isset( $map[ $size ] ) ? $map[ $size ] : ( '' !== $size ? $size : __( 'Full set', 'bible-brain-players' ) );
	}

	private static function size_rank( $size ) {
		$rank = array( 'C' => 0, 'NT' => 1, 'OT' => 2, 'NTP' => 3, 'OTP' => 4, 'P' => 5, 'S' => 6 );
		$size = strtoupper( (string) $size );
		return isset( $rank[ $size ] ) ? $rank[ $size ] : 9;
	}

	private static function accepted_types( $media, $have ) {
		if ( 'text' === $media ) {
			$pref = array( 'text_plain' );
			$alt  = array( 'text_format' );
		} elseif ( 'audio' === $media ) {
			$pref = array( 'audio', 'audio_drama' );
			$alt  = array( 'audio_stream', 'audio_drama_stream' );
		} else {
			$pref = array( 'video_stream' );
			$alt  = array();
		}
		foreach ( $pref as $t ) {
			if ( in_array( $t, $have, true ) ) {
				return $pref;
			}
		}
		return $alt;
	}

	private static function collect_filesets( $raw ) {
		$flat = array();
		if ( ! is_array( $raw ) ) {
			return $flat;
		}
		if ( isset( $raw['id'] ) ) {
			$raw = array( $raw );
		}
		foreach ( $raw as $asset => $list ) {
			if ( isset( $list['id'] ) ) {
				$list = array( $list );
			}
			if ( ! is_array( $list ) ) {
				continue;
			}
			foreach ( $list as $fs ) {
				if ( ! is_array( $fs ) || empty( $fs['id'] ) || empty( $fs['type'] ) ) {
					continue;
				}
				$flat[] = array(
					'id'    => (string) $fs['id'],
					'type'  => (string) $fs['type'],
					'size'  => isset( $fs['size'] ) ? (string) $fs['size'] : '',
					'asset' => is_string( $asset ) ? $asset : '',
				);
			}
		}
		return $flat;
	}

	private static function filter_filesets( $raw, $media ) {
		$flat  = self::collect_filesets( $raw );
		$types = array_unique( wp_list_pluck( $flat, 'type' ) );
		$ok    = self::accepted_types( $media, $types );
		$out   = array();
		$seen  = array();
		foreach ( $flat as $fs ) {
			if ( ! in_array( $fs['type'], $ok, true ) || isset( $seen[ $fs['id'] ] ) ) {
				continue;
			}
			$seen[ $fs['id'] ] = true;
			$drama             = false !== strpos( $fs['type'], 'drama' );
			$label             = self::size_label( $fs['size'] );
			if ( 'video' === $media ) {
				$label = __( 'Video', 'bible-brain-players' ) . ' - ' . $label;
			}
			if ( $drama ) {
				$label .= ' - ' . __( 'Dramatized', 'bible-brain-players' );
			}
			$fs['label'] = $label;
			$fs['_rank'] = self::size_rank( $fs['size'] ) * 2 + ( $drama ? 0 : 1 );
			$out[]       = $fs;
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $a['_rank'] - $b['_rank'];
			}
		);
		foreach ( $out as &$o ) {
			unset( $o['_rank'] );
		}
		unset( $o );
		return $out;
	}

	public static function catalog( $country, $media ) {
		$ck  = 'bbplay_cat_' . md5( $country . '|' . $media );
		$hit = get_transient( $ck );
		if ( false !== $hit ) {
			return $hit;
		}

		$rows = self::paged( '/bibles', array( 'country' => $country, 'media' => $media ) );
		if ( is_wp_error( $rows ) ) {
			// Some deployments reject the media filter; retry unfiltered and filter locally.
			$rows = self::paged( '/bibles', array( 'country' => $country ) );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
		}

		$langs = array();
		foreach ( $rows as $b ) {
			if ( ! is_array( $b ) || empty( $b['abbr'] ) ) {
				continue;
			}
			$filesets = self::filter_filesets( isset( $b['filesets'] ) ? $b['filesets'] : array(), $media );
			if ( ! $filesets ) {
				continue;
			}
			$iso = '';
			if ( ! empty( $b['iso'] ) ) {
				$iso = strtolower( (string) $b['iso'] );
			} elseif ( ! empty( $b['language_id'] ) ) {
				$iso = 'id' . $b['language_id'];
			}
			if ( '' === $iso ) {
				continue;
			}
			$english = isset( $b['language'] ) ? (string) $b['language'] : $iso;
			$auto    = isset( $b['autonym'] ) ? (string) $b['autonym'] : '';
			$display = ( '' !== $auto && 0 !== strcasecmp( $auto, $english ) ) ? $auto . ' (' . $english . ')' : $english;

			$vname = isset( $b['vname'] ) ? trim( (string) $b['vname'] ) : '';
			$name  = isset( $b['name'] ) ? trim( (string) $b['name'] ) : '';
			$label = '' !== $vname ? $vname : $name;
			if ( '' !== $vname && '' !== $name && 0 !== strcasecmp( $vname, $name ) ) {
				$label = $vname . ' (' . $name . ')';
			}
			if ( '' === $label ) {
				$label = (string) $b['abbr'];
			}

			if ( ! isset( $langs[ $iso ] ) ) {
				$langs[ $iso ] = array( 'id' => $iso, 'name' => $display, 'versions' => array() );
			}
			$langs[ $iso ]['versions'][ $b['abbr'] ] = array(
				'abbr'     => (string) $b['abbr'],
				'name'     => $label,
				'filesets' => $filesets,
			);
		}

		foreach ( $langs as &$l ) {
			$l['versions'] = array_values( $l['versions'] );
			usort(
				$l['versions'],
				function ( $a, $b ) {
					return strcasecmp( $a['name'], $b['name'] );
				}
			);
		}
		unset( $l );
		$langs = array_values( $langs );
		usort(
			$langs,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		$out = array( 'languages' => $langs );
		set_transient( $ck, $out, self::ttl() );
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Books                                                               */
	/* ------------------------------------------------------------------ */

	private static function normalize_books( $rows ) {
		$books = array();
		if ( ! is_array( $rows ) ) {
			return $books;
		}
		foreach ( $rows as $b ) {
			if ( ! is_array( $b ) || empty( $b['book_id'] ) ) {
				continue;
			}
			$chapters = array();
			if ( isset( $b['chapters'] ) && is_array( $b['chapters'] ) ) {
				foreach ( $b['chapters'] as $c ) {
					$n = is_array( $c ) ? ( isset( $c['chapter'] ) ? (int) $c['chapter'] : 0 ) : (int) $c;
					if ( $n > 0 ) {
						$chapters[] = $n;
					}
				}
			} elseif ( isset( $b['chapters'] ) && is_numeric( $b['chapters'] ) ) {
				$chapters = range( 1, max( 1, (int) $b['chapters'] ) );
			}
			if ( ! $chapters ) {
				continue;
			}
			$name    = '';
			$name_ks = array( 'name', 'name_short' );
			foreach ( $name_ks as $k ) {
				if ( ! empty( $b[ $k ] ) ) {
					$name = (string) $b[ $k ];
					break;
				}
			}
			$books[] = array(
				'id'       => strtoupper( (string) $b['book_id'] ),
				'name'     => '' !== $name ? $name : BBPlay_Canon::name( $b['book_id'] ),
				'chapters' => array_values( array_unique( $chapters ) ),
			);
		}
		return $books;
	}

	public static function books( $fileset, $type, $asset, $bible, $size ) {
		$ttl   = WEEK_IN_SECONDS;
		$books = array();

		$r = self::request( '/bibles/filesets/' . rawurlencode( $fileset ) . '/books', array( 'type' => $type, 'asset_id' => $asset ), $ttl );
		if ( ! is_wp_error( $r ) && isset( $r['data'] ) ) {
			$books = self::normalize_books( $r['data'] );
		}
		if ( ! $books && '' !== $bible ) {
			$r = self::request( '/bibles/' . rawurlencode( $bible ) . '/book', array(), $ttl );
			if ( ! is_wp_error( $r ) && isset( $r['data'] ) ) {
				$books = self::normalize_books( $r['data'] );
			}
		}
		if ( ! $books ) {
			$books = BBPlay_Canon::books_for_size( $size );
		}
		return array( 'books' => $books );
	}

	/* ------------------------------------------------------------------ */
	/* Content                                                             */
	/* ------------------------------------------------------------------ */

	public static function text( $fileset, $book, $chapter, $type ) {
		$r = self::request(
			'/bibles/filesets/' . rawurlencode( $fileset ) . '/' . rawurlencode( $book ) . '/' . (int) $chapter,
			array( 'type' => $type ),
			self::ttl()
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$verses = array();
		$name   = '';
		$rows   = isset( $r['data'] ) && is_array( $r['data'] ) ? $r['data'] : array();
		foreach ( $rows as $v ) {
			if ( ! is_array( $v ) || ! isset( $v['verse_text'] ) ) {
				continue;
			}
			$n = '';
			if ( ! empty( $v['verse_start_alt'] ) ) {
				$n = (string) $v['verse_start_alt'];
			} elseif ( isset( $v['verse_start'] ) ) {
				$n = (string) $v['verse_start'];
			}
			$txt = (string) $v['verse_text'];
			if ( 'text_format' === $type ) {
				$txt = wp_strip_all_tags( $txt );
			}
			$verses[] = array( 'n' => $n, 'text' => trim( $txt ) );
			if ( '' === $name && ! empty( $v['book_name'] ) ) {
				$name = (string) $v['book_name'];
			}
		}
		return array(
			'book_name' => '' !== $name ? $name : BBPlay_Canon::name( $book ),
			'chapter'   => (int) $chapter,
			'verses'    => $verses,
		);
	}

	public static function audio( $fileset, $book, $chapter, $type, $asset ) {
		$r = self::request(
			'/bibles/filesets/' . rawurlencode( $fileset ) . '/' . rawurlencode( $book ) . '/' . (int) $chapter,
			array( 'type' => $type, 'asset_id' => $asset ),
			5 * MINUTE_IN_SECONDS
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$urls = array();
		$rows = isset( $r['data'] ) && is_array( $r['data'] ) ? $r['data'] : array();
		foreach ( $rows as $a ) {
			if ( is_array( $a ) && ! empty( $a['path'] ) ) {
				$urls[] = (string) $a['path'];
			}
		}
		if ( ! $urls ) {
			return new WP_Error( 'bbp_no_audio', __( 'No audio is available for this chapter.', 'bible-brain-players' ), array( 'status' => 404 ) );
		}
		return array( 'urls' => $urls );
	}

	public static function video( $fileset, $type, $asset ) {
		$r = self::request(
			'/bibles/filesets/' . rawurlencode( $fileset ),
			array( 'type' => $type, 'asset_id' => '' !== $asset ? $asset : 'dbp-vid' ),
			5 * MINUTE_IN_SECONDS
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$clips = array();
		$rows  = isset( $r['data'] ) && is_array( $r['data'] ) ? $r['data'] : array();
		foreach ( $rows as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$path = '';
			if ( ! empty( $c['path'] ) ) {
				$path = (string) $c['path'];
			} elseif ( ! empty( $c['url'] ) ) {
				$path = (string) $c['url'];
			}
			if ( '' === $path ) {
				continue;
			}
			$book    = isset( $c['book_id'] ) ? strtoupper( (string) $c['book_id'] ) : '';
			$title   = '';
			$title_k = array( 'title', 'name' );
			foreach ( $title_k as $k ) {
				if ( ! empty( $c[ $k ] ) && is_string( $c[ $k ] ) ) {
					$title = $c[ $k ];
					break;
				}
			}
			$thumb = '';
			if ( ! empty( $c['thumbnail'] ) && is_string( $c['thumbnail'] ) ) {
				$thumb = $c['thumbnail'];
			} elseif ( ! empty( $c['thumb'] ) && is_string( $c['thumb'] ) ) {
				$thumb = $c['thumb'];
			}
			$clips[] = array(
				'url'       => $path,
				'thumb'     => $thumb,
				'title'     => $title,
				'book'      => $book,
				'book_name' => '' !== $book ? BBPlay_Canon::name( $book ) : '',
				'chapter'   => isset( $c['chapter_start'] ) ? (int) $c['chapter_start'] : ( isset( $c['chapter'] ) ? (int) $c['chapter'] : 0 ),
				'vs'        => isset( $c['verse_start'] ) ? (int) $c['verse_start'] : 0,
				've'        => isset( $c['verse_end'] ) ? (int) $c['verse_end'] : 0,
				'duration'  => isset( $c['duration'] ) ? (int) $c['duration'] : 0,
			);
		}
		if ( ! $clips ) {
			return new WP_Error( 'bbp_no_video', __( 'No video is available for this selection.', 'bible-brain-players' ), array( 'status' => 404 ) );
		}
		return array( 'clips' => $clips );
	}

	public static function timestamps( $fileset, $book, $chapter ) {
		$r = self::request(
			'/timestamps/' . rawurlencode( $fileset ) . '/' . rawurlencode( $book ) . '/' . (int) $chapter,
			array(),
			DAY_IN_SECONDS
		);
		if ( is_wp_error( $r ) ) {
			return array( 'times' => array() );
		}
		$times = array();
		$rows  = isset( $r['data'] ) && is_array( $r['data'] ) ? $r['data'] : array();
		foreach ( $rows as $t ) {
			if ( ! is_array( $t ) || ! isset( $t['timestamp'] ) ) {
				continue;
			}
			$v = '';
			if ( ! empty( $t['verse_start_alt'] ) ) {
				$v = (string) $t['verse_start_alt'];
			} elseif ( isset( $t['verse_start'] ) ) {
				$v = (string) $t['verse_start'];
			}
			if ( '' !== $v ) {
				$times[] = array( 'v' => $v, 't' => (float) $t['timestamp'] );
			}
		}
		return array( 'times' => $times );
	}

	public static function copyright( $bible ) {
		$r = self::request( '/bibles/' . rawurlencode( $bible ) . '/copyright', array(), WEEK_IN_SECONDS );
		$txt = '';
		if ( ! is_wp_error( $r ) ) {
			$d = isset( $r['data'] ) ? $r['data'] : $r;
			if ( is_array( $d ) && isset( $d[0] ) ) {
				$d = $d[0];
			}
			if ( is_array( $d ) ) {
				if ( isset( $d['copyright']['copyright'] ) && is_string( $d['copyright']['copyright'] ) ) {
					$txt = $d['copyright']['copyright'];
				} elseif ( isset( $d['copyright'] ) && is_string( $d['copyright'] ) ) {
					$txt = $d['copyright'];
				}
			}
		}
		return array( 'copyright' => wp_strip_all_tags( $txt ) );
	}
}
