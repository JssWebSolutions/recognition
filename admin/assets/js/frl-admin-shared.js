/**
 * Recognition - Shared Admin JavaScript
 *
 * Theme toggle, sidebar / mobile menu, and Swiss / Glass / Bento
 * micro-interactions shared by every admin page. Extracted from
 * inline <script> blocks during the 1.0.0 release and extended
 * with the v2.0.0 redesign helpers (aurora pointer, glass
 * tilt, etc.).
 *
 * @package Face_Recognition_Login
 * @since   1.0.0
 * @version 2.0.0
 */
(function () {
	'use strict';

	// ---------------------------------------------------------------------
	// Theme toggle
	// ---------------------------------------------------------------------
	var STORAGE_KEY = 'frl-theme';

	function setTheme(dark) {
		if (dark) {
			document.documentElement.setAttribute('data-frl-theme', 'dark');
		} else {
			document.documentElement.removeAttribute('data-frl-theme');
		}
		// Toggle all sun/moon icons on the page in a single pass.
		var suns  = document.querySelectorAll('.frl-icon-sun');
		var moons = document.querySelectorAll('.frl-icon-moon');
		suns.forEach(function (el) { el.style.display = dark ? 'none' : 'block'; });
		moons.forEach(function (el) { el.style.display = dark ? 'block' : 'none'; });
		try {
			localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
		} catch (e) {
			// localStorage may be disabled (Safari private mode, sandboxed
			// iframe, etc.) - silently ignore and fall back to in-memory
			// state for the current page.
		}
	}

	function isDark() {
		return document.documentElement.getAttribute('data-frl-theme') === 'dark';
	}

	function initTheme() {
		var saved = null;
		try {
			saved = localStorage.getItem(STORAGE_KEY);
		} catch (e) {
			saved = null;
		}
		var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
		if (saved === 'dark' || (!saved && prefersDark)) {
			setTheme(true);
		} else {
			setTheme(false);
		}
	}

	function bindThemeToggles() {
		document.querySelectorAll('.frl-theme-toggle').forEach(function (btn) {
			if (btn.dataset.frlThemeBound === '1') {
				return;
			}
			btn.dataset.frlThemeBound = '1';
			btn.addEventListener('click', function () {
				setTheme(!isDark());
			});
		});
	}

	// ---------------------------------------------------------------------
	// Sidebar / mobile menu
	// ---------------------------------------------------------------------
	function bindSidebarToggles() {
		var sidebar        = document.getElementById('frl-sidebar');
		if (!sidebar) {
			return;
		}
		var sidebarToggle  = document.getElementById('frl-toggle-sidebar');
		var mobileMenuBtn  = document.getElementById('frl-mobile-menu-btn');

		if (sidebarToggle) {
			sidebarToggle.addEventListener('click', function () {
				sidebar.classList.toggle('collapsed');
			});
		}
		if (mobileMenuBtn) {
			mobileMenuBtn.addEventListener('click', function () {
				sidebar.classList.toggle('open');
			});
		}

		// Close the mobile drawer when clicking outside it.
		document.addEventListener('click', function (e) {
			if (window.innerWidth > 1024) {
				return;
			}
			if (!sidebar.classList.contains('open')) {
				return;
			}
			if (!sidebar.contains(e.target) && (!mobileMenuBtn || !mobileMenuBtn.contains(e.target))) {
				sidebar.classList.remove('open');
			}
		});
	}

	// ---------------------------------------------------------------------
	// Glass tilt (subtle 3D on hover for KPI / glass cards)
	// ---------------------------------------------------------------------
	function bindGlassTilt() {
		// Respect reduced motion: skip tilt entirely if the user has
		// requested it via the OS / browser.
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (reduce) {
			return;
		}

		var targets = document.querySelectorAll('.frl-glass, .frl-kpi, .frl-promo');
		targets.forEach(function (el) {
			if (el.dataset.frlTiltBound === '1') {
				return;
			}
			el.dataset.frlTiltBound = '1';

			el.addEventListener('mousemove', function (e) {
				var rect       = el.getBoundingClientRect();
				var x          = e.clientX - rect.left;
				var y          = e.clientY - rect.top;
				var cx         = rect.width  / 2;
				var cy         = rect.height / 2;
				var rotateY    = ((x - cx) / cx) * 4;  // up to 4deg
				var rotateX    = -((y - cy) / cy) * 4; // up to 4deg
				el.style.transform =
					'perspective(900px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) translateY(-2px)';
			});

			el.addEventListener('mouseleave', function () {
				el.style.transform = '';
			});
		});
	}

	// ---------------------------------------------------------------------
	// Aurora pointer (gentle gradient follows the cursor)
	// ---------------------------------------------------------------------
	function bindAuroraPointer() {
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (reduce) {
			return;
		}

		// Throttle with rAF to keep this cheap.
		var pending = false;
		var lastX   = -1000;
		var lastY   = -1000;

		document.addEventListener('mousemove', function (e) {
			lastX = e.clientX;
			lastY = e.clientY;
			if (pending) {
				return;
			}
			pending = true;
			window.requestAnimationFrame(function () {
				pending = false;
				document.documentElement.style.setProperty('--frl-cursor-x', lastX + 'px');
				document.documentElement.style.setProperty('--frl-cursor-y', lastY + 'px');
			});
		});
	}

	// ---------------------------------------------------------------------
	// Bootstrap
	// ---------------------------------------------------------------------
	function init() {
		initTheme();
		bindThemeToggles();
		bindSidebarToggles();
		bindGlassTilt();
		bindAuroraPointer();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
