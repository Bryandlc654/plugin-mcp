(function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var testBtn = document.getElementById( 'mcp-test-button' );
		var result = document.getElementById( 'mcp-test-result' );

		if ( testBtn && result && window.WPMCPConnectAdmin ) {
			testBtn.addEventListener( 'click', function () {
				var original = testBtn.innerHTML;
				testBtn.disabled = true;
				testBtn.innerHTML = window.WPMCPConnectAdmin.i18n.testing;
				result.className = 'wp-mcp-connect-test-result';
				result.textContent = '';

				fetch( window.WPMCPConnectAdmin.rest + '/health', {
					method: 'GET',
					credentials: 'omit',
					headers: { 'Accept': 'application/json' }
				} )
					.then( function ( resp ) {
						return resp.json().then( function ( data ) {
							return { ok: resp.ok, data: data };
						} );
					} )
					.then( function ( res ) {
						if ( res.ok && res.data.status === 'ok' ) {
							if ( res.data.mcp ) {
								result.className = 'wp-mcp-connect-test-result ok';
								result.textContent = window.WPMCPConnectAdmin.i18n.ok + ' · MCP ' + res.data.version;
							} else {
								result.className = 'wp-mcp-connect-test-result fail';
								result.textContent = window.WPMCPConnectAdmin.i18n.fail + ': MCP desactivado';
							}
						} else {
							result.className = 'wp-mcp-connect-test-result fail';
							result.textContent = window.WPMCPConnectAdmin.i18n.fail;
						}
					} )
					.catch( function () {
						result.className = 'wp-mcp-connect-test-result fail';
						result.textContent = window.WPMCPConnectAdmin.i18n.fail;
					} )
					.finally( function () {
						testBtn.disabled = false;
						testBtn.innerHTML = original;
					} );
			} );
		}

		var copyContainers = document.querySelectorAll( '.wp-mcp-connect-copy[data-copy]' );
		copyContainers.forEach( function ( container ) {
			var btn = container.querySelector( '[data-copy-target]' );
			if ( ! btn ) {
				return;
			}
			btn.addEventListener( 'click', function () {
				var value = container.getAttribute( 'data-copy' );
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( value ).then( function () {
						btn.textContent = '✓';
						setTimeout( function () { btn.textContent = btn.getAttribute( 'data-label' ) || 'Copiar'; }, 1200 );
					} );
				} else {
					var ta = document.createElement( 'textarea' );
					ta.value = value;
					document.body.appendChild( ta );
					ta.select();
					document.execCommand( 'copy' );
					document.body.removeChild( ta );
					btn.textContent = '✓';
					setTimeout( function () { btn.textContent = 'Copiar'; }, 1200 );
				}
			} );
		} );

		var checkAll = document.getElementById( 'wp-mcp-connect-check-all' );
		if ( checkAll ) {
			checkAll.addEventListener( 'change', function () {
				var boxes = document.querySelectorAll( '#wp-mcp-connect-scopes input[type=checkbox]:enabled' );
				boxes.forEach( function ( box ) { box.checked = checkAll.checked; } );
			} );
		}
	} );
})();