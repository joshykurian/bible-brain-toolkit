/* Demo API for the GitHub Pages site.
 * It answers the same endpoints as the real proxy (bbp-proxy.php, wp-json/bbp/v1, ...) from sample data, so the players
 * can run on a static page with no server and no API key. Requests to any other address are passed through untouched,
 * which is how "try it with your own proxy" works.
 *
 * Sample data only: text is the King James Version (public domain); the audio is a computer-generated voice reading that
 * text and the video clips are generated test patterns. NONE of it is Bible Brain content. */
(function () {
	'use strict';

	var JOHN3 = [
		'There was a man of the Pharisees, named Nicodemus, a ruler of the Jews:',
		'The same came to Jesus by night, and said unto him, Rabbi, we know that thou art a teacher come from God: for no man can do these miracles that thou doest, except God be with him.',
		'Jesus answered and said unto him, Verily, verily, I say unto thee, Except a man be born again, he cannot see the kingdom of God.',
		'Nicodemus saith unto him, How can a man be born when he is old? can he enter the second time into his mother’s womb, and be born?',
		'Jesus answered, Verily, verily, I say unto thee, Except a man be born of water and of the Spirit, he cannot enter into the kingdom of God.',
		'That which is born of the flesh is flesh; and that which is born of the Spirit is spirit.',
		'Marvel not that I said unto thee, Ye must be born again.',
		'The wind bloweth where it listeth, and thou hearest the sound thereof, but canst not tell whence it cometh, and whither it goeth: so is every one that is born of the Spirit.',
		'Nicodemus answered and said unto him, How can these things be?',
		'Jesus answered and said unto him, Art thou a master of Israel, and knowest not these things?',
		'Verily, verily, I say unto thee, We speak that we do know, and testify that we have seen; and ye receive not our witness.',
		'If I have told you earthly things, and ye believe not, how shall ye believe, if I tell you of heavenly things?',
		'And no man hath ascended up to heaven, but he that came down from heaven, even the Son of man which is in heaven.',
		'And as Moses lifted up the serpent in the wilderness, even so must the Son of man be lifted up:',
		'That whosoever believeth in him should not perish, but have eternal life.',
		'For God so loved the world, that he gave his only begotten Son, that whosoever believeth in him should not perish, but have everlasting life.',
		'For God sent not his Son into the world to condemn the world; but that the world through him might be saved.',
		'He that believeth on him is not condemned: but he that believeth not is condemned already, because he hath not believed in the name of the only begotten Son of God.',
		'And this is the condemnation, that light is come into the world, and men loved darkness rather than light, because their deeds were evil.',
		'For every one that doeth evil hateth the light, neither cometh to the light, lest his deeds should be reproved.',
		'But he that doeth truth cometh to the light, that his deeds may be made manifest, that they are wrought in God.'
	];
	var PSALM23 = [
		'The LORD is my shepherd; I shall not want.',
		'He maketh me to lie down in green pastures: he leadeth me beside the still waters.',
		'He restoreth my soul: he leadeth me in the paths of righteousness for his name’s sake.',
		'Yea, though I walk through the valley of the shadow of death, I will fear no evil: for thou art with me; thy rod and thy staff they comfort me.',
		'Thou preparest a table before me in the presence of mine enemies: thou anointest my head with oil; my cup runneth over.',
		'Surely goodness and mercy shall follow me all the days of my life: and I will dwell in the house of the LORD for ever.'
	];
	var TEXTS = { 'JHN.3': { name: 'John', verses: JOHN3, step: 2 }, 'PSA.23': { name: 'Psalms', verses: PSALM23, step: 7 } };

	var COUNTRIES = [['BD', 'Bangladesh'], ['BT', 'Bhutan'], ['IN', 'India'], ['LK', 'Sri Lanka'], ['MV', 'Maldives'], ['NP', 'Nepal'], ['PK', 'Pakistan']];
	var VERSION = 'DEMOKJV';

	function base(file) {
		var s = document.currentScript || document.querySelector('script[src*="demo-api.js"]');
		var src = s ? s.getAttribute('src') : 'assets/demo-api.js';
		return src.replace(/assets\/demo-api\.js.*$/, '') + file;
	}
	var canMp4 = (function () { try { return !!document.createElement('video').canPlayType('video/mp4; codecs="avc1.42E01E"'); } catch (e) { return true; } })();
	function clip(n) { return base('media/demo-clip-' + n + (canMp4 ? '.mp4' : '.webm')); }

	function catalog(media) {
		var fs = media === 'text' ? [{ id: 'DEMOKJV', type: 'text_plain', size: 'P', asset: 'dbp-prod', label: 'Text - sample passages' }]
			: media === 'audio' ? [{ id: 'DEMOKJVP1DA', type: 'audio_drama', size: 'P', asset: 'dbp-prod', label: 'Audio - sample voice (computer-read)' }]
				: [{ id: 'DEMOVIDP2DV', type: 'video_stream', size: 'P', asset: 'dbp-vid', label: 'Video - sample clips' }];
		return { languages: [{ id: 'eng', name: 'English (demo)', versions: [{ abbr: VERSION, name: 'Sample KJV', filesets: fs }] }] };
	}

	function answer(ep, q) {
		switch (ep) {
			case 'countries': return { countries: COUNTRIES.map(function (c) { return { code: c[0], name: c[1] }; }) };
			case 'catalog': return catalog(q.media);
			case 'books': return { books: [{ id: 'JHN', name: 'John', chapters: [3] }, { id: 'PSA', name: 'Psalms', chapters: [23] }] };
			case 'text':
				var t = TEXTS[q.book + '.' + q.chapter];
				if (!t) { return null; }
				return { book_name: t.name, chapter: Number(q.chapter), verses: t.verses.map(function (v, i) { return { n: String(i + 1), text: v }; }) };
			case 'audio':
				return TEXTS[q.book + '.' + q.chapter] ? { urls: [base('media/demo-' + (q.book === 'PSA' ? 'psalm-23' : 'john-3') + '.mp3')] } : null;
			case 'timestamps':
				var s = TEXTS[q.book + '.' + q.chapter];
				if (!s) { return null; }
				var real = timings && timings[q.book + '.' + q.chapter];
				return { times: s.verses.map(function (v, i) { return { v: String(i + 1), t: real ? real[i] : i * s.step }; }) };
			case 'video':
				return { clips: [
					{ url: clip(1), thumb: '', title: 'Sample clip 1', book: 'DEMO', book_name: 'Demo clips', chapter: 1, vs: 1, ve: 1, duration: 12 },
					{ url: clip(2), thumb: '', title: 'Sample clip 2', book: 'DEMO', book_name: 'Demo clips', chapter: 2, vs: 1, ve: 1, duration: 12 }] };
			case 'copyright': return { copyright: 'Demo data. Text: King James Version (public domain). Audio: computer-generated voice reading the same text. Video: generated test clips, not Scripture. Not Bible Brain content.' };
		}
		return null;
	}

	var timings = null; /* real verse start times of the generated voice, loaded once from media/demo-timings.json */
	var realFetch = window.fetch ? window.fetch.bind(window) : null;
	function loadTimings() {
		if (!realFetch) { return Promise.resolve(); }
		return realFetch(base('media/demo-timings.json')).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) { timings = j; }, function () { /* fall back to even steps */ });
	}
	window.fetch = function (input, init) {
		var url = typeof input === 'string' ? input : (input && input.url) || '';
		var m = /(?:^|\/)demo-api\/([a-z]+)(?:\?(.*))?$/.exec(url);
		if (!m) { return realFetch ? realFetch(input, init) : Promise.reject(new Error('fetch unavailable')); }
		var q = {};
		(m[2] || '').split('&').forEach(function (p) { if (p) { var kv = p.split('='); q[decodeURIComponent(kv[0])] = decodeURIComponent((kv[1] || '').replace(/\+/g, ' ')); } });
		return (m[1] === 'timestamps' && !timings ? loadTimings() : Promise.resolve()).then(function () {
			var body = answer(m[1], q);
			var status = body ? 200 : 404;
			return new Response(JSON.stringify(body || { code: 'bbp_error', message: 'This chapter is not part of the demo. Try John 3 or Psalm 23.' }),
				{ status: status, headers: { 'Content-Type': 'application/json' } });
		});
	};
	window.BBP_DEMO = { version: VERSION, countries: COUNTRIES, texts: { 'JHN.3': JOHN3, 'PSA.23': PSALM23 } };
})();
