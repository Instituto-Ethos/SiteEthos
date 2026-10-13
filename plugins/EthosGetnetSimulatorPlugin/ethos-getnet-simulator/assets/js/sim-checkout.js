( function () {
	'use strict';

	var cfg = window.ethosGetnetSim || {};

	function modalForTrigger( trigger ) {
		var intent = trigger.getAttribute( 'data-ethos-getnet-sim-trigger' );
		return document.querySelector( '[data-ethos-getnet-sim-modal][data-intent="' + intent + '"]' );
	}

	function openModal( modal ) {
		modal.hidden = false;
	}

	function closeModal( modal ) {
		modal.hidden = true;
	}

	function resultBox( modal ) {
		return modal.querySelector( '.ethos-getnet-sim-result' );
	}

	function showResult( modal, data ) {
		var box = resultBox( modal );
		box.hidden = false;
		box.textContent = JSON.stringify( data, null, 2 );

		var webhook = data.webhook || {};
		if ( webhook.status === 200 || ( webhook.body && webhook.body.processed ) ) {
			var link = modal.querySelector( '.ethos-getnet-sim-status-link' );
			link.hidden = false;
		}
	}

	function fireOutcome( modal, outcome, paymentId ) {
		var box = resultBox( modal );
		box.hidden = false;
		box.textContent = 'Enviando…';

		var body = {
			intent_id: modal.getAttribute( 'data-intent' ),
			outcome: outcome,
			token: modal.getAttribute( 'data-token' ) || ''
		};

		if ( paymentId ) {
			body.payment_id = paymentId;
		}

		window.fetch( cfg.restUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'same-origin',
			body: JSON.stringify( body )
		} )
			.then( function ( response ) { return response.json(); } )
			.then( function ( data ) {
				showResult( modal, data );

				if ( data.payment_id ) {
					modal.setAttribute( 'data-last-payment', data.payment_id );
					modal.setAttribute( 'data-last-outcome', outcome );
					modal.querySelector( '.ethos-getnet-sim-redeliver' ).hidden = false;
				}
			} )
			.catch( function ( error ) {
				box.textContent = 'Falha: ' + error.message;
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-ethos-getnet-sim-trigger]' );
		if ( trigger ) {
			var modal = modalForTrigger( trigger );
			if ( modal ) {
				openModal( modal );
			}
			return;
		}

		var outcomeButton = event.target.closest( '.ethos-getnet-sim-outcome' );
		if ( outcomeButton ) {
			var modal2 = outcomeButton.closest( '[data-ethos-getnet-sim-modal]' );
			if ( modal2 ) {
				fireOutcome( modal2, outcomeButton.getAttribute( 'data-outcome' ), null );
			}
			return;
		}

		var redeliver = event.target.closest( '.ethos-getnet-sim-redeliver' );
		if ( redeliver && ! redeliver.hidden ) {
			var modal3 = redeliver.closest( '[data-ethos-getnet-sim-modal]' );
			var lastOutcome = modal3.getAttribute( 'data-last-outcome' );
			var lastPayment = modal3.getAttribute( 'data-last-payment' );
			if ( lastOutcome && lastPayment ) {
				fireOutcome( modal3, lastOutcome, lastPayment );
			}
			return;
		}

		var close = event.target.closest( '.ethos-getnet-sim-close' );
		if ( close ) {
			closeModal( close.closest( '[data-ethos-getnet-sim-modal]' ) );
			return;
		}

		if ( event.target.matches( '.ethos-getnet-sim-backdrop' ) ) {
			closeModal( event.target );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key !== 'Escape' ) {
			return;
		}
		document.querySelectorAll( '[data-ethos-getnet-sim-modal]' ).forEach( function ( modal ) {
			if ( ! modal.hidden ) {
				closeModal( modal );
			}
		} );
	} );
} )();
