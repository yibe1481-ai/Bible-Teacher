/**
 * Quiz screen — multiple-choice questions with instant right/wrong feedback.
 * Registers into BEApp._flow.steps.
 */
( function () {
	'use strict';
	var A = window.BEApp;

	A._flow.steps['quiz'] = renderQuiz;

	function renderQuiz( mount, today, app ) {
		var qs = today.quiz && today.quiz.questions ? today.quiz.questions : [];
		if ( ! qs.length ) {
			return A._flow.step( mount, 'speaking', app );
		}
		var i = 0, score = 0, selected = null;

		function draw() {
			var q = qs[i];
			var opts;
			try { opts = ( q.options && q.options.length ) ? q.options : ( q.choices || [] ); } catch ( e ) { opts = []; }

			var optionsHtml = opts.map( function ( o, n ) {
				var t = ( typeof o === 'object' ) ? ( o.text || o.option || '' ) : o;
				var cls = selected === null ? '' : ( n === q.correct_index ? 'correct' : ( n === selected ? 'incorrect' : '' ) );
				return '<button class="option ' + cls + '" data-i="' + n + '">' +
					String( 'ABC'[n] || '?' ) + '. ' + app.esc( t ) + '</button>';
			} ).join( '' );

			mount.innerHTML = '<div class="step-label">Step 3 · Quiz · ' + ( i + 1 ) + ' of ' + qs.length + '</div>' +
				'<div class="progress"><span style="width:' + Math.round( ( i / qs.length ) * 100 ) + '%"></span></div>' +
				'<div style="height:10px"></div>' +
				'<p class="verse-text" style="font-size:16px">' + app.esc( q.prompt || q.question || '' ) + '</p>' +
				optionsHtml +
				'<button class="btn" id="quiz-next" disabled>Next</button>';

			document.querySelectorAll( '.option' ).forEach( function ( el ) {
				el.addEventListener( 'click', function () {
					if ( selected !== null ) { return; }
					var n = Number( el.getAttribute( 'data-i' ) );
					selected = n;
					if ( n === q.correct_index ) { score++; app.haptic(); }
					draw();
					document.getElementById( 'quiz-next' ).disabled = false;
				} );
			} );

			document.getElementById( 'quiz-next' ).addEventListener( 'click', function () {
				A._flow.answers.quiz.push( selected );
				selected = null;
				i++;
				if ( i < qs.length ) { draw(); return; }
				app.post( '/lesson/quiz/submit', { answers: A._flow.answers.quiz } ).then( function ( res ) {
					A._quizResult = res && res.body ? res.body : res;
					renderPostQuiz( mount, today, app, A._quizResult );
				} ).catch( function ( err ) {
					A._quizResult = err && err.body ? err.body : {};
					renderPostQuiz( mount, today, app, A._quizResult );
				} );
			} );
		}

		draw();

		function renderPostQuiz( mount, today, app, res ) {
			var total = ( res && res.total !== undefined ) ? res.total : qs.length;
			var correct = ( res && res.score !== undefined ) ? res.score : score;
			mount.innerHTML = '<div class="card center"><h2>Quiz done! 🎯</h2>' +
				'<div class="score-num" style="font-size:30px;margin:6px 0">' + correct + ' / ' + total + '</div>' +
				( res && res.awarded ? '<p class="hint">+ ' + app.fmtXp( res.awarded ) + ' XP</p>' : '' ) +
				'<button class="btn" id="quiz-continue">Continue → Speaking</button></div>';
			document.getElementById( 'quiz-continue' ).addEventListener( 'click', function () {
				app.haptic();
				A._flow.step( mount, 'speaking', app );
			} );
		}
	}
} )();