jQuery(document).ready(function () {
	let myAjax = Ajax_sasoEventtickets;
	if (jQuery('#'+myAjax.divId)) {
		// lazy loading script, only if needed
		jQuery.getScript( myAjax.jsFiles, function( data, textStatus, jqxhr ){});
	}
} );