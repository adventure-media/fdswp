function SasoEventticketsValidator_WC_frontend($, phpObject) {
	const { __, _x, _n, sprintf } = wp.i18n;
	let _self = this;
	let inputType = phpObject.inputType;

	function init() {
		_addHandlerToTheCodeFields();
		//_activateAllInputFields();
	}

	// fkt nicht bei cart updates, da dies nicht neu initialisiert wird
	function _addHandlerToTheCodeFields() {
		let isStoring = false;
		let waitingTimeout = null;

		function sendCode(elem, code) {
			clearWaitingTimeout();
			if (!isStoring) {
				$('div[class="woocommerce"]').block({
	 				//message: '...loading...',
	 				message: null,
	 				overlayCSS: {
	 					background: '#fff',
	 					opacity: 0.6
	 				}
	 			});
				isStoring = true;
				let cart_item_id = elem.data('cart-item-id');
		 		$.ajax(
		 			{
		 				type: 'GET',
		 				url: phpObject.ajaxurl,
		 				data: {
		 					action: phpObject.action,
		 					a: 'updateSerialCodeToCartItem',
		 					security: $('#woocommerce-cart-nonce').val(),
		 					cart_item_id: cart_item_id,
		 					code: code
		 				},
		 				success: function( response ) {
		 					//$('div[class="woocommerce"]').unblock();
		 					//$('.cart_totals').unblock();
		 					if (response.success) {
			 					elem.val(response.code);
		 					} else {
		 						if (response.msg) alert(response.msg);
		 					}
							window.location.reload();
		 				}
		 			}
		 		)
	 		}
		}

		function clearWaitingTimeout() {
			clearTimeout(waitingTimeout);
		}
		function setWaitingTimeout(elem, code) {
			clearWaitingTimeout();
			waitingTimeout = setTimeout(()=>{
				sendCode(elem, code);
			}, 1500);
		}

		// finde die code text inputs
		$('body').find('input[data-input-type="'+inputType+'"]')
			.on('keyup',function(){
	 			$('.cart_totals').block({
	 				message: null,
	 				overlayCSS: {
	 					background: '#fff',
	 					opacity: 0.6
	 				}
	 			});
	 			isStoring = false;
				let elem = $(this);
				let code = elem.val();
				setWaitingTimeout(elem, code);
			})
			.on('paste', ()=>{
	 			isStoring = false;
				let elem = $(event.srcElement);
			    let code = (event.clipboardData || window.clipboardData).getData('text');
			    sendCode(elem, code);
			})
			.on('change',function(){
				let elem = $(this);
				let code = elem.val();
	 			let cart_item_id = elem.data('cart-item-id');
	 			//let d = document.querySelector('input[data-cart-item-id="'+cart_item_id+'"]').value
				sendCode(elem, code);
	 		})
	 		.removeAttr('disabled');


	}

	function _activateAllInputFields() {
		$('body').find('input[data-input-type="'+inputType+'"]').removeAttr('disabled');
	}

	init();
}

(function($){
 	$(document).ready(function(){
 		SasoEventticketsValidator_WC_frontend($, phpObject);
 	});
})(jQuery);