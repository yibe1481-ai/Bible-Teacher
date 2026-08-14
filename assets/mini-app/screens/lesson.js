/**
 * Lesson screen — orchestrates the daily lesson flow:
 * vocab → listening → quiz → speaking → writing → results.
 *
 * Steps: vocab & listening are defined here; quiz, speaking, and writing
 * register themselves into BEApp._flow.steps from their own files.
 */
( function () {
	'use strict';
	var A = window.BEApp;

	A._flow = A._flow || { steps: {}, today: null, answers: { quiz: [], writing: '' } };

	A.screens['lesson'] = {
		render: function ( mount, user, params, app ) {
			mount.innerHTML = app.spinner();
			app.get( '/lesson/today' ).then( function ( today ) {
				A._flow.today = today;
				A._flow.answers = { quiz: [], writing: '' };
				runStep( mount, 'vocab', app );
			} ).catch( function ( err ) {
				mount.innerHTML = '<div class="state"><div class="state-title">Could not load today’s lesson</div>' +
					'<p class="state-sub">' + app.esc( err && err.message ? err.message : '' ) + '</p>' +
					'<button class="btn" id="l-back">Back to today</button></div>';
				var b = document.getElementById( 'l-back' );
				b && b.addEventListener( 'click', function () { app.navigate( 'home' ); } );
			} );
		}
	};

	// Public dispatcher that step modules call to advance the flow.
	A._flow.step = function ( mount, nextStep, app ) {
		runStep( mount, nextStep, app );
	};

	function runStep( mount, step, app ) {
		var today = A._flow.today;
		if ( A._flow.steps[step] ) {
			return A._flow.steps[step]( mount, today, app );
		}
		// Fall back to local handlers.
		switch ( step ) {
			case 'vocab':     return renderVocab( mount, today, app );
			case 'listening': return renderListening( mount, today, app );
		}
	}

	// ---- Vocab -------------------------------------------------------------

	function renderVocab( mount, today, app ) {
		var list = today.vocab && ( today.vocab.words || today.vocab.items ) ? ( today.vocab.words || today.vocab.items ) : [];
		mount.innerHTML = '<div class="step-label">Step 1 · Vocabulary</div>' +
			'<h1>' + app.esc( today.reference ) + '</h1>' +
			'<p class="verse-text">“' + app.esc( today.verse.text ) + '”</p>' +
			'<p class="hint">Learn the key words before you continue.</p>' +
			listHtml( list, app ) +
			'<div style="height:12px"></div>' +
			'<button class="btn" id="vocab-next">I’ve learned these words →</button>';

		document.getElementById( 'vocab-next' ).addEventListener( 'click', function () {
			app.haptic();
			app.post( '/lesson/vocab/complete', {} ).then( function () {
				A._flow.step( mount, 'listening', app );
			} ).catch( function () { A._flow.step( mount, 'listening', app ); } );
		} );
	}

	function listHtml( list, app ) {
		if ( ! list.length ) { return '<p class="hint">No new words today.</p>'; }
		var out = '';
		list.forEach( function ( w ) {
			out += '<div class="vocab-item" style="background:var(--tg-theme-secondary,var(--be-fallback-secondary));border-radius:12px;padding:12px 14px;margin-bottom:8px;">' +
				'<div class="vocab-word">' + app.esc( w.word || w.term ) + '</div>' +
				'<div class="hint">' + app.esc( ( w.meaning || w.definition || '' ) ) + '</div>' +
				( w.example ? '<div class="muted">' + app.esc( w.example ) + '</div>' : '' ) +
				'</div>';
		} );
		return out;
	}

	// ---- Listening ---------------------------------------------------------

	function renderListening( mount, today, app ) {
		var audio = today.listening && today.listening.audio_url;
		mount.innerHTML = '<div class="step-label">Step 2 · Listening</div>' +
			'<h1>' + app.esc( today.reference ) + '</h1>' +
			'<p class="hint">Listen to today’s verse, then read it aloud in the next step.</p>' +
			( audio ? '<div class="card center"><audio controls style="width:100%" src="' + app.esc( audio ) + '"></audio>' +
				'<p class="hint">Play the verse as many times as you like.</p></div>'
				: '<div class="card center"><p class="hint">Audio is generating — try the reading step.</p></div>' ) +
			'<button class="btn" id="listen-ok">I’ve listened →</button>';

		document.getElementById( 'listen-ok' ).addEventListener( 'click', function () {
			app.haptic();
			app.post( '/lesson/listening/complete', {} ).then( function () {
				A._flow.step( mount, 'quiz', app );
			} ).catch( function () { A._flow.step( mount, 'quiz', app ); } );
		} );
	}
} )();