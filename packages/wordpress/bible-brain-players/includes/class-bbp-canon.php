<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static 66-book canon (Bible Brain / USFM book ids). Used only as a fallback
 * when the API does not return a book list, and to label video clips.
 */
class BBPlay_Canon {

	public static function all() {
		return array(
			'GEN' => array( 'Genesis', 50 ),
			'EXO' => array( 'Exodus', 40 ),
			'LEV' => array( 'Leviticus', 27 ),
			'NUM' => array( 'Numbers', 36 ),
			'DEU' => array( 'Deuteronomy', 34 ),
			'JOS' => array( 'Joshua', 24 ),
			'JDG' => array( 'Judges', 21 ),
			'RUT' => array( 'Ruth', 4 ),
			'1SA' => array( '1 Samuel', 31 ),
			'2SA' => array( '2 Samuel', 24 ),
			'1KI' => array( '1 Kings', 22 ),
			'2KI' => array( '2 Kings', 25 ),
			'1CH' => array( '1 Chronicles', 29 ),
			'2CH' => array( '2 Chronicles', 36 ),
			'EZR' => array( 'Ezra', 10 ),
			'NEH' => array( 'Nehemiah', 13 ),
			'EST' => array( 'Esther', 10 ),
			'JOB' => array( 'Job', 42 ),
			'PSA' => array( 'Psalms', 150 ),
			'PRO' => array( 'Proverbs', 31 ),
			'ECC' => array( 'Ecclesiastes', 12 ),
			'SNG' => array( 'Song of Solomon', 8 ),
			'ISA' => array( 'Isaiah', 66 ),
			'JER' => array( 'Jeremiah', 52 ),
			'LAM' => array( 'Lamentations', 5 ),
			'EZK' => array( 'Ezekiel', 48 ),
			'DAN' => array( 'Daniel', 12 ),
			'HOS' => array( 'Hosea', 14 ),
			'JOL' => array( 'Joel', 3 ),
			'AMO' => array( 'Amos', 9 ),
			'OBA' => array( 'Obadiah', 1 ),
			'JON' => array( 'Jonah', 4 ),
			'MIC' => array( 'Micah', 7 ),
			'NAM' => array( 'Nahum', 3 ),
			'HAB' => array( 'Habakkuk', 3 ),
			'ZEP' => array( 'Zephaniah', 3 ),
			'HAG' => array( 'Haggai', 2 ),
			'ZEC' => array( 'Zechariah', 14 ),
			'MAL' => array( 'Malachi', 4 ),
			'MAT' => array( 'Matthew', 28 ),
			'MRK' => array( 'Mark', 16 ),
			'LUK' => array( 'Luke', 24 ),
			'JHN' => array( 'John', 21 ),
			'ACT' => array( 'Acts', 28 ),
			'ROM' => array( 'Romans', 16 ),
			'1CO' => array( '1 Corinthians', 16 ),
			'2CO' => array( '2 Corinthians', 13 ),
			'GAL' => array( 'Galatians', 6 ),
			'EPH' => array( 'Ephesians', 6 ),
			'PHP' => array( 'Philippians', 4 ),
			'COL' => array( 'Colossians', 4 ),
			'1TH' => array( '1 Thessalonians', 5 ),
			'2TH' => array( '2 Thessalonians', 3 ),
			'1TI' => array( '1 Timothy', 6 ),
			'2TI' => array( '2 Timothy', 4 ),
			'TIT' => array( 'Titus', 3 ),
			'PHM' => array( 'Philemon', 1 ),
			'HEB' => array( 'Hebrews', 13 ),
			'JAS' => array( 'James', 5 ),
			'1PE' => array( '1 Peter', 5 ),
			'2PE' => array( '2 Peter', 3 ),
			'1JN' => array( '1 John', 5 ),
			'2JN' => array( '2 John', 1 ),
			'3JN' => array( '3 John', 1 ),
			'JUD' => array( 'Jude', 1 ),
			'REV' => array( 'Revelation', 22 ),
		);
	}

	public static function name( $id ) {
		$all = self::all();
		$id  = strtoupper( (string) $id );
		return isset( $all[ $id ] ) ? $all[ $id ][0] : $id;
	}

	public static function is_nt( $id ) {
		$ids = array_keys( self::all() );
		$pos = array_search( strtoupper( (string) $id ), $ids, true );
		$nt  = array_search( 'MAT', $ids, true );
		return false !== $pos && $pos >= $nt;
	}

	/**
	 * Fallback book list for a fileset "size" (C, NT, OT, NTP, OTP, P, S).
	 */
	public static function books_for_size( $size ) {
		$size  = strtoupper( (string) $size );
		$books = array();
		foreach ( self::all() as $id => $b ) {
			$nt = self::is_nt( $id );
			if ( 'NT' === $size || 'NTP' === $size ) {
				if ( ! $nt ) {
					continue;
				}
			} elseif ( 'OT' === $size || 'OTP' === $size ) {
				if ( $nt ) {
					continue;
				}
			}
			$books[] = array(
				'id'       => $id,
				'name'     => $b[0],
				'chapters' => range( 1, $b[1] ),
			);
		}
		return $books;
	}
}
