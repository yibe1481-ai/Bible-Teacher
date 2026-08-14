/**
 * Home screen — today's verse, lesson status, streak, weekly XP.
 */
( function () {
	'use strict';
	var A = window.BEApp;

	A.screens['home'] = {
		render: function ( mount, user, params, app ) {
			mount.innerHTML = app.spinner();
			app.get( '/me' ).then( function ( me ) {
				app.get( '/lesson/today' ).then( function ( today ) {
					renderHome( mount, me, today, app );
				} ).catch( function () {
					renderHome( mount, me, null, app );
				} );
			} ).catch( function ( err ) {
				mount.innerHTML = '<div class="state"><div class="state-title">Could not load your profile</div>' +
					'<p class="state-sub">' + app.esc( err && err.message ? err.message : '' ) + '</p></div>';
			} );
		}
	};

	function hello( me ) {
		var h = new Date().getHours();
		if ( h < 12 ) { return 'Good morning' + ( me.first_name ? ', ' + me.first_name : '' ); }
		if ( h < 18 ) { return 'Good afternoon' + ( me.first_name ? ', ' + me.first_name : '' ); }
		return 'Good evening' + ( me.first_name ? ', ' + me.first_name : '' );
	}

	function renderHome( mount, me, today, app ) {
		var html = '';

		// Header.
		html += '<div class="section-hdr"><h1>' + app.esc( hello( me ) ) + '</h1></div>';
		html += '<p class="hint">' + app.esc( me.level ? ucfirst( me.level ) + ' level' : '' ) + '</p>';

		// Streak + XP chips.
		var streak = today && today.streak ? today.streak : ( me.current_streak || 0 );
		var weekly = today && today.weekly_xp !== undefined ? today.weekly_xp : 0;
		html += '<div class="stat-row">' +
			'<div class="stat"><span class="num">🔥 ' + app.fmtXp( streak ) + '</span><span class="lbl">Day streak</span></div>' +
			'<div class="stat"><span class="num">⚡ ' + app.fmtXp( weekly ) + '</span><span class="lbl">Weekly XP</span></div>' +
			'<div class="stat"><span class="num">👑 ' + app.fmtXp( me.level_xp || 0 ) + '</span><span class="lbl">Total XP</span></div>' +
			'</div>';

		if ( today && today.verse ) {
			html += '<div class="verse-card">' +
				'<p class="verse-text">“' + app.esc( today.verse.text ) + '”</p>' +
				'<div class="verse-ref">' + app.esc( today.reference ) + '</div>' +
				'</div>';

			html += '<p class="hint">Today’s lesson has ' + todayStepsText( today ) + ' steps.</p>';
			html += '<button class="btn" id="home-start">📖 Continue Today’s Lesson</button>';
		} else {
			html += '<div class="card center"><p>Your verse isn’t ready yet — try again shortly.</p></div>';
			html += '<button class="btn" id="home-start">📖 Start Lesson</button>';
		}

		mount.innerHTML = html;

		var start = document.getElementById( 'home-start' );
		if ( start ) { start.addEventListener( 'click', function () { app.haptic(); app.navigate( 'lesson' ); } ); }
	}

	function todayStepsText( today ) {
		var n = 0;
		if ( today.vocab && ( today.vocab.words || today.vocab.items ) ) { n++; }
		if ( today.listening && today.listening.audio_url ) { n++; }
		if ( today.quiz && today.quiz.questions ) { n += today.quiz.questions.length ? 1 : 0; }
		n += 2; // speaking + writing base
		return n;
	}

	function ucfirst( s ) { return s ? s.charAt( 0 ).toUpperCase() + s.slice( 1 ) : ''; }
} )();