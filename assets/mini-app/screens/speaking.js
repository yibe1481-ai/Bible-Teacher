/**
 * Speaking screen — records audio with MediaRecorder and uploads it as a
 * multipart `audio` field. Uses MediaRecorder, never the Web Speech API
 * (unreliable inside the Telegram Mini App on Android — spec §9.2).
 */
( function () {
	'use strict';
	var A = window.BEApp;

	A._flow.steps['speaking'] = renderSpeaking;

	function renderSpeaking( mount, today, app ) {
		mount.innerHTML =
			'<div class="step-label">Step 4 · Speaking</div>' +
			'<h1>' + app.esc( today.reference ) + '</h1>' +
			'<p class="verse-text" style="font-size:17px">' + app.esc( today.verse.text ) + '</p>' +
			'<p class="hint">Tap and read the verse aloud. Hold to record.</p>' +
			'<div style="height:8px"></div>' +
			'<div class="card center">' +
				'<button class="record-btn" id="rec-btn">🎤</button>' +
				'<p class="record-hint" id="rec-hint">Tap to start recording</p>' +
				'<div id="rec-wave" style="min-height:24px"></div>' +
			'</div>' +
			'<button class="btn" id="rec-submit" disabled>📤 Submit & Score My Reading</button>';

		var btn   = document.getElementById( 'rec-btn' );
		var sub   = document.getElementById( 'rec-submit' );
		var hint  = document.getElementById( 'rec-hint' );
		var wave  = document.getElementById( 'rec-wave' );
		var mediaRec = null, chunks = [], timer = null, elapsed = 0;

		function tick() {
			elapsed++;
			var m = Math.floor( elapsed / 60 ), s = elapsed % 60;
			hint.textContent = 'Recording ' + ( m < 10 ? '0' + m : m ) + ':' + ( s < 10 ? '0' + s : s );
		}
		function waves() {
			var dots = [ '⠋','⠙','⠹','⠸','⠼','⠴','⠦','⠧','⠇','⠏' ];
			var n = 0;
			wave.textContent = dots[0];
			window.clearInterval( window._beWave );
			window._beWave = setInterval( function () { n = ( n + 1 ) % dots.length; wave.textContent = dots[n]; }, 120 );
		}

		function stop() {
			if ( mediaRec ) { try { mediaRec.stop(); } catch ( e ) { /* ignore */ } }
			clearInterval( timer );
			clearInterval( window._beWave );
			btn.classList.remove( 'recording' );
			btn.textContent = '🎤';
			hint.textContent = 'Processing…';
		}

		btn.addEventListener( 'click', function () {
			if ( mediaRec && mediaRec.state === 'recording' ) {
				stop();
				return;
			}
			startRecording();
		} );

		function startRecording() {
			if ( ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia ) {
				hint.textContent = 'Recording is not supported in this browser.';
				return;
			}
			navigator.mediaDevices.getUserMedia( { audio: true } ).then( function ( stream ) {
				var Mime = window.MediaRecorder;
				var mimeType = Mime.isTypeSupported( 'audio/webm' ) ? 'audio/webm' : 'audio/ogg';
				mediaRec = new Mime( stream, { mimeType: mimeType } );
				chunks = []; elapsed = 0;
				mediaRec.ondataavailable = function ( e ) { if ( e.data.size ) { chunks.push( e.data ); } };
				mediaRec.onstop = function () {
					stream.getTracks().forEach( function ( t ) { t.stop(); } );
					var blob = new Blob( chunks, { type: mimeType } );
					if ( blob.size < 200 ) {
						hint.textContent = 'Recording was too short — try again.';
						sub.disabled = true;
						return;
					}
					sub.disabled = false;
					sub.dataset.blob = ''; // store via closure
					sub.addEventListener( 'click', function () { upload( blob, mimeType, app ); }, { once: true } );
					hint.textContent = 'Ready — submit to score your reading.';
					btn.textContent = '🎤';
				};
				mediaRec.start();
				btn.classList.add( 'recording' );
				btn.textContent = '⏹';
				sub.disabled = true;
				timer = setInterval( tick, 1000 );
				waves();
			} ).catch( function () {
				hint.textContent = 'Microphone access was denied.';
			} );
		}
	}

	function upload( blob, type, app ) {
		var mounted = document.getElementById( 'app' );
		mounted.innerHTML = app.spinner();
		var fd = new FormData();
		fd.append( 'audio', blob, 'speaking.webm' );
		app.post( '/lesson/speaking/submit', fd, true ).then( function ( res ) {
			A._speakingResult = res;
			A._flow.step( mounted, 'writing', app );
		} ).catch( function ( err ) {
			if ( err && ( err.status === 400 || err.status === 413 ) ) {
				mounted.innerHTML = '<div class="state"><div class="state-title">Could not score recording</div>' +
					'<p class="state-sub">' + app.esc( err.message ) + '</p>' +
					'<button class="btn" id="sp-retry">Back to lesson</button></div>';
				document.getElementById( 'sp-retry' ).addEventListener( 'click', function () { app.navigate( 'lesson' ); } );
			} else {
				// Transcription may be unavailable but XP was awarded server-side.
				A._speakingResult = err && err.body ? err.body : {};
				A._flow.step( mounted, 'writing', app );
			}
		} );
	}
} )();