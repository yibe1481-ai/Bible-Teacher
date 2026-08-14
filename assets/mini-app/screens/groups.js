/**
 * Groups screen — join/leave/create church groups and view their leaderboards.
 */
( function () {
	'use strict';
	var A = window.BEApp;

	A.screens['groups'] = {
		render: function ( mount, user, params, app ) {
			mount.innerHTML = app.spinner();
			app.get( '/groups/mine' ).then( function ( res ) { renderGroups( mount, res.groups || [], app ); } )
				.catch( function () { renderGroups( mount, [], app ); } );
		}
	};

	function renderGroups( mount, groups, app ) {
		// Route with an id param → show that group's leaderboard.
		var target = Number( A._groupsTarget || 0 );
		A._groupsTarget = 0;
		var match = groups.find( function ( g ) { return Number( g.id ) === target; } ) ||
			groups.find( function ( g ) { return Number( g.group_id ) === target; } );

		if ( target && ( match ) ) {
			return renderGroupBoard( mount, match, app );
		}

		var html = '<h1>👥 Groups</h1>';
		if ( ! groups.length ) {
			html += '<div class="state"><div class="state-title">No groups yet</div>' +
				'<p class="state-sub">Create a church group or join one with a code.</p></div>';
		} else {
			html += groups.map( function ( g ) {
				var id = g.id || g.group_id;
				return '<div class="list-item">' +
					'<div class="avatar">⛪</div>' +
					'<div class="row-main"><b>' + app.esc( g.name || 'Group' ) + '</b>' +
					'<small class="muted">' + app.fmtXp( g.member_count || 0 ) + ' members · ' + app.esc( ( g.status || '' ) ) + '</small></div>' +
					'<button class="btn ghost" data-g="' + id + '" style="width:auto;padding:8px 12px;font-size:13px">Open</button>' +
					'</div>';
			} ).join( '' );
		}

		html += '<div style="height:12px"></div>';
		html += '<!-- Join/create --><div class="card">' +
			'<h3>Join by invite code</h3>' +
			'<input class="inp" id="g-code" placeholder="Enter 6-letter code">' +
			'<button class="btn" id="g-join">Join Group</button>' +
			'</div>';

		html += '<div class="card">' +
			'<h3>Start a group</h3>' +
			'<input class="inp" id="g-name" placeholder="Group name">' +
			'<input class="inp" id="g-desc" placeholder="Short description (optional)">' +
			'<button class="btn" id="g-create">Create Group</button>' +
			'</div>';

		mount.innerHTML = html;

		document.querySelectorAll( '[data-g]' ).forEach( function ( el ) {
			el.addEventListener( 'click', function () {
				A._groupsTarget = Number( el.getAttribute( 'data-g' ) );
				app.haptic();
				app.navigate( 'groups' );
			} );
		} );

		document.getElementById( 'g-join' ).addEventListener( 'click', function () {
			var code = document.getElementById( 'g-code' ).value.trim();
			if ( ! code ) { app.toast( 'Enter a code' ); return; }
			app.post( '/groups/join', { code: code } ).then( function () {
				app.toast( 'Joined ✓' ); app.haptic(); app.navigate( 'groups' );
			} ).catch( function ( err ) { app.toast( err && err.message ? err.message : 'Could not join' ); } );
		} );

		document.getElementById( 'g-create' ).addEventListener( 'click', function () {
			var name = document.getElementById( 'g-name' ).value.trim();
			if ( ! name ) { app.toast( 'Name your group' ); return; }
			app.post( '/groups/create', {
				name: name,
				description: document.getElementById( 'g-desc' ).value.trim(),
			} ).then( function () {
				app.toast( 'Created ✓' ); app.haptic(); app.navigate( 'groups' );
			} ).catch( function ( err ) { app.toast( err && err.message ? err.message : 'Could not create' ); } );
		} );
	}

	function renderGroupBoard( mount, g, app ) {
		var id = g.id || g.group_id;
		mount.innerHTML = app.spinner() + '<p class="hint">Loading leaderboard…</p>';
		app.get( '/groups/' + id + '/leaderboard' ).then( function ( res ) {
			var board = res.leaderboard || res || [];
			var meId  = A._meId || 0;
			var html  = '<h1>' + app.esc( g.name || 'Group' ) + '</h1>' +
				'<p class="hint">' + app.esc( ( g.description || ( g.invite_code ? 'Code: ' + g.invite_code : '' ) ) ) + '</p>';

			if ( board.length ) {
				html += board.map( function ( en, i ) {
					var isMe = Number( en.user_id ) === meId;
					return '<div class="list-item ' + ( isMe ? 'mine' : '' ) + '">' +
						'<span class="rank">' + ( i < 3 ? [ '🥇','🥈','🥉' ][i] : i + 1 ) + '</span>' +
						'<div class="avatar">' + ( en.role === 'admin' ? '⭐' : 'B' ) + '</div>' +
						'<div class="row-main"><b>' + ( isMe ? 'You' : 'Learner #' + en.user_id ) + '</b>' +
						'<small class="muted">' + app.fmtXp( en.weekly_xp ) + ' XP</small></div>' +
						'</div>';
				} ).join( '' );
			} else {
				html += '<p class="hint">No members yet.</p>';
			}

			html += '<div style="height:10px"></div><button class="btn plain" id="g-back">Back to groups</button>';
			mount.innerHTML = html;
			document.getElementById( 'g-back' ).addEventListener( 'click', function () { app.navigate( 'groups' ); } );
		} ).catch( function () {
			mount.innerHTML = '<div class="state"><div class="state-title">Could not load group</div>' +
				'<button class="btn" id="g-back2">Back</button></div>';
			document.getElementById( 'g-back2' ).addEventListener( 'click', function () { app.navigate( 'groups' ); } );
		} );
	}
} )();