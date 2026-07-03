/**
 * Media Cleaner Lite - batch scan controller.
 */
( function () {
	'use strict';

	var config = window.mclScan || null;

	function text( key, fallback ) {
		return ( config && config.i18n && config.i18n[ key ] ) || fallback || '';
	}

	function request( action, extra ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		if ( extra ) {
			Object.keys( extra ).forEach( function ( key ) {
				body.append( key, extra[ key ] );
			} );
		}

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function formatNumber( value ) {
		var number = Number( value ) || 0;
		try {
			return number.toLocaleString();
		} catch ( e ) {
			return String( number );
		}
	}

	function initScanner() {
		var scanner = document.querySelector( '[data-mcl-scanner]' );

		if ( ! scanner || ! config ) {
			return;
		}

		var startBtn = scanner.querySelector( '[data-mcl-start]' );
		var pauseBtn = scanner.querySelector( '[data-mcl-pause]' );
		var cancelBtn = scanner.querySelector( '[data-mcl-cancel]' );
		var clearBtn = scanner.querySelector( '[data-mcl-clear]' );
		var bar = scanner.querySelector( '[data-mcl-progressbar]' );
		var fill = scanner.querySelector( '[data-mcl-progress-fill]' );
		var progressText = scanner.querySelector( '[data-mcl-progress-text]' );
		var statusEl = scanner.querySelector( '[data-mcl-status]' );
		var running = false;

		function setStatus( message ) {
			if ( statusEl ) {
				statusEl.textContent = message;
			}
		}

		function setProgress( percent ) {
			var value = Math.max( 0, Math.min( 100, Math.round( Number( percent ) || 0 ) ) );
			if ( fill ) {
				fill.style.width = value + '%';
			}
			if ( bar ) {
				bar.setAttribute( 'aria-valuenow', String( value ) );
			}
			if ( progressText ) {
				progressText.textContent = value + '%';
			}
		}

		function toggleControls( isRunning ) {
			running = isRunning;
			if ( startBtn ) {
				startBtn.disabled = isRunning || scanner.getAttribute( 'data-mcl-can-scan' ) !== '1';
			}
			if ( pauseBtn ) {
				pauseBtn.hidden = ! isRunning;
			}
			if ( cancelBtn ) {
				cancelBtn.hidden = ! isRunning;
			}
		}

		function finish( message ) {
			toggleControls( false );
			setStatus( message );
		}

		function applyProgress( data ) {
			if ( ! data ) {
				return;
			}
			setProgress( data.percent );
			if ( data.phase === 'analyze' ) {
				setStatus( text( 'analyzing', 'در حال تحلیل نتایج…' ) );
			} else if ( data.phase === 'references' ) {
				setStatus(
					text( 'scanning', 'در حال اسکن ارجاعات…' ) + ' ' +
					formatNumber( data.processed_posts || 0 ) + ' / ' + formatNumber( data.total_posts || 0 )
				);
			} else if ( data.phase === 'index' ) {
				setStatus(
					text( 'indexing', 'در حال ایندکس رسانه…' ) + ' ' +
					formatNumber( data.indexed_count || 0 ) + ' / ' + formatNumber( data.total_attachments || 0 )
				);
			}
		}

		function loop() {
			if ( ! running ) {
				return;
			}
			request( config.actions.continue ).then( function ( json ) {
				if ( ! json || ! json.success ) {
					finish( ( json && json.data && json.data.message ) || text( 'error', 'خطا' ) );
					return;
				}
				var data = json.data;
				applyProgress( data );
				if ( data.status === 'complete' ) {
					setProgress( 100 );
					finish( text( 'complete', 'اسکن کامل شد.' ) );
					window.setTimeout( function () {
						window.location.reload();
					}, 800 );
					return;
				}
				if ( data.status === 'paused' ) {
					running = false;
					toggleControls( false );
					setStatus( text( 'paused', 'اسکن متوقف شد.' ) );
					return;
				}
				if ( data.status !== 'running' ) {
					finish( text( 'cancelled', 'اسکن لغو شد.' ) );
					return;
				}
				loop();
			} ).catch( function () {
				finish( text( 'error', 'خطا' ) );
			} );
		}

		function start() {
			if ( running ) {
				return;
			}
			toggleControls( true );
			var isPaused = scanner.getAttribute( 'data-mcl-paused' ) === '1';
			setStatus( isPaused ? text( 'scanning', 'در حال اسکن…' ) : text( 'starting', 'در حال شروع…' ) );
			if ( ! isPaused ) {
				setProgress( 0 );
			}
			var action = isPaused ? config.actions.continue : config.actions.start;
			request( action ).then( function ( json ) {
				if ( ! json || ! json.success ) {
					finish( ( json && json.data && json.data.message ) || text( 'error', 'خطا' ) );
					return;
				}
				scanner.setAttribute( 'data-mcl-paused', '0' );
				applyProgress( json.data );
				loop();
			} ).catch( function () {
				finish( text( 'error', 'خطا' ) );
			} );
		}

		function pause() {
			if ( ! running ) {
				return;
			}
			request( config.actions.pause ).then( function ( json ) {
				running = false;
				toggleControls( false );
				setStatus( text( 'paused', 'اسکن متوقف شد.' ) );
				if ( json && json.data ) {
					applyProgress( json.data );
				}
			} );
		}

		function cancel() {
			if ( ! running ) {
				return;
			}
			running = false;
			request( config.actions.cancel ).then( function () {
				finish( text( 'cancelled', 'اسکن لغو شد.' ) );
				setProgress( 0 );
			} );
		}

		function clearCache() {
			request( config.actions.clear ).then( function ( json ) {
				if ( json && json.success ) {
					setStatus( ( json.data && json.data.message ) || text( 'cacheClear', 'کش پاک شد.' ) );
				}
			} );
		}

		if ( startBtn ) {
			startBtn.addEventListener( 'click', start );
		}
		if ( pauseBtn ) {
			pauseBtn.addEventListener( 'click', pause );
		}
		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', cancel );
		}
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', clearCache );
		}

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( running ) {
				event.preventDefault();
				event.returnValue = text( 'cleaveWarn', '' );
			}
		} );

		if ( scanner.getAttribute( 'data-mcl-active' ) === '1' ) {
			toggleControls( true );
			if ( scanner.getAttribute( 'data-mcl-paused' ) === '1' ) {
				running = false;
				toggleControls( false );
				setStatus( text( 'paused', 'اسکن متوقف شد.' ) );
			} else {
				setStatus( text( 'scanning', 'در حال اسکن…' ) );
				loop();
			}
		}
	}

	function initDeleteButtons() {
		if ( ! config ) {
			return;
		}

		document.querySelectorAll( '[data-mcl-delete]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				if ( ! window.confirm( text( 'confirm', 'آیا مطمئن هستید؟' ) ) ) {
					return;
				}
				button.disabled = true;
				request( config.actions.delete, {
					attachment_id: button.getAttribute( 'data-attachment-id' ) || ''
				} ).then( function ( json ) {
					if ( json && json.success ) {
						var row = button.closest( '[data-mcl-media-row]' );
						if ( row ) {
							row.remove();
						}
						return;
					}
					button.disabled = false;
					window.alert( ( json && json.data && json.data.message ) || text( 'deleteFail', 'حذف رسانه ممکن نشد.' ) );
				} ).catch( function () {
					button.disabled = false;
					window.alert( text( 'deleteFail', 'حذف رسانه ممکن نشد.' ) );
				} );
			} );
		} );
	}

	initScanner();
	initDeleteButtons();
}() );
