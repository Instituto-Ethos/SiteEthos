<?php
/**
 * Third-party script deferral (epic #282, slice #323).
 *
 * Defers the heaviest third-party scripts (characterization G1: ~2.9 MB constant
 * on every page — Dynamics chat 1.87 MB, GTM stack 655 KB, Meta Pixel via GTM,
 * UserWay, VLibras) off the critical path, via targeted rewrites of the final HTML.
 *
 * Mechanism: an output buffer started at `template_redirect` (theme-side, no plugin
 * edits) feeds a PURE function — `hacklabr_defer_third_parties_rewrite()` — that
 * performs anchored, fail-open replacements on the exact signatures observed in the
 * production HTML (evidence: docs/rounds/R01-2026-11-melhorias-de-desempenho/).
 * If a signature drifts (plugin update, snippet edit in admin), that rule stops
 * matching and the original tag is served unchanged. Nothing is proxied, rewritten
 * or hosted locally — scripts load from their original URLs, later.
 *
 * Triggers (shared helper `window.__ethosLazyLoad`):
 *  - Analytics/chat (GTM, gtag.js, Dynamics chat): requestIdleCallback (bounded)
 *    OR first interaction (pointerdown|keydown|wheel|scroll|touchstart, once)
 *    OR hard cap 4 s — whichever comes first.
 *  - Accessibility (N6: UserWay + VLibras): idle + timeout ONLY — never gated
 *    behind user interaction.
 *
 * Event preservation:
 *  - `window.dataLayer` + `gtm.start` are pushed early (shim); events queued
 *    before gtm.js loads are replayed by GTM on load (official dataLayer behaviour).
 *  - `gtag`/`fbq` queue stubs are provided early (official pre-load semantics).
 *  - MonsterInsights' inline tracker keeps pushing to dataLayer (queue-safe).
 *
 * Kill switches (filters):
 *  - `hacklabr_defer_third_parties` (bool, default true) — master switch.
 *  - `hacklabr_defer_accessibility_third_parties` (bool, default true) — UserWay +
 *    VLibras only. If post-deploy measurement shows any a11y regression, set to
 *    false to restore the original eager loading for those two widgets.
 *
 * @package hacklabr
 */

namespace hacklabr;

/**
 * Rewrite third-party tags in a final HTML document.
 *
 * Pure function: no WordPress calls, safe to include standalone for testing
 * (define HACKLABR_THIRD_PARTIES_STANDALONE to skip the wiring below).
 *
 * @param string   $html    Final HTML document.
 * @param array    $options ['accessibility' => bool] — defer UserWay/VLibras too (default true).
 * @param array    $log     Out: per-rule replacement counts (debug/audit).
 * @return string Rewritten HTML (unchanged when no rule matches).
 */
