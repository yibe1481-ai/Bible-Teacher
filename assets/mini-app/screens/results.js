/**
 * Results screen — today's stats, AI feedback, and preview of tomorrow's verse.
 * Fetches /lesson/feedback.
 */
( function () {
	'use strict';
	var A = window.BEApp;

	A.screens['results'] = {
		render: function ( mount, user, params, app ) {
			mount.innerHTML = app.spinner();
			app.get( '/lesson/feedback' ).then( function ( res ) {
				renderResults( mount, res, app );
			} ).catch( function () {
				mount.innerHTML = '<div class="state"><div class="state-title">No results yet</div>' +
					'<p class="state-sub">Complete today’s lesson to see your results.</p>' +
					'<button class="btn" id="r-home">Back to Today</button></div>';
				document.getElementById( 'r-home' ).addEventListener( 'click', function () { app.navigate( 'home' ); } );
			} );
		}
	};

	function renderResults( mount, res, app ) {
		var s = res.stats || {};
		var overall = overallScore( s );

		var html = '<div class="score-hero">' +
			'<h1 style="margin-bottom:2px">Lesson Complete! 🎉</h1>' +
			'<p class="hint" style="margin:0">Streak: 🔥 ' + app.fmtXp( s.streak || 0 ) + ' days  ·  +' + app.fmtXp( s.xp_today || 0 ) + ' XP today</p>' +
			'</div>';

		html += '<div class="card center">' +
			'<div class="score-num" style="color:var(--tg-theme-button,var(--be-fallback-button))">' + overall + '%</div>' +
			'<div class="hint">overall today</div>' +
			'</div>';

		html += '<div class="stat-row">' +
			stat( '🧠', 'Quiz', s.quiz_total ? Math.round( ( s.quiz_score / s.quiz_total ) * 100 ) + '%' : '—', 'fewer than <b>'+ ( s.quiz_total || 0 ) +'</b>' ) +
			stat( '🗣', 'Speaking', ( s.speaking_score || 0 ) + '%', 'pronunciation' ) +
			stat( '✍️', 'Writing', ( s.writing_score || 0 ) + '%', 'recall' ) +
			'</div>';

		if ( res.feedback ) {
			html += '<div class="card"><h3>💬 Feedback from your teacher</h3><p>' + app.esc( res.feedback ) + '</p></div>';
		}

		if ( res.preview ) {
			html += '<div class="card"><h3>☀️ Tomorrow’s verse</h3>' +
				'<p class="verse-text" style="font-size:16px">’<span>' + app.esc( res.preview.text || '' ) + '</span>’</p>' +
				( res.preview.reference ? '<div class="verse-ref">' + app.esc( res.preview.reference ) + '</div>' : '' ) +
				'</div>';
		}

		html += '<button class="btn" id="r-home2">Back to Today</button>';
		mount.innerHTML = html;
		document.getElementById( 'r-home2' ).addEventListener( 'click', function () { app.haptic(); app.navigate( 'home' ); } );
	}

	function stat( ico, lbl, val, sub ) {
		var fill = lbl === 'Quiz' || lbl === 'Speaking' ? '<b>' + ico + '</b>' : '<b>' + ico + '</b>';
		return '<div class="stat"><span class="num">' + val + '</span><span class="lbl">' + lbl + '</span></div>';
	}

	function overallScore( s ) {
		var parts = [];
		if ( s.quiz_total > 0 ) { parts.push( s.quiz_score / s.quiz_total ); }
		if ( s.speaking_score ) { parts.push( s.speaking_score / 100 ); }
		if ( s.writing_score )  { parts.push( s.writing_score / 100 ); }
		if ( ! parts.length ) { return 0; }
		return Math.round( parts.reduce( function ( a, b ) { return a + b; }, 0 ) / parts.length * 100 );
	}
} )();