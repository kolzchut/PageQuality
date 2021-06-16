(function( $ ) {

	var popup = false;

	function failed(){
		alert( "Failed doing last operation, check internet connection!" );
	};

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
	};

	$(document).ready(function () { //jquery
		$( '.page_quality_show' ).click( function() {
			page_id = $(this).data('page_id');

			if ( popup != false ) {
				popup.close();
			}

			// popup = $.confirm({
			// 	boxWidth: '500px',
			// 	buttons: false,
			// 	draggable: false,
			//     content: function () {
			//         var self = this;
			// 		var api = new mw.Api();
			// 		api.get( {
			// 		    action: 'page_quality_api',
			// 		    pq_action: 'fetch_report_html',
			// 		    page_id: page_id
			// 		} ).done( function ( data ) {
			//             self.setContent(data.result.html);
			//             self.setTitle(data.result.title);
			// 			success(data);
			// 		} );
			//     }
			// });

			var api = new mw.Api();
			api.get( {
			    action: 'page_quality_api',
			    pq_action: 'fetch_report_html',
			    page_id: page_id
			} ).done( function ( data ) {
				popup = $.confirm({
					boxWidth: '500px',
					buttons: false,
					draggable: false,
					animation: 'none',
				    content: data.result.html,
				    title: data.result.title
				});
				success(data);
			});

		})
	});

} )( jQuery );