function defer_third_parties_rewrite( $html, array $options = [], array &$log = [] ) {
	if ( ! is_string( $html ) || strpos( $html, '<head' ) === false ) {
		return $html;
	}

	$defer_a11y = isset( $options['accessibility'] ) ? (bool) $options['accessibility'] : true;
	$log        = [];
	$applied    = 0;

	/*
	 * Rule 1 — GTM loader snippet (HFCM "Google Tag Manager - Header").
	 * Before: canonical snippet, inserts gtm.js (async) during head parse.
	 * After: dataLayer/gtag/fbq shims run in place; gtm.js loads on
	 * idle | first interaction | 4 s cap. The <noscript> iframe (HFCM body
	 * snippet) is intentionally untouched — it only renders without JS.
	 */
	$html = preg_replace_callback(
		'#<script>\s*\(function\(w,d,s,l,i\)\{w\[l\]=w\[l\]\|\|\[\];w\[l\]\.push\(\{\'gtm\.start\':[\s\S]*?\}\)\(window,document,\'script\',\'dataLayer\',\'(GTM-[A-Z0-9]+)\'\);\s*</script>#',
		function ( $m ) use ( &$log, &$applied ) {
			$log['gtm'] = ( isset( $log['gtm'] ) ? $log['gtm'] : 0 ) + 1;
			$applied++;
			$gtm_id = $m[1]; // [A-Z0-9] only by pattern — safe to embed.
			return "<script>\n"
				. "/* [theme #323] GTM deferred: dataLayer/gtag/fbq shims run now; gtm.js loads on idle/first interaction (4s cap). Queued events are replayed by GTM on load. */\n"
				. 'window.dataLayer = window.dataLayer || [];' . "\n"
				. 'window.dataLayer.push({ "gtm.start": new Date().getTime(), "event": "gtm.js" });' . "\n"
				. 'window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };' . "\n"
				. 'if (!window.fbq) {' . "\n"
				. '    window.fbq = function () { if (window.fbq.callMethod) { window.fbq.callMethod.apply(window.fbq, arguments); } else { window.fbq.queue.push(arguments); } };' . "\n"
				. '    window.fbq.push = window.fbq;' . "\n"
				. '    window.fbq.loaded = true;' . "\n"
				. '    window.fbq.version = "2.0";' . "\n"
				. '    window.fbq.queue = [];' . "\n"
				. '}' . "\n"
				. 'window.__ethosLazyLoad(function () {' . "\n"
				. '    var s = document.createElement("script");' . "\n"
				. '    s.async = true;' . "\n"
				. '    s.src = "https://www.googletagmanager.com/gtm.js?id=' . $gtm_id . '";' . "\n"
				. '    document.head.appendChild(s);' . "\n"
				. '}, { idleMs: 2000, capMs: 4000 });' . "\n"
				. '</script>';
		},
		$html,
		1,
		$c
	);
	if ( $c ) { $log['gtm'] = $c; }

	/*
	 * Rule 2 — MonsterInsights gtag.js tag (analytics stack, googletagmanager.com
	 * domain — part of the 655 KB measured in baseline §5).
	 * Before: <script src="//www.googletagmanager.com/gtag/js?id=G-…" async>.
	 * After: loads on idle | first interaction | 4 s cap. The MonsterInsights
	 * inline tracker (which defines window.gtag = __gtagTracker and pushes to
	 * dataLayer) is untouched and queue-safe until gtag.js arrives.
	 */
	$html = preg_replace_callback(
		'#<script[^>]*src=["\'](?:https?:)?//www\.googletagmanager\.com/gtag/js\?id=(G-[A-Z0-9_-]+)["\'][^>]*></script>#',
		function ( $m ) use ( &$log, &$applied ) {
			$log['gtag'] = ( isset( $log['gtag'] ) ? $log['gtag'] : 0 ) + 1;
			$applied++;
			$gid = $m[1]; // [A-Z0-9_-] only by pattern — safe to embed.
			return "<script>\n"
				. '/* [theme #323] gtag.js deferred; MonsterInsights queue replays on load. */' . "\n"
				. 'window.__ethosLazyLoad(function () {' . "\n"
				. '    var s = document.createElement("script");' . "\n"
				. '    s.async = true;' . "\n"
				. '    s.src = "https://www.googletagmanager.com/gtag/js?id=' . $gid . '";' . "\n"
				. '    document.head.appendChild(s);' . "\n"
				. '}, { idleMs: 2000, capMs: 4000 });' . "\n"
				. '</script>';
		},
		$html,
		1,
		$c
	);
	if ( $c ) { $log['gtag'] = $c; }

	/*
	 * Rule 3 — Dynamics 365 Omnichannel chat (HFCM "Omnichat"; 1.87 MB chain, the
	 * single largest third-party cost). The original tag has NO async/defer — it is
	 * parser-blocking right after <body>.
	 * Before: <script id="Microsoft_Omnichannel_LCWidget" src=… data-*…></script>.
	 * After: identical tag staged as type="application/json" (no fetch, no exec) +
	 * loader that recreates the script element with the very same attributes on
	 * idle | first interaction | 4 s cap — the chat ALWAYS appears (fallback).
	 */
	$html = preg_replace_callback(
		'#<script([^>]*\sid="Microsoft_Omnichannel_LCWidget"[^>]*)>\s*</script>#',
		function ( $m ) use ( &$log, &$applied ) {
			$log['dynamics'] = ( isset( $log['dynamics'] ) ? $log['dynamics'] : 0 ) + 1;
			$applied++;
			$attrs = $m[1]; // Served back as HTML attributes verbatim.
			return '<script type="application/json" data-ethos-deferred="1"' . $attrs . '></script>' . "\n"
				. "<script>\n"
				. '/* [theme #323] Dynamics chat staged: loads on idle/first interaction/4s cap so the chat always appears. */' . "\n"
				. 'window.__ethosLazyLoad(function () {' . "\n"
				. '    var staged = document.getElementById("Microsoft_Omnichannel_LCWidget");' . "\n"
				. '    if (!staged || !staged.parentNode) { return; }' . "\n"
				. '    var s = document.createElement("script");' . "\n"
				. '    for (var i = 0; i < staged.attributes.length; i++) {' . "\n"
				. '        var a = staged.attributes[i];' . "\n"
				. '        if (a.name === "type" || a.name === "data-ethos-deferred") { continue; }' . "\n"
				. '        s.setAttribute(a.name, a.value);' . "\n"
				. '    }' . "\n"
				. '    staged.parentNode.removeChild(staged);' . "\n"
				. '    document.body.appendChild(s);' . "\n"
				. '}, { idleMs: 2500, capMs: 4000 });' . "\n"
				. '</script>';
		},
		$html,
		1,
		$c
	);
	if ( $c ) { $log['dynamics'] = $c; }

	if ( $defer_a11y ) {
		/*
		 * Rule 4 — VLibras plugin script, enqueued by the production VLibras plugin
		 * (parser-blocking in <head>). Current vlibras-plugin.js (v7.12.3 via 302 →
		 * jsdelivr) does NOT define the `vlibrasjs` global, so the `vlibrasjs-js-after`
		 * inline (try{vlibrasjs.load(...)}catch{}) is a silent no-op today — both tags
		 * are removed; loading moves to Rule 5.
		 */
		$html = preg_replace_callback(
			'#<script[^>]*\sid="vlibrasjs-js"[^>]*></script>#',
			function ( $m ) use ( &$log, &$applied ) {
				$log['vlibras-head'] = ( isset( $log['vlibras-head'] ) ? $log['vlibras-head'] : 0 ) + 1;
				$applied++;
				return '<!-- [theme #323] vlibras-plugin.js deferred to idle+timeout (see footer loader, N6: never interaction-gated) -->';
			},
			$html,
			1,
			$c
		);
		if ( $c ) { $log['vlibras-head'] = $c; }

		$html = preg_replace_callback(
			'#<script[^>]*\sid="vlibrasjs-js-after"[^>]*>[\s\S]*?</script>#',
			function ( $m ) use ( &$log, &$applied ) {
				$log['vlibras-after'] = ( isset( $log['vlibras-after'] ) ? $log['vlibras-after'] : 0 ) + 1;
				$applied++;
				return '<!-- [theme #323] vlibrasjs-js-after removed (no-op today: `vlibrasjs` is not defined by vlibras-plugin.js v7.x) -->';
			},
			$html,
			1,
			$c
		);
		if ( $c ) { $log['vlibras-after'] = $c; }

		/*
		 * Rule 5 — VLibras widget instantiation (production plugin footer inline).
		 * Before: new window.VLibras.Widget('https://vlibras.gov.br/app') — runs at
		 * parse time (plugin script is already executed in <head>).
		 * After: on idle + 3.5 s cap (NEVER interaction), load vlibras-plugin.js
		 * from the same base URL, then instantiate the Widget on load.
		 * The <div vw class="enabled"> markup is untouched (inert, no CLS — fixed
		 * position widget).
		 */
		$html = preg_replace_callback(
			'#new\s+window\.VLibras\.Widget\(\s*([\'"])(https?://[^\'"]+)\1\s*\)\s*;?#',
			function ( $m ) use ( &$log, &$applied ) {
				$log['vlibras-widget'] = ( isset( $log['vlibras-widget'] ) ? $log['vlibras-widget'] : 0 ) + 1;
				$applied++;
				$base_json = json_encode( $m[2], JSON_UNESCAPED_SLASHES ); // JSON string is valid JS literal.
				return 'window.__ethosLazyLoad(function () {' . "\n"
					. '    var base = ' . $base_json . ';' . "\n"
					. '    var s = document.createElement("script");' . "\n"
					. '    s.src = base + "/vlibras-plugin.js";' . "\n"
					. '    s.onload = function () {' . "\n"
					. '        try { if (window.VLibras && window.VLibras.Widget) { new window.VLibras.Widget(base); } }' . "\n"
					. '        catch (e) { if (window.console && window.console.warn) { window.console.warn("[ethos-lazy] vlibras init", e); } }' . "\n"
					. '    };' . "\n"
					. '    (document.body || document.head).appendChild(s);' . "\n"
					. '}, { interaction: false, idleMs: 3000, capMs: 3500 });';
			},
			$html,
			1,
			$c
		);
		if ( $c ) { $log['vlibras-widget'] = $c; }

		/*
		 * Rule 6 — UserWay (accessibility widget; theme template header-lateral.php).
		 * Before: IIFE at the very top of <head> appends widget.js during head parse.
		 * After: same append on idle + 3.5 s cap (NEVER interaction). data-account
		 * preserved.
		 */
		$html = preg_replace_callback(
			'#<script>\s*\(function\(d\)\{\s*var s = d\.createElement\("script"\);\s*s\.setAttribute\("data-account", "([A-Za-z0-9_-]+)"\);\s*s\.setAttribute\("src", "https://cdn\.userway\.org/widget\.js"\);\s*\(d\.body \|\| d\.head\)\.appendChild\(s\);\s*\}\)\(document\)\s*</script>#',
			function ( $m ) use ( &$log, &$applied ) {
				$log['userway'] = ( isset( $log['userway'] ) ? $log['userway'] : 0 ) + 1;
				$applied++;
				$account = $m[1]; // [A-Za-z0-9_-] only by pattern — safe to embed.
				return "<script>\n"
					. '/* [theme #323] UserWay deferred to idle + timeout only (N6: never interaction-gated). */' . "\n"
					. 'window.__ethosLazyLoad(function () {' . "\n"
					. '    var s = document.createElement("script");' . "\n"
					. '    s.setAttribute("data-account", "' . $account . '");' . "\n"
					. '    s.setAttribute("src", "https://cdn.userway.org/widget.js");' . "\n"
					. '    (document.body || document.head).appendChild(s);' . "\n"
					. '}, { interaction: false, idleMs: 3000, capMs: 3500 });' . "\n"
					. '</script>';
			},
			$html,
			1,
			$c
		);
		if ( $c ) { $log['userway'] = $c; }
	}

	/*
	 * Helper injection — only when at least one rule applied: tiny inline loader
	 * right after <head>, before every consumer. Idempotent definition.
	 */
	if ( $applied > 0 ) {
		$helper = "<script>\n"
			. '/* [theme #323] lazy loader: requestIdleCallback (bounded) | first interaction (pointerdown|keydown|wheel|scroll|touchstart, once) | hard cap. A11y widgets use interaction:false. */' . "\n"
			. 'window.__ethosLazyLoad = window.__ethosLazyLoad || function (fn, opts) {' . "\n"
			. '    opts = opts || {};' . "\n"
			. '    var fired = false, listeners = [];' . "\n"
			. '    function cleanup() {' . "\n"
			. '        for (var i = 0; i < listeners.length; i++) {' . "\n"
			. '            document.removeEventListener(listeners[i][0], listeners[i][1], true);' . "\n"
			. '        }' . "\n"
			. '    }' . "\n"
			. '    function go() {' . "\n"
			. '        if (fired) { return; }' . "\n"
			. '        fired = true;' . "\n"
			. '        cleanup();' . "\n"
			. '        try { fn(); } catch (e) { if (window.console && window.console.warn) { window.console.warn("[ethos-lazy]", e); } }' . "\n"
			. '    }' . "\n"
			. '    var events = (opts.interaction === false) ? [] : ["pointerdown", "keydown", "wheel", "scroll", "touchstart"];' . "\n"
			. '    for (var i = 0; i < events.length; i++) {' . "\n"
			. '        (function (name) {' . "\n"
			. '            var handler = function () { go(); };' . "\n"
			. '            if (name === "keydown" || name === "pointerdown") { document.addEventListener(name, handler, true); }' . "\n"
			. '            else { document.addEventListener(name, handler, { capture: true, passive: true }); }' . "\n"
			. '            listeners.push([name, handler]);' . "\n"
			. '        }(events[i]));' . "\n"
			. '    }' . "\n"
			. '    var idleMs = (opts.idleMs == null) ? 2000 : opts.idleMs;' . "\n"
			. '    if (window.requestIdleCallback) {' . "\n"
			. '        window.requestIdleCallback(function () { go(); }, { timeout: idleMs });' . "\n"
			. '    } else {' . "\n"
			. '        setTimeout(go, Math.min(idleMs, 400));' . "\n"
			. '    }' . "\n"
			. '    setTimeout(go, (opts.capMs == null) ? 4000 : opts.capMs);' . "\n"
			. '};' . "\n"
			. '</script>';

		$html = preg_replace_callback(
			'#(<head[^>]*>)#i',
			function ( $m ) use ( $helper, &$log ) {
				$log['helper'] = 1;
				return $m[1] . "\n" . $helper;
			},
			$html,
			1,
			$c
		);
		if ( empty( $c ) ) {
			// No <head> found (unexpected, but fail-safe): prepend the helper.
			$log['helper'] = 'prepended';
			$html = $helper . $html;
		}
	}

	return $html;
}

