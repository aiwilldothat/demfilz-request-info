/**
 * Demfilz Request Info - popup form behaviour.
 * Vanilla JS, no dependencies.
 */
( function () {
	'use strict';

	var cfg = window.DRIF_FORM || {};
	var i18n = cfg.i18n || {};
	var overlay, modal, form, formView, successView, msgBox, submitBtn, lastFocus;
	var prefilled = false;
	var resetTimer = null;

	function qs( sel, ctx ) {
		return ( ctx || document ).querySelector( sel );
	}

	function qsa( sel, ctx ) {
		return Array.prototype.slice.call( ( ctx || document ).querySelectorAll( sel ) );
	}

	function pageTitle() {
		return ( cfg.page && cfg.page.title ) || document.title || '';
	}

	function pageUrl() {
		return ( cfg.page && cfg.page.url ) || window.location.href;
	}

	function prefill() {
		if ( prefilled || ! form ) {
			return;
		}
		prefilled = true;

		var user = cfg.user || {};
		var map = {
			drif_name: user.name,
			drif_company: user.company,
			drif_phone: user.phone,
			drif_email: user.email,
			drif_suburb: user.suburb,
			drif_postcode: user.postcode
		};
		Object.keys( map ).forEach( function ( name ) {
			var input = form.elements[ name ];
			if ( input && ! input.value && map[ name ] ) {
				input.value = map[ name ];
			}
		} );

		// Pre-fill the details field with the page/product name and link.
		var details = form.elements.drif_details;
		if ( details && ! details.value ) {
			details.value = ( i18n.detailsIntro || 'I would like more information about:' ) +
				'\n' + pageTitle() + '\n' + pageUrl() + '\n\n';
		}
	}

	function clearErrors() {
		qsa( '.dri-invalid', form ).forEach( function ( el ) {
			el.classList.remove( 'dri-invalid' );
		} );
		qsa( '.dri-field-error', form ).forEach( function ( el ) {
			el.parentNode.removeChild( el );
		} );
		if ( msgBox ) {
			msgBox.hidden = true;
			msgBox.textContent = '';
		}
	}

	function fieldError( input, message ) {
		input.classList.add( 'dri-invalid' );
		var note = document.createElement( 'span' );
		note.className = 'dri-field-error';
		note.textContent = message;
		input.parentNode.appendChild( note );
	}

	function validate() {
		clearErrors();
		var ok = true;
		var required = [ 'drif_name', 'drif_email', 'drif_details' ];

		required.forEach( function ( name ) {
			var input = form.elements[ name ];
			if ( input && ! input.value.trim() ) {
				fieldError( input, i18n.required || 'Please fill in this field.' );
				ok = false;
			}
		} );

		var email = form.elements.drif_email;
		if ( email && email.value.trim() && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email.value.trim() ) ) {
			fieldError( email, i18n.invalidEmail || 'Please enter a valid email address.' );
			ok = false;
		}
		return ok;
	}

	function showError( message ) {
		if ( msgBox ) {
			msgBox.textContent = message || i18n.error || 'Sorry, something went wrong. Please try again.';
			msgBox.hidden = false;
		}
	}

	function resetForm() {
		// Back to a fresh form so another request can be sent. Contact fields
		// keep their values; the details field is re-templated for the page.
		successView.hidden = true;
		formView.hidden = false;
		clearErrors();
		var details = form.elements.drif_details;
		if ( details ) {
			details.value = '';
		}
		prefilled = false;
		prefill();
	}

	function showSuccess( message ) {
		if ( message ) {
			var text = qs( '.dri-success-text', successView );
			if ( text ) {
				text.textContent = message;
			}
		}
		formView.hidden = true;
		successView.hidden = false;
		var done = qs( '.dri-done', successView );
		if ( done ) {
			done.focus();
		}
		// Reset after 10s so a follow-up request can be sent.
		if ( resetTimer ) {
			clearTimeout( resetTimer );
		}
		resetTimer = setTimeout( resetForm, 10000 );
	}

	function open() {
		lastFocus = document.activeElement;
		prefill();
		overlay.classList.add( 'dri-open' );
		overlay.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'dri-noscroll' );
		var first = form && form.elements.drif_name;
		if ( formView.hidden ) {
			first = qs( '.dri-done', successView );
		}
		if ( first ) {
			first.focus();
		}
	}

	function close() {
		overlay.classList.remove( 'dri-open' );
		overlay.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'dri-noscroll' );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	function isOpen() {
		return overlay.classList.contains( 'dri-open' );
	}

	function trapTab( e ) {
		var focusable = qsa( 'a[href], button:not([disabled]), input:not([type="hidden"]), textarea, select', modal )
			.filter( function ( el ) {
				return el.offsetParent !== null && ! el.closest( '.dri-hp' );
			} );
		if ( ! focusable.length ) {
			return;
		}
		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	function submit( e ) {
		e.preventDefault();
		if ( ! validate() ) {
			return;
		}

		var data = new FormData();
		data.append( 'action', 'drif_submit' );
		data.append( 'nonce', cfg.nonce || '' );
		data.append( 'page_id', ( cfg.page && cfg.page.id ) || 0 );
		data.append( 'page_title', pageTitle() );
		data.append( 'page_url', pageUrl() );
		[ 'drif_name', 'drif_company', 'drif_phone', 'drif_email', 'drif_suburb', 'drif_postcode', 'drif_details', 'drif_website' ].forEach( function ( name ) {
			var input = form.elements[ name ];
			data.append( name, input ? input.value : '' );
		} );

		var label = submitBtn.textContent;
		submitBtn.disabled = true;
		submitBtn.textContent = i18n.sending || 'Sending...';

		fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					showSuccess( json.data && json.data.message );
				} else {
					showError( json && json.data && json.data.message );
				}
			} )
			.catch( function () {
				showError();
			} )
			.finally( function () {
				submitBtn.disabled = false;
				submitBtn.textContent = label;
			} );
	}

	function init() {
		overlay = qs( '.dri-overlay' );
		if ( ! overlay ) {
			return;
		}
		modal = qs( '.dri-modal', overlay );
		form = qs( '.dri-form', overlay );
		formView = qs( '.dri-form-view', overlay );
		successView = qs( '.dri-success-view', overlay );
		msgBox = qs( '.dri-msg', overlay );
		submitBtn = qs( '.dri-submit', overlay );

		qsa( '[data-dri-open]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				open();
			} );
		} );

		qs( '.dri-close', overlay ).addEventListener( 'click', close );
		var done = qs( '.dri-done', overlay );
		if ( done ) {
			done.addEventListener( 'click', close );
		}

		overlay.addEventListener( 'mousedown', function ( e ) {
			if ( e.target === overlay ) {
				close();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( ! isOpen() ) {
				return;
			}
			if ( 'Escape' === e.key ) {
				close();
			} else if ( 'Tab' === e.key ) {
				trapTab( e );
			}
		} );

		form.addEventListener( 'submit', submit );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
