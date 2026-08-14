/**
 * Bible English Mini App — core application.
 *
 * Responsible for:
 *  - Talking to Telegram.WebApp (theme colors, back button, haptics).
 *  - Authenticating via initData and caching the session JWT.
 *  - API client (fetch wrapper) with Bearer token + error handling.
 *  - A tiny hash router, bottom navigation, and shared UI helpers.
 *
 * Screens attach themselves to BE.App.screens under `BE.App.screens['home']`
 * etc. in their own files.
 */
( function () {
	'use strict';

	var API = window.BE_CONFIG.API_BASE;
	var TG = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;

	function App() {
		this.token   = null;
		this.user    = null;
		this.screens = {};
		this.nav     = [ 'home', 'lesson', 'league', 'groups' ];
	}

	App.prototype.init = function () {
		if ( TG ) {
			TG.ready();
			this.applyTheme();
			try {
				TG.setHeaderColor && TG.setHeaderColor( TG.themeParams && TG.themeParams.bg_color ? TG.themeParams.bg_color : '#1f1f1f' );
			} catch ( e ) { /* ignore */ }
			TG.onEvent && TG.onEvent( 'themeChanged', this.applyTheme.bind( this ) );
		}
		this.bindRouter();
		this.route();
	};

	// ---- Theme -------------------------------------------------------------

	// Telegram's themeParams keys drop the '-color' suffix (bg_color,
	// secondary_bg_color, button_text_color, destructive_bg_color). We map them
	// onto CSS vars under BOTH naming conventions so the stylesheet can use
	// var(--tg-theme-*color) and var(--tg-theme-*) interchangeably.
	App.prototype.applyTheme = function () {
		var p = TG && TG.themeParams;
		if ( ! p ) { return; }
		var s = document.documentElement.style;
		var pairs = [
			[ 'bg_color',              '--tg-theme-bg',           '--tg-theme-bg-color' ],
			[ 'secondary_bg_color',    '--tg-theme-secondary',    '--tg-theme-secondary-bg-color' ],
			[ 'text_color',            '--tg-theme-text',         '--tg-theme-text-color' ],
			[ 'hint_color',            '--tg-theme-hint',         '--tg-theme-hint-color' ],
			[ 'link_color',            '--tg-theme-link',         '--tg-theme-link-color' ],
			[ 'button_color',          '--tg-theme-button',       '--tg-theme-button-color' ],
			[ 'button_text_color',     '--tg-theme-button-text',  '--tg-theme-button-text-color' ],
			[ 'destructive_bg_color',  '--tg-theme-destructive-bg-color', '--tg-theme-destructive-bg-color' ],
		];
		pairs.forEach( function ( pair ) {
			var v = p[pair[0]];
			if ( ! v ) { return; }
			s.setProperty( pair[1], v );
			if ( pair[2] ) { s.setProperty( pair[2], v ); }
		} );
	};

	function css( name ) { return 'var(--tg-theme-' + name + ', var(--be-fallback-' + name + '))'; }

	// ---- Auth --------------------------------------------------------------

	App.prototype.authenticate = function () {
		var self = this;
		if ( this.token ) { return Promise.resolve( this.user ); }
		var initData = TG ? TG.initData : ( window.BE_DEV_INIT_DATA || '' );
		if ( ! initData ) {
			return Promise.reject( new Error( 'TelegramWebApp.initData is empty — open this app from Telegram.' ) );
		}
		// Reuse an in-flight auth request so rapid navigation doesn't fire a
		// burst of identical POSTs (which tripped the backend auth rate limiter).
		if ( this._authPending ) { return this._authPending; }
		this._authPending = this.post( '/auth/telegram', { initData: initData } ).then(
			function ( res ) {
				self._authPending = null;
				self.token = res.token;
				self.user  = res.user;
				try { localStorage.setItem( 'be_token', res.token ); } catch ( e ) { /* ignore */ }
				return res.user;
			},
			function ( err ) {
				self._authPending = null;
				throw err;
			}
		);
		return this._authPending;
	};

	App.prototype.restoreSession = function () {
		if ( this.token ) { return; }
		try { this.token = localStorage.getItem( 'be_token' ); } catch ( e ) { /* ignore */ }
	};

	App.prototype.signOut = function () {
		this.token = null;
		this.user  = null;
		try { localStorage.removeItem( 'be_token' ); } catch ( e ) { /* ignore */ }
		this.route();
	};

	// ---- API client --------------------------------------------------------

	function shortcode( errUnknown ) {
		if ( ! errUnknown ) { return 'unknown'; }
		return String( errUnknown.code || errUnknown.error_code || '' );
	}

	App.prototype.post = function ( path, data, isMultipart ) {
		return this.request( path, {
			method: 'POST',
			body: isMultipart ? data : JSON.stringify( data || {} ),
			isMultipart: !! isMultipart,
		} );
	};

	App.prototype.get = function ( path ) {
		return this.request( path, { method: 'GET' } );
	};

	App.prototype.request = function ( path, opts ) {
		var self = this;
		var headers = {};
		if ( this.token ) { headers['Authorization'] = 'Bearer ' + this.token; }

		var fetchOpts = {
			method: opts.method,
			headers: headers,
			credentials: 'same-origin',
		};
		if ( opts.body && opts.isMultipart ) {
			fetchOpts.body = opts.body; // FormData; browser sets multipart boundary.
		} else if ( opts.body ) {
			headers['Content-Type'] = 'application/json';
			fetchOpts.body = opts.body;
		}

		return fetch( API + path, fetchOpts ).then( function ( resp ) {
			return resp.json().catch( function () { return {}; } ).then( function ( body ) {
				if ( ! resp.ok ) {
					var e = new Error( body.message || body.code || 'Request failed' );
					e.status = resp.status;
					e.body   = body;
					if ( resp.status === 401 ) { self.signOut(); }
					throw e;
				}
				return body;
			} );
		} );
	};

	// ---- Router ------------------------------------------------------------

	App.prototype.getRoute = function () {
		var hash = window.location.hash.replace( /^#\/?/, '' ).split( '/' );
		var route = ( hash[0] || 'home' ).trim() || 'home';
		var params = hash.slice( 1 );
		return { name: route, params: params };
	};

	App.prototype.bindRouter = function () {
		var self = this;
		window.addEventListener( 'hashchange', function () {
			self.route();
			window.scrollTo( 0, 0 );
		} );
	};

	App.prototype.navigate = function ( route ) {
		window.location.hash = '#/' + route;
	};

	App.prototype.route = function () {
		var r = this.getRoute();
		var self = this;

		if ( r.name === 'lesson' || r.name === 'results' ) {
			// Lesson flow requires an authenticated user; auth first.
			this.authenticate().then( function ( user ) {
				self.renderRoute( user, r );
			} ).catch( function ( err ) {
				if ( err && err.body && ( err.body.code === 'be_banned' || err.status === 403 ) ) {
					return self.renderBlocked( err.body.message );
				}
				self.renderAuthError( err );
			} );
			return;
		}

		// Home / profile / league / groups / settings — authenticate. If we
		// already hold a valid token (restored session) we can render right
		// away. On auth failure with no token, show an auth error rather than
		// rendering screens that would fire authenticated API calls with no
		// token — that previously produced repeated "Missing bearer token"
		// 401s and rate-limit hits during failed logins.
		this.authenticate().then( function ( user ) {
			self.renderRoute( user, r );
		} ).catch( function ( err ) {
			self.restoreSession();
			if ( self.token ) { self.renderRoute( self.user, r ); return; }
			if ( err && err.body && ( err.body.code === 'be_banned' || err.status === 403 ) ) {
				return self.renderBlocked( err.body.message );
			}
			self.renderAuthError( err );
		} );
	};

	App.prototype.renderRoute = function ( user, r ) {
		if ( user && user.id && window.BEApp._meId !== user.id ) { window.BEApp._meId = user.id; }
		var screen = this.screens[r.name === 'results' ? 'results' : r.name];
		if ( ! screen ) { screen = this.screens['home']; r = { name: 'home', params: [] }; }
		this.setupBackButton( r.name );
		this.drawShell( r.name );
		var mount = document.getElementById( 'app' );
		mount.innerHTML = '';
		screen.render( mount, user, r.params, this );
	};

	App.prototype.setupBackButton = function ( name ) {
		if ( ! TG ) { return; }
		if ( name === 'home' ) {
			TG.BackButton && TG.BackButton.hide();
			return;
		}
		var self = this;
		try {
			TG.BackButton && TG.BackButton.show();
			TG.BackButton && TG.BackButton.onClick( function () {
				window.history.length > 1 ? window.history.back() : self.navigate( 'home' );
			} );
		} catch ( e ) { /* ignore */ }
	};

	App.prototype.drawShell = function ( active ) {
		var shown = this.nav.indexOf( active ) !== -1;
		var navHtml = shown ? this.navHtml( active ) : '';
		var existing = document.getElementById( 'bottom-nav' );
		if ( existing ) { existing.outerHTML = ''; }
		if ( navHtml ) {
			var div = document.createElement('div');
			div.innerHTML = navHtml;
			document.body.appendChild( div.firstChild );
		}
	};

	App.prototype.navHtml = function ( active ) {
		var items = [
			[ 'home', '🏠', 'Today' ],
			[ 'lesson', '📖', 'Lesson' ],
			[ 'league', '🏆', 'League' ],
			[ 'groups', '👥', 'Groups' ],
		];
		var out = '<nav id="bottom-nav" class="bottom-nav">' +
			items.map( function ( it ) {
				var cls = ( active === it[0] ) ? 'active' : '';
				return '<a href="#/' + it[0] + '" class="nav-item ' + cls + '" data-nav="' + it[0] + '">' +
					'<span class="nav-ico">' + it[1] + '</span><span class="nav-label">' + it[2] + '</span></a>';
			} ).join( '' ) + '</nav>';
		return out;
	};

	// ---- Errors & helpers --------------------------------------------------

	App.prototype.renderAuthError = function ( err ) {
		var mount = document.getElementById( 'app' );
		var initData = TG ? TG.initData : ( window.BE_DEV_INIT_DATA || '' );
		var title, sub, code = '';
		if ( err && err.body && err.body.code ) { code = err.body.code; }
		if ( ! initData ) {
			// Running outside Telegram — there is no signed initData to verify.
			title = '👋 Open this from Telegram';
			sub = 'This app only works inside Telegram. Open the bot and tap <b>Open Bible English</b> ' +
				'(or the bot\'s Menu button) — don\'t load the URL in a web browser.';
		} else if ( code === 'be_rate_limited' ) {
			title = '⏳ A moment please';
			sub = 'Too many sign-in attempts in a row. Wait about a minute, then tap <b>Try again</b>.';
		} else if ( code === 'be_banned' ) {
			title = '🚫 Access blocked';
			sub = ( err && err.body && err.body.message ) ? err.body.message : 'Your access has been blocked.';
		} else {
			// Running inside Telegram but the server rejected the session —
			// usually the bot token isn't configured, or the button belongs to a
			// different bot than the one set in plugin settings.
			title = '⚠️ Sign-in rejected by server';
			sub = 'We\'re inside Telegram but the server couldn\'t verify this session. ' +
				'An admin needs to check the <b>Telegram → Bot token</b> in the Bible Teacher settings.';
			if ( code ) { sub += ' <span class="hint">(' + code + ')</span>'; }
		}
		mount.innerHTML = '<div class="state">' +
			'<div class="state-title">' + title + '</div>' +
			'<p class="state-sub">' + sub + '</p>' +
			'<button class="btn" id="auth-retry">Try again</button></div>';
		var b = document.getElementById( 'auth-retry' );
		if ( b ) { b.addEventListener( 'click', function () { location.reload(); } ); }
	};

	App.prototype.renderBlocked = function ( msg ) {
		var mount = document.getElementById( 'app' );
		mount.innerHTML = '<div class="state">' +
			'<div class="state-title">🚫 Access temporarily blocked</div>' +
			'<p class="state-sub">' + ( msg ? msg : '' ) + '</p></div>';
	};

	App.prototype.toast = function ( msg ) {
		var el = document.getElementById( 'toast' );
		el.textContent = msg;
		el.classList.add( 'show' );
		clearTimeout( App.prototype.toast._t );
		App.prototype.toast._t = setTimeout( function () { el.classList.remove( 'show' ); }, 2600 );
	};

	App.prototype.haptic = function () {
		try { TG && TG.HapticFeedback && TG.HapticFeedback.impactOccurred && TG.HapticFeedback.impactOccurred( 'medium' ); } catch ( e ) { /* ignore */ }
	};

	App.prototype.spinner = function () {
		return '<div class="spinner-row"><div class="spinner"></div></div>';
	};

	App.prototype.fmtXp = function ( n ) { return n === null || n === undefined ? '0' : Number( n ).toLocaleString(); };
	App.prototype.esc = function ( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	};

	// Export
	window.BE = { App: App, css: css };
	window.BEApp = new App();
	window.BEApp.restoreSession();
	window.BEApp.init();
} )();