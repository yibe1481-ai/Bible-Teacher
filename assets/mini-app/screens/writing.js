/**
 * Writing screen — recall the verse into a textarea, submit for scoring.
 * Mode (fill-the-gap / paraphrase / free recall) comes from today.writing.
 */
( function () {
	'use strict';
	var A = window.BEApp;

	A._flow.steps['writing'] = renderWriting;

	function renderWriting( mount, today, app ) {
		var writing = today.writing && today.writing.mode ? today.writing.mode : 'free';
		var title   = modeTitle( writing );

		mount.innerHTML =
			'<div class="step-label">Step 5 · Writing</div>' +
			'<h1>' + title + '</h1>' +
			'<p class="verse-text" style="font-size:16px">' + app.esc( today.verse.text ) + '</p>' +
			'<p class="hint">' + modeHint( writing ) + '</p>' +
			'<div style="height:8px"></div>' +
			'<textarea class="inp" id="write-text" rows="4" placeholder="Type here…" data-gramm="false"></textarea>' +
			'<button class="btn" id="write-submit">Submit My Writing ✍️</button>';

		var submit = document.getElementById( 'write-submit' );
		var text   = document.getElementById( 'write-text' );
		submit.disabled = true;

		text.addEventListener( 'input', function () {
			submit.disabled = text.value.trim().length < 3;
		} );

		submit.addEventListener( 'click', function () {
			submit.disabled = true;
			submit.textContent = 'Scoring…';
			app.post( '/lesson/writing/submit', { text: text.value.trim() } ).then( function ( res ) {
				A._writingResult = res;
				finish( mount, app, res );
			} ).catch( function ( err ) {
				A._writingResult = ( err && err.body ) ? err.body : {};
				finish( mount, app, A._writingResult );
			} );
		} );
	}

	function finish( mount, app, res ) {
		// Display a brief writing-score summary then jump to results.
		mount.innerHTML = '<div class="card center"><h2>Writing submitted ✍️</h2>' +
			( res && res.feedback ? '<p class="muted">' + app.esc( res.feedback ) + '</p>' : '' ) +
			'<button class="btn" id="w-see">See My Results →</button></div>';
		document.getElementById( 'w-see' ).addEventListener( 'click', function () {
			app.haptic();
			app.navigate( 'results' );
		} );
	}

	function modeTitle( m ) {
		switch ( m ) {
			case 'fill':     return 'Fill the Gap';
			case 'paraphrase': return 'Paraphrase the Verse';
			default:         return 'Write from Memory';
		}
	}
	function modeHint( m ) {
		switch ( m ) {
			case 'fill':     return 'Fill in the missing words from the verse above.';
			case 'paraphrase': return 'Rewrite today’s verse in your own words.';
			default:         return 'Recount today’s verse in your own words. Focus on meaning, not exact wording.';
		}
	}
} )();