if ( ! defined( 'HACKLABR_THIRD_PARTIES_STANDALONE' ) && \function_exists( 'add_action' ) ) {
	/**
	 * Output-buffer wiring. Buffers the frontend HTML (GET/HEAD, non-AJAX/CRON/REST)
	 * and runs the pure rewrite on flush. Fail-open by construction: if no pattern
	 * matches, the buffer is returned byte-identical.
	 */
	\add_action( 'template_redirect', function () {
		if ( \is_admin() || \wp_doing_ajax() || \wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( strtoupper( (string) $_SERVER['REQUEST_METHOD'] ), [ 'GET', 'HEAD' ], true ) ) {
			return;
		}
		if ( ! \apply_filters( 'hacklabr_defer_third_parties', true ) ) {
			return; // Master kill switch: serve everything eager, as before.
		}

		\ob_start( function ( $html ) {
			$options = [
				'accessibility' => (bool) \apply_filters( 'hacklabr_defer_accessibility_third_parties', true ),
			];
			$log = [];
			$out = defer_third_parties_rewrite( $html, $options, $log );
			if ( WP_DEBUG && ! empty( $log ) && \function_exists( 'error_log' ) ) {
				\error_log( '[hacklabr/third-parties] ' . \wp_json_encode( $log ) );
			}
			return $out;
		} );
	}, 1 );
}
