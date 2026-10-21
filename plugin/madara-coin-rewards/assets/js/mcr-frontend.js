/**
 * Madara Coin & Rewards - frontend behaviour.
 *
 * One vanilla bundle, no dependencies, no build step. Every module is guarded
 * by the presence of its `data-mcr-*` hook, so shipping this file on any page
 * is safe.
 *
 * The client never grants coins. It asks the server, then repaints what the
 * server returned: balances from `/balance`, ad rewards from `/ad/verify`,
 * transaction rows from `/transactions`.
 *
 * @package MCR
 */

( function ( window, document ) {
	'use strict';

	var data = window.mcrData;

	if ( ! data || ! document.documentElement ) {
		return;
	}

	var strings = data.i18n || data.strings || {};
	var state = {
		skew: 0,
		renderSkew: 0,
		serverRemaining: null,
		serverRemainingAt: null,
		nextIn: null,
		ticker: null,
		lastToast: '',
		lastToastAt: 0,
		toasts: null
	};

	var ROUTE_FALLBACKS = {
		balance: 'balance',
		checkin: 'checkin',
		adStart: 'ad/start',
		adVerify: 'ad/verify',
		orders: 'orders',
		transactions: 'transactions'
	};

	/* --------------------------------------------------------------------
	 * Small utilities
	 * ------------------------------------------------------------------ */

	function str( key ) {
		return typeof strings[ key ] === 'string' ? strings[ key ] : '';
	}

	function format( template ) {
		if ( typeof template !== 'string' ) {
			return '';
		}

		var args = Array.prototype.slice.call( arguments, 1 );
		var next = 0;

		return template.replace( /%(\d+)\$s|%s/g, function ( match, position ) {
			var value;

			if ( position ) {
				value = args[ parseInt( position, 10 ) - 1 ];
			} else {
				value = args[ next ];
				next += 1;
			}

			return value === undefined || value === null ? '' : String( value );
		} );
	}

	var numberFormatter = null;

	try {
		if ( window.Intl && window.Intl.NumberFormat ) {
			numberFormatter = new window.Intl.NumberFormat( data.locale || undefined );
		}
	} catch ( error ) {
		numberFormatter = null;
	}

	function number( value ) {
		var parsed = parseInt( value, 10 );

		if ( isNaN( parsed ) ) {
			parsed = 0;
		}

		if ( numberFormatter ) {
			return numberFormatter.format( parsed );
		}

		return String( parsed );
	}

	function route( name ) {
		var value = data.routes && data.routes[ name ] ? String( data.routes[ name ] ) : '';

		return value || ROUTE_FALLBACKS[ name ] || name;
	}

	function pad( value ) {
		return value < 10 ? '0' + value : String( value );
	}

	function buttonLabel( button ) {
		return String( button && button.textContent ? button.textContent : '' ).replace( /\s+/g, ' ' ).replace( /^\s+|\s+$/g, '' );
	}

	function each( list, callback ) {
		Array.prototype.forEach.call( list || [], callback );
	}

	function api( path, options ) {
		var settings = options || {};
		var headers = { 'X-WP-Nonce': data.nonce, Accept: 'application/json' };
		var index;

		if ( settings.headers ) {
			for ( index in settings.headers ) {
				if ( Object.prototype.hasOwnProperty.call( settings.headers, index ) ) {
					headers[ index ] = settings.headers[ index ];
				}
			}
		}

		var request = {
			method: settings.method || 'GET',
			credentials: 'same-origin',
			headers: headers
		};

		if ( settings.body !== undefined ) {
			headers[ 'Content-Type' ] = 'application/json';
			request.body = JSON.stringify( settings.body );
		}

		return window.fetch( data.restUrl + path, request ).then( function ( response ) {
			/*
			 * Every HTTP response carries a Date header set by the server.
			 * It is the only FRESH source of the server clock, so the skew
			 * is measured from it rather than from a timestamp rendered
			 * into (possibly cached) HTML.
			 */
			syncClockFromResponse( response );

			return response.text().then( function ( text ) {
				var payload = null;

				if ( text ) {
					try {
						payload = JSON.parse( text );
					} catch ( error ) {
						payload = null;
					}
				}

				if ( ! response.ok ) {
					var failure = new Error( 'mcr_http' );
					failure.status = response.status;
					failure.data = payload && typeof payload === 'object' ? payload : {};
					throw failure;
				}

				return payload && typeof payload === 'object' ? payload : {};
			} );
		} );
	}

	/**
	 * Re-reads the server clock from a response Date header.
	 *
	 * @param {Object} response Fetch response.
	 */
	function syncClockFromResponse( response ) {
		if ( ! response || ! response.headers || ! response.headers.get ) {
			return;
		}

		var header = response.headers.get( 'date' );

		if ( ! header ) {
			return;
		}

		var serverTime = Date.parse( header );

		if ( isNaN( serverTime ) ) {
			return;
		}

		state.skew = serverTime - Date.now();
		state.skewed = Math.abs( state.skew ) > 120000;
	}

	function errorWithCode( code, status ) {
		var failure = new Error( code );
		failure.status = status || 0;
		failure.data = { code: code };
		return failure;
	}

	function errorCode( error ) {
		if ( error && error.data && error.data.code ) {
			return String( error.data.code );
		}

		return '';
	}

	function errorMessage( error ) {
		if ( error && error.data && error.data.message ) {
			return String( error.data.message );
		}

		return '';
	}

	/* --------------------------------------------------------------------
	 * Toasts
	 * ------------------------------------------------------------------ */

	function toastContainer() {
		if ( state.toasts && document.body.contains( state.toasts ) ) {
			return state.toasts;
		}

		var existing = document.querySelector( '.mcr-toasts' );

		if ( existing ) {
			state.toasts = existing;
			return existing;
		}

		var container = document.createElement( 'div' );
		container.className = 'mcr-portal mcr-toasts';
		container.setAttribute( 'role', 'region' );
		container.setAttribute( 'aria-label', str( 'notifications' ) || 'Notifications' );
		document.body.appendChild( container );
		state.toasts = container;

		return container;
	}

	function removeToast( toast ) {
		if ( ! toast || ! toast.parentNode ) {
			return;
		}

		toast.classList.add( 'is-leaving' );

		window.setTimeout( function () {
			if ( toast.parentNode ) {
				toast.parentNode.removeChild( toast );
			}
		}, 200 );
	}

	function toast( message, type, duration ) {
		message = String( message || '' ).replace( /\s+/g, ' ' ).replace( /^\s+|\s+$/g, '' );

		if ( ! message ) {
			return null;
		}

		if ( ! type || [ 'success', 'info', 'warning', 'error' ].indexOf( type ) === -1 ) {
			type = 'info';
		}

		var now = Date.now();

		if ( message === state.lastToast && now - state.lastToastAt < 1500 ) {
			return null;
		}

		state.lastToast = message;
		state.lastToastAt = now;

		var container = toastContainer();
		var visible = container.querySelectorAll( '.mcr-toast:not(.is-leaving)' );

		while ( visible.length >= 3 ) {
			removeToast( visible[ 0 ] );
			visible = container.querySelectorAll( '.mcr-toast:not(.is-leaving)' );
		}

		var item = document.createElement( 'div' );
		item.className = 'mcr-toast mcr-toast--' + type;
		item.setAttribute( 'role', 'error' === type ? 'alert' : 'status' );
		item.setAttribute( 'aria-atomic', 'true' );

		var text = document.createElement( 'span' );
		text.className = 'mcr-toast__text';
		text.textContent = message;

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'mcr-toast__close';
		close.setAttribute( 'aria-label', str( 'dismiss' ) || 'Dismiss' );
		close.textContent = '\u00d7';
		close.addEventListener( 'click', function () {
			removeToast( item );
		} );

		item.appendChild( text );
		item.appendChild( close );
		container.appendChild( item );

		var lifetime = typeof duration === 'number' ? duration : ( 'error' === type ? 0 : ( 'warning' === type ? 10000 : 6000 ) );

		if ( lifetime > 0 ) {
			window.setTimeout( function () {
				removeToast( item );
			}, lifetime );
		}

		return item;
	}

	function newestToast() {
		var container = state.toasts || document.querySelector( '.mcr-toasts' );

		if ( ! container ) {
			return null;
		}

		var items = container.querySelectorAll( '.mcr-toast:not(.is-leaving)' );

		return items.length ? items[ items.length - 1 ] : null;
	}

	/* --------------------------------------------------------------------
	 * Inline alerts (same classes as parts/notice.php)
	 * ------------------------------------------------------------------ */

	function clearNode( node ) {
		while ( node && node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function feedbackTarget( element ) {
		var scope = element && element.closest ? element.closest( '.mcr-card' ) : null;

		if ( ! scope && element && element.closest ) {
			scope = element.closest( '.mcr-section' );
		}

		if ( ! scope ) {
			scope = document.querySelector( '[data-mcr-page]' ) || document.body;
		}

		var target = scope.querySelector( '[data-mcr-checkin-feedback], [data-mcr-ad-feedback], [data-mcr-buy-feedback]' );

		if ( target ) {
			return target;
		}

		target = document.createElement( 'div' );
		scope.appendChild( target );

		return target;
	}

	function alertBox( target, type, title, text, actions ) {
		if ( ! target ) {
			return;
		}

		clearNode( target );

		if ( [ 'info', 'success', 'warning', 'error' ].indexOf( type ) === -1 ) {
			type = 'info';
		}

		var icons = { info: 'i', success: '\u2713', warning: '!', error: '!' };
		var alert = document.createElement( 'div' );
		alert.className = 'mcr-alert mcr-alert--' + type;
		alert.setAttribute( 'role', 'error' === type ? 'alert' : 'status' );

		var icon = document.createElement( 'span' );
		icon.className = 'mcr-alert__icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		icon.textContent = icons[ type ];

		var body = document.createElement( 'div' );
		body.className = 'mcr-alert__body';

		if ( title ) {
			var heading = document.createElement( 'strong' );
			heading.className = 'mcr-alert__title';
			heading.textContent = String( title );
			body.appendChild( heading );
		}

		if ( text ) {
			var paragraph = document.createElement( 'p' );
			paragraph.className = 'mcr-alert__text';
			paragraph.textContent = String( text );
			body.appendChild( paragraph );
		}

		if ( actions && actions.length ) {
			var row = document.createElement( 'div' );
			row.className = 'mcr-alert__actions';

			each( actions, function ( action ) {
				if ( ! action || ! action.label ) {
					return;
				}

				var button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'mcr-btn mcr-btn--sm mcr-btn--quiet';
				button.textContent = String( action.label );

				if ( typeof action.onClick === 'function' ) {
					button.addEventListener( 'click', action.onClick );
				}

				row.appendChild( button );
			} );

			if ( row.firstChild ) {
				body.appendChild( row );
			}
		}

		alert.appendChild( icon );
		alert.appendChild( body );
		target.appendChild( alert );
	}

	/* --------------------------------------------------------------------
	 * Balance repaint + live region
	 * ------------------------------------------------------------------ */

	function repaintBalance( balance, announce ) {
		var parsed = parseInt( balance, 10 );

		if ( isNaN( parsed ) ) {
			return;
		}

		each( document.querySelectorAll( '[data-mcr-balance]' ), function ( element ) {
			var value = element.querySelector( '.mcr-amount__value' );

			element.setAttribute( 'data-mcr-amount', String( parsed ) );

			if ( value ) {
				value.textContent = number( parsed );
			}

			element.classList.remove( 'is-updated' );

			// Restart the animation on repeated updates.
			void element.offsetWidth;
			element.classList.add( 'is-updated' );

			window.setTimeout( function () {
				element.classList.remove( 'is-updated' );
			}, 1000 );
		} );

		if ( announce ) {
			/*
			 * A page can render the plugin more than once (a dashboard plus
			 * a sidebar balance panel), and each render has its own live
			 * region, so every one of them is announced - not just the
			 * first.
			 */
			each( document.querySelectorAll( '[data-mcr-live="balance"]' ), function ( live ) {
				live.textContent = format( str( 'balanceUpdated' ), number( parsed ) );
			} );
		}
	}

	function refreshBalance( announce ) {
		return api( route( 'balance' ) )
			.then( function ( payload ) {
				if ( payload && typeof payload.balance !== 'undefined' ) {
					repaintBalance( payload.balance, !! announce );
				}

				if ( payload && payload.checkin && typeof payload.checkin.next_in !== 'undefined' ) {
					state.nextIn = parseInt( payload.checkin.next_in, 10 ) || 0;
					state.serverRemaining = state.nextIn;
					state.serverRemainingAt = Date.now();
				}

				return payload;
			} )
			.catch( function () {
				return null;
			} );
	}

	/* --------------------------------------------------------------------
	 * Countdown ticker (single interval for every [data-mcr-deadline])
	 * ------------------------------------------------------------------ */

	function secondsUntilNextDay() {
		var now = new Date( Date.now() + state.skew );
		var next = new Date( now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 0, 0 );

		return Math.max( 0, Math.round( ( next.getTime() - now.getTime() ) / 1000 ) );
	}

	function deadlineFromSeconds( seconds ) {
		seconds = parseInt( seconds, 10 );

		if ( isNaN( seconds ) || seconds < 0 ) {
			seconds = 0;
		}

		return new Date( Date.now() + state.skew + ( seconds * 1000 ) ).toISOString();
	}

	function countdownElement( deadline, small ) {
		var wrapper = document.createElement( 'div' );
		wrapper.className = 'mcr-countdown' + ( small ? ' mcr-countdown--sm' : '' );
		wrapper.setAttribute( 'data-mcr-deadline', deadline );

		var units = [
			{ key: 'h', label: 'h' },
			{ key: 'm', label: 'm' },
			{ key: 's', label: 's' }
		];

		each( units, function ( unit, index ) {
			if ( index > 0 ) {
				var separator = document.createElement( 'span' );
				separator.className = 'mcr-countdown__sep';
				separator.setAttribute( 'aria-hidden', 'true' );
				separator.textContent = ':';
				wrapper.appendChild( separator );
			}

			var box = document.createElement( 'span' );
			box.className = 'mcr-countdown__unit';

			var value = document.createElement( 'span' );
			value.className = 'mcr-countdown__num';
			value.setAttribute( 'data-mcr-unit', unit.key );
			value.textContent = '00';

			var label = document.createElement( 'span' );
			label.className = 'mcr-countdown__label';
			label.textContent = unit.label;

			box.appendChild( value );
			box.appendChild( label );
			wrapper.appendChild( box );
		} );

		var live = document.createElement( 'span' );
		live.className = 'mcr-sr-only';
		live.setAttribute( 'data-mcr-live', '' );
		live.setAttribute( 'aria-live', 'polite' );
		live.setAttribute( 'aria-atomic', 'true' );
		wrapper.appendChild( live );

		return wrapper;
	}

	function announceCountdown( element, key ) {
		var live = element.querySelector( '[data-mcr-live]' );

		if ( live ) {
			live.textContent = str( key );
		}
	}

	function tickCountdowns() {
		if ( document.hidden ) {
			return;
		}

		each( document.querySelectorAll( '[data-mcr-deadline], [data-mcr-seconds]' ), function ( element ) {
			var secondsAttr = element.getAttribute( 'data-mcr-seconds' );
			var deadlineAttr = element.getAttribute( 'data-mcr-deadline' );
			var left;

			if ( secondsAttr !== null && secondsAttr !== '' ) {
				/*
				 * Server-issued remaining seconds. The page may be cached,
				 * so the absolute deadline can be stale; the server's own
				 * remaining value is the authority and is corrected
				 * whenever an API response refreshes it.
				 */
				var base = state.serverRemaining !== null ? state.serverRemaining : parseInt( secondsAttr, 10 );

				var elapsed = state.serverRemainingAt !== null
					? ( Date.now() - state.serverRemainingAt ) / 1000
					: 0;

				left = ( base - elapsed ) * 1000;
			} else {
				var deadline = Date.parse( deadlineAttr || '' );

				if ( isNaN( deadline ) ) {
					return;
				}

				left = deadline - ( Date.now() + state.skew );
			}

			if ( isNaN( left ) ) {
				return;
			}

			if ( left < 0 ) {
				left = 0;
			}

			var total = Math.ceil( left / 1000 );
			var hours = Math.floor( total / 3600 );
			var minutes = Math.floor( ( total % 3600 ) / 60 );
			var seconds = total % 60;

			var hoursNode = element.querySelector( '[data-mcr-unit="h"]' );
			var minutesNode = element.querySelector( '[data-mcr-unit="m"]' );
			var secondsNode = element.querySelector( '[data-mcr-unit="s"]' );
			var hoursBox = hoursNode ? hoursNode.parentNode : null;
			var separators = element.querySelectorAll( '.mcr-countdown__sep' );

			if ( hoursBox ) {
				hoursBox.classList.toggle( 'mcr-hidden', total < 3600 );
			}

			if ( separators.length ) {
				separators[ 0 ].classList.toggle( 'mcr-hidden', total < 3600 );
			}

			if ( minutesNode ) {
				minutesNode.textContent = pad( minutes );
			}

			if ( secondsNode ) {
				secondsNode.textContent = pad( seconds );
			}

			if ( hoursNode ) {
				hoursNode.textContent = pad( hours );
			}

			if ( total <= 0 ) {
				if ( element.getAttribute( 'data-mcr-done' ) !== '1' ) {
					element.setAttribute( 'data-mcr-done', '1' );
					element.classList.add( 'is-expired' );
					announceCountdown( element, element.getAttribute( 'data-mcr-ready-key' ) || 'readyToClaim' );

					var done;

					try {
						done = new window.CustomEvent( 'mcr:countdown-done', { bubbles: true } );
					} catch ( error ) {
						done = document.createEvent( 'CustomEvent' );
						done.initCustomEvent( 'mcr:countdown-done', true, false, null );
					}

					element.dispatchEvent( done );
				}

				return;
			}

			if ( total <= 10 && element.getAttribute( 'data-mcr-announced' ) !== '10' ) {
				element.setAttribute( 'data-mcr-announced', '10' );
				announceCountdown( element, 'announce10' );
			} else if ( total <= 60 && total > 10 && element.getAttribute( 'data-mcr-announced' ) !== '60' ) {
				element.setAttribute( 'data-mcr-announced', '60' );
				announceCountdown( element, 'announce60' );
			}
		} );
	}

	function startCountdowns() {
		if ( state.ticker ) {
			return;
		}

		tickCountdowns();
		state.ticker = window.setInterval( tickCountdowns, 1000 );
	}

	function stopCountdowns() {
		if ( state.ticker ) {
			window.clearInterval( state.ticker );
			state.ticker = null;
		}
	}

	/* --------------------------------------------------------------------
	 * Check-in
	 * ------------------------------------------------------------------ */

	function restoreButton( button, label ) {
		if ( ! button ) {
			return;
		}

		button.classList.remove( 'is-loading' );
		button.removeAttribute( 'aria-busy' );
		button.removeAttribute( 'aria-disabled' );
		button.disabled = false;

		if ( label ) {
			button.textContent = label;
		}
	}

	function swapClaimed( button, form ) {
		var container = form || button;

		if ( ! container || ! container.parentNode ) {
			return;
		}

		var seconds = state.nextIn === null ? secondsUntilNextDay() : state.nextIn;
		var fragment = document.createDocumentFragment();

		var badgeRow = document.createElement( 'div' );
		badgeRow.className = 'mcr-row';

		var badge = document.createElement( 'span' );
		badge.className = 'mcr-badge mcr-badge--success mcr-badge--dot';
		badge.textContent = str( 'checkedIn' ) || 'Checked in today';
		badgeRow.appendChild( badge );

		var countdownRow = document.createElement( 'div' );
		countdownRow.className = 'mcr-row';

		var label = document.createElement( 'span' );
		label.className = 'mcr-muted';
		label.textContent = str( 'nextCheckin' ) || 'Next check-in in';
		countdownRow.appendChild( label );
		countdownRow.appendChild( countdownElement( deadlineFromSeconds( seconds ), true ) );

		fragment.appendChild( badgeRow );
		fragment.appendChild( countdownRow );

		container.parentNode.insertBefore( fragment, container );

		/*
		 * The control that had focus is about to be removed, so focus
		 * would fall back to <body> and a keyboard user would lose their
		 * place. Move it to the replacement instead.
		 */
		var hadFocus = false;

		if ( document.activeElement === container || ( container.contains && container.contains( document.activeElement ) ) ) {
			hadFocus = true;
		}

		container.parentNode.removeChild( container );

		if ( hadFocus && badgeRow.setAttribute ) {
			badgeRow.setAttribute( 'tabindex', '-1' );
			badgeRow.focus();
		}
	}

	function handleClaimError( error, button, label, form, target ) {
		var status = error && error.status ? parseInt( error.status, 10 ) : 0;
		var code = errorCode( error );

		if ( 'mcr_already_checked_in' === code || 'mcr_already_claimed' === code || 409 === status ) {
			toast( str( 'alreadyClaimed' ) || 'You have already checked in today.', 'info' );
			refreshBalance( false );
			swapClaimed( button, form );
			return;
		}

		restoreButton( button, label );

		if ( 401 === status || 403 === status ) {
			alertBox( target, 'error', str( 'sessionExpired' ), '', [
				{
					label: str( 'reload' ) || 'Reload page',
					onClick: function () {
						window.location.reload();
					}
				}
			] );
			return;
		}

		if ( 429 === status ) {
			alertBox( target, 'error', '', str( 'tooMany' ) );
			return;
		}

		alertBox( target, 'error', '', errorMessage( error ) || str( 'claimFailed' ), [
			{
				label: str( 'retry' ) || 'Try again',
				onClick: function () {
					claim( button, form );
				}
			}
		] );
	}

	function claim( button, form ) {
		if ( ! button || button.getAttribute( 'aria-busy' ) === 'true' ) {
			return;
		}

		/*
		 * A skewed device clock is NOT a reason to refuse the claim. The
		 * server owns the day bucket and decides whether the reward is
		 * granted, so the request is simply sent and the server has the
		 * final say. Blocking here would strand a user whose clock is
		 * wrong.
		 */
		var label = buttonLabel( button );
		var target = feedbackTarget( button );

		clearNode( target );

		button.classList.add( 'is-loading' );
		button.setAttribute( 'aria-busy', 'true' );
		button.disabled = true;
		button.textContent = str( 'claiming' ) || 'Claiming...';

		api( route( 'checkin' ), { method: 'POST', body: {} } )
			.then( function ( response ) {
				var payload = response && response.data ? response.data : {};
				var coins = parseInt( payload.total, 10 );

				if ( isNaN( coins ) ) {
					coins = 0;
				}

				if ( payload.next_in !== undefined ) {
					state.nextIn = parseInt( payload.next_in, 10 ) || 0;
					state.serverRemaining = state.nextIn;
					state.serverRemainingAt = Date.now();
				}

				toast( format( str( 'added' ) || '+%s coins added.', number( coins ) ), 'success' );

				if ( response && response.message ) {
					alertBox( target, 'success', '', String( response.message ) );
				}

				refreshBalance( true );
				swapClaimed( button, form );
			} )
			.catch( function ( error ) {
				handleClaimError( error, button, label, form, target );
			} );
	}

	function initCheckin() {
		each( document.querySelectorAll( '[data-mcr-checkin-form]' ), function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var button = form.querySelector( '[data-mcr-checkin-claim]' ) || form.querySelector( 'button[type="submit"]' );

				claim( button, form );
			} );
		} );

		each( document.querySelectorAll( '[data-mcr-checkin-claim]' ), function ( button ) {
			if ( button.closest && button.closest( '[data-mcr-checkin-form]' ) ) {
				return;
			}

			button.addEventListener( 'click', function () {
				claim( button, null );
			} );
		} );
	}

	function warnAboutClockSkew() {
		if ( ! state.skewed ) {
			return;
		}

		var buttons = document.querySelectorAll( '[data-mcr-checkin-claim]' );

		if ( ! buttons.length ) {
			return;
		}

		/*
		 * A skewed device clock is INFORMATION, not a blocker. The server
		 * decides whether a claim is allowed, so disabling the button here
		 * would strand a user whose clock is wrong, and would stay
		 * disabled forever because the page is never re-checked. The
		 * warning explains the situation and the countdown is corrected
		 * from the server time we already measured.
		 */
		alertBox( feedbackTarget( buttons[ 0 ] ), 'warning', '', str( 'clockSkew' ) );
	}

	/* --------------------------------------------------------------------
	 * Ads
	 * ------------------------------------------------------------------ */

	function playProvider( session ) {
		var provider = window.mcrAdProvider;

		if ( ! provider || typeof provider.play !== 'function' ) {
			return window.Promise.reject( errorWithCode( 'mcr_ad_not_loaded' ) );
		}

		var result;

		try {
			result = provider.play( {
				ad_id: session.ad_id,
				session_token: session.session_token,
				signature: session.signature,
				coins: session.coins
			} );
		} catch ( error ) {
			return window.Promise.reject( errorWithCode( 'mcr_ad_not_loaded' ) );
		}

		return window.Promise.resolve( result ).then( function ( value ) {
			if ( ! value ) {
				return '';
			}

			if ( typeof value === 'string' ) {
				return value;
			}

			return value.completion_token ? String( value.completion_token ) : '';
		} );
	}

	function startAdCooldown( button, target, seconds, label ) {
		seconds = parseInt( seconds, 10 );

		if ( isNaN( seconds ) || seconds < 1 ) {
			return;
		}

		var countdown = countdownElement( deadlineFromSeconds( seconds ), true );
		countdown.setAttribute( 'data-mcr-ready-key', 'adReady' );
		var row = document.createElement( 'div' );
		row.className = 'mcr-row';

		var caption = document.createElement( 'span' );
		caption.className = 'mcr-muted';
		caption.textContent = str( 'nextAdInLabel' ) || 'Next ad in';

		row.appendChild( caption );
		row.appendChild( countdown );

		clearNode( target );
		target.appendChild( row );

		button.disabled = true;
		button.setAttribute( 'aria-disabled', 'true' );

		countdown.addEventListener( 'mcr:countdown-done', function () {
			restoreButton( button, label );

			if ( target ) {
				clearNode( target );
			}
		} );
	}

	function disableAdButtons() {
		each( document.querySelectorAll( '[data-mcr-ad-start]' ), function ( button ) {
			if ( button.getAttribute( 'data-mcr-capped' ) === '1' ) {
				return;
			}

			button.setAttribute( 'data-mcr-capped', '1' );
			button.classList.remove( 'is-loading' );
			button.removeAttribute( 'aria-busy' );
			button.disabled = true;
			button.setAttribute( 'aria-disabled', 'true' );
			button.textContent = str( 'dailyLimitReached' ) || 'Daily limit reached';
		} );
	}

	function updateAdAvailability( payload ) {
		if ( ! payload || ! payload.ads ) {
			return;
		}

		var remaining = parseInt( payload.ads.remaining, 10 );
		var today = parseInt( payload.ads.today, 10 );
		var limit = parseInt( payload.ads.limit, 10 );

		if ( isNaN( remaining ) ) {
			remaining = ( isNaN( limit ) ? 0 : limit ) - ( isNaN( today ) ? 0 : today );
		}

		if ( remaining < 1 ) {
			disableAdButtons();
		}
	}

	function handleAdError( error, button, label, target ) {
		restoreButton( button, label );

		var status = error && error.status ? parseInt( error.status, 10 ) : 0;
		var code = errorCode( error );
		var message = '';
		var retry = true;

		if ( 'mcr_ad_not_loaded' === code ) {
			message = str( 'adNotLoaded' );
		} else if ( 'mcr_invalid_completion' === code ) {
			message = str( 'adNotConfirmed' ) || str( 'adFailed' );
		} else if ( 'mcr_ad_not_configured' === code || 'mcr_provider_not_configured' === code ) {
			message = str( 'adNotConfigured' );
			retry = false;
		} else if ( 'mcr_daily_limit_reached' === code ) {
			// The server refuses further sessions today; stop offering the CTA.
			disableAdButtons();
			message = errorMessage( error ) || str( 'dailyLimitReached' );
			retry = false;
		} else if ( 'mcr_already_verified' === code ) {
			toast( str( 'adAlreadyCredited' ), 'info' );
			return;
		} else if ( 401 === status || 403 === status ) {
			message = str( 'sessionExpired' );
		} else if ( 429 === status ) {
			message = errorMessage( error ) || str( 'tooMany' );
		} else {
			message = errorMessage( error ) || str( 'adFailed' );
		}

		alertBox( target, 'error', '', message, retry ? [
			{
				label: str( 'retry' ) || 'Try again',
				onClick: function () {
					startAd( button );
				}
			}
		] : [] );
	}

	function startAd( button ) {
		if ( ! button || button.getAttribute( 'aria-busy' ) === 'true' ) {
			return;
		}

		var label = buttonLabel( button );
		var unit = button.getAttribute( 'data-mcr-ad-unit' ) || '';
		var cooldown = parseInt( button.getAttribute( 'data-mcr-cooldown' ), 10 );
		var target = feedbackTarget( button );
		var session = null;

		clearNode( target );
		button.classList.add( 'is-loading' );
		button.setAttribute( 'aria-busy', 'true' );
		button.disabled = true;
		button.textContent = str( 'starting' ) || 'Starting...';

		api( route( 'adStart' ), { method: 'POST', body: { ad_id: unit } } )
			.then( function ( response ) {
				session = response && response.data ? response.data : {};

				if ( ! session.session_token ) {
					throw errorWithCode( 'mcr_ad_not_configured', 400 );
				}

				button.textContent = str( 'watching' ) || 'Watching ad...';

				return playProvider( session );
			} )
			.then( function ( token ) {
				if ( ! token ) {
					throw errorWithCode( 'mcr_invalid_completion', 400 );
				}

				button.textContent = str( 'verifying' ) || 'Checking with the provider...';

				return api( route( 'adVerify' ), {
					method: 'POST',
					body: {
						session_token: session.session_token,
						completion_token: token
					}
				} );
			} )
			.then( function ( response ) {
				var payload = response && response.data ? response.data : {};
				var coins = parseInt( payload.coins, 10 );

				if ( isNaN( coins ) ) {
					coins = 0;
				}

				if ( payload.duplicate ) {
					toast( str( 'adAlreadyCredited' ), 'info' );
				} else {
					toast( format( str( 'added' ), number( coins ) ), 'success' );
				}

				if ( typeof payload.balance !== 'undefined' ) {
					repaintBalance( payload.balance, true );
				}

				restoreButton( button, label );

				refreshBalance( false ).then( function ( status ) {
					updateAdAvailability( status );

					var remaining = status && status.ads ? parseInt( status.ads.remaining, 10 ) : NaN;

					if ( isNaN( remaining ) || remaining > 0 ) {
						startAdCooldown( button, target, cooldown, label );
					}
				} );
			} )
			.catch( function ( error ) {
				handleAdError( error, button, label, target );
			} );
	}

	function initAds() {
		each( document.querySelectorAll( '[data-mcr-ad-start]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				startAd( button );
			} );
		} );
	}

	/* --------------------------------------------------------------------
	 * Buy coins
	 * ------------------------------------------------------------------ */

	function instructionText( instructions ) {
		if ( ! instructions ) {
			return '';
		}

		if ( typeof instructions === 'string' ) {
			return instructions;
		}

		if ( Array.isArray( instructions ) ) {
			return instructions.join( ' ' );
		}

		if ( typeof instructions === 'object' ) {
			var parts = [];

			for ( var key in instructions ) {
				if ( Object.prototype.hasOwnProperty.call( instructions, key ) ) {
					parts.push( String( instructions[ key ] ) );
				}
			}

			return parts.join( ' ' );
		}

		return '';
	}

	function startBuy( button ) {
		if ( ! button || button.getAttribute( 'aria-busy' ) === 'true' ) {
			return;
		}

		var label = buttonLabel( button );
		var packageId = button.getAttribute( 'data-mcr-package' ) || '';
		var target = feedbackTarget( button );

		clearNode( target );
		button.classList.add( 'is-loading' );
		button.setAttribute( 'aria-busy', 'true' );
		button.disabled = true;
		button.textContent = str( 'buyStarting' ) || 'Starting checkout...';

		api( route( 'orders' ), { method: 'POST', body: { package_id: packageId } } )
			.then( function ( response ) {
				var order = response && response.data ? response.data : {};

				if ( order.redirect_url ) {
					window.location.assign( String( order.redirect_url ) );
					return;
				}

				restoreButton( button, label );

				var text = instructionText( order.instructions );

				alertBox(
					target,
					'info',
					str( 'processingPayment' ) || 'Awaiting payment confirmation',
					text || str( 'orderCreated' )
				);
			} )
			.catch( function ( error ) {
				restoreButton( button, label );

				alertBox( target, 'error', '', errorMessage( error ) || str( 'buyFailed' ), [
					{
						label: str( 'retry' ) || 'Try again',
						onClick: function () {
							startBuy( button );
						}
					}
				] );
			} );
	}

	function initBuy() {
		each( document.querySelectorAll( '[data-mcr-buy]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				startBuy( button );
			} );
		} );
	}

	/* --------------------------------------------------------------------
	 * Transaction history
	 * ------------------------------------------------------------------ */

	function txnTypeLabel( type ) {
		if ( data.types && data.types[ type ] ) {
			return String( data.types[ type ] );
		}

		return type ? String( type ) : '';
	}

	function txnCell( label, text, className ) {
		var cell = document.createElement( 'td' );

		if ( label ) {
			cell.setAttribute( 'data-label', label );
		}

		if ( className ) {
			cell.className = className;
		}

		cell.textContent = text === undefined || text === null ? '' : String( text );

		return cell;
	}

	function txnRow( transaction ) {
		var row = document.createElement( 'tr' );
		var header = document.createElement( 'th' );
		header.setAttribute( 'scope', 'row' );

		var display = transaction.created_display ? String( transaction.created_display ) : '';

		if ( transaction.created_at ) {
			var stamp = document.createElement( 'time' );
			stamp.setAttribute( 'datetime', String( transaction.created_at ).replace( ' ', 'T' ) + 'Z' );
			stamp.textContent = display;
			header.appendChild( stamp );
		} else {
			header.textContent = display;
		}

		row.appendChild( header );

		var type = transaction.type ? String( transaction.type ) : '';
		row.appendChild( txnCell( str( 'typeLabel' ) || 'Type', txnTypeLabel( type ) ) );

		var description = transaction.reason ? String( transaction.reason ) : txnTypeLabel( type );
		row.appendChild( txnCell( str( 'descriptionLabel' ) || 'Description', description, 'mcr-table__muted' ) );

		var amount = parseInt( transaction.amount, 10 );

		if ( isNaN( amount ) ) {
			amount = 0;
		}

		var credit = amount >= 0;
		var amountCell = txnCell(
			str( 'amountLabel' ) || 'Amount',
			( credit ? '+' : '\u2212' ) + number( Math.abs( amount ) ),
			'mcr-table__num ' + ( credit ? 'mcr-table__credit' : 'mcr-table__debit' )
		);

		var suffix = document.createElement( 'span' );
		suffix.className = 'mcr-sr-only';
		suffix.textContent = credit ? ( str( 'coins' ) || 'coins' ) : ( str( 'coinsSpent' ) || 'coins spent' );
		amountCell.appendChild( document.createTextNode( ' ' ) );
		amountCell.appendChild( suffix );
		row.appendChild( amountCell );

		var balance = parseInt( transaction.balance_after, 10 );

		row.appendChild( txnCell( str( 'balanceAfter' ) || 'Balance after', number( isNaN( balance ) ? 0 : balance ), 'mcr-table__num' ) );

		return row;
	}

	function setPaginationStatus( text ) {
		var status = document.querySelector( '.mcr-pagination__status' );

		if ( status ) {
			status.textContent = text;
		}
	}

	function loadMoreTransactions( button ) {
		if ( ! button || button.getAttribute( 'aria-busy' ) === 'true' ) {
			return;
		}

		var page = parseInt( button.getAttribute( 'data-mcr-next-page' ), 10 );

		/*
		 * The table is looked up inside the plugin's own container. A
		 * page can render the plugin more than once, and the ids of the
		 * later copies are suffixed, so a document-wide lookup would
		 * have found the FIRST table and the rows would have landed in
		 * the wrong one.
		 */
		var scope = button.closest ? button.closest( '[data-mcr-page]' ) : null;
		var tbody = scope ? scope.querySelector( '[data-mcr-txn-rows]' ) : null;

		if ( ! tbody ) {
			tbody = document.getElementById( 'mcr-txn-rows' );
		}

		if ( ! tbody ) {
			var all = document.querySelectorAll( '[data-mcr-txn-rows]' );
			tbody = all.length ? all[ all.length - 1 ] : null;
		}

		if ( isNaN( page ) || page < 1 || ! tbody ) {
			return;
		}

		var label = buttonLabel( button );
		var shown = parseInt( button.getAttribute( 'data-mcr-shown' ), 10 );
		var total = parseInt( button.getAttribute( 'data-mcr-total' ), 10 );
		var perPage = parseInt( data.perPage, 10 );

		if ( isNaN( shown ) ) {
			shown = tbody.querySelectorAll( 'tr' ).length;
		}

		if ( isNaN( total ) ) {
			total = shown;
		}

		if ( isNaN( perPage ) || perPage < 1 ) {
			perPage = 20;
		}

		button.classList.add( 'is-loading' );
		button.setAttribute( 'aria-busy', 'true' );
		button.disabled = true;
		button.textContent = str( 'loadingMore' ) || 'Loading...';

		api( route( 'transactions' ) + '?page=' + page + '&per_page=' + perPage )
			.then( function ( payload ) {
				var rows = payload && payload.transactions ? payload.transactions : [];
				var fragment = document.createDocumentFragment();

				each( rows, function ( transaction ) {
					fragment.appendChild( txnRow( transaction ) );
				} );

				tbody.appendChild( fragment );

				shown += rows.length;

				if ( payload && typeof payload.total !== 'undefined' ) {
					total = parseInt( payload.total, 10 );

					if ( isNaN( total ) ) {
						total = shown;
					}
				}

				button.setAttribute( 'data-mcr-shown', String( shown ) );
				button.setAttribute( 'data-mcr-total', String( total ) );

				if ( shown < total ) {
					var next = parseInt( payload && payload.page ? payload.page : page, 10 ) + 1;
					button.setAttribute( 'data-mcr-next-page', String( next ) );
					button.classList.remove( 'is-loading' );
					button.removeAttribute( 'aria-busy' );
					button.disabled = false;
					button.textContent = label;
					setPaginationStatus( format( str( 'showing' ) || 'Showing %1$s of %2$s', number( shown ), number( total ) ) );
				} else {
					/*
					 * The button is being removed because the list is
					 * exhausted. Move focus to the status message so a
					 * keyboard user is not dropped back on <body>.
					 */
					var status = setPaginationStatus( format( str( 'allShown' ) || 'All %s transactions shown.', number( total ) ) );

					if ( status && status.setAttribute ) {
						status.setAttribute( 'tabindex', '-1' );
						status.focus();
					}

					button.parentNode.removeChild( button );
				}
			} )
			.catch( function () {
				restoreButton( button, label );
				toast( str( 'transactionsFailed' ) || 'We could not load your transactions. Please try again.', 'error' );
			} );
	}

	function initTransactions() {
		each( document.querySelectorAll( '[data-mcr-load-more]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				loadMoreTransactions( button );
			} );
		} );
	}

	/**
	 * Wires the retry control of the error partial. The page has no state to
	 * restore, so a reload is the only honest retry.
	 */
	function initRetry() {
		each( document.querySelectorAll( '[data-mcr-retry]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				window.location.reload();
			} );
		} );
	}

	/* --------------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------------ */

	function init() {
		/*
		 * The render-time server timestamp is only a hint: on a cached page
		 * it can be minutes old, and treating that as clock skew would
		 * warn every visitor of a cached site. The authoritative skew is
		 * measured from the Date header of the first API response, and
		 * the countdown is corrected from the server's own `next_in`
		 * value. Until then the local clock is used as-is.
		 */
		var serverTime = data.serverTime ? Date.parse( data.serverTime ) : NaN;

		if ( ! isNaN( serverTime ) ) {
			state.renderSkew = serverTime - Date.now();
		}

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				stopCountdowns();
			} else {
				startCountdowns();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'Escape' && event.keyCode !== 27 ) {
				return;
			}

			var item = newestToast();

			if ( item ) {
				removeToast( item );
			}
		} );

		startCountdowns();
		initCheckin();
		initAds();
		initBuy();
		initTransactions();
		initRetry();

		if ( ! state.skewed ) {
			return;
		}

		warnAboutClockSkew();
	}

	window.mcr = {
		fetch: api,
		toast: toast,
		formatAmount: number,
		format: format,
		repaintBalance: repaintBalance,
		on: function ( target, type, handler ) {
			if ( target && typeof target.addEventListener === 'function' ) {
				target.addEventListener( type, handler );
			}
		}
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( window, document ) );
