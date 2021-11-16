(function() {

	var $sidebar = false,
		currentlyVisible = false,
		$indicator = $( '#mw-indicator-pq_status' );

	function failed(){
		alert( "Failed doing last operation, check internet connection!" );
	}

	function success(data){
		if ( typeof( data.result ) != 'undefined' ) {
			if ( typeof( data.result.notification ) != 'undefined' ) {
				$.each( data.result.notification, function( k, v ) {
					mw.notify( v );
				} );
			}

			if (typeof(data.result.success) != 'undefined') {
				$('.temp-data').remove();
			} else if ( typeof( data.result.error ) != 'undefined' ) {
				mw.notify("Error: " + data.result.error.info );
			} else {
				alert("ERROR " + data.result.failed);
			}
		}

		if ( typeof ( data.error ) != 'undefined' ) {
			if ( typeof( data.error.info ) != 'undefined' ) {
				alert( "ERROR : " + data.error.info );
			}
		}
	}

	$( '[data-target="#pagequality-sidebar"]' ).click( toggleSidebar );

	function createSidebar() {
		$sidebar = $( '<div id="pagequality-sidebar"><header></header><div class="inner"></div></div>');
		var api = new mw.Api();
		api.get( {
			action: 'page_quality_api',
			pq_action: 'fetch_report_html',
			page_id: mw.config.get( 'wgArticleId' )
		} ).done( function ( data ) {
			var $closeBtn = $( '<btn class="close" data-target="#pagequality-sidebar" aria-controls="pagequality-sidebar" aria-label="Close">&times;</btn>' );
			$closeBtn.click( toggleSidebar );
			$sidebar.find( '.inner' ).append( data.result.html );
			$sidebar.find( 'header' ).append( [ $closeBtn, $indicator.clone() ] );
			$sidebar.hide();
			$sidebar.appendTo( 'body' );
			success(data);

			toggleSidebar();
		});
	}

	function toggleSidebar() {
		if ( !$sidebar ) {
			createSidebar();
			return;
		}

		if ( currentlyVisible ) {
			$sidebar.hide();
		} else {
			$sidebar.show();
		}
		currentlyVisible = !currentlyVisible;
	}

} )();
