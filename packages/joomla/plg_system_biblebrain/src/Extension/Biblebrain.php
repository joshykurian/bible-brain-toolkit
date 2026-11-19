<?php
/**
 * Bible Brain Players for Joomla - plugin class.
 *
 * @license GPL-2.0-or-later
 */

namespace Biblebrain\Plugin\System\Biblebrain\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

require_once \dirname(__DIR__, 2) . '/lib/BibleBrainCore.php';

/**
 * Bible Brain Players for Joomla.
 *
 * 1. Serves the data proxy at index.php?bbapi=<endpoint> (the API key stays on the server).
 * 2. Replaces {bbp reader|audio|video key="value" ...} tags anywhere in the page with a player.
 */
final class Biblebrain extends CMSPlugin implements SubscriberInterface
{
	private const DEFAULT_PROMPTS = "What did you hear? Tell it back in your own words.\nWhat does this passage show me about God and about people?\nWhat will I do about it, and who will I share it with?";

	/** @var array */
	private $counter = ['reader' => 0, 'audio' => 0, 'video' => 0];

	/** @var array */
	private $used = [];

	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterInitialise' => 'onAfterInitialise',
			'onAfterRender'     => 'onAfterRender',
		];
	}

	/* ------------------------------------------------------------------ */
	/* Data proxy                                                          */
	/* ------------------------------------------------------------------ */

	public function onAfterInitialise(): void
	{
		$app = $this->getApplication();

		if (!$app->isClient('site')) {
			return;
		}

		$endpoint = (string) $app->getInput()->get('bbapi', '', 'cmd');

		if ($endpoint === '') {
			return;
		}

		$query = $app->getInput()->get->getArray();
		unset($query['bbapi']);

		$cache = new \BibleBrain\FileCache(JPATH_CACHE . '/plg_system_biblebrain');

		try {
			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';

			if (!\BibleBrain\RateLimiter::allow($cache, md5($ip), 300, 300)) {
				$this->sendJson(['code' => 'bbp_rate', 'message' => 'Too many requests. Please wait a few minutes.'], 429);
			}

			$core = new \BibleBrain\Core(
				trim((string) $this->params->get('api_key', '')),
				null,
				$cache,
				[
					'cache_hours'       => (int) $this->params->get('cache_hours', 12),
					'allowed_countries' => (string) $this->params->get('allowed_countries', ''),
				]
			);

			$this->sendJson($core->handle($endpoint, $query));
		} catch (\BibleBrain\ApiException $e) {
			$this->sendJson(['code' => 'bbp_error', 'message' => $e->getMessage()], $e->status);
		} catch (\Throwable $e) {
			$this->sendJson(['code' => 'bbp_error', 'message' => 'Server error.'], 500);
		}
	}

	private function sendJson($data, int $status = 200): void
	{
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
		echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$this->getApplication()->close();
	}

	/* ------------------------------------------------------------------ */
	/* Tag replacement                                                     */
	/* ------------------------------------------------------------------ */

	public function onAfterRender(): void
	{
		$app = $this->getApplication();

		if (!$app->isClient('site') || $app->getDocument()->getType() !== 'html') {
			return;
		}

		$body = $app->getBody();

		if ($body === null || stripos($body, '{bbp') === false) {
			return;
		}

		$body = preg_replace_callback('/\{bbp(?:\s|&nbsp;)+(reader|audio|video)([^}]*)\}/i', [$this, 'renderTag'], $body);

		if (!$this->used) {
			return;
		}

		$root = rtrim(Uri::root(true), '/') . '/media/plg_system_biblebrain';
		$css  = '<link rel="stylesheet" href="' . $root . '/css/bible-brain-players.css">';
		$js   = '<script src="' . $root . '/js/bible-brain-players.js" defer></script>';

		if ($this->params->get('cdn_hls', 1) && (isset($this->used['audio']) || isset($this->used['video']))) {
			$js = '<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js" defer></script>' . $js;
		}

		$body = $this->insertBefore($body, '</head>', $css);
		$body = $this->insertBefore($body, '</body>', $js);

		$app->setBody($body);
	}

	private function insertBefore(string $html, string $needle, string $add): string
	{
		$pos = strripos($html, $needle);

		if ($pos === false) {
			return $html . $add;
		}

		return substr_replace($html, $add, $pos, 0);
	}

	private function parseAttributes(string $raw): array
	{
		$raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$raw = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{2018}", "\u{2019}"], ['"', '"', '"', "'", "'"], $raw);
		$out = [];

		if (preg_match_all('/([a-z_]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/i', $raw, $m, PREG_SET_ORDER)) {
			foreach ($m as $row) {
				$value = isset($row[4]) ? $row[4] : (isset($row[3]) && $row[3] !== '' ? $row[3] : $row[2]);
				$out[strtolower($row[1])] = trim($value);
			}
		}

		return $out;
	}

	private static function clean(string $value, int $max): string
	{
		return substr((string) preg_replace('/[^A-Za-z0-9_\-]/', '', $value), 0, $max);
	}

	private static function flag($value, bool $fallback): bool
	{
		if ($value === null || $value === '') {
			return $fallback;
		}

		return \in_array(strtolower((string) $value), ['1', 'yes', 'true', 'on'], true);
	}

	public function renderTag(array $match): string
	{
		$type = strtolower($match[1]);
		$a    = $this->parseAttributes($match[2]);
		$p    = $this->params;

		if (trim((string) $p->get('api_key', '')) === '') {
			return '<div class="bbp-notice">Bible Brain Players: add your free API key in the plugin settings.</div>';
		}

		foreach (['country', 'language', 'bible', 'fileset'] as $k) {
			if (!isset($a[$k]) || $a[$k] === '') {
				$a[$k] = (string) $p->get($type . '_' . $k, '');
			}
		}

		$this->counter[$type]++;
		$this->used[$type] = true;

		$promptsRaw = trim((string) $p->get('reflect_prompts', ''));
		$prompts    = array_values(array_filter(array_map('trim', explode("\n", $promptsRaw !== '' ? $promptsRaw : self::DEFAULT_PROMPTS))));

		$cfg = [
			'type'     => $type,
			'uid'      => $this->counter[$type],
			'rest'     => rtrim(Uri::root(), '/') . '/index.php?bbapi=',
			'country'  => strtoupper(self::clean($a['country'], 2)),
			'language' => strtolower(self::clean($a['language'], 16)),
			'bible'    => self::clean($a['bible'], 24),
			'fileset'  => self::clean($a['fileset'], 40),
			'book'     => strtoupper(self::clean(isset($a['book']) ? $a['book'] : '', 4)),
			'chapter'  => max(0, (int) (isset($a['chapter']) ? $a['chapter'] : 0)),
			'selector' => self::flag(isset($a['selector']) ? $a['selector'] : null, true),
			'share'    => self::flag(isset($a['share']) ? $a['share'] : null, (bool) $p->get('share', 1)),
			'reflect'  => self::flag(isset($a['reflect']) ? $a['reflect'] : null, (bool) $p->get('reflect', 1)),
			'listen'   => $type === 'reader' ? self::flag(isset($a['listen']) ? $a['listen'] : null, (bool) $p->get('listen', 1)) : false,
			'prompts'  => $prompts,
			'title'    => isset($a['title']) ? strip_tags($a['title']) : '',
		];

		$labels = json_decode((string) $p->get('labels', ''), true);

		if (\is_array($labels)) {
			$cfg['i18n'] = $labels;
		}

		$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $p->get('accent', '')) ? (string) $p->get('accent') : '#7E0E2A';
		$theme  = isset($a['theme']) && \in_array($a['theme'], ['auto', 'light', 'dark'], true) ? $a['theme'] : (string) $p->get('theme', 'auto');

		return sprintf(
			'<div class="bbp bbp-%1$s" data-theme="%2$s" style="--bbp-accent:%3$s;" data-bbp="%4$s"></div>',
			$type,
			htmlspecialchars($theme, ENT_QUOTES, 'UTF-8'),
			$accent,
			htmlspecialchars((string) json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')
		);
	}
}
