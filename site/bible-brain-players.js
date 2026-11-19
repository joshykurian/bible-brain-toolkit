/*!
 * Bible Brain Players - front end
 * Vanilla JS. Talks only to this site's REST proxy; the API key never reaches the browser.
 */
(function () {
	'use strict';

	var REST_DEFAULT = (document.currentScript && document.currentScript.getAttribute('data-rest')) || window.BBP_REST || '';
	var DEFAULT_I18N = {
		country: 'Country', language: 'Language', version: 'Version', recording: 'Edition', book: 'Book', chapter: 'Chapter',
		loading: 'Loading...', error: 'Something went wrong. Please try again.', noversions: 'No versions were found for this selection.',
		noconfig: 'No default version has been set for this player.', prev: 'Previous', next: 'Next', smaller: 'Smaller text',
		larger: 'Larger text', speed: 'Speed', autoplay: 'Play the next chapter automatically', autoclip: 'Play the next clip automatically',
		listen: 'Listen to this chapter', clips: 'Clips', nowplaying: 'Now playing', reflect: 'Reflect and respond', share: 'Share this',
		copylink: 'Copy link', copied: 'Link copied', whatsapp: 'WhatsApp', facebook: 'Facebook', telegram: 'Telegram',
		poweredby: 'Scripture content provided by Bible Brain (Faith Comes By Hearing).', chapterlabel: 'Chapter',
		play: 'Play', pause: 'Pause', back: 'Back 15 seconds', forward: 'Forward 15 seconds', seek: 'Seek'
	};
	var DEFAULT_PROMPTS = ['What did you hear? Tell it back in your own words.', 'What does this passage show me about God and about people?', 'What will I do about it, and who will I share it with?'];
	/*BBP_LOCALES_START*/
	var LOCALES = {"bn":{"rtl":false,"labels":{"country":"দেশ","language":"ভাষা","version":"অনুবাদ","recording":"সংস্করণ","book":"বই","chapter":"অধ্যায়","loading":"লোড হচ্ছে...","error":"কিছু ভুল হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।","noversions":"এই নির্বাচনের জন্য কোনো অনুবাদ পাওয়া যায়নি।","noconfig":"এই প্লেয়ারের জন্য কোনো ডিফল্ট অনুবাদ সেট করা হয়নি।","prev":"আগের","next":"পরের","smaller":"ছোট লেখা","larger":"বড় লেখা","speed":"গতি","autoplay":"পরের অধ্যায় নিজে নিজে চালান","autoclip":"পরের ক্লিপ নিজে নিজে চালান","listen":"এই অধ্যায়টি শুনুন","clips":"ক্লিপ","nowplaying":"এখন চলছে","reflect":"ভাবুন ও সাড়া দিন","share":"শেয়ার করুন","copylink":"লিংক কপি করুন","copied":"লিংক কপি হয়েছে","whatsapp":"WhatsApp","facebook":"Facebook","telegram":"Telegram","poweredby":"পবিত্র শাস্ত্রের বিষয়বস্তু Bible Brain (Faith Comes By Hearing) থেকে","chapterlabel":"অধ্যায়","play":"চালান","pause":"থামান","back":"১৫ সেকেন্ড পিছনে","forward":"১৫ সেকেন্ড সামনে","seek":"অবস্থান বেছে নিন"},"prompts":["আপনি কী শুনলেন? নিজের ভাষায় বলুন।","এই অংশ ঈশ্বর ও মানুষ সম্পর্কে আমাকে কী দেখায়?","আমি এটি নিয়ে কী করব, আর কাকে জানাব?"]},"hi":{"rtl":false,"labels":{"country":"देश","language":"भाषा","version":"अनुवाद","recording":"संस्करण","book":"पुस्तक","chapter":"अध्याय","loading":"लोड हो रहा है...","error":"कुछ गड़बड़ हो गई। कृपया फिर से प्रयास करें।","noversions":"इस चयन के लिए कोई अनुवाद नहीं मिला।","noconfig":"इस प्लेयर के लिए कोई डिफ़ॉल्ट अनुवाद सेट नहीं किया गया है।","prev":"पिछला","next":"अगला","smaller":"छोटा अक्षर","larger":"बड़ा अक्षर","speed":"गति","autoplay":"अगला अध्याय अपने-आप चलाएँ","autoclip":"अगली क्लिप अपने-आप चलाएँ","listen":"इस अध्याय को सुनें","clips":"क्लिप","nowplaying":"अब चल रहा है","reflect":"मनन करें और उत्तर दें","share":"साझा करें","copylink":"लिंक कॉपी करें","copied":"लिंक कॉपी हो गया","whatsapp":"WhatsApp","facebook":"Facebook","telegram":"Telegram","poweredby":"पवित्र शास्त्र की सामग्री Bible Brain (Faith Comes By Hearing) से","chapterlabel":"अध्याय","play":"चलाएँ","pause":"रोकें","back":"15 सेकंड पीछे","forward":"15 सेकंड आगे","seek":"स्थान चुनें"},"prompts":["आपने क्या सुना? अपने शब्दों में बताइए।","यह अंश मुझे परमेश्वर और मनुष्यों के बारे में क्या दिखाता है?","मैं इसके अनुसार क्या करूँगा, और किसे बताऊँगा?"]},"ml":{"rtl":false,"labels":{"country":"രാജ്യം","language":"ഭാഷ","version":"പരിഭാഷ","recording":"പതിപ്പ്","book":"പുസ്തകം","chapter":"അധ്യായം","loading":"ലോഡ് ചെയ്യുന്നു...","error":"എന്തോ പിശക് സംഭവിച്ചു. ദയവായി വീണ്ടും ശ്രമിക്കുക.","noversions":"ഈ തിരഞ്ഞെടുപ്പിന് പരിഭാഷകളൊന്നും കണ്ടെത്തിയില്ല.","noconfig":"ഈ പ്ലെയറിന് ഡിഫോൾട്ട് പരിഭാഷ സജ്ജമാക്കിയിട്ടില്ല.","prev":"മുമ്പത്തേത്","next":"അടുത്തത്","smaller":"ചെറിയ അക്ഷരം","larger":"വലിയ അക്ഷരം","speed":"വേഗത","autoplay":"അടുത്ത അധ്യായം തനിയെ പ്ലേ ചെയ്യുക","autoclip":"അടുത്ത ക്ലിപ്പ് തനിയെ പ്ലേ ചെയ്യുക","listen":"ഈ അധ്യായം കേൾക്കുക","clips":"ക്ലിപ്പുകൾ","nowplaying":"ഇപ്പോൾ പ്ലേ ചെയ്യുന്നു","reflect":"ധ്യാനിക്കുക, പ്രതികരിക്കുക","share":"പങ്കിടുക","copylink":"ലിങ്ക് പകർത്തുക","copied":"ലിങ്ക് പകർത്തി","whatsapp":"WhatsApp","facebook":"Facebook","telegram":"Telegram","poweredby":"തിരുവെഴുത്ത് ഉള്ളടക്കം Bible Brain (Faith Comes By Hearing) നൽകിയത്","chapterlabel":"അധ്യായം","play":"പ്ലേ","pause":"നിർത്തുക","back":"15 സെക്കൻഡ് പിന്നോട്ട്","forward":"15 സെക്കൻഡ് മുന്നോട്ട്","seek":"സ്ഥാനം തിരഞ്ഞെടുക്കുക"},"prompts":["നിങ്ങൾ എന്താണ് കേട്ടത്? സ്വന്തം വാക്കുകളിൽ പറയുക.","ഈ ഭാഗം ദൈവത്തെക്കുറിച്ചും മനുഷ്യരെക്കുറിച്ചും എനിക്ക് എന്ത് കാണിച്ചുതരുന്നു?","ഞാൻ ഇതിനെക്കുറിച്ച് എന്ത് ചെയ്യും, ആരോട് പറയും?"]},"ne":{"rtl":false,"labels":{"country":"देश","language":"भाषा","version":"अनुवाद","recording":"संस्करण","book":"पुस्तक","chapter":"अध्याय","loading":"लोड हुँदैछ...","error":"केही गडबड भयो। कृपया फेरि प्रयास गर्नुहोस्।","noversions":"यो छनोटका लागि कुनै अनुवाद फेला परेन।","noconfig":"यो प्लेयरका लागि पूर्वनिर्धारित अनुवाद तोकिएको छैन।","prev":"अघिल्लो","next":"अर्को","smaller":"सानो अक्षर","larger":"ठूलो अक्षर","speed":"गति","autoplay":"अर्को अध्याय आफैँ बजाउनुहोस्","autoclip":"अर्को क्लिप आफैँ बजाउनुहोस्","listen":"यो अध्याय सुन्नुहोस्","clips":"क्लिपहरू","nowplaying":"अहिले बजिरहेको","reflect":"मनन गर्नुहोस् र प्रतिक्रिया दिनुहोस्","share":"सेयर गर्नुहोस्","copylink":"लिङ्क कपी गर्नुहोस्","copied":"लिङ्क कपी भयो","whatsapp":"WhatsApp","facebook":"Facebook","telegram":"Telegram","poweredby":"पवित्र शास्त्रको सामग्री Bible Brain (Faith Comes By Hearing) बाट","chapterlabel":"अध्याय","play":"बजाउनुहोस्","pause":"रोक्नुहोस्","back":"१५ सेकेन्ड पछाडि","forward":"१५ सेकेन्ड अगाडि","seek":"स्थान छान्नुहोस्"},"prompts":["तपाईंले के सुन्नुभयो? आफ्नै शब्दमा भन्नुहोस्।","यो खण्डले मलाई परमेश्वर र मानिसहरूबारे के देखाउँछ?","म यसबारे के गर्नेछु, र कसलाई बताउनेछु?"]},"si":{"rtl":false,"labels":{"country":"රට","language":"භාෂාව","version":"පරිවර්තනය","recording":"සංස්කරණය","book":"පොත","chapter":"පරිච්ඡේදය","loading":"පූරණය වෙමින්...","error":"යම් දෝෂයක් සිදුවිය. කරුණාකර නැවත උත්සාහ කරන්න.","noversions":"මෙම තේරීම සඳහා පරිවර්තන හමු නොවීය.","noconfig":"මෙම ප්ලේයරය සඳහා පෙරනිමි පරිවර්තනයක් සකසා නැත.","prev":"කලින්","next":"ඊළඟ","smaller":"කුඩා අකුරු","larger":"විශාල අකුරු","speed":"වේගය","autoplay":"ඊළඟ පරිච්ඡේදය ස්වයංක්‍රීයව වාදනය කරන්න","autoclip":"ඊළඟ ක්ලිපය ස්වයංක්‍රීයව වාදනය කරන්න","listen":"මෙම පරිච්ඡේදය අසන්න","clips":"ක්ලිප්","nowplaying":"දැන් වාදනය වෙමින්","reflect":"මෙනෙහි කර ප්‍රතිචාර දක්වන්න","share":"බෙදාගන්න","copylink":"සබැඳිය පිටපත් කරන්න","copied":"සබැඳිය පිටපත් විය","whatsapp":"WhatsApp","facebook":"Facebook","telegram":"Telegram","poweredby":"ශුද්ධ ලියවිලි අන්තර්ගතය Bible Brain (Faith Comes By Hearing) මගින්","chapterlabel":"පරිච්ඡේදය","play":"වාදනය","pause":"විරාමය","back":"තත්පර 15ක් ආපසු","forward":"තත්පර 15ක් ඉදිරියට","seek":"ස්ථානය තෝරන්න"},"prompts":["ඔබ ඇසුවේ කුමක්ද? ඔබේම වචනවලින් කියන්න.","මෙම කොටස දෙවියන් වහන්සේ ගැනත් මිනිසුන් ගැනත් මට පෙන්වන්නේ කුමක්ද?","මම මේ ගැන කුමක් කරන්නද, කාට පවසන්නද?"]},"ur":{"rtl":true,"labels":{"country":"ملک","language":"زبان","version":"ترجمہ","recording":"ایڈیشن","book":"کتاب","chapter":"باب","loading":"لوڈ ہو رہا ہے...","error":"کچھ غلط ہو گیا۔ براہِ کرم دوبارہ کوشش کریں۔","noversions":"اس انتخاب کے لیے کوئی ترجمہ نہیں ملا۔","noconfig":"اس پلیئر کے لیے کوئی طے شدہ ترجمہ مقرر نہیں کیا گیا۔","prev":"پچھلا","next":"اگلا","smaller":"چھوٹا متن","larger":"بڑا متن","speed":"رفتار","autoplay":"اگلا باب خود بخود چلائیں","autoclip":"اگلی کلپ خود بخود چلائیں","listen":"یہ باب سنیں","clips":"کلپس","nowplaying":"اب چل رہا ہے","reflect":"غور کریں اور جواب دیں","share":"شیئر کریں","copylink":"لنک کاپی کریں","copied":"لنک کاپی ہو گیا","whatsapp":"WhatsApp","facebook":"Facebook","telegram":"Telegram","poweredby":"کلامِ مقدس کا مواد Bible Brain (Faith Comes By Hearing) کی طرف سے","chapterlabel":"باب","play":"چلائیں","pause":"روکیں","back":"15 سیکنڈ پیچھے","forward":"15 سیکنڈ آگے","seek":"مقام منتخب کریں"},"prompts":["آپ نے کیا سنا؟ اپنے الفاظ میں بتائیں۔","یہ حصہ مجھے خدا اور انسانوں کے بارے میں کیا دکھاتا ہے؟","میں اس پر کیا عمل کروں گا، اور کسے بتاؤں گا؟"]}};
	/*BBP_LOCALES_END*/
	var T = {};
	function resetLabels(lang, overrides) {
		Object.keys(DEFAULT_I18N).forEach(function (k) { T[k] = DEFAULT_I18N[k]; });
		var code = String(lang || '').toLowerCase().split(/[-_]/)[0];
		var L = LOCALES[code] || null;
		if (L) { Object.keys(L.labels).forEach(function (k) { T[k] = L.labels[k]; }); }
		// Translations supplied by the site (gettext, Joomla labels) win over the bundled ones.
		[window.BBP_I18N, overrides].forEach(function (map) {
			Object.keys(map || {}).forEach(function (k) { if (map[k] && map[k] !== DEFAULT_I18N[k]) { T[k] = map[k]; } });
		});
		return L;
	}
	resetLabels('', null);
	function tr(k) { return T[k] || k; }

	var CANON = ('GEN EXO LEV NUM DEU JOS JDG RUT 1SA 2SA 1KI 2KI 1CH 2CH EZR NEH EST JOB PSA PRO ECC SNG ISA JER LAM EZK DAN HOS JOL AMO OBA JON MIC NAM HAB ZEP HAG ZEC MAL ' +
		'MAT MRK LUK JHN ACT ROM 1CO 2CO GAL EPH PHP COL 1TH 2TH 1TI 2TI TIT PHM HEB JAS 1PE 2PE 1JN 2JN 3JN JUD REV').split(' ');
	function isNT(id) { var i = CANON.indexOf(id); return i >= 39; }

	/* ------------------------------------------------------------------ */
	/* Small helpers                                                       */
	/* ------------------------------------------------------------------ */

	function h(tag, attrs, kids) {
		var n = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				var v = attrs[k];
				if (v === null || v === undefined || v === false) { return; }
				if (k === 'class') { n.className = v; }
				else if (k === 'text') { n.textContent = v; }
				else if (k.slice(0, 2) === 'on' && typeof v === 'function') { n.addEventListener(k.slice(2), v); }
				else { n.setAttribute(k, v === true ? '' : v); }
			});
		}
		(kids || []).forEach(function (c) {
			if (c) { n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); }
		});
		return n;
	}

	var store = {
		get: function (k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } },
		set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } }
	};

	var memo = {};
	function memoGet(base, key, fn) {
		key = base + '|' + key; /* scope the cache to one endpoint */
		if (!memo[key]) { memo[key] = fn().catch(function (e) { delete memo[key]; throw e; }); }
		return memo[key];
	}

	function apiGet(base, path, params) {
		var qs = Object.keys(params || {}).filter(function (k) { return params[k] !== '' && params[k] != null; })
			.map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
		var url = base + path + (qs ? (base.indexOf('?') > -1 ? '&' : '?') + qs : '');
		return fetch(url, { credentials: 'omit', headers: { 'Accept': 'application/json' } }).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) { throw new Error(j && j.message ? j.message : tr('error')); }
				return j;
			});
		});
	}

	function fmtTime(s) {
		if (!isFinite(s) || s < 0) { return '0:00'; }
		var m = Math.floor(s / 60), x = Math.floor(s % 60);
		return m + ':' + (x < 10 ? '0' : '') + x;
	}

	function isRtl(sample) {
		return /[\u0590-\u08FF\u0700-\u074F\u0780-\u07BF\uFB1D-\uFDFF\uFE70-\uFEFF]/.test(sample || '');
	}

	function attachSource(media, url) {
		if (media._hls) { try { media._hls.destroy(); } catch (e) { /* ignore */ } media._hls = null; }
		var isHls = /\.m3u8(\?|$)/i.test(url);
		if (isHls && !media.canPlayType('application/vnd.apple.mpegurl') && window.Hls && window.Hls.isSupported()) {
			var hls = new window.Hls();
			hls.loadSource(url);
			hls.attachMedia(media);
			media._hls = hls;
		} else {
			media.src = url;
		}
	}

	function setMediaSession(title, artist, handlers) {
		if (!('mediaSession' in navigator) || typeof window.MediaMetadata !== 'function') { return; }
		try {
			navigator.mediaSession.metadata = new window.MediaMetadata({ title: title, artist: artist });
			['previoustrack', 'nexttrack'].forEach(function (a) {
				navigator.mediaSession.setActionHandler(a, handlers[a] || null);
			});
		} catch (e) { /* unsupported */ }
	}

	function cover(size, nt) {
		size = (size || '').toUpperCase();
		if (size === 'C') { return 1; }
		if (size === 'NT' || size === 'NTP') { return nt ? 0 : 9; }
		if (size === 'OT' || size === 'OTP') { return nt ? 9 : 0; }
		return 5;
	}

	/* ------------------------------------------------------------------ */
	/* Audio controls (big play button, 15 second skips, speed)            */
	/* ------------------------------------------------------------------ */

	function audioUI(audio) {
		var rates = [1, 1.25, 1.5, 0.75];
		var ri = Math.max(0, rates.indexOf(store.get('bbp_rate') || 1));
		var dragging = false;

		var play = h('button', { type: 'button', class: 'bbp-play', 'data-state': 'play', 'aria-label': tr('play') });
		var back = h('button', { type: 'button', class: 'bbp-skip', 'aria-label': tr('back'), text: '-15' });
		var fwd = h('button', { type: 'button', class: 'bbp-skip', 'aria-label': tr('forward'), text: '+15' });
		var seek = h('input', { type: 'range', class: 'bbp-seek', min: '0', max: '1000', value: '0', step: '1', 'aria-label': tr('seek') });
		var cur = h('span', { class: 'bbp-time', text: '0:00' });
		var dur = h('span', { class: 'bbp-time', text: '0:00' });
		var spd = h('button', { type: 'button', class: 'bbp-speed', 'aria-label': tr('speed') });

		function applyRate() {
			var r = rates[ri];
			audio.defaultPlaybackRate = r;
			audio.playbackRate = r;
			spd.textContent = r + 'x';
		}
		applyRate();

		play.addEventListener('click', function () {
			if (audio.paused) { var p = audio.play(); if (p && p.catch) { p.catch(function () {}); } } else { audio.pause(); }
		});
		back.addEventListener('click', function () { audio.currentTime = Math.max(0, audio.currentTime - 15); });
		fwd.addEventListener('click', function () { audio.currentTime = Math.min(audio.duration || 1e9, audio.currentTime + 15); });
		spd.addEventListener('click', function () {
			ri = (ri + 1) % rates.length;
			store.set('bbp_rate', rates[ri]);
			applyRate();
		});
		seek.addEventListener('input', function () {
			dragging = true;
			if (isFinite(audio.duration)) { audio.currentTime = (seek.value / 1000) * audio.duration; }
		});
		seek.addEventListener('change', function () { dragging = false; });

		audio.addEventListener('play', function () { play.setAttribute('data-state', 'pause'); play.setAttribute('aria-label', tr('pause')); });
		audio.addEventListener('pause', function () { play.setAttribute('data-state', 'play'); play.setAttribute('aria-label', tr('play')); });
		audio.addEventListener('loadedmetadata', function () { applyRate(); dur.textContent = fmtTime(audio.duration); });
		audio.addEventListener('durationchange', function () { dur.textContent = fmtTime(audio.duration); });
		audio.addEventListener('timeupdate', function () {
			cur.textContent = fmtTime(audio.currentTime);
			if (!dragging && isFinite(audio.duration) && audio.duration > 0) {
				seek.value = String(Math.round((audio.currentTime / audio.duration) * 1000));
			}
		});

		var el = h('div', { class: 'bbp-audio-ui' }, [
			h('div', { class: 'bbp-transport' }, [back, play, fwd, spd]),
			h('div', { class: 'bbp-scrub' }, [cur, seek, dur])
		]);
		return { el: el };
	}

	/* ------------------------------------------------------------------ */
	/* Player                                                              */
	/* ------------------------------------------------------------------ */

	function Player(root, cfg) {
		var type = cfg.type;
		var media = type === 'reader' ? 'text' : type;
		var param = 'bbp-' + type + (cfg.uid > 1 ? '-' + cfg.uid : '');
		var S = { country: '', language: '', bible: '', fileset: null, book: '', chapter: 0 };
		var langs = [];
		var view = null;
		var shareCtl = null;
		var reflectEl = null;
		var firstLoad = true;

		/* ---- initial wishes: shortcode/defaults < remembered choice < shared link ---- */
		function parseUrl() {
			try {
				var v = new URL(window.location.href).searchParams.get(param);
				if (!v) { return null; }
				var p = v.split('.');
				if (p.length < 3) { return null; }
				return { country: p[0].toUpperCase(), bible: p[1], fileset: p[2], book: (p[3] || '').toUpperCase(), chapter: parseInt(p[4], 10) || 0 };
			} catch (e) { return null; }
		}
		var want = { country: cfg.country, language: cfg.language, bible: cfg.bible, fileset: cfg.fileset, book: cfg.book, chapter: cfg.chapter };
		var saved = cfg.selector ? store.get('bbp_sel_' + media) : null;
		var fromUrl = parseUrl();
		if (fromUrl && !cfg.selector && cfg.bible && fromUrl.bible !== cfg.bible) { fromUrl = null; }
		[saved, fromUrl].forEach(function (src) {
			if (!src) { return; }
			Object.keys(src).forEach(function (k) { if (src[k]) { want[k] = src[k]; } });
		});

		/* ---- frame ---- */
		root.innerHTML = '';
		root.classList.add('bbp-ready');
		var elSel = h('div', { class: 'bbp-selector' });
		var elStatus = h('div', { class: 'bbp-status', role: 'status', 'aria-live': 'polite' });
		var elBody = h('div', { class: 'bbp-body' });
		var elCopy = h('p', { class: 'bbp-copy' });
		var elFoot = h('div', { class: 'bbp-foot' }, [elCopy, h('p', { class: 'bbp-credit', text: tr('poweredby') })]);
		if (cfg.title) { root.appendChild(h('div', { class: 'bbp-title', text: cfg.title })); }
		root.appendChild(elSel);
		root.appendChild(elStatus);
		root.appendChild(elBody);
		root.appendChild(elFoot);

		function field(label) {
			var s = h('select', { 'aria-label': label });
			return { sel: s, wrap: h('label', { class: 'bbp-field' }, [h('span', { text: label }), s]) };
		}
		var fC = field(tr('country')), fL = field(tr('language')), fV = field(tr('version')), fR = field(tr('recording'));
		[fC, fL, fV, fR].forEach(function (f) { elSel.appendChild(f.wrap); });
		fR.wrap.hidden = true;
		if (!cfg.selector) { elSel.hidden = true; }

		function status(msg, bad) {
			elStatus.textContent = msg || '';
			elStatus.className = 'bbp-status' + (bad ? ' is-error' : '');
		}
		function showError(e) { status(e && e.message ? e.message : tr('error'), true); }

		function setOpts(sel, items, val) {
			sel.innerHTML = '';
			items.forEach(function (it) { sel.appendChild(h('option', { value: it.value, text: it.label })); });
			var ok = val && items.some(function (it) { return it.value === val; });
			if (ok) { sel.value = val; } else if (items.length) { sel.value = items[0].value; }
		}

		function track(name, extra) {
			var d = { event: 'bbp_' + name, bbp_player: type, bbp_country: S.country, bbp_bible: S.bible,
				bbp_fileset: S.fileset ? S.fileset.id : '', bbp_book: S.book, bbp_chapter: S.chapter };
			if (extra) { Object.keys(extra).forEach(function (k) { d[k] = extra[k]; }); }
			try { if (window.dataLayer && window.dataLayer.push) { window.dataLayer.push(d); } } catch (e) { /* ignore */ }
			try { document.dispatchEvent(new CustomEvent('bbp:event', { detail: d })); } catch (e2) { /* ignore */ }
		}

		/* ---- links people can share ---- */
		function shareUrl() {
			var u = new URL(window.location.href);
			u.searchParams.set(param, [S.country, S.bible, S.fileset ? S.fileset.id : '', S.book || '', S.chapter || ''].join('.'));
			return u.toString();
		}
		function updateUrl() { try { window.history.replaceState(null, '', shareUrl()); } catch (e) { /* ignore */ } }

		function curLang() { return langs.filter(function (l) { return l.id === fL.sel.value; })[0]; }
		function curVersion() {
			var L = curLang();
			return L ? L.versions.filter(function (v) { return v.abbr === fV.sel.value; })[0] : null;
		}
		function versionName() { var v = curVersion(); return v ? v.name : S.bible; }

		function buildShare() {
			if (!cfg.share) { return null; }
			var links = {};
			var box = h('div', { class: 'bbp-share' }, [h('span', { class: 'bbp-share-label', text: tr('share') })]);
			[['whatsapp', tr('whatsapp')], ['telegram', tr('telegram')], ['facebook', tr('facebook')]].forEach(function (p) {
				links[p[0]] = h('a', { class: 'bbp-btn bbp-btn-quiet', target: '_blank', rel: 'noopener noreferrer', text: p[1], href: '#' });
				links[p[0]].addEventListener('click', function () { track('share', { bbp_channel: p[0] }); });
				box.appendChild(links[p[0]]);
			});
			var copy = h('button', { type: 'button', class: 'bbp-btn bbp-btn-quiet', text: tr('copylink') });
			copy.addEventListener('click', function () {
				var u = shareUrl();
				track('share', { bbp_channel: 'copy' });
				var done = function () { copy.textContent = tr('copied'); setTimeout(function () { copy.textContent = tr('copylink'); }, 2000); };
				if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(u).then(done, function () { window.prompt(tr('copylink'), u); }); }
				else { window.prompt(tr('copylink'), u); }
			});
			box.appendChild(copy);
			return {
				el: box,
				refresh: function (title) {
					var u = encodeURIComponent(shareUrl());
					var tx = encodeURIComponent(title || '');
					links.whatsapp.href = 'https://wa.me/?text=' + tx + '%20' + u;
					links.telegram.href = 'https://t.me/share/url?url=' + u + '&text=' + tx;
					links.facebook.href = 'https://www.facebook.com/sharer/sharer.php?u=' + u;
				}
			};
		}

		function buildReflect() {
			if (!cfg.reflect || !cfg.prompts || !cfg.prompts.length) { return null; }
			var ol = h('ol', { class: 'bbp-prompts' }, cfg.prompts.map(function (p) { return h('li', { text: p }); }));
			var d = h('details', { class: 'bbp-reflect' }, [h('summary', { text: tr('reflect') }), ol]);
			d.addEventListener('toggle', function () { if (d.open) { track('reflect_open'); } });
			return d;
		}
		function openReflect() { if (reflectEl) { reflectEl.open = true; } }

		function loadCopyright() {
			var abbr = S.bible;
			elCopy.textContent = '';
			memoGet(cfg.rest, 'copy|' + abbr, function () { return apiGet(cfg.rest, 'copyright', { bible: abbr }); })
				.then(function (r) { if (abbr === S.bible) { elCopy.textContent = r.copyright || ''; } })
				.catch(function () { /* optional */ });
		}

		/* ---- book and chapter navigation (reader + audio) ---- */
		function BookNav(onChange) {
			var nav = { books: [], book: '', chapter: 0 };
			var selBook = h('select', { class: 'bbp-book', 'aria-label': tr('book') });
			var chips = h('div', { class: 'bbp-chips', role: 'group', 'aria-label': tr('chapter') });
			nav.el = h('div', { class: 'bbp-nav' }, [h('label', { class: 'bbp-field bbp-field-book' }, [h('span', { text: tr('book') }), selBook]), chips]);

			function curBook() { return nav.books.filter(function (b) { return b.id === nav.book; })[0]; }
			function renderChips() {
				chips.innerHTML = '';
				var b = curBook();
				if (!b) { return; }
				b.chapters.forEach(function (c) {
					chips.appendChild(h('button', {
						type: 'button', class: 'bbp-chip' + (c === nav.chapter ? ' is-on' : ''), text: String(c),
						'aria-label': tr('chapterlabel') + ' ' + c, 'aria-current': c === nav.chapter ? 'true' : null,
						onclick: function () { nav.chapter = c; nav.fire(true); }
					}));
				});
				var on = chips.querySelector('.is-on');
				if (on) { chips.scrollLeft = on.offsetLeft - chips.clientWidth / 2 + on.offsetWidth / 2; }
			}
			nav.fire = function (user) { selBook.value = nav.book; renderChips(); onChange(user); };
			selBook.addEventListener('change', function () {
				nav.book = selBook.value;
				var b = curBook();
				nav.chapter = b ? b.chapters[0] : 0;
				nav.fire(true);
			});
			nav.setBooks = function (books, pref) {
				nav.books = books;
				setOpts(selBook, books.map(function (b) { return { value: b.id, label: b.name }; }), nav.book);
				var wantBook = books.some(function (b) { return b.id === nav.book; }) ? nav.book : (pref.book && books.some(function (b) { return b.id === pref.book; }) ? pref.book : books[0].id);
				var changed = wantBook !== nav.book;
				nav.book = wantBook;
				var b = curBook();
				var ch = (!changed && b.chapters.indexOf(nav.chapter) > -1) ? nav.chapter : ((pref.chapter && b.chapters.indexOf(pref.chapter) > -1 && pref.book === wantBook) ? pref.chapter : b.chapters[0]);
				nav.chapter = ch;
				selBook.value = nav.book;
				renderChips();
			};
			function step(dir) {
				var b = curBook();
				if (!b) { return false; }
				var i = b.chapters.indexOf(nav.chapter) + dir;
				if (i >= 0 && i < b.chapters.length) { nav.chapter = b.chapters[i]; return true; }
				var bi = nav.books.indexOf(b) + dir;
				if (bi < 0 || bi >= nav.books.length) { return false; }
				var nb = nav.books[bi];
				nav.book = nb.id;
				nav.chapter = dir > 0 ? nb.chapters[0] : nb.chapters[nb.chapters.length - 1];
				return true;
			}
			nav.next = function () { if (step(1)) { nav.fire(true); return true; } return false; };
			nav.prev = function () { if (step(-1)) { nav.fire(true); return true; } return false; };
			nav.label = function () { var b = curBook(); return (b ? b.name : nav.book) + ' ' + nav.chapter; };
			return nav;
		}

		function loadBooks() {
			var f = S.fileset;
			var key = 'books|' + f.id + '|' + f.type;
			return memoGet(cfg.rest, key, function () {
				return apiGet(cfg.rest, 'books', { fileset: f.id, type: f.type, asset: f.asset, bible: S.bible, size: f.size });
			}).then(function (r) { return r.books || []; });
		}

		function navRow(nav, extra) {
			var prev = h('button', { type: 'button', class: 'bbp-btn bbp-btn-quiet', text: tr('prev'), onclick: function () { nav.prev(); } });
			var next = h('button', { type: 'button', class: 'bbp-btn bbp-btn-quiet', text: tr('next'), onclick: function () { nav.next(); } });
			return h('div', { class: 'bbp-row' }, [prev, next].concat(extra || []));
		}

		/* ================================================================ */
		/* Reader                                                            */
		/* ================================================================ */
		function ReaderView() {
			var fs = store.get('bbp_fs') || 100;
			var token = 0;
			var headEl = h('h3', { class: 'bbp-passage' });
			var textEl = h('div', { class: 'bbp-text', lang: '' });
			var listenEl = h('div', { class: 'bbp-listen' });
			var autoOn = true, pendingPlay = false, lAudio = null, times = [], verseEls = {};
			var nav = BookNav(function (user) { load(user); });

			function applyFs() { textEl.style.fontSize = fs + '%'; store.set('bbp_fs', fs); }
			var smaller = h('button', { type: 'button', class: 'bbp-btn bbp-btn-quiet', 'aria-label': tr('smaller'), text: 'A-', onclick: function () { fs = Math.max(80, fs - 10); applyFs(); } });
			var larger = h('button', { type: 'button', class: 'bbp-btn bbp-btn-quiet', 'aria-label': tr('larger'), text: 'A+', onclick: function () { fs = Math.min(220, fs + 10); applyFs(); } });
			applyFs();

			reflectEl = buildReflect();
			shareCtl = buildShare();
			elBody.innerHTML = '';
			[nav.el, navRow(nav, [smaller, larger]), listenEl, headEl, textEl, reflectEl, shareCtl ? shareCtl.el : null].forEach(function (n) { if (n) { elBody.appendChild(n); } });

			function stopAudio() {
				if (lAudio) { try { lAudio.pause(); } catch (e) { /* ignore */ } if (lAudio._hls) { lAudio._hls.destroy(); } lAudio = null; }
				times = [];
			}

			function highlight() {
				if (!lAudio || !times.length) { return; }
				var t = lAudio.currentTime, v = null, i;
				for (i = 0; i < times.length; i++) { if (times[i].t <= t + 0.15) { v = times[i].v; } else { break; } }
				Object.keys(verseEls).forEach(function (k) { verseEls[k].classList.toggle('is-now', k === v); });
			}

			function buildListen(urls, f, tok) {
				if (tok !== token) { return; }
				lAudio = new Audio();
				lAudio.preload = 'none';
				var ui = audioUI(lAudio);
				var chk = h('input', { type: 'checkbox' });
				chk.checked = autoOn;
				chk.addEventListener('change', function () { autoOn = chk.checked; });
				listenEl.innerHTML = '';
				listenEl.appendChild(h('div', { class: 'bbp-listen-head', text: tr('listen') }));
				listenEl.appendChild(ui.el);
				listenEl.appendChild(h('label', { class: 'bbp-check' }, [chk, h('span', { text: tr('autoplay') })]));
				attachSource(lAudio, urls[0]);
				lAudio.addEventListener('play', function () { track('play', { bbp_mode: 'listen_read' }); });
				lAudio.addEventListener('timeupdate', highlight);
				lAudio.addEventListener('ended', function () {
					track('complete', { bbp_mode: 'listen_read' });
					openReflect();
					if (autoOn && nav.next()) { pendingPlay = true; }
				});
				if (pendingPlay) { pendingPlay = false; var p = lAudio.play(); if (p && p.catch) { p.catch(function () {}); } }
				apiGet(cfg.rest, 'timestamps', { fileset: f.id, book: S.book, chapter: S.chapter }).then(function (r) {
					if (tok === token) { times = r.times || []; }
				}).catch(function () { /* karaoke is optional */ });
			}

			function setupListen(tok) {
				stopAudio();
				listenEl.innerHTML = '';
				if (!cfg.listen) { return; }
				getCatalog(S.country, 'audio').then(function (list) {
					var cands = [];
					list.forEach(function (l) { l.versions.forEach(function (v) { if (v.abbr === S.bible) { cands = v.filesets; } }); });
					var nt = isNT(S.book);
					cands = cands.filter(function (f) { return cover(f.size, nt) < 9; })
						.sort(function (a, b) { return cover(a.size, nt) - cover(b.size, nt); }).slice(0, 3);
					var tryNext = function (i) {
						if (i >= cands.length || tok !== token) { return; }
						var f = cands[i];
						apiGet(cfg.rest, 'audio', { fileset: f.id, book: S.book, chapter: S.chapter, type: f.type, asset: f.asset })
							.then(function (r) { if (r.urls && r.urls.length) { buildListen(r.urls, f, tok); } else { tryNext(i + 1); } })
							.catch(function () { tryNext(i + 1); });
					};
					tryNext(0);
				}).catch(function () { /* listening is a bonus */ });
			}

			function render(r) {
				textEl.innerHTML = '';
				verseEls = {};
				var all = '';
				(r.verses || []).forEach(function (v) {
					var span = h('span', { class: 'bbp-v', 'data-v': v.n }, [h('sup', { class: 'bbp-vn', text: v.n }), document.createTextNode(v.text + ' ')]);
					span.addEventListener('click', function () {
						if (!lAudio || !times.length) { return; }
						var hit = times.filter(function (x) { return x.v === v.n; })[0];
						if (hit) { lAudio.currentTime = hit.t; var p = lAudio.play(); if (p && p.catch) { p.catch(function () {}); } }
					});
					verseEls[v.n] = span;
					textEl.appendChild(span);
					all += v.text;
				});
				var rtl = isRtl(all);
				textEl.setAttribute('dir', rtl ? 'rtl' : 'ltr');
				textEl.setAttribute('lang', S.language || '');
				textEl.classList.toggle('is-rtl', rtl);
			}

			function load(user) {
				var tok = ++token;
				S.book = nav.book; S.chapter = nav.chapter;
				status(tr('loading'));
				headEl.textContent = nav.label() + ' - ' + versionName();
				return apiGet(cfg.rest, 'text', { fileset: S.fileset.id, book: S.book, chapter: S.chapter, type: S.fileset.type })
					.then(function (r) {
						if (tok !== token) { return; }
						status('');
						render(r);
						headEl.textContent = (r.book_name || nav.label()) + ' ' + S.chapter + ' - ' + versionName();
						updateUrl();
						if (shareCtl) { shareCtl.refresh(headEl.textContent); }
						track(user ? 'chapter_select' : 'chapter_view');
						setupListen(tok);
					})
					.catch(function (e) { if (tok === token) { showError(e); } });
			}

			return {
				select: function (pref) {
					status(tr('loading'));
					return loadBooks().then(function (books) {
						if (!books.length) { throw new Error(tr('noversions')); }
						nav.setBooks(books, pref);
						return load(false);
					});
				},
				stop: function () { stopAudio(); }
			};
		}

		/* ================================================================ */
		/* Audio                                                             */
		/* ================================================================ */
		function AudioView() {
			var audio = new Audio();
			audio.preload = 'none';
			var ui = audioUI(audio);
			var token = 0, urls = [], qi = 0, retried = false, autoOn = true;
			var nowEl = h('div', { class: 'bbp-now' });
			var nav = BookNav(function (user) { load(user); });
			var chk = h('input', { type: 'checkbox' });
			chk.checked = true;
			chk.addEventListener('change', function () { autoOn = chk.checked; });

			var card = h('div', { class: 'bbp-player' }, [h('div', { class: 'bbp-now-label', text: tr('nowplaying') }), nowEl, ui.el]);
			reflectEl = buildReflect();
			shareCtl = buildShare();
			elBody.innerHTML = '';
			[nav.el, card, navRow(nav), h('label', { class: 'bbp-check' }, [chk, h('span', { text: tr('autoplay') })]), reflectEl, shareCtl ? shareCtl.el : null]
				.forEach(function (n) { if (n) { elBody.appendChild(n); } });

			function playNow() { var p = audio.play(); if (p && p.catch) { p.catch(function () {}); } }

			function load(autoplay) {
				var tok = ++token;
				S.book = nav.book; S.chapter = nav.chapter;
				var f = S.fileset;
				status(tr('loading'));
				nowEl.textContent = nav.label() + ' - ' + versionName();
				return apiGet(cfg.rest, 'audio', { fileset: f.id, book: S.book, chapter: S.chapter, type: f.type, asset: f.asset })
					.then(function (r) {
						if (tok !== token) { return; }
						status('');
						urls = r.urls || []; qi = 0;
						attachSource(audio, urls[0]);
						if (autoplay) { playNow(); }
						updateUrl();
						var title = nav.label() + ' - ' + versionName();
						if (shareCtl) { shareCtl.refresh(title); }
						setMediaSession(nav.label(), versionName(), {
							nexttrack: function () { nav.next(); },
							previoustrack: function () { nav.prev(); }
						});
						track(autoplay ? 'chapter_select' : 'chapter_view');
					})
					.catch(function (e) { if (tok === token) { showError(e); } });
			}

			audio.addEventListener('play', function () { track('play'); });
			audio.addEventListener('ended', function () {
				if (qi < urls.length - 1) { qi++; attachSource(audio, urls[qi]); playNow(); return; }
				track('complete');
				openReflect();
				if (autoOn) { nav.next(); }
			});
			audio.addEventListener('error', function () {
				// Signed links expire; fetch a fresh one once.
				if (!retried && S.fileset) { retried = true; var was = !audio.paused; memo = {}; load(was); }
			});
			audio.addEventListener('playing', function () { retried = false; });

			return {
				select: function (pref) {
					status(tr('loading'));
					return loadBooks().then(function (books) {
						if (!books.length) { throw new Error(tr('noversions')); }
						nav.setBooks(books, pref);
						return load(false);
					});
				},
				stop: function () { audio.pause(); }
			};
		}

		/* ================================================================ */
		/* Video                                                             */
		/* ================================================================ */
		function VideoView() {
			var clips = [], idx = -1, autoOn = true, retried = false;
			var vid = h('video', { class: 'bbp-vid', controls: true, playsinline: true, preload: 'metadata' });
			var nowEl = h('div', { class: 'bbp-now' });
			var grid = h('div', { class: 'bbp-clips' });
			var bookSel = h('select', { class: 'bbp-book', 'aria-label': tr('book') });
			var bookWrap = h('label', { class: 'bbp-field bbp-field-book' }, [h('span', { text: tr('book') }), bookSel]);
			var chk = h('input', { type: 'checkbox' });
			chk.checked = true;
			chk.addEventListener('change', function () { autoOn = chk.checked; });
			reflectEl = buildReflect();
			shareCtl = buildShare();
			elBody.innerHTML = '';
			[h('div', { class: 'bbp-stage' }, [vid]), nowEl, bookWrap, h('div', { class: 'bbp-clips-head', text: tr('clips') }), grid,
				h('label', { class: 'bbp-check' }, [chk, h('span', { text: tr('autoclip') })]), reflectEl, shareCtl ? shareCtl.el : null]
				.forEach(function (n) { if (n) { elBody.appendChild(n); } });

			function clipLabel(c, i) {
				if (c.title) { return c.title; }
				if (c.book_name) {
					var r = c.book_name + (c.chapter ? ' ' + c.chapter : '');
					if (c.vs) { r += ':' + c.vs + (c.ve && c.ve !== c.vs ? '-' + c.ve : ''); }
					return r;
				}
				return tr('clips') + ' ' + (i + 1);
			}

			function renderGrid() {
				grid.innerHTML = '';
				clips.forEach(function (c, i) {
					if (bookSel.value && c.book && c.book !== bookSel.value) { return; }
					var kids = [];
					if (c.thumb) { kids.push(h('img', { src: c.thumb, alt: '', loading: 'lazy' })); }
					kids.push(h('span', { class: 'bbp-clip-title', text: clipLabel(c, i) }));
					if (c.duration) { kids.push(h('span', { class: 'bbp-clip-dur', text: fmtTime(c.duration) })); }
					grid.appendChild(h('button', {
						type: 'button', class: 'bbp-clip' + (i === idx ? ' is-on' : ''), 'data-i': String(i),
						onclick: function () { playClip(i, true); }
					}, kids));
				});
			}

			function playClip(i, autoplay) {
				var c = clips[i];
				if (!c) { return; }
				idx = i;
				S.book = c.book || ''; S.chapter = i + 1;
				vid.poster = c.thumb || '';
				attachSource(vid, c.url);
				if (autoplay) { var p = vid.play(); if (p && p.catch) { p.catch(function () {}); } }
				var label = clipLabel(c, i) + ' - ' + versionName();
				nowEl.textContent = label;
				Array.prototype.forEach.call(grid.children, function (b) { b.classList.toggle('is-on', b.getAttribute('data-i') === String(i)); });
				updateUrl();
				if (shareCtl) { shareCtl.refresh(label); }
				track(autoplay ? 'clip_select' : 'clip_view');
			}

			vid.addEventListener('play', function () { track('play'); });
			vid.addEventListener('ended', function () {
				track('complete');
				openReflect();
				if (autoOn && idx + 1 < clips.length) { playClip(idx + 1, true); }
			});
			vid.addEventListener('error', function () {
				if (retried || idx < 0) { return; }
				retried = true;
				var keep = idx, was = !vid.paused;
				memo = {};
				fetchClips().then(function () { playClip(keep, was); }).catch(function () {});
			});
			vid.addEventListener('playing', function () { retried = false; });
			bookSel.addEventListener('change', function () {
				renderGrid();
				var first = -1;
				clips.some(function (c, i) { if (c.book === bookSel.value) { first = i; return true; } return false; });
				if (first > -1) { playClip(first, false); }
			});

			function fetchClips() {
				var f = S.fileset;
				return apiGet(cfg.rest, 'video', { fileset: f.id, asset: f.asset }).then(function (r) { clips = r.clips || []; });
			}

			return {
				select: function (pref) {
					status(tr('loading'));
					return fetchClips().then(function () {
						if (!clips.length) { throw new Error(tr('noversions')); }
						status('');
						var books = [], seen = {};
						clips.forEach(function (c) { if (c.book && !seen[c.book]) { seen[c.book] = 1; books.push({ value: c.book, label: c.book_name || c.book }); } });
						setOpts(bookSel, books, pref.book || S.book);
						bookWrap.hidden = books.length < 2;
						var start = pref.chapter && clips[pref.chapter - 1] ? pref.chapter - 1 : -1;
						if (start > -1 && clips[start].book) { bookSel.value = clips[start].book; }
						if (start < 0) {
							clips.some(function (c, i) { if (!bookSel.value || c.book === bookSel.value) { start = i; return true; } return false; });
						}
						idx = -1;
						renderGrid();
						playClip(Math.max(0, start), false);
					});
				},
				stop: function () { vid.pause(); }
			};
		}

		/* ---- catalog access ---- */
		function getCountries() { return memoGet(cfg.rest, 'countries', function () { return apiGet(cfg.rest, 'countries'); }).then(function (r) { return r.countries || []; }); }
		function getCatalog(country, m) {
			return memoGet(cfg.rest, 'cat|' + country + '|' + m, function () { return apiGet(cfg.rest, 'catalog', { country: country, media: m }); })
				.then(function (r) { return r.languages || []; });
		}

		/* ---- cascade: country > language > version > edition ---- */
		function commit(pref) {
			var V = curVersion();
			if (!V) { throw new Error(tr('noversions')); }
			elBody.hidden = false;
			S.language = fL.sel.value;
			S.bible = V.abbr;
			S.fileset = V.filesets.filter(function (f) { return f.id === fR.sel.value; })[0] || V.filesets[0];
			if (cfg.selector) { store.set('bbp_sel_' + media, { country: S.country, language: S.language, bible: S.bible, fileset: S.fileset.id }); }
			if (view) { view.stop(); }
			loadCopyright();
			track(firstLoad ? 'ready' : 'version_select');
			return view.select(pref).then(function () { firstLoad = false; want = {}; });
		}
		function loadVersion(pref) {
			var V = curVersion();
			if (!V) { throw new Error(tr('noversions')); }
			var items = V.filesets.map(function (f) { return { value: f.id, label: f.label }; });
			setOpts(fR.sel, items, pref.fileset);
			fR.wrap.hidden = items.length < 2;
			return commit(pref);
		}
		function loadLanguage(pref) {
			var L = curLang();
			if (!L) { throw new Error(tr('noversions')); }
			setOpts(fV.sel, L.versions.map(function (v) { return { value: v.abbr, label: v.name }; }), pref.bible);
			return loadVersion(pref);
		}
		function loadCountry(pref) {
			status(tr('loading'));
			return getCatalog(S.country, media).then(function (list) {
				langs = list;
				if (!langs.length) { if (view) { view.stop(); } elBody.hidden = true; elCopy.textContent = ''; setOpts(fL.sel, [], ''); setOpts(fV.sel, [], ''); setOpts(fR.sel, [], ''); fR.wrap.hidden = true; throw new Error(tr('noversions')); }
				var lang = pref.language;
				if (pref.bible) {
					langs.forEach(function (l) { if (l.versions.some(function (v) { return v.abbr === pref.bible; })) { lang = l.id; } });
				}
				setOpts(fL.sel, langs.map(function (l) { return { value: l.id, label: l.name }; }), lang);
				return loadLanguage(pref);
			});
		}

		fC.sel.addEventListener('change', function () { S.country = fC.sel.value; loadCountry({}).catch(showError); });
		fL.sel.addEventListener('change', function () { Promise.resolve().then(function () { return loadLanguage({}); }).catch(showError); });
		fV.sel.addEventListener('change', function () { Promise.resolve().then(function () { return loadVersion({}); }).catch(showError); });
		fR.sel.addEventListener('change', function () { Promise.resolve().then(function () { return commit({}); }).catch(showError); });

		view = type === 'reader' ? ReaderView() : (type === 'audio' ? AudioView() : VideoView());

		/* ---- go ---- */
		status(tr('loading'));
		var first = cfg.selector ? getCountries() : Promise.resolve([]);
		first.then(function (countries) {
			if (cfg.selector) {
				if (!countries.length) { throw new Error(tr('noversions')); }
				var items = countries.map(function (c) { return { value: c.code, label: c.name }; });
				setOpts(fC.sel, items, want.country);
				S.country = fC.sel.value;
			} else {
				S.country = want.country;
			}
			if (!S.country) { throw new Error(tr('noconfig')); }
			return loadCountry(want);
		}).catch(showError);
	}

	/* ------------------------------------------------------------------ */
	/* Boot: reads data-bbp (JSON, used by the CMS modules) or simple data- attributes (HTML5 embed) */
	/* ------------------------------------------------------------------ */
	var uidCount = { reader: 0, audio: 0, video: 0 };

	function truthy(v, d) {
		if (v === undefined || v === null || v === '') { return d; }
		return ['1', 'yes', 'true', 'on'].indexOf(String(v).toLowerCase()) > -1;
	}

	function cfgFromDataset(root) {
		var d = root.dataset || {};
		var m = /bbp-(reader|audio|video)/.exec(root.className || '');
		var type = d.type || (m ? m[1] : '');
		var rest = d.rest || REST_DEFAULT;
		if (!type || !uidCount.hasOwnProperty(type) || !rest) { return null; }
		return {
			type: type, uid: ++uidCount[type], rest: rest,
			country: (d.country || '').toUpperCase(), language: (d.language || '').toLowerCase(),
			bible: d.bible || '', fileset: d.fileset || '', book: (d.book || '').toUpperCase(), chapter: parseInt(d.chapter, 10) || 0,
			selector: truthy(d.selector, true), share: truthy(d.share, true), reflect: truthy(d.reflect, true),
			listen: type === 'reader' ? truthy(d.listen, true) : false,
			prompts: d.prompts ? d.prompts.split('|') : DEFAULT_PROMPTS, title: d.title || ''
		};
	}

	function mount(root) {
		if (!root || root.classList.contains('bbp-ready')) { return; }
		var cfg = null;
		var raw = root.getAttribute('data-bbp');
		if (raw) {
			try { cfg = JSON.parse(raw); } catch (e) { return; }
		} else {
			cfg = cfgFromDataset(root);
		}
		if (!cfg) { return; }
		// Interface language: explicit data-lang / cfg.lang, otherwise the page language (<html lang>).
		var lang = cfg.lang || (root.dataset && root.dataset.lang) || document.documentElement.lang || '';
		var locale = resetLabels(lang, cfg.i18n);
		if (locale) {
			if (locale.rtl) { root.setAttribute('dir', 'rtl'); }
			var current = (cfg.prompts || []).join('|');
			if (!cfg.prompts || !cfg.prompts.length || current === DEFAULT_PROMPTS.join('|')) { cfg.prompts = locale.prompts; }
		}
		if (!root.classList.contains('bbp')) { root.classList.add('bbp'); }
		if (!root.getAttribute('data-theme')) { root.setAttribute('data-theme', 'auto'); }
		if (root.dataset && root.dataset.accent) { root.style.setProperty('--bbp-accent', root.dataset.accent); }
		Player(root, cfg);
	}

	function boot() {
		var nodes = document.querySelectorAll('.bbp[data-bbp], .bbp[data-type], .bbp-reader, .bbp-audio, .bbp-video');
		Array.prototype.forEach.call(nodes, mount);
	}
	window.BBPlayers = { mount: mount, boot: boot, locales: Object.keys(LOCALES) };
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
