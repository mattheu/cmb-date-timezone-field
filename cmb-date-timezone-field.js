(function($) {

	/**
	 * Date & Time Fields
	 */

	function CMBDateFieldInit( $container ) {

		var $dateFields = $container.find( '.cmb-new-date-field-date input' );
		var $timeFields = $container.find( '.cmb-new-date-field-time input' );

		// Reinitialize all the datepickers
		$dateFields.each(function () {

			var val, field;

			field = $( this );

			field.datepicker({
				altFormat:  "yy-mm-dd",
				altField:   field.data( 'alt-field' ),
				dateFormat: 'd M yy',
			});

			if ( val = $( field.data( 'alt-field' ) ).val() ) {
				field.datepicker( "setDate", new Date( val ) );
			}

		});

		// Wrap date picker in class to narrow the scope of jQuery UI CSS and prevent conflicts
		$("#ui-datepicker-div").wrap('<div class="cmb_element"/>');

		// Timepicker
		$timeFields.each(function () {
			jQuery( this ).timePicker({
				startTime: "00:00",
				endTime: "23:30",
				show24Hours: false,
				separator: ':',
				step: 30
			});
		} );

	}

	CMB.addCallbackForClonedField( ['CMB_Date_Timezone_Field'], function( $container ) {
		CMBDateFieldInit( $container );
	} );

	CMB.addCallbackForInit( function() {
		$( '.CMB_Date_Timezone_Field' ).each( function() {
			CMBDateFieldInit( $(this) );
		} );
	});


}(jQuery));
