/* Behaviour for the demo page: language words, live players, install tabs, "use my proxy". */
(function () {
	'use strict';

	var LANGS = [
		{ code: 'en', word: 'Listen', name: 'English' },
		{ code: 'hi', word: 'सुनें', name: 'Hindi' },
		{ code: 'bn', word: 'শুনুন', name: 'Bengali' },
		{ code: 'ne', word: 'सुन्नुहोस्', name: 'Nepali' },
		{ code: 'si', word: 'අසන්න', name: 'Sinhala' },
		{ code: 'ur', word: 'سنیں', name: 'Urdu', rtl: true },
		{ code: 'ml', word: 'കേൾക്കുക', name: 'Malayalam' }
	];
	var state = { lang: 'en', rest: 'demo-api/', own: false };

	function $(id) { return document.getElementById(id); }
	function el(tag, attrs, text) {
		var n = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
		if (text) { n.textContent = text; }
		return n;
	}

	/* ---- live players ---- */
	function player(type, extra) {
		var d = el('div', { 'class': 'bbp-' + type, 'data-rest': state.rest, 'data-lang': state.lang });
		var base = state.own ? { country: 'IN', language: 'hin', bible: 'HINDOV' } : { country: 'IN', language: 'eng', bible: 'DEMOKJV' };
		Object.keys(base).forEach(function (k) { d.setAttribute('data-' + k, base[k]); });
		Object.keys(extra || {}).forEach(function (k) { d.setAttribute('data-' + k, extra[k]); });
		return d;
	}
	function put(id, node) {
		var host = $(id);
		host.textContent = '';
		host.appendChild(node);
		if (window.BBPlayers) { window.BBPlayers.mount(node); }
	}
	function clearShareParams() {
		try { history.replaceState(null, '', location.pathname + location.hash); } catch (e) { /* ignore */ }
	}
	function forgetSelections() {
		try {
			Object.keys(localStorage).forEach(function (k) { if (k.indexOf('bbp_sel_') === 0) { localStorage.removeItem(k); } });
		} catch (e) { /* storage unavailable */ }
	}
	function mountAll() {
		clearShareParams();
		put('hero-player', player('audio', { book: 'JHN', chapter: '3' }));
		put('reader-player', player('reader', { book: 'JHN', chapter: '3' }));
		put('video-player', player('video'));
	}

	/* ---- the word "Listen" in each language ---- */
	function buildWords() {
		var ul = $('words');
		LANGS.forEach(function (l) {
			var li = el('li');
			var b = el('button', { type: 'button', lang: l.code, 'aria-pressed': l.code === state.lang ? 'true' : 'false', 'aria-label': l.name + ': ' + l.word, 'data-code': l.code }, l.word);
			if (l.rtl) { b.setAttribute('dir', 'rtl'); }
			b.addEventListener('click', function () {
				state.lang = l.code;
				Array.prototype.forEach.call(ul.querySelectorAll('button'), function (x) { x.setAttribute('aria-pressed', x.getAttribute('data-code') === l.code ? 'true' : 'false'); });
				mountAll();
			});
			li.appendChild(b);
			ul.appendChild(li);
		});
	}

	/* ---- install tabs ---- */
	var REL = 'https://github.com/shalomdesigns2dio/bible-brain-toolkit/releases';
	var PLATFORMS = [
		{ id: 'wp', name: 'WordPress', steps: [
			'Download the WordPress plugin ZIP from <a href="' + REL + '">Releases</a>, then Plugins > Add New > Upload Plugin.',
			'Open Settings > Bible Brain Players and paste your API key.',
			'Add a shortcode to any page.'],
			code: '[bb_audio country="IN" language="hin" bible="HINDOV"]\n[bb_reader country="IN" bible="HINDOV" selector="no"]\n[bb_video country="IN" language="hin"]' },
		{ id: 'joomla', name: 'Joomla', steps: [
			'Install the Joomla plugin ZIP from <a href="' + REL + '">Releases</a> (System > Install > Extensions).',
			'Enable System - Bible Brain Players and paste your API key.',
			'Type a tag in any article.'],
			code: '{bbp audio country="IN" language="hin" bible="HINDOV"}\n{bbp reader country="IN" bible="HINDOV" selector="no"}\n{bbp video country="IN" language="hin"}' },
		{ id: 'drupal', name: 'Drupal', steps: [
			'Copy the module folder to modules/custom and enable it.',
			'Open Configuration > Media > Bible Brain Players and paste your API key.',
			'Place the Bible Brain player block where you want it.'],
			code: 'drush en bible_brain\n\n# then: Structure > Block layout > Place block\n#   "Bible Brain player"' },
		{ id: 'html', name: 'Any website', steps: [
			'Upload the HTML5 package to a PHP host and add your key to config.php.',
			'Open generator.html, choose a country, language and version.',
			'Paste the code it gives you into your page.'],
			code: '<link rel="stylesheet" href="https://example.org/bible/bible-brain-players.css">\n<div class="bbp-audio"\n     data-rest="https://example.org/bible/bbp-proxy.php?ep="\n     data-country="IN" data-language="hin" data-bible="HINDOV"></div>\n<script src="https://example.org/bible/bible-brain-players.js" defer></script>' },
		{ id: 'dl', name: 'Downloader', steps: [
			'Download the downloader (Python 3.8 or newer) from <a href="' + REL + '">Releases</a>.',
			'Run it for a window, or use the command line.',
			'Check the license of each Bible version before you share the files.'],
			code: 'python main.py                      # window\npython main.py --about              # links\nexport BIBLE_BRAIN_KEY=your-key\npython main.py --list-countries\npython main.py --list-filesets --country IN --language hin\npython main.py --fileset HINDOVN2DA --type audio_drama --out ./downloads' }
	];

	function buildTabs() {
		var tabs = $('tabs'), panels = $('panels');
		PLATFORMS.forEach(function (p, i) {
			var b = el('button', { type: 'button', role: 'tab', id: 'tab-' + p.id, 'aria-controls': 'panel-' + p.id, 'aria-selected': i === 0 ? 'true' : 'false', tabindex: i === 0 ? '0' : '-1' }, p.name);
			tabs.appendChild(b);
			var panel = el('div', { 'class': 'panel', role: 'tabpanel', id: 'panel-' + p.id, 'aria-labelledby': 'tab-' + p.id });
			if (i) { panel.hidden = true; }
			var ol = el('ol');
			p.steps.forEach(function (s) { var li = el('li'); li.innerHTML = s; ol.appendChild(li); });
			var box = el('div', { 'class': 'code' });
			var copy = el('button', { type: 'button', 'class': 'copy' }, 'Copy');
			copy.addEventListener('click', function () {
				var done = function () { copy.textContent = 'Copied'; setTimeout(function () { copy.textContent = 'Copy'; }, 1500); };
				if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(p.code).then(done); }
			});
			var pre = el('pre'); pre.textContent = p.code;
			box.appendChild(copy); box.appendChild(pre);
			panel.appendChild(ol); panel.appendChild(box);
			panels.appendChild(panel);
			b.addEventListener('click', function () { select(i); });
			b.addEventListener('keydown', function (e) {
				var n = e.key === 'ArrowRight' ? (i + 1) % PLATFORMS.length : e.key === 'ArrowLeft' ? (i + PLATFORMS.length - 1) % PLATFORMS.length : -1;
				if (n > -1) { e.preventDefault(); select(n); tabs.children[n].focus(); }
			});
		});
		function select(n) {
			Array.prototype.forEach.call(tabs.children, function (b, k) { b.setAttribute('aria-selected', k === n ? 'true' : 'false'); b.tabIndex = k === n ? 0 : -1; });
			Array.prototype.forEach.call(panels.children, function (p, k) { p.hidden = k !== n; });
		}
	}

	/* ---- use my own proxy ---- */
	function buildOwn() {
		var form = $('own-form'), status = $('own-status');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var v = $('own-url').value.trim();
			if (!/^(https:\/\/|http:\/\/(localhost|127\.0\.0\.1)(:\d+)?\/)/i.test(v)) { status.textContent = 'Enter an address that starts with https:// (http:// works only for localhost).'; return; }
			state.rest = v; state.own = true; forgetSelections();
			status.textContent = 'Showing content from your server. If a player stays empty, check that the address opens in a browser and that allowed_origins includes this website.';
			mountAll();
		});
		$('own-reset').addEventListener('click', function () {
			state.rest = 'demo-api/'; state.own = false; forgetSelections(); $('own-url').value = '';
			status.textContent = 'Showing sample data again.';
			mountAll();
		});
	}

	function init() {
		buildWords(); buildTabs(); buildOwn(); mountAll();
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
