/**
 * League screen — current league, my rank, and the weekly leaderboard.
 */
( function () {
	'use strict';
	var A = window.BEApp;
	var DIV_EMOJI = { bronze: '🥉', silver: '🥈', gold: '🥇', platinum: '💠', diamond: '💎' };

	A.screens['league'] = {
		render: function ( mount, user, params, app ) {
			mount.innerHTML = app.spinner();
			app.get( '/league/current' ).then( function ( res ) { renderLeague( mount, res, app ); } )
				.catch( function () {
					mount.innerHTML = '<div class="state"><div class="state-title">No league yet</div>' +
						'<p class="state-sub">You’ll join a league after your placement lesson.</p>' +
						'<button class="btn" id="l-gohome">Start lesson</button></div>';
					document.getElementById( 'l-gohome' ).addEventListener( 'click', function () { app.navigate( 'lesson' ); } );
				} );
		}
	};

	function renderLeague( mount, res, app ) {
		var lg   = res.league || {};
		var meId = user( res );
		var board = res.leaderboard || [];

		var html = '<h1>🏆 League</h1>';
		html += '<div class="card center">' +
			'<div style="font-size:34px">' + ( DIV_EMOJI[lg.division] || '🏆' ) + '</div>' +
			'<h2 style="margin:6px 0 2px">' + app.esc( lg.name || 'Weekly League' ) + '</h2>' +
			'<p class="hint" style="margin:0">' + app.esc( ( lg.division || '' ).replace( /_/g, ' ' ) ) +
			' · ' + app.esc( ( lg.level || '' ).replace( /_/g, ' ' ) ) + '</p>' +
			'<p class="hint">Week: ' + app.esc( ( lg.week_start || '' ) ) + ' → ' + app.esc( ( lg.week_end || '' ) ) + '</p>' +
			( res.my_rank ? '<div style="margin-top:6px;font-size:18px;font-weight:700">My rank: #' + res.my_rank + '</div>' : '' ) +
			'</div>';

		html += '<h2>Leaderboard</h2>';
		if ( ! board.length ) {
			html += '<p class="hint">Leaderboard is being computed.</p>';
		} else {
			html += board.map( function ( en, i ) {
				var isMe = ( Number( en.user_id ) === meId );
				var rows = '<div class="list-item ' + ( isMe ? 'mine' : '' ) + '">' +
					'<span class="rank">' + medal( i ) + '</span>' +
					'<div class="avatar">' + ( en.user_id ? 'B' : '?' ) + '</div>' +
					'<div class="row-main"><b>' + ( isMe ? 'You' : 'Learner #' + en.user_id ) + '</b>' +
					'<small class="muted">' + app.fmtXp( en.weekly_xp ) + ' XP this week</small></div>' +
					'</div>';
				return rows;
			} ).join( '' );
		}

		mount.innerHTML = html;
	}

	function user( res ) {
		// current_user id not in this payload; caller may pass via A._meId.
		return A._meId || 0;
	}
	function medal( i ) {
		return i === 0 ? '🥇' : ( i === 1 ? '🥈' : ( i === 2 ? '🥉' : String( i + 1 ) ) );
	}
} )();