(function () {
	'use strict';
	var cfg = window.BBP_ADMIN;
	if (!cfg) { return; }
	var T = cfg.i18n || {};

	function url(path, params) {
		var qs = Object.keys(params || {}).filter(function (k) { return params[k] !== '' && params[k] != null; })
			.map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
		return cfg.rest + path + (qs ? (cfg.rest.indexOf('?') > -1 ? '&' : '?') + qs : '');
	}

	function get(path, params, withNonce) {
		var headers = { 'Accept': 'application/json' };
		if (withNonce) { headers['X-WP-Nonce'] = cfg.nonce; }
		return fetch(url(path, params), { credentials: 'same-origin', headers: headers }).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (j) {
				if (!r.ok) { throw new Error(j && j.message ? j.message : T.error); }
				return j;
			});
		});
	}

	var memo = {};
	function cached(key, fn) {
		if (!memo[key]) { memo[key] = fn().catch(function (e) { delete memo[key]; throw e; }); }
		return memo[key];
	}

	function fill(select, items, value, withBlank) {
		select.innerHTML = '';
		if (withBlank) {
			var b = document.createElement('option');
			b.value = ''; b.textContent = T.choose;
			select.appendChild(b);
		}
		items.forEach(function (it) {
			var o = document.createElement('option');
			o.value = it.value; o.textContent = it.label;
			select.appendChild(o);
		});
		select.value = value || '';
		if (select.value !== (value || '')) { select.value = ''; }
	}

	/* ---------- Default content cards ---------- */
	document.querySelectorAll('.bbp-default-card').forEach(function (card) {
		var media = card.getAttribute('data-media');
		var sC = card.querySelector('.bbp-s-country');
		var sL = card.querySelector('.bbp-s-language');
		var sB = card.querySelector('.bbp-s-bible');
		var sF = card.querySelector('.bbp-s-fileset');
		var hC = card.querySelector('.bbp-h-country');
		var hL = card.querySelector('.bbp-h-language');
		var hB = card.querySelector('.bbp-h-bible');
		var hF = card.querySelector('.bbp-h-fileset');
		var status = card.querySelector('.bbp-card-status');
		var langs = [];

		function setStatus(msg) { status.textContent = msg || ''; }

		function writeHidden() {
			hC.value = sC.value; hL.value = sL.value; hB.value = sB.value; hF.value = sF.value;
		}

		function onFileset() { writeHidden(); setStatus(sF.value ? T.selected : ''); }

		function onBible(pref) {
			var L = langs.filter(function (l) { return l.id === sL.value; })[0];
			var V = L ? L.versions.filter(function (v) { return v.abbr === sB.value; })[0] : null;
			fill(sF, V ? V.filesets.map(function (f) { return { value: f.id, label: f.label }; }) : [], pref, true);
			if (V && !sF.value && V.filesets.length) { sF.value = V.filesets[0].id; }
			onFileset();
		}

		function onLang(prefBible, prefFileset) {
			var L = langs.filter(function (l) { return l.id === sL.value; })[0];
			fill(sB, L ? L.versions.map(function (v) { return { value: v.abbr, label: v.name }; }) : [], prefBible, true);
			onBible(prefFileset);
		}

		function onCountry(prefLang, prefBible, prefFileset) {
			langs = [];
			fill(sL, [], '', true); fill(sB, [], '', true); fill(sF, [], '', true);
			writeHidden();
			if (!sC.value) { setStatus(''); return; }
			setStatus(T.loading);
			cached('cat|' + sC.value + '|' + media, function () { return get('catalog', { country: sC.value, media: media }); })
				.then(function (r) {
					langs = r.languages || [];
					setStatus(langs.length ? '' : T.none);
					fill(sL, langs.map(function (l) { return { value: l.id, label: l.name }; }), prefLang, true);
					onLang(prefBible, prefFileset);
				})
				.catch(function (e) { setStatus(e.message); });
		}

		sC.addEventListener('change', function () { onCountry('', '', ''); });
		sL.addEventListener('change', function () { onLang('', ''); });
		sB.addEventListener('change', function () { onBible(''); });
		sF.addEventListener('change', onFileset);

		setStatus(T.loading);
		cached('countries', function () { return get('countries'); })
			.then(function (r) {
				var list = (r.countries || []).map(function (c) { return { value: c.code, label: c.name }; });
				var want = card.getAttribute('data-country');
				fill(sC, list, want, true);
				if (want) {
					onCountry(card.getAttribute('data-language'), card.getAttribute('data-bible'), card.getAttribute('data-fileset'));
				} else {
					setStatus('');
				}
			})
			.catch(function (e) { setStatus(e.message); });
	});

	/* ---------- Test connection ---------- */
	var testBtn = document.getElementById('bbp-test');
	var testOut = document.getElementById('bbp-test-result');
	if (testBtn) {
		testBtn.addEventListener('click', function () {
			testOut.textContent = T.testing;
			testOut.className = '';
			get('admin/test', {}, true)
				.then(function (r) { testOut.textContent = r.message; testOut.className = r.ok ? 'bbp-ok' : 'bbp-bad'; })
				.catch(function (e) { testOut.textContent = e.message; testOut.className = 'bbp-bad'; });
		});
	}

	/* ---------- Diagnostics ---------- */
	var rawBtn = document.getElementById('bbp-raw-go');
	var rawOut = document.getElementById('bbp-raw-out');
	if (rawBtn) {
		rawBtn.addEventListener('click', function () {
			rawOut.hidden = false;
			rawOut.textContent = T.loading;
			get('admin/raw', {
				path: document.getElementById('bbp-raw-path').value,
				query: document.getElementById('bbp-raw-query').value
			}, true)
				.then(function (r) { rawOut.textContent = r.json; })
				.catch(function (e) { rawOut.textContent = e.message; });
		});
	}
})();
