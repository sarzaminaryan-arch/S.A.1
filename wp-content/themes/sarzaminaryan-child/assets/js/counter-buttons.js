/* Homepage counter-button press feedback (vanilla JS, no jQuery). */
( function () {
	'use strict';

	var buttons = document.querySelectorAll( '.counter-btn' );
	if ( ! buttons.length ) {
		return;
	}

	var passive = { passive: true };

	function lightOn( event ) {
		event.currentTarget.classList.add( 'light-up' );
	}

	function lightOff( event ) {
		event.currentTarget.classList.remove( 'light-up' );
	}

	function isActivationKey( event ) {
		return 'Enter' === event.key || ' ' === event.key || 'Spacebar' === event.key || 'Space' === event.code;
	}

	buttons.forEach( function ( button ) {
		// Pointer Events cover mouse, touch and modern iOS Safari.
		button.addEventListener( 'pointerdown', lightOn, passive );
		button.addEventListener( 'pointerup', lightOff, passive );
		button.addEventListener( 'pointercancel', lightOff, passive );
		button.addEventListener( 'pointerleave', lightOff, passive );

		// Keep keyboard feedback momentary without blocking native link behaviour.
		button.addEventListener( 'keydown', function ( event ) {
			if ( isActivationKey( event ) ) {
				lightOn( event );
			}
		}, passive );
		button.addEventListener( 'keyup', function ( event ) {
			if ( isActivationKey( event ) ) {
				lightOff( event );
			}
		}, passive );
		button.addEventListener( 'blur', lightOff, passive );
	} );
}() );
