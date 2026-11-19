<?php
/**
 * Bible Brain Core - framework-agnostic client and proxy logic for the Bible Brain (DBP v4) API.
 * Used by the standalone proxy, the Drupal module and the Joomla plugin. PHP 7.4 or newer.
 *
 * Bible Brain is a project of Faith Comes By Hearing. Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. You need your own free API key:
 * https://4.dbt.io/api_key/request
 *
 * @license GPL-2.0-or-later
 */

namespace BibleBrain;

class ApiException extends \Exception
{
    /** @var int */
    public $status;

    public function __construct($message, $status = 502)
    {
        parent::__construct($message);
        $this->status = (int) $status;
    }
}

/** Tiny JSON file cache. get() returns null on a miss. */
class FileCache
{
    private $dir;

    public function __construct($dir)
    {
        $this->dir = rtrim($dir, '/\\');
    }

    private function path($key)
    {
        return $this->dir . '/bbc_' . md5($key) . '.json';
    }

    public function get($key)
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $d = json_decode($raw, true);
        if (!is_array($d) || !isset($d['e']) || $d['e'] < time() || !array_key_exists('v', $d)) {
            @unlink($file);
            return null;
        }
        return $d['v'];
    }

    public function set($key, $value, $ttl)
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        if (!is_dir($this->dir) || !is_writable($this->dir)) {
            return;
        }
        $json = json_encode(array('e' => time() + (int) $ttl, 'v' => $value));
        if ($json !== false) {
            @file_put_contents($this->path($key), $json, LOCK_EX);
        }
    }

    public function flush()
    {
        $files = glob($this->dir . '/bbc_*.json');
        if (is_array($files)) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }
}

/** Fixed-window rate limiter on top of any cache with get()/set(). */
class RateLimiter
{
    public static function allow($cache, $id, $limit = 300, $window = 300)
    {
        if (!$cache || $limit <= 0) {
            return true;
        }
        $key = 'rl_' . $id;
        $now = time();
        $d = $cache->get($key);
        if (!is_array($d) || !isset($d['n'], $d['t'])) {
            $d = array('n' => 0, 't' => $now);
        }
        $d['n']++;
        if ($d['n'] > $limit) {
            return false;
        }
        $cache->set($key, $d, max(1, $window - ($now - (int) $d['t'])));
        return true;
    }
}

class Canon
{
    const DATA = 'GEN:Genesis:50;EXO:Exodus:40;LEV:Leviticus:27;NUM:Numbers:36;DEU:Deuteronomy:34;JOS:Joshua:24;JDG:Judges:21;RUT:Ruth:4;1SA:1 Samuel:31;2SA:2 Samuel:24;1KI:1 Kings:22;2KI:2 Kings:25;1CH:1 Chronicles:29;2CH:2 Chronicles:36;EZR:Ezra:10;NEH:Nehemiah:13;EST:Esther:10;JOB:Job:42;PSA:Psalms:150;PRO:Proverbs:31;ECC:Ecclesiastes:12;SNG:Song of Solomon:8;ISA:Isaiah:66;JER:Jeremiah:52;LAM:Lamentations:5;EZK:Ezekiel:48;DAN:Daniel:12;HOS:Hosea:14;JOL:Joel:3;AMO:Amos:9;OBA:Obadiah:1;JON:Jonah:4;MIC:Micah:7;NAM:Nahum:3;HAB:Habakkuk:3;ZEP:Zephaniah:3;HAG:Haggai:2;ZEC:Zechariah:14;MAL:Malachi:4;MAT:Matthew:28;MRK:Mark:16;LUK:Luke:24;JHN:John:21;ACT:Acts:28;ROM:Romans:16;1CO:1 Corinthians:16;2CO:2 Corinthians:13;GAL:Galatians:6;EPH:Ephesians:6;PHP:Philippians:4;COL:Colossians:4;1TH:1 Thessalonians:5;2TH:2 Thessalonians:3;1TI:1 Timothy:6;2TI:2 Timothy:4;TIT:Titus:3;PHM:Philemon:1;HEB:Hebrews:13;JAS:James:5;1PE:1 Peter:5;2PE:2 Peter:3;1JN:1 John:5;2JN:2 John:1;3JN:3 John:1;JUD:Jude:1;REV:Revelation:22';

