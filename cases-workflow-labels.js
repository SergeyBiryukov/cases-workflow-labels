jQuery(document).ready( function($) {

	$('.cases-box-content').on('click', 'input[type="checkbox"]', function() {
		var checked = $(this).closest('tbody').find(':checkbox').filter(':checked'),
			labels = $(this).closest('tr').find('a[href*="/label/"]');

		$('#link-labels').toggle( checked.length > 0 );

		// alert( labels.length );
		if ( $(this).prop('checked') ) {
			labels.each( function() {
				var href = $(this).attr('href');
				if ( 0 == $('.tagchecklist a[href="' + href + '"]').length )
					$('.tagchecklist').append( $(this).clone(), ' ' );
			});
		} else {
		}
	});

});
