( function( $ ) {
	$( document ).ready( function() {
		var gglcptch_version_not_selected = $( 'input[name="gglcptch_recaptcha_version"]:not(:checked)' ).val();
		$( '.gglcptch_theme_' + gglcptch_version_not_selected ).hide();

		$( 'input[name="gglcptch_recaptcha_version"]' ).change( function() {
			var gglcptch_version_selected = $( this ).val(),
				gglcptch_version_not_selected = $( 'input[name="gglcptch_recaptcha_version"]:not(:checked)' ).val();
			$( '.gglcptch_theme_' + gglcptch_version_selected ).show();
			$( '.gglcptch_theme_' + gglcptch_version_not_selected ).hide();
		} );

		$( 'input[name="gglcptch_private_key"], input[name="gglcptch_public_key"]' ).change( function() {
			$( '.gglcptch_verified, #gglcptch-test-keys, #gglcptch-test-block' ).hide();
		} );

		$( 'select[name="gglcptch_theme_v2"]' ).on( "change", function() {
			$( '.gglcptch_size_sample' ).hide();
			var theme = $( this ).find( 'option:selected' ).val();
			$( '.gglcptch_size_sample_' + theme ).show();
		} );

		/**
		 * Handle the "Whitelist" tab on the plugins option page
		 */
		$( 'button[name="gglcptch_show_whitelist_form"]' ).click( function() {
			$( this ).closest( 'form' ).hide();
			$( '.gglcptch_whitelist_form' ).show();
			return false;
		} );

		$( 'input[name="gglcptch_add_to_whitelist_my_ip"]' ).change( function() {
			if ( $( this ).is( ':checked' ) ) {
				var my_ip = $( 'input[name="gglcptch_add_to_whitelist_my_ip_value"]' ).val();
				$( 'input[name="gglcptch_add_to_whitelist"]' ).val( my_ip ).attr( 'readonly', 'readonly' );
			} else {
				$( 'input[name="gglcptch_add_to_whitelist"]' ).val( '' ).removeAttr( 'readonly' );
			}
		} );
	} );

	$( document ).on( 'click', '#gglcptch-test-keys a', function( e ) {
		e.preventDefault();

		if ( ! $( '#gglcptch-test-block' ).length )
			$( this ).closest( 'p' ).after( '<div id="gglcptch-test-block" />' );

		$( '.gglcptch-test-results' ).remove();

		$( '#gglcptch-test-block' ).load( $( this ).prop( 'href' ), function() {
			$( '.gglcptch_v1, .gglcptch_v2' ).each( function() {
				var container = $( this ).find( '.gglcptch_recaptcha' ).attr( 'id' );
				if ( $( this ).is( ':visible' ) ) {
					gglcptch.display( container );
				}
			} );
		} );

		e.stopPropagation();
		$( '#gglcptch-test-keys' ).hide();
		return false;
	} );

	$( document ).on( 'click', '#gglcptch_test_keys_verification', function( e ) {
		e.preventDefault();
		$.ajax( {
			async   : false,
			cache   : false,
			type    : 'POST',
			url     : ajaxurl,
			headers : {
				'Content-Type' : 'application/x-www-form-urlencoded'
			},
			data    : {
				action: 'gglcptch_test_keys_verification',
				recaptcha_challenge_field : $( '#recaptcha_challenge_field' ).val(),
				recaptcha_response_field  : $( '#recaptcha_response_field' ).val(),
				'g-recaptcha-response'  : $( '.g-recaptcha-response' ).val(),
				_wpnonce : $( '[name="gglcptch_test_keys_verification-nonce"]' ).val()
			},
			success: function( data ) {
				$( '#gglcptch-test-block' ).after( data );
				$( '#gglcptch-test-block' ).html( '' );
				if ( $( '.gglcptch-test-results' ).hasClass( 'updated' ) ) {
					$( '.gglcptch_verified' ).show();
				} else {
					$( '.gglcptch_verified' ).hide();
					if ( 'v2' == $( 'input[name="gglcptch_recaptcha_version"]:checked' ).val() ) {
						$( '#gglcptch-test-keys' ).show();
					}
				}
			}
		} );

		e.stopPropagation();
		return false;
	} );
} )( jQuery );