    public static function all()
    {
        $out = array();
        foreach (explode(';', self::DATA) as $row) {
            $p = explode(':', $row);
            $out[$p[0]] = array($p[1], (int) $p[2]);
        }
        return $out;
    }

    public static function name($id)
    {
        $all = self::all();
        $id = strtoupper((string) $id);
        return isset($all[$id]) ? $all[$id][0] : $id;
    }

    public static function isNt($id)
    {
        $ids = array_keys(self::all());
        $pos = array_search(strtoupper((string) $id), $ids, true);
        $nt = array_search('MAT', $ids, true);
        return $pos !== false && $pos >= $nt;
    }

    public static function booksForSize($size)
    {
        $size = strtoupper((string) $size);
        $books = array();
        foreach (self::all() as $id => $b) {
            $nt = self::isNt($id);
            if (($size === 'NT' || $size === 'NTP') && !$nt) {
                continue;
            }
            if (($size === 'OT' || $size === 'OTP') && $nt) {
                continue;
            }
            $books[] = array('id' => $id, 'name' => $b[0], 'chapters' => range(1, $b[1]));
        }
        return $books;
    }
}

class Core
{
    const BASE = 'https://4.dbt.io/api';

    private $key;
    private $http;
    private $cache;
    private $ttl;
    private $allowed;
    private $base;

    /**
     * @param string        $key   Bible Brain API key
     * @param callable|null $http  function ($url) returning array($statusCode, $body); null = built-in
     * @param object|null   $cache object with get($key) (null on miss) and set($key, $value, $ttlSeconds)
     * @param array         $opts  cache_hours, allowed_countries (comma list), base
     */
    public function __construct($key, $http = null, $cache = null, $opts = array())
    {
        $this->key = trim((string) $key);
        $this->http = $http ? $http : array(__CLASS__, 'defaultHttp');
        $this->cache = $cache;
        $hours = isset($opts['cache_hours']) ? (int) $opts['cache_hours'] : 12;
        $this->ttl = max(1, min(168, $hours)) * 3600;
        $this->allowed = array();
        if (!empty($opts['allowed_countries'])) {
            foreach (preg_split('/[\s,;]+/', strtoupper((string) $opts['allowed_countries'])) as $c) {
                if (preg_match('/^[A-Z]{2}$/', $c)) {
                    $this->allowed[] = $c;
                }
            }
        }
        $this->base = !empty($opts['base']) ? rtrim($opts['base'], '/') : self::BASE;
    }

