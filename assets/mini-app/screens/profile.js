/**
 * Profile screen — user info, stats, badges, and settings.
 */
( function () {
	'use strict';
	var A = window.BEApp;
	var BADGE_EMOJI = {
		streak_7: '🔥', streak_30: '⚡', streak_365: '🌟',
		first_lesson: '🎓', quiz_master: '🧠', speaker: '🗣', writer: '✍️',
		fast_learner: '🚀', xp_1000: '💎', xp_5000: '👑',
	};

	A.screens['profile'] = {
		render: function ( mount, user, params, app ) {
			mount.innerHTML = app.spinner();
			Promise.all( [ app.get( '/user/me' ), app.get( '/user/stats' ) ] )
				.then( function ( both ) { renderProfile( mount, both[0], both[1], app ); } )
				.catch( function () { renderProfile( mount, user || {}, {}, app ); } );
		}
	};

	// Settings screen: view/'settings'
	A.screens['settings'] = {
		render: function ( mount, user, params, app ) {
			mount.innerHTML = app.spinner();
			app.get( '/user/me' ).then( function ( me ) { renderSettings( mount, me, app ); } )
				.catch( function () { renderSettings( mount, user || {}, app ); } );
		}
	};

	function renderProfile( mount, me, stats, app ) {
		var s = stats || {};
		var badges = s.badges || [];
		var html = '';

		html += '<div class="card center">' +
			'<div class="avatar" style="width:64px;height:64px;margin:0 auto 10px;font-size:26px">' + app.esc( ( me.first_name || 'U' ).charAt( 0 ).toUpperCase() ) + '</div>' +
			'<h1 style="margin:0">' + app.esc( me.first_name || '' ) + ' ' + app.esc( me.last_name || '' ) + '</h1>' +
			( me.username ? '<p class="hint" style="margin:2px 0 0">@' + app.esc( me.username ) + '</p>' : '' ) +
			'<div style="margin-top:8px"><span class="badge-light">' + app.esc( ( me.level || 'beginner' ).replace( /_/g, ' ' ) ) + '</span>' +
			( me.notifications_enabled ? ' <span class="badge-light">🔔 on</span>' : '' ) + '</div>' +
			'</div>';

		html += '<div class="stat-row">' +
			stat( app.fmtXp( s.lifetime_xp ), 'Lifetime XP' ) +
			stat( app.fmtXp( s.weekly_xp ), 'Weekly XP' ) +
			'</div><div class="stat-row">' +
			stat( app.fmtXp( s.current_streak ), 'Current streak' ) +
			stat( app.fmtXp( s.longest_streak ), 'Longest streak' ) +
			'</div>';

		html += '<h2>Badges</h2>';
		if ( badges.length ) {
			html += '<div class="badge-grid">' + badges.map( function ( b ) {
				return '<div class="badge"><div class="emoji">' + ( BADGE_EMOJI[b.badge_slug] || '🏅' ) + '</div>' +
					'<small>' + app.esc( b.badge_name ) + '</small></div>';
			} ).join( '' ) + '</div>';
		} else {
			html += '<p class="hint">No badges yet — complete lessons to earn your first! 🎯</p>';
		}

		html += '<div style="height:10px"></div>';
		html += '<button class="btn plain" id="p-settings">⚙️ Settings</button>';
		html += '<div style="height:8px"></div>';
		html += '<button class="btn danger" id="p-signout">Sign out</button>';

		mount.innerHTML = html;
		document.getElementById( 'p-settings' ).addEventListener( 'click', function () { app.navigate( 'settings' ); } );
		document.getElementById( 'p-signout' ).addEventListener( 'click', function () { app.signOut(); } );
	}

	function renderSettings( mount, me, app ) {
		var levels = [ 'beginner', 'intermediate', 'advanced' ];
		var html = '<h1>Settings</h1>' +
			'<div class="card"><h3>Level</h3><p class="hint">Keep it honest so your lessons stay pitched right.</p>' +
			levels.map( function ( lv ) {
				return '<button class="option ' + ( me.level === lv ? 'selected' : '' ) + '" data-lv="' + lv + '">' +
					app.esc( lv.charAt( 0 ).toUpperCase() + lv.slice( 1 ) ) + '</button>';
			} ).join( '' ) +
			'</div>' +

			'<div class="card"><h3>Notification time</h3>' +
			'<input type="time" class="inp" id="s-time" value="' + app.esc( me.notification_time || '07:00' ) + '">' +
			'<label class="hint"><input type="checkbox" id="s-notify" ' + ( me.notifications_enabled ? 'checked' : '' ) + '> Enable daily verse notifications</label>' +
			'</div>' +

			'<div class="card"><h3>Language</h3>' +
			'<input type="text" class="inp" id="s-lang" value="' + app.esc( me.language || 'en' ) + '">' +
			'</div>' +

			'<button class="btn" id="s-save">Save Settings</button>';

		mount.innerHTML = html;

		document.querySelectorAll( '[data-lv]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				document.querySelectorAll( '[data-lv]' ).forEach( function ( x ) { x.classList.remove( 'selected' ); } );
				el.classList.add( 'selected' );
			} );
		} );

		document.getElementById( 's-save' ).addEventListener( 'click', function () {
			var lv = document.querySelector( '[data-lv].selected' ) && document.querySelector( '[data-lv].selected' ).getAttribute( 'data-lv' );
			app.post( '/user/settings' , {
				level: lv || me.level,
				notification_time: document.getElementById( 's-time' ).value || '07:00',
				notifications_enabled: document.getElementById( 's-notify' ).checked ? '1' : '0',
				language_code: document.getElementById( 's-lang' ).value || 'en',
			} ).then( function () {
				app.toast( 'Saved ✓' );
				app.haptic();
				app.navigate( 'profile' );
			} ).catch( function ( err ) {
				app.toast( 'Could not save: ' + ( err && err.message ? err.message : 'error' ) );
			} );
		} );
	}

	function stat( num, lbl ) {
		return '<div class="stat"><span class="num">' + num + '</span><span class="lbl">' + lbl + '</span></div>';
	}
} )();