    public static function defaultHttp($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => array('Accept: application/json'),
                CURLOPT_USERAGENT => 'BibleBrainEmbed/1.0',
            ));
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return array($code, $body === false ? '' : (string) $body);
        }
        $ctx = stream_context_create(array('http' => array(
            'timeout' => 25,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: BibleBrainEmbed/1.0\r\n",
        )));
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        return array($code, $body === false ? '' : (string) $body);
    }

    /* ---------------------------------------------------------------- */
    /* Dispatcher used by every adapter                                   */
    /* ---------------------------------------------------------------- */

    public function handle($endpoint, array $q)
    {
        switch ($endpoint) {
            case 'countries':
                return $this->epCountries();
            case 'catalog':
                $country = strtoupper(self::id(self::arg($q, 'country'), 2));
                $media = (string) self::arg($q, 'media');
                if (!preg_match('/^[A-Z]{2}$/', $country) || !in_array($media, array('text', 'audio', 'video'), true)) {
                    throw new ApiException('Invalid country or media type.', 400);
                }
                return $this->catalog($country, $media);
            case 'books':
                $fs = self::id(self::arg($q, 'fileset'));
                if ($fs === '') {
                    throw new ApiException('Missing fileset.', 400);
                }
                return $this->books($fs, self::id(self::arg($q, 'type')), self::id(self::arg($q, 'asset')), self::id(self::arg($q, 'bible')), self::id(self::arg($q, 'size'), 4));
            case 'text':
                list($fs, $book, $ch) = $this->chapterArgs($q);
                $type = self::id(self::arg($q, 'type'));
                if (!in_array($type, array('text_plain', 'text_format'), true)) {
                    throw new ApiException('Invalid request.', 400);
                }
                return $this->text($fs, $book, $ch, $type);
            case 'audio':
                list($fs, $book, $ch) = $this->chapterArgs($q);
                return $this->audio($fs, $book, $ch, self::id(self::arg($q, 'type')), self::id(self::arg($q, 'asset')));
            case 'timestamps':
                list($fs, $book, $ch) = $this->chapterArgs($q);
                return $this->timestamps($fs, $book, $ch);
            case 'video':
                $fs = self::id(self::arg($q, 'fileset'));
                if ($fs === '') {
                    throw new ApiException('Missing fileset.', 400);
                }
                return $this->video($fs, self::id(self::arg($q, 'asset')));
            case 'copyright':
                $bible = self::id(self::arg($q, 'bible'));
                if ($bible === '') {
                    throw new ApiException('Missing Bible id.', 400);
                }
                return $this->copyright($bible);
        }
        throw new ApiException('Unknown endpoint.', 404);
    }

    private static function id($v, $max = 40)
    {
        return substr(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $v), 0, $max);
    }

    private static function arg(array $q, $k)
    {
        return (isset($q[$k]) && is_scalar($q[$k])) ? $q[$k] : '';
    }

    private function chapterArgs(array $q)
    {
        $fs = self::id(self::arg($q, 'fileset'));
        $book = strtoupper(self::id(self::arg($q, 'book'), 4));
        $ch = (int) self::arg($q, 'chapter');
        if ($fs === '' || $book === '' || $ch < 1 || $ch > 200) {
            throw new ApiException('Invalid request.', 400);
        }
        return array($fs, $book, $ch);
    }

    /* ---------------------------------------------------------------- */
    /* HTTP + pagination                                                  */
    /* ---------------------------------------------------------------- */

    public function request($path, array $params = array(), $ttl = 0)
    {
        if ($this->key === '') {
            throw new ApiException('No Bible Brain API key has been set yet.', 400);
        }
        $params = array_filter($params, function ($v) {
            return $v !== '' && $v !== null;
        });
        ksort($params);

        $ck = '';
        if ($ttl > 0 && $this->cache) {
            $ck = 'r_' . md5($path . '?' . http_build_query($params));
            $hit = $this->cache->get($ck);
            if ($hit !== null) {
                return $hit;
            }
        }

        $params['key'] = $this->key;
        $params['v'] = 4;
        $url = $this->base . $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $res = call_user_func($this->http, $url);
        $code = isset($res[0]) ? (int) $res[0] : 0;
        $body = isset($res[1]) ? (string) $res[1] : '';
        if ($code === 0) {
            throw new ApiException('Could not reach Bible Brain. Please try again shortly.', 502);
        }
        $data = json_decode($body, true);
        if ($code >= 400 || !is_array($data)) {
            $msg = 'Bible Brain returned an error.';
            if (is_array($data) && isset($data['error']['message']) && is_string($data['error']['message'])) {
                $msg = $data['error']['message'];
            }
            throw new ApiException($msg, $code === 404 ? 404 : 502);
        }
        if ($ck !== '') {
            $this->cache->set($ck, $data, $ttl);
        }
        return $data;
    }

    public function paged($path, array $params = array(), $maxPages = 80)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        $all = array();
        $page = 1;
        $last = 1;
        do {
            try {
                $r = $this->request($path, array_merge($params, array('page' => $page, 'limit' => 50)));
            } catch (ApiException $e) {
                if ($page === 1) {
                    throw $e;
                }
                break;
            }
            if (isset($r['data']) && is_array($r['data'])) {
                $all = array_merge($all, array_values($r['data']));
            }
            $pg = (isset($r['meta']['pagination']) && is_array($r['meta']['pagination'])) ? $r['meta']['pagination'] : array();
            if (isset($pg['total_pages'])) {
                $last = (int) $pg['total_pages'];
            } elseif (isset($pg['last_page'])) {
                $last = (int) $pg['last_page'];
            } else {
                $last = 1;
            }
            $page++;
        } while ($page <= $last && $page <= $maxPages);
        return $all;
    }

    private function cached($key, $ttl, $fn)
    {
        if ($this->cache) {
            $hit = $this->cache->get($key);
            if ($hit !== null) {
                return $hit;
            }
        }
        $val = call_user_func($fn);
        if ($this->cache) {
            $this->cache->set($key, $val, $ttl);
        }
        return $val;
    }

    /* ---------------------------------------------------------------- */
    /* Countries and catalog                                              */
    /* ---------------------------------------------------------------- */

    private function epCountries()
    {
        $list = $this->cached('countries', 7 * 86400, function () {
            $rows = $this->paged('/countries');
            $out = array();
            foreach ($rows as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $code = '';
                if (isset($c['codes']['iso_a2'])) {
                    $code = $c['codes']['iso_a2'];
                } elseif (isset($c['code'])) {
                    $code = $c['code'];
                } elseif (isset($c['id'])) {
                    $code = $c['id'];
                }
                $code = strtoupper((string) $code);
                $name = isset($c['name']) ? (string) $c['name'] : '';
                if (preg_match('/^[A-Z]{2}$/', $code) && $name !== '') {
                    $out[$code] = array('code' => $code, 'name' => $name);
                }
            }
            $out = array_values($out);
            usort($out, function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            return $out;
        });
        if ($this->allowed) {
            $allowed = $this->allowed;
            $list = array_values(array_filter($list, function ($c) use ($allowed) {
                return in_array($c['code'], $allowed, true);
            }));
        }
        return array('countries' => $list);
    }

    private static function sizeLabel($size)
    {
        $map = array(
            'C' => 'Complete Bible', 'NT' => 'New Testament', 'OT' => 'Old Testament',
            'NTP' => 'New Testament (partial)', 'OTP' => 'Old Testament (partial)', 'P' => 'Selected portions', 'S' => 'Stories',
        );
        $size = strtoupper((string) $size);
        if (isset($map[$size])) {
            return $map[$size];
        }
        return $size !== '' ? $size : 'Full set';
    }

    private static function sizeRank($size)
    {
        $rank = array('C' => 0, 'NT' => 1, 'OT' => 2, 'NTP' => 3, 'OTP' => 4, 'P' => 5, 'S' => 6);
        $size = strtoupper((string) $size);
        return isset($rank[$size]) ? $rank[$size] : 9;
    }

    private static function acceptedTypes($media, array $have)
    {
        if ($media === 'text') {
            $pref = array('text_plain');
            $alt = array('text_format');
        } elseif ($media === 'audio') {
            $pref = array('audio', 'audio_drama');
            $alt = array('audio_stream', 'audio_drama_stream');
        } else {
            $pref = array('video_stream');
            $alt = array();
        }
        foreach ($pref as $t) {
            if (in_array($t, $have, true)) {
                return $pref;
            }
        }
        return $alt;
    }

    private static function collectFilesets($raw)
    {
        $flat = array();
        if (!is_array($raw)) {
            return $flat;
        }
        if (isset($raw['id'])) {
            $raw = array($raw);
        }
        foreach ($raw as $asset => $list) {
            if (!is_array($list)) {
                continue;
            }
            if (isset($list['id'])) {
                $list = array($list);
            }
            foreach ($list as $fs) {
                if (!is_array($fs) || empty($fs['id']) || empty($fs['type'])) {
                    continue;
                }
                $flat[] = array(
                    'id' => (string) $fs['id'],
                    'type' => (string) $fs['type'],
                    'size' => isset($fs['size']) ? (string) $fs['size'] : '',
                    'asset' => is_string($asset) ? $asset : '',
                );
            }
        }
        return $flat;
    }

    private static function filterFilesets($raw, $media)
    {
        $flat = self::collectFilesets($raw);
        $types = array_values(array_unique(array_column($flat, 'type')));
        $ok = self::acceptedTypes($media, $types);
        $out = array();
        $seen = array();
        foreach ($flat as $fs) {
            if (!in_array($fs['type'], $ok, true) || isset($seen[$fs['id']])) {
                continue;
            }
            $seen[$fs['id']] = true;
            $drama = strpos($fs['type'], 'drama') !== false;
            $label = self::sizeLabel($fs['size']);
            if ($media === 'video') {
                $label = 'Video - ' . $label;
            }
            if ($drama) {
                $label .= ' - Dramatized';
            }
            $fs['label'] = $label;
            $fs['rank'] = self::sizeRank($fs['size']) * 2 + ($drama ? 0 : 1);
            $out[] = $fs;
        }
        usort($out, function ($a, $b) {
            return $a['rank'] - $b['rank'];
        });
        foreach ($out as $i => $o) {
            unset($out[$i]['rank']);
        }
        return $out;
    }

    public function catalog($country, $media)
    {
        return $this->cached('cat_' . $country . '_' . $media, $this->ttl, function () use ($country, $media) {
            try {
                $rows = $this->paged('/bibles', array('country' => $country, 'media' => $media));
            } catch (ApiException $e) {
                $rows = $this->paged('/bibles', array('country' => $country));
            }
            $langs = array();
            foreach ($rows as $b) {
                if (!is_array($b) || empty($b['abbr'])) {
                    continue;
                }
                $filesets = self::filterFilesets(isset($b['filesets']) ? $b['filesets'] : array(), $media);
                if (!$filesets) {
                    continue;
                }
                $iso = '';
                if (!empty($b['iso'])) {
                    $iso = strtolower((string) $b['iso']);
                } elseif (!empty($b['language_id'])) {
                    $iso = 'id' . $b['language_id'];
                }
                if ($iso === '') {
                    continue;
                }
                $english = isset($b['language']) ? (string) $b['language'] : $iso;
                $auto = isset($b['autonym']) ? (string) $b['autonym'] : '';
                $display = ($auto !== '' && strcasecmp($auto, $english) !== 0) ? $auto . ' (' . $english . ')' : $english;
                $vname = isset($b['vname']) ? trim((string) $b['vname']) : '';
                $name = isset($b['name']) ? trim((string) $b['name']) : '';
                $label = $vname !== '' ? $vname : $name;
                if ($vname !== '' && $name !== '' && strcasecmp($vname, $name) !== 0) {
                    $label = $vname . ' (' . $name . ')';
                }
                if ($label === '') {
                    $label = (string) $b['abbr'];
                }
                if (!isset($langs[$iso])) {
                    $langs[$iso] = array('id' => $iso, 'name' => $display, 'versions' => array());
                }
                $langs[$iso]['versions'][$b['abbr']] = array('abbr' => (string) $b['abbr'], 'name' => $label, 'filesets' => $filesets);
            }
            foreach ($langs as $k => $l) {
                $v = array_values($l['versions']);
                usort($v, function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                $langs[$k]['versions'] = $v;
            }
            $langs = array_values($langs);
            usort($langs, function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            return array('languages' => $langs);
        });
    }

    /* ---------------------------------------------------------------- */
    /* Books and content                                                  */
    /* ---------------------------------------------------------------- */

    private static function normalizeBooks($rows)
    {
        $books = array();
        if (!is_array($rows)) {
            return $books;
        }
        foreach ($rows as $b) {
            if (!is_array($b) || empty($b['book_id'])) {
                continue;
            }
            $chapters = array();
            if (isset($b['chapters']) && is_array($b['chapters'])) {
                foreach ($b['chapters'] as $c) {
                    $n = is_array($c) ? (isset($c['chapter']) ? (int) $c['chapter'] : 0) : (int) $c;
                    if ($n > 0) {
                        $chapters[] = $n;
                    }
                }
            } elseif (isset($b['chapters']) && is_numeric($b['chapters'])) {
                $chapters = range(1, max(1, (int) $b['chapters']));
            }
            if (!$chapters) {
                continue;
            }
            $name = '';
            foreach (array('name', 'name_short') as $k) {
                if (!empty($b[$k])) {
                    $name = (string) $b[$k];
                    break;
                }
            }
            $books[] = array(
                'id' => strtoupper((string) $b['book_id']),
                'name' => $name !== '' ? $name : Canon::name($b['book_id']),
                'chapters' => array_values(array_unique($chapters)),
            );
        }
        return $books;
    }

    public function books($fileset, $type, $asset, $bible, $size)
    {
        $books = array();
        try {
            $r = $this->request('/bibles/filesets/' . rawurlencode($fileset) . '/books', array('type' => $type, 'asset_id' => $asset), 7 * 86400);
            if (isset($r['data'])) {
                $books = self::normalizeBooks($r['data']);
            }
        } catch (ApiException $e) {
            $books = array();
        }
        if (!$books && $bible !== '') {
            try {
                $r = $this->request('/bibles/' . rawurlencode($bible) . '/book', array(), 7 * 86400);
                if (isset($r['data'])) {
                    $books = self::normalizeBooks($r['data']);
                }
            } catch (ApiException $e) {
                $books = array();
            }
        }
        if (!$books) {
            $books = Canon::booksForSize($size);
        }
        return array('books' => $books);
    }

    public function text($fileset, $book, $chapter, $type)
    {
        $r = $this->request('/bibles/filesets/' . rawurlencode($fileset) . '/' . rawurlencode($book) . '/' . (int) $chapter, array('type' => $type), $this->ttl);
        $verses = array();
        $name = '';
        $rows = (isset($r['data']) && is_array($r['data'])) ? $r['data'] : array();
        foreach ($rows as $v) {
            if (!is_array($v) || !isset($v['verse_text'])) {
                continue;
            }
            $n = '';
            if (!empty($v['verse_start_alt'])) {
                $n = (string) $v['verse_start_alt'];
            } elseif (isset($v['verse_start'])) {
                $n = (string) $v['verse_start'];
            }
            $txt = (string) $v['verse_text'];
            if ($type === 'text_format') {
                $txt = strip_tags($txt);
            }
            $verses[] = array('n' => $n, 'text' => trim($txt));
            if ($name === '' && !empty($v['book_name'])) {
                $name = (string) $v['book_name'];
            }
        }
        return array('book_name' => $name !== '' ? $name : Canon::name($book), 'chapter' => (int) $chapter, 'verses' => $verses);
    }

    public function audio($fileset, $book, $chapter, $type, $asset)
    {
        $r = $this->request('/bibles/filesets/' . rawurlencode($fileset) . '/' . rawurlencode($book) . '/' . (int) $chapter, array('type' => $type, 'asset_id' => $asset), 300);
        $urls = array();
        $rows = (isset($r['data']) && is_array($r['data'])) ? $r['data'] : array();
        foreach ($rows as $a) {
            if (is_array($a) && !empty($a['path'])) {
                $urls[] = (string) $a['path'];
            }
        }
        if (!$urls) {
            throw new ApiException('No audio is available for this chapter.', 404);
        }
        return array('urls' => $urls);
    }

    public function video($fileset, $asset)
    {
        $r = $this->request('/bibles/filesets/' . rawurlencode($fileset), array('type' => 'video_stream', 'asset_id' => $asset !== '' ? $asset : 'dbp-vid'), 300);
        $clips = array();
        $rows = (isset($r['data']) && is_array($r['data'])) ? $r['data'] : array();
        foreach ($rows as $c) {
            if (!is_array($c)) {
                continue;
            }
            $path = '';
            if (!empty($c['path'])) {
                $path = (string) $c['path'];
            } elseif (!empty($c['url'])) {
                $path = (string) $c['url'];
            }
            if ($path === '') {
                continue;
            }
            $book = isset($c['book_id']) ? strtoupper((string) $c['book_id']) : '';
            $title = '';
            foreach (array('title', 'name') as $k) {
                if (!empty($c[$k]) && is_string($c[$k])) {
                    $title = $c[$k];
                    break;
                }
            }
            $thumb = '';
            if (!empty($c['thumbnail']) && is_string($c['thumbnail'])) {
                $thumb = $c['thumbnail'];
            } elseif (!empty($c['thumb']) && is_string($c['thumb'])) {
                $thumb = $c['thumb'];
            }
            $clips[] = array(
                'url' => $path,
                'thumb' => $thumb,
                'title' => $title,
                'book' => $book,
                'book_name' => $book !== '' ? Canon::name($book) : '',
                'chapter' => isset($c['chapter_start']) ? (int) $c['chapter_start'] : (isset($c['chapter']) ? (int) $c['chapter'] : 0),
                'vs' => isset($c['verse_start']) ? (int) $c['verse_start'] : 0,
                've' => isset($c['verse_end']) ? (int) $c['verse_end'] : 0,
                'duration' => isset($c['duration']) ? (int) $c['duration'] : 0,
            );
        }
        if (!$clips) {
            throw new ApiException('No video is available for this selection.', 404);
        }
        return array('clips' => $clips);
    }

    public function timestamps($fileset, $book, $chapter)
    {
        try {
            $r = $this->request('/timestamps/' . rawurlencode($fileset) . '/' . rawurlencode($book) . '/' . (int) $chapter, array(), 86400);
        } catch (ApiException $e) {
            return array('times' => array());
        }
        $times = array();
        $rows = (isset($r['data']) && is_array($r['data'])) ? $r['data'] : array();
        foreach ($rows as $t) {
            if (!is_array($t) || !isset($t['timestamp'])) {
                continue;
            }
            $v = '';
            if (!empty($t['verse_start_alt'])) {
                $v = (string) $t['verse_start_alt'];
            } elseif (isset($t['verse_start'])) {
                $v = (string) $t['verse_start'];
            }
            if ($v !== '') {
                $times[] = array('v' => $v, 't' => (float) $t['timestamp']);
            }
        }
        return array('times' => $times);
    }

    public function copyright($bible)
    {
        $txt = '';
        try {
            $r = $this->request('/bibles/' . rawurlencode($bible) . '/copyright', array(), 7 * 86400);
            $d = isset($r['data']) ? $r['data'] : $r;
            if (is_array($d) && isset($d[0])) {
                $d = $d[0];
            }
            if (is_array($d)) {
                if (isset($d['copyright']['copyright']) && is_string($d['copyright']['copyright'])) {
                    $txt = $d['copyright']['copyright'];
                } elseif (isset($d['copyright']) && is_string($d['copyright'])) {
                    $txt = $d['copyright'];
                }
            }
        } catch (ApiException $e) {
            $txt = '';
        }
        return array('copyright' => strip_tags($txt));
    }

    /** Raw passthrough for admin diagnostics. */
    public function raw($path, array $params)
    {
        if ($path === '' || $path[0] !== '/' || strpos($path, '..') !== false || !preg_match('#^/[A-Za-z0-9_\-/\.]*$#', $path)) {
            throw new ApiException('Enter an API path such as /bibles or /countries.', 400);
        }
        return $this->request($path, $params);
    }
}
