<?php
include(plugin_dir_path(__FILE__)."init_file.php");
final class sasoEventtickets_Ticket {
	private $MAIN;

	private $request_uri;
	private $parts = null;

	private $codeObj;
	private $order;

	private $isScanner = null;

	private $redeem_successfully = false;
	private $onlyLoggedInScannerAllowed = false;

	public static function Instance($request_uri) {
		static $inst = null;
        if ($inst === null) {
            $inst = new sasoEventtickets_Ticket($request_uri);
        }
        return $inst;
	}

	public function __construct($request_uri) {
		global $sasoEventtickets;
		$this->MAIN = $sasoEventtickets;
		$this->setRequestURI($request_uri);
		$this->onlyLoggedInScannerAllowed = $this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketOnlyLoggedInScannerAllowed') ? true : false;
		//load_plugin_textdomain('event-tickets-with-ticket-scanner', false, 'event-tickets-with-ticket-scanner/languages');
	}

	public function setRequestURI($request_uri) {
		$this->request_uri = trim($request_uri);
	}

	function rest_permission_callback($web_request) {
		$allowed_role = $this->MAIN->getOptions()->getOptionValue('wcTicketScannerAllowedRoles');
		if (!$this->onlyLoggedInScannerAllowed && $allowed_role == "-") return true;
		$user = wp_get_current_user();
		$user_roles = (array) $user->roles;
		if ($this->onlyLoggedInScannerAllowed && in_array("administrator", $user_roles)) return true;
		if ($allowed_role != "-") {
			if (in_array($allowed_role, $user_roles)) return true;
		}
		return false;
	}
	function rest_ping($web_request) {
		return ['time'=>time(), 'img_pfad'=>plugins_url( "img/",__FILE__ )];
	}
	function rest_helper_tickets_redeemed($codeObj) {
		$metaObj = $metaObj = $codeObj['metaObj'];
		$ret = [];
		$ret['tickets_redeemed'] = 0;
		$ret['tickets_redeemed_show'] = false;
		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDisplayRedeemedAtScanner') == false) {
			$ret['tickets_redeemed_show'] = true;
			if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'getTicketStats')) {
				if (isset($metaObj['woocommerce']['product_id'])) {
					$ret['tickets_redeemed'] = $this->MAIN->getPremiumFunctions()->getTicketStats()->getEntryAmountForProductId($metaObj['woocommerce']['product_id']);
				}
			}
		}
		return $ret;
	}

	function rest_retrieve_ticket($web_request) {
		if (!isset($_GET['code'])) {
			return wp_send_json_error(esc_html__("code missing", 'event-tickets-with-ticket-scanner'));
		}
		$code = trim($_GET['code']);

		$codeObj = $this->getCodeObj(true);
		$codeObj = apply_filters( $this->MAIN->_do_action_prefix.'filter_updateExpirationInfo', $codeObj );

		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketScanneCountRetrieveAsConfirmed')) {
			$codeObj = $this->getFrontend()->countConfirmedStatus($codeObj, true);
		}

		$metaObj = $codeObj['metaObj'];

		if (!isset($metaObj["wc_ticket"]["_public_ticket_id"])) $metaObj["wc_ticket"]["_public_ticket_id"] = "";
		do_action( $this->MAIN->_do_action_prefix.'trackIPForTicketScannerCheck', array_merge($codeObj, ["_data_code"=>$metaObj["wc_ticket"]["_public_ticket_id"]]) );

		$order = $this->getOrder();
		$order_item = $this->getOrderItem($order, $metaObj);
		if ($order_item == null) return wp_send_json_error(__("Order item not found", 'event-tickets-with-ticket-scanner'));
		$product = $order_item->get_product();
		$is_variation = $product->get_type() == "variation" ? true : false;
		$product_parent = $product;
		$product_parent_id = $product->get_parent_id();
		$saso_eventtickets_is_date_for_all_variants = true;
		if ($is_variation && $product_parent_id > 0) {
			$product_parent = $this->get_product( $product_parent_id );
			$saso_eventtickets_is_date_for_all_variants = get_post_meta( $product_parent->get_id(), 'saso_eventtickets_is_date_for_all_variants', true ) == "yes" ? true : false;
		}

		$date_time_format = $this->MAIN->getOptions()->getOptionDateTimeFormat();

		$is_expired = $this->MAIN->getCore()->checkCodeExpired($codeObj);

		$ret = [];
		$ret['is_expired'] = $is_expired;
		$ret['timezone_id'] = wp_timezone_string();
		$ret['option_displayDateFormat'] = $this->MAIN->getOptions()->getOptionDateFormat();
		$ret['option_displayTimeFormat'] = $this->MAIN->getOptions()->getOptionTimeFormat();
		$ret['option_displayDateTimeFormat'] = $date_time_format;
		$ret['is_paid'] = $this->isPaid($order);
		$ret['allow_redeem_only_paid'] = $this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketAllowRedeemOnlyPaid');
		$ret['order_status'] = $order->get_status();
		$ret = array_merge($ret, $this->rest_helper_tickets_redeemed($codeObj));
		$ret['ticket_heading'] = esc_html($this->getAdminSettings()->getOptionValue("wcTicketHeading"));
		$ret['ticket_title'] = esc_html($product_parent->get_Title());
		$ret['ticket_sub_title'] = "";
		if ($is_variation && $this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketPDFDisplayVariantName') && count($product->get_attributes()) > 0) {
			foreach($product->get_attributes() as $k => $v){
				$ret['ticket_sub_title'] .= $v." ";
			}
		}
		$ret['ticket_location'] = trim(get_post_meta( $product_parent->get_id(), 'saso_eventtickets_event_location', true ));
		$ret['ticket_location_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransLocation"));
		$tmp_product = $product_parent;
		if (!$saso_eventtickets_is_date_for_all_variants) $tmp_product = $product; // unter Umständen die Variante

		$ret = array_merge($ret, $this->calcDateStringAllowedRedeemFrom($tmp_product->get_id()));

		$ret['ticket_date_as_string'] = $this->displayTicketDateAsString($tmp_product, $this->MAIN->getOptions()->getOptionDateFormat(), $this->MAIN->getOptions()->getOptionTimeFormat());
		$ret['short_desc'] = "";
		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDisplayShortDesc')) {
			$ret['short_desc'] = wp_kses_post(trim($product->get_short_description()));
		}
		$ret['ticket_info'] = wp_kses_post(nl2br(trim(get_post_meta( $product_parent->get_id(), 'saso_eventtickets_ticket_is_ticket_info', true ))));
		$ret['cst_label'] = "";
		$ret['cst_billing_address'] = "";
		if (!$this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayCustomer')) {
			$ret['cst_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransCustomer"));
			$ret['cst_billing_address'] = wp_kses_post(trim($order->get_formatted_billing_address()));
		}
		$ret['payment_label'] = "";
		$ret['payment_paid_at_label'] = "";
		$ret['payment_paid_at'] = "";
		$ret['payment_completed_at_label'] = "";
		$ret['payment_completed_at'] = "";
		$ret['payment_method'] = "";
		$ret['payment_trx_id'] = "";
		$ret['payment_method_label'] = "";
		$ret['coupon_label'] = "";
		$ret['coupon'] = "";
		if (!$this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayPayment')) {
			$ret['payment_label'] = wp_kses_post(trim($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetail")));
			$ret['payment_paid_at_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailPaidAt"));
			$ret['payment_completed_at_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailCompletedAt"));
			$ret['payment_paid_at'] = $order->get_date_paid() != null ? date($date_time_format, strtotime($order->get_date_paid())) : "-";
			$ret['payment_completed_at'] = $order->get_date_completed() != null ? date($date_time_format, strtotime($order->get_date_completed())) : "-";
			$payment_method = $order->get_payment_method_title();
			if (!empty($payment_method)) {
				$ret['payment_method_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailPaidVia"));
				$ret['payment_method'] = esc_html($payment_method);
				$ret['payment_trx_id'] = esc_html($order->get_transaction_id());
			} else {
				$ret['payment_method_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailFreeTicket"));
			}
			$coupons = $order->get_coupon_codes();
			if (count($coupons) > 0) {
				$ret['coupon_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailCouponUsed"));
				$ret['coupon'] = esc_html(implode(", ", $coupons));
			}
		}
		$ret['ticket_amount_label'] = "";
		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDisplayPurchasedTicketQuantity')) {
			$text_ticket_amount = wp_kses_post($this->MAIN->getOptions()->getOptionValue('wcTicketPrefixTextTicketQuantity'));
			$order_quantity = $order_item->get_quantity();
			$ticket_pos = 1;
			if ($order_quantity > 1) {
				// ermittel ticket pos
				$codes = explode(",", $order_item->get_meta('_saso_eventtickets_product_code', true));
				$ticket_pos = $this->ermittelCodePosition($codeObj['code_display'], $codes);
			}
			$text_ticket_amount = str_replace("{TICKET_POSITION}", $ticket_pos, $text_ticket_amount);
			$text_ticket_amount = str_replace("{TICKET_TOTAL_AMOUNT}", $order_quantity, $text_ticket_amount);
			$ret['ticket_amount_label'] = $text_ticket_amount;
		}
		$ret['ticket_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransTicket"));
		$paid_price = $order_item->get_subtotal() / $order_item->get_quantity();
		$ret['paid_price_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPrice"));
		$ret['paid_price'] = floatval($paid_price);
		$ret['paid_price_as_string'] = function_exists("wc_price") ? wc_price($paid_price, ['decimals'=>2]) : $paid_price;
		$product_price = $product->get_price();
		$ret['product_price_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransProductPrice"));
		$ret['product_price'] = floatval($product_price);
		$ret['product_price_as_string'] = function_exists("wc_price") ? wc_price($product_price, ['decimals'=>2]) : $product_price;

		$ret['msg_redeemed'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransTicketRedeemed"));
		$ret['redeemed_date_label'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransRedeemDate"));
		$ret['msg_ticket_valid'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransTicketValid"));
		$ret['msg_ticket_expired'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransTicketExpired"));

		$ret['msg_ticket_not_valid_yet'] = wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransTicketNotValidToEarly"));

		$ret['max_redeem_amount'] = intval(get_post_meta( $product_parent->get_id(), 'saso_eventtickets_ticket_max_redeem_amount', true ));
		if ($ret['max_redeem_amount'] < 1) $ret['max_redeem_amount'] = 1;

		$ret['_options'] = [
			"displayConfirmedCounter"=>$this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketScannerDisplayConfirmedCount'),
			"wcTicketDontAllowRedeemTicketBeforeStart"=>$this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontAllowRedeemTicketBeforeStart')
		];

		$codeObj["_ret"] = $ret;
		$codeObj["metaObj"] = $metaObj;

		return $codeObj;
	}
	function calcDateStringAllowedRedeemFrom($product_id) {
		$ret = [];
		$ret['ticket_start_date'] = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_start_date', true ));
		$ret['ticket_start_time'] = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_start_time', true ));
		$ret['ticket_end_date'] = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_end_date', true ));
		$ret['ticket_end_time'] = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_end_time', true ));
		$ret['ticket_start_date_timestamp'] = time();
		if (!empty($ret['ticket_start_date'])) {
			$ret['ticket_start_date_timestamp'] = strtotime(trim($ret['ticket_start_date']." ".$ret['ticket_start_time']));
		}
		$ticket_end_time = $ret['ticket_end_time'];
		if (empty($ticket_end_time)) {
			$ticket_end_time = "23:59:59";
		}
		$ret['ticket_end_date_timestamp'] = strtotime(trim($ret['ticket_end_date']." ".$ticket_end_time));

		$redeem_allowed_from = time();
		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontAllowRedeemTicketBeforeStart')) {
			$time_offset = intval($this->getAdminSettings()->getOptionValue("wcTicketOffsetAllowRedeemTicketBeforeStart"));
			if ($time_offset < 0) $time_offset = 0;
			$redeem_allowed_from = $ret['ticket_start_date_timestamp'] - ($time_offset * 3600);
		}
		$ret['redeem_allowed_from'] = date("Y-m-d H:i", $redeem_allowed_from);
		$ret['redeem_allowed_from_timestamp'] = $redeem_allowed_from;
		return $ret;
	}
	function rest_redeem_ticket($web_request) {
		if (!isset($_REQUEST['code'])) wp_send_json_error(esc_html__("code missing", 'event-tickets-with-ticket-scanner'));

		$codeObj = $this->getCodeObj(true);
		$metaObj = $codeObj['metaObj'];

		$this->redeemTicket($codeObj);
		$ticket_id = $this->getCore()->getTicketId($codeObj, $metaObj);

		$ret = ['redeem_successfully'=>$this->redeem_successfully, 'ticket_id'=>$ticket_id];
		$ret = array_merge($ret, $this->rest_helper_tickets_redeemed($codeObj));

		return $ret;
	}

	/**
	 * has to be explicitly called
	 */
	public function initFilterAndActions() {
		add_filter('query_vars', function( $query_vars ){
		    $query_vars[] = 'symbol';
		    return $query_vars;
		});
		add_filter("pre_get_document_title", function($title){
			return __("Ticket Info", "event-tickets-with-ticket-scanner");
		}, 2000);
		add_action('wp_head', function() {
			include_once plugin_dir_path(__FILE__)."sasoEventtickets_Ticket.php";
			$sasoEventtickets_Ticket = sasoEventtickets_Ticket::Instance($_SERVER["REQUEST_URI"]);
			$sasoEventtickets_Ticket->addMetaTags();
		}, 1);
		add_action('template_redirect', function() {
			include_once plugin_dir_path(__FILE__)."sasoEventtickets_Ticket.php";
			$sasoEventtickets_Ticket = sasoEventtickets_Ticket::Instance($_SERVER["REQUEST_URI"]);
			$sasoEventtickets_Ticket->output();
			exit;
		}, 300);
	}

	public function initFilterAndActionsTicketScanner() {
		add_filter('query_vars', function( $query_vars ){
		    $query_vars[] = 'symbol';
		    return $query_vars;
		});
		add_filter("pre_get_document_title", function($title){
			return __("Ticket Info", "event-tickets-with-ticket-scanner");
		}, 2000);
		add_action('template_redirect', function() {
			include_once plugin_dir_path(__FILE__)."sasoEventtickets_Ticket.php";
			$sasoEventtickets_Ticket = sasoEventtickets_Ticket::Instance($_SERVER["REQUEST_URI"]);
			$sasoEventtickets_Ticket->outputTicketScannerStandalone();
			exit;
		}, 100);
	}

	/** falls man direkt aufrufen muss. Wie beim /ticket/scanner/ */
	public function renderPage() {
		include_once plugin_dir_path(__FILE__)."sasoEventtickets_Ticket.php";
		$vollstart_Ticket = sasoEventtickets_Ticket::Instance($_SERVER["REQUEST_URI"]);
		$vollstart_Ticket->output();
	}

	private function getCore() {
		return $this->MAIN->getCore();
	}
	private function getBase() {
		return $this->MAIN->getBase();
	}
	private function getFrontend() {
		return $this->MAIN->getFrontend();
	}
	private function getAdminSettings() {
		return $this->MAIN->getAdmin();
	}
	private function getOptions() {
		return $this->MAIN->getOptions();
	}

	public function getOptionsRawObject() {
		// called from outside to have the ticket options
		$options = [];

		$options[] = [
			'key'=>'h12a',
			'label'=>__("Ticket scanner", 'event-tickets-with-ticket-scanner'),
			'desc'=>"",
			'type'=>"heading"
			];
		$all_roles = wp_roles()->roles;
		//$editable_roles = apply_filters('editable_roles', $all_roles); // herausfiltern von höheren Rollen, als der User hat - hier nicht nötig
		$editable_roles = $all_roles;
		$additional = [ "values"=>[["label"=>esc_attr__("No special role allowed", 'event-tickets-with-ticket-scanner'), "value"=>"-"]] ];
		foreach($editable_roles as $key => $value) {
			$additional['values'][] = ["label"=>translate_user_role($value['name']), "value"=>$key];
		}
		$options[] = [
				'key'=>'wcTicketScannerAllowedRoles',
				'label'=>__("Allow the specific role to access the ticket scanner", 'event-tickets-with-ticket-scanner'),
				'desc'=>__("If a role is chosen, then the user with this role is allowed to use the ticket scanner. This will not exclude the 'administrator', if the option is activated.", 'event-tickets-with-ticket-scanner'),
				'type'=>"dropdown",
				'def'=>"-",
				'additional'=>$additional,
				'isPublc'=>false
				];
		$options[] = ['key'=>'wcTicketOnlyLoggedInScannerAllowed', 'label'=>__('Allow logged in user as adminstrator to open the ticket scanner', 'event-tickets-with-ticket-scanner'), 'desc'=>__('If active, only logged-in user can scan a ticket. It is also testing if the user is an administrator.', 'event-tickets-with-ticket-scanner'), 'type'=>'checkbox'];
		$options[] = ['key'=>'wcTicketAllowRedeemOnlyPaid', 'label'=>__('Allow to redeem ticket only if it is paid', 'event-tickets-with-ticket-scanner'), 'desc'=>__('If active, only paid and not refunded or cancelled tickets can be redeemed by the ticket scanner. Normal users can anyway not redeem unpaid tickets by themself.', 'event-tickets-with-ticket-scanner'), 'type'=>'checkbox', 'def'=>true];
		$options[] = ['key'=>'wcTicketScanneCountRetrieveAsConfirmed', 'label'=>__('Count each ticket scan with the ticket scanner as a confirmed status check', 'event-tickets-with-ticket-scanner'), 'desc'=>__('If active, each ticket scan will be counted treated as a confirmed validation check and increase the confirmed status check counter. Only if the ticket is active.', 'event-tickets-with-ticket-scanner'), 'type'=>'checkbox'];
		$options[] = ['key'=>'wcTicketScannerDisplayConfirmedCount', 'label'=>__('Display confirmed status checks on the ticket scanner view', 'event-tickets-with-ticket-scanner'), 'desc'=>__('If active, the confirmed validation checks are displayed whith the retrieved ticket on the ticket scanner.', 'event-tickets-with-ticket-scanner'), 'type'=>'checkbox'];
		$options[] = ['key'=>'wcTicketDontAllowRedeemTicketBeforeStart', 'label'=>__('Do not allow tickets to be redeemed before starting date', 'event-tickets-with-ticket-scanner'), 'desc'=>__('If active, the ticket can only be redeemed at the start date and during the event.', 'event-tickets-with-ticket-scanner'), 'type'=>'checkbox'];
		$options[] = ['key'=>'wcTicketOffsetAllowRedeemTicketBeforeStart', 'label'=>__('How many hours before the event can the ticket be redeemed?', 'event-tickets-with-ticket-scanner'), 'desc'=>__('The hours will be subtracted from the starting time of the event. Only used if the option "wcTicketDontAllowRedeemTicketBeforeStart" is active.', 'event-tickets-with-ticket-scanner'), 'type'=>'number', 'def'=>1, "additional"=>["min"=>0]];

		$options[] = [
				'key'=>'h12',
				'label'=>__("Woocommerce ticket sale", 'event-tickets-with-ticket-scanner'),
				'desc'=>__("You can assign a list to a product and this will generate or re-use a ticket from this list as a ticket number. It will be printed on the purchase information to the customer.", 'event-tickets-with-ticket-scanner'),
				'type'=>"heading"
				];
		$options[] = ['key'=>'wcTicketCompatibilityModeURLPath', 'label'=>__("Ticket detail URL path", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If left empty, default will be using the default ticket detail page from within the plugin folder. On some installations this leads to a 403 problem. If the the default ticket detail view of the plugin is not working try to set the ticket detail URL path. Make sure that the URL path does not exists, otherwise the page will be shown instead of the ticket. Example of a URL path 'event-tickets/myticket' or 'event-tickets/ticket-details/'. Any leading and trailing slash '/' will be ignored.", 'event-tickets-with-ticket-scanner'), 'type'=>"text"];
		$options[] = ['key'=>'wcTicketCompatibilityMode', 'label'=>__("Compatibility mode for ticket URL"), 'desc'=>__("If your theme is showing the 404 title or the ticket is not rendered at all, then you can try to use this compatibility mode. If active, then the URL /ticket/XYZ will be /ticket/?code=XYZ URL for the link to the ticket detail and ticket PDF page. Some themes causing issues with the normal mode."), 'type'=>"checkbox"];
		$options[] = ['key'=>'wcTicketDontShowRedeemBtnOnTicket', 'label'=>__("Do not show the redeem button on the ticket for the client", 'event-tickets-with-ticket-scanner'),'desc'=>__("If active, it will not add the self-redeem button on the ticket detail view.", 'event-tickets-with-ticket-scanner'),'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketPrefixTextCode', 'label'=>__("Text that will be added before the ticket number on the PDF invoice, order table and order details", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If left empty, default will be 'Ticket number:'", 'event-tickets-with-ticket-scanner'), 'type'=>"text", 'def'=>__("Ticket number:", 'event-tickets-with-ticket-scanner'), 'additional'=>[], 'isPublic'=>false];
		$options[] = ['key'=>'wcTicketDontDisplayPDFButtonOnDetail', 'label'=>__("Hide the PDF download button on ticket detail page", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the PDF download button on the ticket detail view. But the PDF can still be generated with the URL.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketDisplayDownloadAllTicketsPDFButtonOnMail', 'label'=>__("Display the all tickets in one PDF download button/link on purchase order email", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, a link to download all tickets as one PDF within the purchase email to the client. Below the order details table.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketDontDisplayPDFButtonOnMail', 'label'=>__("Hide the PDF download button/link on purchase order email", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the PDF download option for a single ticket on the purchase email to the client. But the PDF can still be generated with the URL.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketDontDisplayDetailLinkOnMail', 'label'=>__("Hide the ticket detail page link on purchase order email", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the URL to the ticket detail page on the purchase email to the client.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketLabelPDFDownloadHeading', 'label'=>__("Heading for the Ticket Download section within the purchase order email", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If left empty, default will be 'Download Tickets' as the heading for the section below the order details table.", 'event-tickets-with-ticket-scanner'), 'type'=>"text", 'def'=>__("Download Tickets", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketLabelPDFDownload', 'label'=>__("Text that will be added as the PDF Ticket download label", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If left empty, default will be 'Download PDF Ticket' on the button and on the link within the purchase email.", 'event-tickets-with-ticket-scanner'), 'type'=>"text", 'def'=>__("Download PDF Ticket", 'event-tickets-with-ticket-scanner')];

		if ($this->MAIN->isPremium()) {
			if (function_exists("wc_get_order_statuses")) {
				$order_status = wc_get_order_statuses();
				$def = $this->get_is_paid_statuses();
				$additional = [ "multiple"=>1, "values"=>[] ];
				foreach($order_status as $key => $value) {
					$additional['values'][] = ["label"=>$value, "value"=>substr($key, 3)];
				}
				$options[] = [
						'key'=>'wcTicketAddToOrderOnlyWithOrderStatus',
						'label'=>__("Choose the order status for ticket assignment", 'event-tickets-with-ticket-scanner').' <span style="color:green;">(Premium Feature)</span>',
						'desc'=>__("In doubt, do not play with it. :) The ticket will added to the order items, if the choosen order status is applied to the order. If none is selected, then you need to manually assign the tickets, by clicking the corresponding button within the order details. The button will only be executed, if the order status is paid (processing or completed) then.", 'event-tickets-with-ticket-scanner'),
						'type'=>"dropdown",
						'def'=>$def,
						'additional'=>$additional,
						'isPublc'=>false
						];
			}
		}

		$options[] = [
			'key'=>'h12b2',
			'label'=>__("Ticket PDF settings", 'event-tickets-with-ticket-scanner'),
			'desc'=>"",
			'type'=>"heading"
			];
		$options[] = ['key'=>'wcTicketPDFFontSize', 'label'=>__("Font size for text on the ticket PDF", 'event-tickets-with-ticket-scanner'), 'desc'=>__("Please choose a font size between 6pt and 16pt.", 'event-tickets-with-ticket-scanner'), 'type'=>"dropdown", 'def'=>10, "additional"=>[ "values"=>[["label"=>"6pt", "value"=>6], ["label"=>"7pt", "value"=>7], ["label"=>"8pt", "value"=>8], ["label"=>"9pt", "value"=>9], ["label"=>"10pt", "value"=>10], ["label"=>"11pt", "value"=>11], ["label"=>"12pt", "value"=>12], ["label"=>"13pt", "value"=>13], ["label"=>"14pt", "value"=>14], ["label"=>"15pt", "value"=>15], ["label"=>"16pt", "value"=>16]]] ];
		$options[] = ['key'=>'wcTicketPDFStripHTML', 'label'=>__("Strip HTML from text", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If you experience issues with the rendered PDF, then you can change the settings here to strip some not garanteed supported elements or choose even to display the HTML code (helps for debug purpose).", 'event-tickets-with-ticket-scanner'), 'type'=>"dropdown", 'def'=>1, "additional"=>[ "values"=>[["label"=>__("No HTML strip (default)", 'event-tickets-with-ticket-scanner'), "value"=>1], ["label"=>__("Remove unsupported HTML", 'event-tickets-with-ticket-scanner'), "value"=>2], ["label"=>__("Show HTML Tags as text (Debugging)", 'event-tickets-with-ticket-scanner'), "value"=>3]]] ];
		$options[] = ['key'=>'wcTicketPDFDisplayVariantName', 'label'=>__("Display product variant name", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, the variant name(s) will be display below the title without its variant id. Just the variant value. If more than one variant is choosen, then the delimiter will be a blank space.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox" ];
		$options[] = ['key'=>'wcTicketDisplayShortDesc', 'label'=>__("Display the short description of the product on the ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will be printed on the ticket detail view.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketDisplayCustomerNote', 'label'=>__("Display the customer note of the order on the ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will be printed on the ticket detail view.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketDontDisplayCustomer', 'label'=>__("Hide the customer name and address on the ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not print the customer information on the ticket detail view.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketDontDisplayPayment', 'label'=>__("Hide the payment method on the ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not print the payment details on the ticket detail view.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketDontDisplayPrice', 'label'=>__("Hide your ticket price.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, the ticket price will not be displayed on the ticket and the PDF ticket. The ticket scanner will still display the price.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketDisplayPurchasedItemFromOrderOnTicket', 'label'=>__("Display the purchased items of the order on the ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will print all the products of the order on the ticket. The ticket product will be excluded from the list.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketDisplayPurchasedTicketQuantity', 'label'=>__("Display the quantity of the purchased item on the ticket.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will print the amount of the purchased tickets on the ticket.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketDisplayTicketListName', 'label'=>__("Display the ticket list name on the ticket.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will print the name of the ticket list.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketDisplayTicketListDesc', 'label'=>__("Display the ticket list description on the ticket.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will print the description of the ticket list on the ticket.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketPrefixTextTicketQuantity', 'label'=>__("Text that will be added to the PDF if the option <b>'Display the quantity of the purchased tickets'</b> is activated.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If left empty, default will be '{TICKET_POSITION} of {TICKET_TOTAL_AMOUNT} Tickets'. {TICKET_POSITION} will be replaced with the position within the quantity of the item purchase. {TICKET_TOTAL_AMOUNT} will be replaced with the quantity of the purchased tickets for the order.", 'event-tickets-with-ticket-scanner'), 'type'=>"text", 'def'=>__("{TICKET_POSITION} of {TICKET_TOTAL_AMOUNT} Tickets", 'event-tickets-with-ticket-scanner'), 'additional'=>[], 'isPublic'=>false];
		$options[] = ['key'=>'wcTicketDisplayTicketUserValue', 'label'=>__("Display the registered user value on the ticket.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will print the registered user value on the ticket. The value and the label for it are only displayed, if the registered user value is not empty.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>"", 'additional'=>[]];
		$options[] = ['key'=>'wcTicketDontDisplayBlogName', 'label'=>__("Hide your wordpress name", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the wordpress name.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketDontDisplayBlogDesc', 'label'=>__("Hide your blog description", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the wordpress description.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketDontDisplayBlogURL', 'label'=>__("Hide your wordpress URL", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the wordpress URL.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketTicketLogo', 'label'=>__("Display a small logo (max. 300x300px) at the bottom in the center", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If a media file is chosen, the logo will be placed on the ticket PDF.", 'event-tickets-with-ticket-scanner'), 'type'=>"media", 'def'=>""
						, 'additional'=>[
							'max'=>['width'=>200,'height'=>200],
							'button'=>esc_attr__('Choose logo for the ticket PDF', 'event-tickets-with-ticket-scanner'),
							'msg_error'=>[
								'width'=>__('Too big! Choose an image with smaller size. Max 300px width, otherwise it will look not good on your ticket.', 'event-tickets-with-ticket-scanner')
							]
						]
					];
		$options[] = ['key'=>'wcTicketTicketBanner', 'label'=>__("Display a banner image image at the top of the PDF", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If a media file is chosen, the banner will be placed on the ticket PDF.", 'event-tickets-with-ticket-scanner'), 'type'=>"media", 'def'=>""
					, 'additional'=>[
						'min'=>['width'=>600],
						'button'=>esc_attr__('Choose banner image for the ticket PDF', 'event-tickets-with-ticket-scanner'),
						'msg_error_min'=>[
							'width'=>__('Too small! Choose an image with bigger size. Min 600px width, otherwise it will look not good on your ticket.', 'event-tickets-with-ticket-scanner')
							]
						]
					];
		$options[] = ['key'=>'wcTicketTicketBG', 'label'=>__("Display a background image image at the center of the PDF", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If a media file is chosen, the image will be placed on the ticket PDF.", 'event-tickets-with-ticket-scanner'), 'type'=>"media", 'def'=>""
					, 'additional'=>[
						'button'=>esc_attr__('Choose background image for the ticket PDF', 'event-tickets-with-ticket-scanner')
						]
					];
		$options[] = ['key'=>'wcTicketTicketAttachPDFOnTicket', 'label'=>__("Attach additional PDF to the PDF ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If a PDF file is chosen, the PDF will be attached to the PDF ticket.", 'event-tickets-with-ticket-scanner'), 'type'=>"media", 'def'=>""
					, 'additional'=>[
						'type_filter'=>'*',
						'button'=>esc_attr__('Choose PDF to be added to the ticket PDF', 'event-tickets-with-ticket-scanner')
						]
					];

		$options[] = [
			'key'=>'h12b1',
			'label'=>__("Ticket Translations", 'event-tickets-with-ticket-scanner'),
			'desc'=>"",
			'type'=>"heading"
			];
		$options[] = ['key'=>'wcTicketHeading', 'label'=>__("Ticket title", 'event-tickets-with-ticket-scanner'), 'desc'=>__("This is the title of the ticket", 'event-tickets-with-ticket-scanner'), 'type'=>"text", 'def'=>__("Ticket", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransExpired', 'label'=>__("Label 'EXPIRED' on the event date", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("EXPIRED", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransLocation', 'label'=>__("Label 'Location' heading on for the event location", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Location", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransCustomer', 'label'=>__("Label 'Customer' heading on the customer details", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Customer", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransPaymentDetail', 'label'=>__("Label 'Payment details' heading on the payment details", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Payment details", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransPaymentDetailPaidAt', 'label'=>__("Label 'Order paid at' on the payment details", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Order paid at:", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransPaymentDetailCompletedAt', 'label'=>__("Label 'Order completed at' on the payment details", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Order completed at:", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransPaymentDetailPaidVia', 'label'=>__("Label 'Paid via' on the payment details", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Paid via:", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransPaymentDetailFreeTicket', 'label'=>__("Label 'Free ticket' on the payment details", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Free ticket", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransPaymentDetailCouponUsed', 'label'=>__("Label 'Coupon used' on the payment details", 'event-tickets-with-ticket-scanner'), 'desc'=>__("It will display which coupon was used.", 'event-tickets-with-ticket-scanner'), 'type'=>"text", 'def'=>__("Coupon used:", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransTicket', 'label'=>__("Label 'Ticket' for the ticket number", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Ticket:", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransPrice', 'label'=>__("Label 'Price' for the paid price", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Price:", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransProductPrice', 'label'=>__("Label 'Original price' for the ticket number", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Original price:", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransTicketRedeemed', 'label'=>__("Label 'Ticket redeemed' for the customer notice", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Ticket redeemed", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransRedeemDate', 'label'=>__("Label 'Redeemed at' for the customer notice", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Redeemed at:", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransTicketValid', 'label'=>__("Label 'Ticket valid' for the customer notice", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Ticket valid", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransRefreshPage', 'label'=>__("Label 'Refresh page' for the button", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Refresh page", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransRedeemQuestion', 'label'=>__("Label 'Do you want to redeem the ticket?' for the question to your client", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Do you want to redeem the ticket? Typically this is done at the entrance. This will mark this ticket as redeemed.", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransBtnRedeemTicket', 'label'=>sprintf(/* translators: %s: default value */__("Label '%s' for the button to your client", 'event-tickets-with-ticket-scanner'), __("Redeem Ticket", 'event-tickets-with-ticket-scanner')), 'desc'=>"", 'type'=>"text", 'def'=>__("Redeem Ticket", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransTicketExpired', 'label'=>sprintf(/* translators: %s: default value */__("Label Error '%s' for the customer notice", 'event-tickets-with-ticket-scanner'), __("Ticket expired", 'event-tickets-with-ticket-scanner')), 'desc'=>"", 'type'=>"text", 'def'=>__("Ticket expired", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransTicketIsStolen', 'label'=>__("Label Error 'Ticket is STOLEN' for the customer notice", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Ticket is STOLEN", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransTicketNotValid', 'label'=>__("Label Error 'Ticket is not valid' for the customer notice", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Ticket is not valid", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransTicketNumberWrong', 'label'=>__("Label Error 'Ticket number is wrong' for the customer notice", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"text", 'def'=>__("Ticket number is wrong", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransRedeemMaxAmount', 'label'=>__("Text for max redeem amount for the customer notice on the PDF ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>sprintf(/* translators: %s: max amount ticket redeem */__("This text will be added to the PDF ticket only if the ticket can be redeemed more than one time! Use the placeholder %s to display the amount.", 'event-tickets-with-ticket-scanner'), '{MAX_REDEEM_AMOUNT}'), 'type'=>"text", 'def'=>sprintf(/* translators: %s: max amount ticket redeem */__("You can redeem this ticket <b>%s times</b> within the valid period.", 'event-tickets-with-ticket-scanner'), '{MAX_REDEEM_AMOUNT}')];
		$options[] = ['key'=>'wcTicketTransRedeemedAmount', 'label'=>__("Text for redeemed amount for the customer notice on the ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>sprintf(/* translators: 1: amount redeemed ticket 2: max amount ticket redeem */__('This text will be added to the ticket scanner and ticket detail page view. Only if the ticket can be redeemed more than one time! Use the placeholders %1$s and %2$s and to display the amounts.', 'event-tickets-with-ticket-scanner'), '{REDEEMED_AMOUNT}', '{MAX_REDEEM_AMOUNT}'), 'type'=>"text", 'def'=>sprintf(/* translators: 1: amount redeemed ticket 2: max amount ticket redeem */__('You have used this ticket %1$s of %2$s.', 'event-tickets-with-ticket-scanner'), '{REDEEMED_AMOUNT}', '{MAX_REDEEM_AMOUNT}')];
		$options[] = ['key'=>'wcTicketTransTicketNotValidToEarly', 'label'=>__("Label Error 'Ticket is not valid yet' for the customer notice", 'event-tickets-with-ticket-scanner'), 'desc'=>"Will be shown on the ticket scanner, if the ticket is too early scanned.", 'type'=>"text", 'def'=>__("Ticket is not valid yet", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketTransDisplayTicketUserValue', 'label'=>__("Label User registered value on the ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>"Will be shown on the ticket, if the corresponding ticket option is activated and the registered user value is not empty.", 'type'=>"text", 'def'=>__("User value:", 'event-tickets-with-ticket-scanner')];

		$options[] = [
			'key'=>'h12b',
			'label'=>__("Ticket Redirect", 'event-tickets-with-ticket-scanner'),
			'desc'=>__("If you customer redeem their own ticket, you can redirect them to another page. For this, the feature 'Do not show the redeem button on the ticket for the client' has to be NOT checked.", 'event-tickets-with-ticket-scanner'),
			'type'=>"heading"
			];
		$options[] = ['key'=>'wcTicketRedirectUser', 'label'=>__("Activate redirect the user after redeeming their own ticket.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, the user will be redirected to the URL your provide below.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketRedirectUserURL', 'label'=>__("URL to redirect the user, if the ticket was redeemed.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("The URL can be relative like '/page/' or absolute 'https//domain/url/'.<br>You can use these placeholder for your URL:<ul><li><b>{USERID}</b>: Will be replaced with the userid if the user is loggedin or empty</li><li><b>{CODE}</b>: Will be replaced with the ticket number (without the delimiters)</li><li><b>{CODEDISPLAY}</b>: Will be replaced with the ticket number (WITH the delimiters)</li><li><b>{IP}</b>: The IP address of the user</li><li><b>{LIST}</b>: Name of the list if assigned</li><li><b>{LIST_DESC}</b>: Description of the assigned list</li><li><a href='#replacementtags'>More tags here</a></li></ul>", 'event-tickets-with-ticket-scanner'), 'type'=>"text", 'def'=>""];

		$options[] = [
			'key'=>'h12c',
			'label'=>__("Event Flyer", 'event-tickets-with-ticket-scanner'),
			'desc'=>__("You can download a PDF flyer for your event within the product detail view. Control the components to be displayed.", 'event-tickets-with-ticket-scanner'),
			'type'=>"heading"
			];
		$options[] = ['key'=>'wcTicketFlyerDontDisplayBlogName', 'label'=>__("Hide your wordpress name.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the wordpress name.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketFlyerDontDisplayBlogDesc', 'label'=>__("Hide your wordpress description.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the wordpress description.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketFlyerDontDisplayBlogURL', 'label'=>__("Hide your wordpress URL.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the wordpress URL.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketFlyerDontDisplayPrice', 'label'=>__("Hide your ticket price.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, the ticket price will not be displayed.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketFlyerLogo', 'label'=>__("Display a small logo (max. 300x300px) at the bottom in the center.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If a media file is choosen, the logo will be placed on the flyer.", 'event-tickets-with-ticket-scanner'), 'type'=>"media", 'def'=>""
						, 'additional'=>[
							'max'=>['width'=>200,'height'=>200],
							'button'=>esc_attr__('Choose logo for the ticket flyer', 'event-tickets-with-ticket-scanner'),
							'msg_error'=>[
								'width'=>__('Too big! Choose an image with smaller size. Max 300px width, otherwise it will look not good on your flyer.', 'event-tickets-with-ticket-scanner')
							]
						]
					];
		$options[] = ['key'=>'wcTicketFlyerBanner', 'label'=>__("Display a banner image image at the top of the PDF.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If a media file is choosen, the banner will be placed on the flyer.", 'event-tickets-with-ticket-scanner'), 'type'=>"media", 'def'=>""
					, 'additional'=>[
						'min'=>['width'=>600],
						'button'=>esc_attr__('Choose banner image for the ticket flyer', 'event-tickets-with-ticket-scanner'),
						'msg_error_min'=>[
							'width'=>__('Too small! Choose an image with bigger size. Min 600px width, otherwise it will look not good on your flyer.', 'event-tickets-with-ticket-scanner')
							]
						]
					];
		$options[] = ['key'=>'wcTicketFlyerBG', 'label'=>__("Display a background image image at the center of the PDF.", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If a media file is choosen, the image will be placed on the ticket flyer.", 'event-tickets-with-ticket-scanner'), 'type'=>"media", 'def'=>""
					, 'additional'=>[
						'button'=>esc_attr__('Choose background image for the ticket flyer', 'event-tickets-with-ticket-scanner')
						]
					];

		$options[] = ['key'=>'h12d', 'label'=>__("Calendar file (ICS)", 'event-tickets-with-ticket-scanner'), 'desc'=>__("The ICS calendar file will cointain the event info and date (if added). This allows your customer to add the event easily from within the email to their calendar. Will work on most mail client.", 'event-tickets-with-ticket-scanner'), 'type'=>"heading"];
		$options[] = ['key'=>'wcTicketDontDisplayICSButtonOnDetail', 'label'=>__("Hide the ICS calendar file download button on ticket detail page", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will not display the calendar file download button on the ticket detail view. It will be only shown if the ticket product has a starting date.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketLabelICSDownload', 'label'=>__("Text that will be added as the ICS calendar file download label", 'event-tickets-with-ticket-scanner'), 'desc'=>sprintf(/* translators: %s: default value */__('If left empty, default will be "%s"', 'event-tickets-with-ticket-scanner'), __("Download calendar file", 'event-tickets-with-ticket-scanner')), 'type'=>"text", 'def'=>__("Download calendar file", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketAttachICSToMail', 'label'=>__("Attach the ICS calendar file to the WooCommerce mails", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, the ICS calendar file will be added as an attachment to the mails (order complete, customer note, customer invoice and processing order)", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketDisplayDateOnMail', 'label'=>__("Show the event date on purchase order email", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active and a date is set on the product, then it will display the date of the event on the purchase email to the client.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketDisplayDateOnPrdDetail', 'label'=>__("Show the event date on the product detail page for your customer", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active and a date is set on the product, then it will display the date of the event on the product detail page to the client.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketHideDateOnPDF', 'label'=>__("Hide the event date on the ticket", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active the event date is not shown on the ticket.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];

		$options[] = ['key'=>'h20', 'label'=>__("User profile", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"heading"];
		$options[] = ['key'=>'wcTicketUserProfileDisplayRegisteredNumbers', 'label'=>__("Display registered ticket numbers within the user profile", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketUserProfileDisplayBoughtNumbers', 'label'=>__("Display bought ticket numbers within the user profile", 'event-tickets-with-ticket-scanner'), 'desc'=>"", 'type'=>"checkbox", 'def'=>""];

		$badgeHTMLDefault = $this->MAIN->getTicketBadgeHandler()->getDefaultTemplate();
		$desc = $this->MAIN->getTicketBadgeHandler()->getReplacementTagsExplanation();
		$options[] = ['key'=>'h15', 'label'=>__("Ticket Badge", 'event-tickets-with-ticket-scanner'), 'desc'=>__("You can download a badge for each ticket. This badge can be give to your customer so they can wear it as a name badge. You can download the badge PDF within the ticket detail view.", 'event-tickets-with-ticket-scanner'), 'type'=>"heading"];
		$options[] = ['key'=>'wcTicketBadgeDisplayButtonOnDetail', 'label'=>__("Show ticket badge download button on ticket detail page", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, it will display the ticket badge file download button on the ticket detail view.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketBadgeLabelDownload', 'label'=>__("Text that will be added as the ticket badge file download label", 'event-tickets-with-ticket-scanner'), 'desc'=>sprintf(/* translators: %s: default value */__('If left empty, default will be "%s"', 'event-tickets-with-ticket-scanner'), __("Download ticket badge", 'event-tickets-with-ticket-scanner')), 'type'=>"text", 'def'=>__("Download ticket badge", 'event-tickets-with-ticket-scanner')];
		$options[] = ['key'=>'wcTicketBadgeAttachLinkToMail', 'label'=>__("Attach the ticket badge download link to the WooCommerce mails", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, the ticket badge download link will be added to the mails.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketBadgeAttachFileToMail', 'label'=>__("Attach the ticket badge file to the WooCommerce mails", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, the ticket badge file will be added as an attachment to the mails.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketBadgeAttachFileToMailAsOnePDF', 'label'=>__("Attach alle ticket badges of an order to the WooCommerce mails as one PDF", 'event-tickets-with-ticket-scanner'), 'desc'=>__("If active, the ticket badge files are merged into one PDF and will be added as an attachment to the mails.", 'event-tickets-with-ticket-scanner'), 'type'=>"checkbox", 'def'=>""];
		$options[] = ['key'=>'wcTicketBadgeSizeWidth', 'label'=>__('Size in mm for the width', 'event-tickets-with-ticket-scanner'), 'desc'=>__('Will be used to set the width of the PDF for the badge. If empty or zero, the default of 80 will be used.', 'event-tickets-with-ticket-scanner'), 'type'=>'number', 'def'=>80, "additional"=>["min"=>20]];
		$options[] = ['key'=>'wcTicketBadgeSizeHeight', 'label'=>__('Size in mm for the height', 'event-tickets-with-ticket-scanner'), 'desc'=>__('Will be used to set the height of the PDF for the badge. If empty or zero, the default of 120 will be used.', 'event-tickets-with-ticket-scanner'), 'type'=>'number', 'def'=>120, "additional"=>["min"=>20]];
		$options[] = ['key'=>'wcTicketBadgeText', 'label'=>__("The HTML value for the PDF", 'event-tickets-with-ticket-scanner'), 'desc'=>__('If left empty, default will be used.', 'event-tickets-with-ticket-scanner'), 'type'=>"textarea", 'def'=>$badgeHTMLDefault, "additional"=>["rows"=>10]];
		$options[] = ['key'=>'h15_desc', 'label'=>__("Possible Tags", 'event-tickets-with-ticket-scanner'), 'desc'=>$desc, 'type'=>"desc"];

		return $options;
	}

	private function isScanner() {
		// /wp-content/plugins/event-tickets-with-ticket-scanner/ticket/scanner/
		if ($this->isScanner == null) {

			if ($this->onlyLoggedInScannerAllowed) {
				if (!in_array('administrator',  wp_get_current_user()->roles)) {
					return false;
				}
			}

			$ret = false;
			$teile = explode("/", $this->request_uri);
			$teile = array_reverse($teile);
			if (count($teile) > 1) {
				if (substr(strtolower(trim($teile[1])), 0, 7) == "scanner") $ret = true;
			}
			$this->isScanner = $ret;
		}
		return $this->isScanner;
	}

	public function setOrder($order) {
		$this->order = $order;
	}

	private function getOrderById($order_id) {
		$order = null;
		if (function_exists("wc_get_order")) {
			$order = wc_get_order( $order_id );
			if (!$order) throw new Exception("#8009 Order not found");
		}
		return $order;
	}

	private function getOrder() {
		if ($this->order != null) return $this->order;

		$codeObj = $this->getCodeObj();
		if (intval($codeObj['order_id']) == 0) throw new Exception("Order not available");

		$this->order = $this->getOrderById($codeObj['order_id']);
		return $this->order;
	}

	public function get_product($product_id) {
		$product = null;
		if (function_exists("wc_get_product")) {
			$product = wc_get_product( $product_id );
		}
		return $product;
	}


	public function get_is_paid_statuses() {
		$def = ['processing', 'completed'];
		if (function_exists("wc_get_is_paid_statuses")) {
			$def = wc_get_is_paid_statuses();
		}
		return $def;
	}

	private function getParts($code="") {
		if ($this->parts == null) {
			if ($this->isScanner()) {
				if (!SASO_EVENTTICKETS::issetRPara('code')) {
					throw new Exception("#8007 ticket number not provided");
				} else {
					$uri = trim(SASO_EVENTTICKETS::getRequestPara('code', $def=''));
					$this->parts =  $this->getCore()->getTicketURLComponents($uri);
				}
			} else {
				$this->parts =  $this->getCore()->getTicketURLComponents($this->request_uri);
			}
		}
		return $this->parts;
	}

	static public function generateICSFile($product) {
		$product_id = $product->get_id();
		$titel = $product->get_name();
		$short_desc = "";

		global $sasoEventtickets;
		if (isset($sasoEventtickets)) {
			if ($sasoEventtickets->getOptions()->isOptionCheckboxActive('wcTicketDisplayShortDesc')) {
				$short_desc .= trim($product->get_short_description());
			}
		}

		$tzid = wp_timezone_string();
		//$tzid_text = empty($tzid) ? '' : ';TZID="'.wp_timezone_string().'":';

		$ticket_info = trim(get_post_meta( $product->get_id(), 'saso_eventtickets_ticket_is_ticket_info', true ));
		if (!empty($short_desc) && !empty($ticket_info)) $short_desc .= "\n\n";
		$short_desc .= trim($ticket_info);
		$ticket_start_date = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_start_date', true ));
		if (empty($ticket_start_date)) throw new Exception(esc_html__("No date available", 'event-tickets-with-ticket-scanner'));
		$ticket_start_time = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_start_time', true ));
		$start_timestamp = strtotime(trim($ticket_start_date." ".$ticket_start_time));

		$ticket_end_date = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_end_date', true ));

		$DTSTART_line = "DTSTART";
		$DTEND_line = "";
		if (empty($ticket_start_time)) {
			$DTSTART_line .= ";VALUE=DATE:".date("Ymd", $start_timestamp);
			if (!empty($ticket_end_date)) {
				$DTEND_line .= ";VALUE=DATE:".date("Ymd", strtotime(trim($ticket_start_date)));
			}
		} else {
			$DTEND_line = "DTEND";
			if (!empty($tzid)) {
				$DTSTART_line .= ";TZID=".$tzid;
				$DTEND_line .= ";TZID=".$tzid;
			}
			$DTSTART_line .= ":".date("Ymd\THis", $start_timestamp);

			if (empty($ticket_end_date)) $ticket_end_date = $ticket_start_date;
			$ticket_end_time = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_end_time', true ));
			if (empty($ticket_end_time)) $ticket_end_time = "23:59:59";
			$end_timestamp = strtotime(trim($ticket_end_date." ".$ticket_end_time));
			$DTEND_line .= ":".date("Ymd\THis", $end_timestamp);
		}

		$LOCATION = trim(get_post_meta( $product_id, 'saso_eventtickets_event_location', true ));

		$temp = wp_kses_post(str_replace(array("\r\n", "<br>"),"\n",$short_desc));
		$lines = explode("\n",$temp);
		$new_lines =array();
		foreach($lines as $i => $line) {
			if(!empty($line))
			$new_lines[]=trim($line).'\n';
		}
		$desc = implode("\r\n ",$new_lines);

		$event_url = get_permalink( $product->get_id() );
		$uid = $product_id."-".date("Y-m-d-H-i-s")."-".get_site_url();

		$ret = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//hacksw/handcal//NONSGML v1.0//EN\nBEGIN:VEVENT\n";
		$ret .= "UID:".$uid."\n";
		$ret .= "LOCATION:".htmlentities($LOCATION)."\n";
		$ret .= "DTSTAMP:".gmdate("Ymd\THis")."\n";
		$ret .= $DTSTART_line."\n";
		if (!empty($DTEND_line)) $ret .= $DTEND_line."\n";
		$ret .= "SUMMARY:".$titel."\n";
		$ret .= "DESCRIPTION:".htmlentities($desc).'\n'."\r\n ".$event_url."\n";
		$ret .= "X-ALT-DESC;FMTTYPE=text/html:".$desc."<br>".$event_url."\n";
		$ret .= "URL:".trim($event_url)."\n";
		$ret .= "END:VEVENT\n";
		$ret .= "END:VCALENDAR";
		return $ret;
	}

	public function setCodeObj($codeObj) {
		$this->codeObj = $codeObj;
	}
	public function setMetaObj($codeObj) {
		if (!isset($codeObj["metaObj"])) {
			$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
			$codeObj["metaObj"] = $metaObj;
		}
		return $codeObj;
	}
	private function getCodeObj($dontFailPaid=false, $code=""){
		global $sasoEventtickets;
		if ($this->codeObj != null) {
			$this->codeObj = $this->setMetaObj($this->codeObj);
			return $this->codeObj;
		}
		$codeObj = $this->getCore()->retrieveCodeByCode($this->getParts()['code']);
		if ($codeObj['aktiv'] == 2) throw new Exception("#8005 ".esc_html($this->getAdminSettings()->getOptionValue("wcTicketTransTicketIsStolen")));
		if ($codeObj['aktiv'] != 1) throw new Exception("#8006 ".esc_html($this->getAdminSettings()->getOptionValue("wcTicketTransTicketNotValid")));
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		$codeObj["metaObj"] = $metaObj;

		// check ob order_id stimmen
		if ($this->getParts()['order_id'] != $codeObj['order_id']) throw new Exception("#8001 ".esc_html($this->getAdminSettings()->getOptionValue("wcTicketTransTicketNumberWrong")));
		// check idcode
		if ($this->getParts()['idcode'] != $metaObj['wc_ticket']['idcode']) throw new Exception("#8006 ".esc_html($this->getAdminSettings()->getOptionValue("wcTicketTransTicketNumberWrong")));
		// check ob serial ein ticket ist
		if ($metaObj['wc_ticket']['is_ticket'] != 1) throw new Exception("#8002 ".esc_html($this->getAdminSettings()->getOptionValue("wcTicketTransTicketNotValid")));
		// check ob order bezahlt ist
		if ($dontFailPaid == false) {
			$order = $this->getOrderById($codeObj["order_id"]);
			$ok_order_statuses = $this->get_is_paid_statuses();
			if (!$dontFailPaid && !$this->isPaid($order)) throw new Exception("#8003 Ticket payment is not completed. The ticket order status has to be set to a paid status like ".join(" or ", $ok_order_statuses).".");
		}

		$this->codeObj = $codeObj;
		return $codeObj;
	}

	private function isPaid($order) {
		return SASO_EVENTTICKETS::isOrderPaid($order);
	}

	public function outputTicketScannerStandalone() {
		header('HTTP/1.1 200 OK');
		$this->MAIN->setTicketScannerJS();
		//get_header();
		echo '<html><head>';
		?>
		<style>
            body {font-family: Helvetica, Arial, sans-serif;}
            h3,h4,h5 {padding-bottom:0.5em;margin-bottom:0;}
            p {padding:0;margin:0;margin-bottom:1em;}
			div.ticket_content p {font-size:initial !important;margin-bottom:1em !important;}
            button {padding:10px;font-size: 1.5em;}
            .lds-dual-ring {display:inline-block;width:64px;height:64px;}
            .lds-dual-ring:after {content:" ";display:block;width:46px;height:46px;margin:1px;border-radius:50%;border:5px solid #fff;border-color:#2e74b5 transparent #2e74b5 transparent;animation:lds-dual-ring 0.6s linear infinite;}
            @keyframes lds-dual-ring {0% {transform: rotate(0deg);} 100% {transform: rotate(360deg);}}
		</style>
		<?php
		wp_head();
		?>
		</head><body>
		<center>
        <h1>Ticket Scanner</h1>
        <div style="width:90%;max-width:800px;">
            <div style="width: 100%; justify-content: center;align-items: center;position: relative;">
                <div class="ticket_content" style="background-color:white;color:black;padding:15px;display:block;position: relative;left: 0;right: 0;margin: auto;text-align:left;border:1px solid black;">
                    <div id="ticket_scanner_info_area"></div>
                    <div id="ticket_info_retrieved" style="padding-top:20px;padding-bottom:20px;"></div>
                    <div id="ticket_info"></div>
                    <div id="ticket_add_info"></div>
                    <div id="ticket_info_btns" style="padding-top:20px;padding-bottom:20px;"></div>
                    <div id="reader_output"></div>
                    <div id="reader" style="width:100%"></div>
					<div id="reader_options" style="width:100%"></div>
                </div>
            </div>
        </div>
        </center>
		<?php
		//echo determine_locale();
		//load_script_translations(__DIR__.'/languages/event-tickets-with-ticket-scanner-de_CH-ajax_script_ticket_scanner.json', 'ajax_script_ticket_scanner', 'event-tickets-with-ticket-scanner');
		get_footer();
		//wp_footer();
		//echo '</body></html>';
	}

	public function outputTicketScanner() {
		echo '<center>';
		echo '<h3>'.__('Ticket scanner', 'event-tickets-with-ticket-scanner').'</h3>';
		echo '<div id="ticket_scanner_info_area">';
		if (isset($_GET['code']) && isset($_GET['redeemauto']) && $this->redeem_successfully == false) {
			echo '<h3 style="color:red;">'.esc_html__('TICKET NOT REDEEMED - see reason below', 'event-tickets-with-ticket-scanner').'</h3>';
		} else if (isset($_GET['code']) && isset($_GET['redeemauto']) && $this->redeem_successfully) {
			echo '<h3 style="color:green;">'.esc_html__('TICKET OK - Redeemed', 'event-tickets-with-ticket-scanner').'</h3>';
		}
		echo '</div>';

		echo '</center>';
		echo '<div id="reader_output">';
		if (SASO_EVENTTICKETS::issetRPara("code")) {
			try {
				$codeObj = $this->getCodeObj();
				$metaObj = $codeObj["metaObj"];

				$ticket_id = $this->getCore()->getTicketId($codeObj, $metaObj);
				$ticket_start_date = trim(get_post_meta( $metaObj['woocommerce']['product_id'], 'saso_eventtickets_ticket_start_date', true ));
				$ticket_start_time = trim(get_post_meta( $metaObj['woocommerce']['product_id'], 'saso_eventtickets_ticket_start_time', true ));
				$ticket_end_date = trim(get_post_meta( $metaObj['woocommerce']['product_id'], 'saso_eventtickets_ticket_end_date', true ));
				$ticket_end_time = trim(get_post_meta( $metaObj['woocommerce']['product_id'], 'saso_eventtickets_ticket_end_time', true ));
				$ticket_end_date_timestamp = strtotime($ticket_end_date." ".$ticket_end_time);
				$color = 'green';
				if ($ticket_end_date != "" && $ticket_end_date_timestamp < time()) {
					$color = 'orange';
				}
				if (!empty($metaObj['wc_ticket']['redeemed_date'])) {
					$color = 'red';
				}

				if (isset($_POST['action']) && $_POST['action'] == "redeem") {
					$pfad = plugins_url( "img/",__FILE__ );
					if ($this->redeem_successfully) {
						echo '<p style="text-align:center;color:green"><img src="'.$pfad.'button_ok.png"><br><b>'.__("Successfully redeemed", 'event-tickets-with-ticket-scanner').'</b></p>';
					} else {
						echo '<p style="text-align:center;color:red;"><img src="'.$pfad.'button_cancel.png"><br><b>'.__("Failed to redeem", 'event-tickets-with-ticket-scanner').'</b></p>';
					}
				}

				echo '<div style="border:5px solid '.esc_attr($color).';margin:10px;padding:10px;">';
				$this->outputTicketInfo();
				echo '</div>';

				echo '<form id="f_reload" action="?" method="get">
				<input type="hidden" name="code" value="'.urlencode($ticket_id).'">
				</form>';
				echo '
					<script>
					function reload_ticket() {
						document.getElementById("f_reload").submit();
					}
					</script>
				';
				if (empty($metaObj['wc_ticket']['redeemed_date'])) {
					echo '<form id="f_redeem" action="?" method="post">
							<input type="hidden" name="action" value="redeem">
							<input type="hidden" name="code" value="'.urlencode($ticket_id).'">
							</form></p></center>';
					echo '
						<script>
						function redeem_ticket() {
							document.getElementById("f_redeem").submit();
						}
						</script>
					';
				}
				echo '<center><p><button onclick="reload_ticket()">'.esc_attr__("Reload Ticket", 'event-tickets-with-ticket-scanner').'</button>';
				if (empty($metaObj['wc_ticket']['redeemed_date'])) {
					echo '<button onclick="redeem_ticket()" style="background-color:green;color:white;">'.__("Redeem Ticket", 'event-tickets-with-ticket-scanner').'</button>';
				}
				echo '</p></center>';
			} catch (Exception $e) {
				echo '</div>';
				echo '<div style="color:red;">'.$e->getMessage().'</div>';
				echo $this->getParts()['code'];
			}
		}
		echo '</div>';
		echo '<center>';
		echo '<div id="reader" width="600px"></div>';
		echo '</center>';
		echo '<script>
			var serial_ticket_scanner_redeem = '.(isset($_GET['redeemauto']) ? 'true' : 'false').';
			var loadingticket = false;
			function setRedeemImmediately() {
				serial_ticket_scanner_redeem = !serial_ticket_scanner_redeem;
			}
			function onScanSuccess(decodedText, decodedResult) {
				if (loadingticket) return;
				loadingticket = true;
				// handle the scanned code as you like, for example:
				jQuery("#reader_output").html(decodedText+"<br>...'.__("loading", 'event-tickets-with-ticket-scanner').'...");
				window.location.href = "?code="+encodeURIComponent(decodedText) + (serial_ticket_scanner_redeem ? "&redeemauto=1" : "");
				window.setTimeout(()=>{
					html5QrcodeScanner.stop().then((ignore) => {
						// QR Code scanning is stopped.
						// reload the page with the ticket info and redeem button
						//console.log("stop success");
					}).catch((err) => {
						// Stop failed, handle it.
						//console.log("stop failed");
					});
				}, 250);
		  	}
		  	function onScanFailure(error) {
				// handle scan failure, usually better to ignore and keep scanning.
				// for example:
				console.warn("Code scan error = ${error}");
		  	}
		  	var html5QrcodeScanner = new Html5QrcodeScanner(
				"reader",
				{ fps: 10, qrbox: {width: 250, height: 250} },
				/* verbose= */ false);
		  </script>';
	  	echo '<script>
		  function startScanner() {
				jQuery("#ticket_scanner_info_area").html("");
				jQuery("#reader_output").html("");
			  	html5QrcodeScanner.render(onScanSuccess, onScanFailure);
		  }
		  </script>';

		if (SASO_EVENTTICKETS::issetRPara("code")) {
			echo "<center>";
			echo '<input type="checkbox" onclick="setRedeemImmediately()"'.(SASO_EVENTTICKETS::issetRPara("redeemauto") ? " ".'checked' :'').'> '.esc_html__('Scan and Redeem immediately', 'event-tickets-with-ticket-scanner').'<br>';
			echo '<button onclick="startScanner()">'.esc_attr__("Scan next Ticket", 'event-tickets-with-ticket-scanner').'</button>';
			echo "</center>";

			// display the amount entered already
			$redeemed_tickets = $this->rest_helper_tickets_redeemed($codeObj);
			if ($redeemed_tickets['tickets_redeemed_show']) {
				echo "<center><h5>";
				echo $redeemed_tickets['tickets_redeemed']." ".__('ticket redeemed already', 'event-tickets-with-ticket-scanner');
				echo "</h5></center>";
			}
		} else {
			echo '<script>
			startScanner();
			</script>';
		}
	}

	private function sendBadgeFile() {
		$codeObj = $this->getCodeObj(true);
		$badgeHandler = $this->MAIN->getTicketBadgeHandler();
		$badgeHandler->downloadPDFTicketBadge($codeObj);
		die();
	}

	private function sendICSFile() {
		$codeObj = $this->getCodeObj(true);
		$metaObj = $codeObj['metaObj'];
		do_action( $this->MAIN->_do_action_prefix.'trackIPForICSDownload', $codeObj );
		$product_id = $metaObj['woocommerce']['product_id'];
		$this->sendICSFileByProductId($product_id);
	}

	public function sendICSFileByProductId($product_id) {
		$product = $this->get_product( $product_id );
		$contents = self::generateICSFile($product);
		SASO_EVENTTICKETS::sendeDaten($contents, "ics_".$product_id.".ics", "text/calendar");
	}

	/**
	 * will generate all tickets PDF
	 * then merge them together to one PDF
	 */
	public function outputPDFTicketsForOrder($order, $filemode="I") {
		$tickets = $this->MAIN->getWC()->getTicketsFromOrder($order);
		if (count($tickets) > 0) {
			set_time_limit(0);
			$this->setOrder($order);
			if ($filemode == "I") {
				do_action( $this->MAIN->_do_action_prefix.'trackIPForPDFOneView', $order );
			}
			$filepaths = [];
			foreach($tickets as $product_id => $obj) {
				$codes = [];
				if (!empty($obj['codes'])) {
					$codes = explode(",", $obj['codes']);
				}
				foreach($codes as $code) {
					try {
						$codeObj = $this->getCore()->retrieveCodeByCode($code);
					} catch (Exception $e) {
						continue;
					}
					$this->setCodeObj($codeObj);
					// attach PDF
					$filepaths[] = $this->outputPDF("F");
				}
			}
			$filename = "tickets_".$order->get_id().".pdf";
			// merge files
			$fullFilePath = $this->MAIN->getCore()->mergePDFs($filepaths, $filename, $filemode);
			return $fullFilePath; // if not already exit call was made
		}
	}

	public function outputPDF($filemode="I") {
		$codeObj = $this->getCodeObj(true);
		$metaObj = $codeObj['metaObj'];
		$order = $this->getOrder();
		$ticket_id = $this->getCore()->getTicketId($codeObj, $metaObj);
		$order_item = $this->getOrderItem($order, $metaObj);
		if ($order_item == null) throw new Exception(esc_html__("Order item not found for the PDF ticket", 'event-tickets-with-ticket-scanner'));

		if ($filemode == "I") {
			do_action( $this->MAIN->_do_action_prefix.'trackIPForPDFView', $codeObj );
		}

		$product = $order_item->get_product();
		$product_id = $product->get_id();
		$product_parent_id = $product->get_parent_id();
		$is_variation = $product->get_type() == "variation" ? true : false;
		if ($is_variation && $product_parent_id > 0) {
			$product_id = $product_parent_id;
		}

		ob_start();
		$this->outputTicketInfo(true);
		$html = ob_get_contents();
		ob_end_clean();
		ob_start();

		if (!class_exists('sasoEventtickets_PDF')) {
			require_once("sasoEventtickets_PDF.php");
		}
		$pdf = new sasoEventtickets_PDF();
		$pdf->setFontSize($this->getAdminSettings()->getOptionValue('wcTicketPDFFontSize'));

		if (get_post_meta( $metaObj['woocommerce']['product_id'], 'saso_eventtickets_ticket_is_RTL', true ) == "yes") {
			//$pdf->setRTL(true);
		}

		$pdf->setFilemode($filemode);
		if ($pdf->getFilemode() == "F") {
			$dirname = get_temp_dir();
			$dirname .= trailingslashit($this->MAIN->getPrefix());
			$filename = "ticket_".$order->get_id()."_".$ticket_id.".pdf";
			wp_mkdir_p($dirname);
			$pdf->setFilepath($dirname);
		} else {
			$filename = "ticket_".$order->get_id()."_".$ticket_id.".pdf";
		}
		$pdf->setFilename($filename);

		$wcTicketTicketBanner = $this->getAdminSettings()->getOptionValue('wcTicketTicketBanner');
		$wcTicketTicketBanner = apply_filters( $this->MAIN->_add_filter_prefix.'wcTicketTicketBanner', $wcTicketTicketBanner, $product_id);
		if (!empty($wcTicketTicketBanner) && intval($wcTicketTicketBanner) >0) {
			$option_wcTicketTicketBanner = $this->getOptions()->getOption('wcTicketTicketBanner');
			$mediaData = SASO_EVENTTICKETS::getMediaData($wcTicketTicketBanner);
			$width = "600";
			if (isset($option_wcTicketTicketBanner['additional']) && isset($option_wcTicketTicketBanner['additional']['min']) && isset($option_wcTicketTicketBanner['additional']['min']['width'])) {
				$width = $option_wcTicketTicketBanner['additional']['min']['width'];
			}
			if (!empty($mediaData['location']) && file_exists($mediaData['location'])) {
				$pdf->addPart('<img width="21cm" src="'.$mediaData['location'].'"><br>');
			}
		}

		$pdf->addPart('<h1 style="font-size:20pt;text-align:center;">'.htmlentities($this->getAdminSettings()->getOptionValue("wcTicketHeading")).'</h1>');
		$pdf->addPart('{QRCODE_INLINE}');
		$pdf->addPart("<style>h4{font-size:16pt;} table.ticket_content_upper {width:14cm;padding-top:10pt;} table.ticket_content_upper td {height:5cm;}</style>".$html);
		$pdf->addPart('<br><br><p style="text-align:center;">'.$ticket_id.'</p>');

		$wcTicketDontDisplayBlogName = $this->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayBlogName');
		if (!$wcTicketDontDisplayBlogName) {
			$pdf->addPart('<br><br><div style="text-align:center;font-size:10pt;"><b>'.wp_kses_post(get_bloginfo("name")).'</b></div>');
		}
		$wcTicketDontDisplayBlogDesc = $this->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayBlogDesc');
		if (!$wcTicketDontDisplayBlogDesc) {
			if ($wcTicketDontDisplayBlogName) $pdf->addPart('<br>');
			$pdf->addPart('<div style="text-align:center;font-size:10pt;">'.wp_kses_post(get_bloginfo("description")).'</div>');
		}
		if (!$this->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayBlogURL')) {
			$pdf->addPart('<br><div style="text-align:center;font-size:10pt;">'.site_url().'</div>');
		}

		$wcTicketTicketLogo = $this->getAdminSettings()->getOptionValue('wcTicketTicketLogo');
		$wcTicketTicketLogo = apply_filters( $this->MAIN->_add_filter_prefix.'wcTicketTicketLogo', $wcTicketTicketLogo, $product_id);
		if (!empty($wcTicketTicketLogo) && intval($wcTicketTicketLogo) >0) {
			$option_wcTicketTicketLogo = $this->getOptions()->getOption('wcTicketTicketLogo');
			$mediaData = SASO_EVENTTICKETS::getMediaData($wcTicketTicketLogo);
			$width = "200";
			if (isset($option_wcTicketTicketLogo['additional']) && isset($option_wcTicketTicketLogo['additional']['max']) && isset($option_wcTicketTicketLogo['additional']['max']['width'])) {
				$width = $option_wcTicketTicketLogo['additional']['max']['width'];
			}
			if (!empty($mediaData['location']) && file_exists($mediaData['location'])) {
				$pdf->addPart('<br><br><p style="text-align:center;"><img width="'.$width.'" src="'.$mediaData['location'].'"></p>');
			}
		}
		$brandingHidePluginBannerText = $this->getOptions()->isOptionCheckboxActive('brandingHidePluginBannerText');
		if ($brandingHidePluginBannerText == false) {
			$pdf->addPart('<br><p style="text-align:center;font-size:6pt;">"Event Tickets With Ticket Scanner Plugin" for Wordpress</p>');
		}
		//$pdf->addPart('{QRCODE}');

		$pdf->setQRParams(['style'=>['position'=>'R'], 'align'=>'C']);
		$pdf->setQRCodeContent(["text"=>$ticket_id]);
		$wcTicketTicketBG = $this->getAdminSettings()->getOptionValue('wcTicketTicketBG');
		$wcTicketTicketBG = apply_filters( $this->MAIN->_add_filter_prefix.'wcTicketTicketBG', $wcTicketTicketBG, $product_id);
		if (!empty($wcTicketTicketBG) && intval($wcTicketTicketBG) >0) {
			$mediaData = SASO_EVENTTICKETS::getMediaData($wcTicketTicketBG);
			if (!empty($mediaData['location']) && file_exists($mediaData['location'])) {
				$pdf->setBackgroundImage($mediaData['location']);
			}
		}

		$wcTicketTicketAttachPDFOnTicket = $this->getAdminSettings()->getOptionValue('wcTicketTicketAttachPDFOnTicket');
		if (!empty($wcTicketTicketAttachPDFOnTicket)) {
			$mediaData = SASO_EVENTTICKETS::getMediaData($wcTicketTicketAttachPDFOnTicket);
			if (!empty($mediaData['location']) && file_exists($mediaData['location'])) {
				$pdf->setAdditionalPDFsToAttachThem([$mediaData['location']]);
			}
		}

		ob_end_clean();

		try {
			$pdf->render();
		} catch(Exception $e) {}
		if ($pdf->getFilemode() == "F") {
			return $pdf->getFullFilePath();
		} else {
			exit;
		}
	}

	public static function displayTicketDateAsString($product, $date_format="Y/m/d", $time_format="H:i") {
		$ticket_start_date = trim(get_post_meta( $product->get_id(), 'saso_eventtickets_ticket_start_date', true ));
		$ticket_start_time = trim(get_post_meta( $product->get_id(), 'saso_eventtickets_ticket_start_time', true ));
		$ticket_end_date = trim(get_post_meta( $product->get_id(), 'saso_eventtickets_ticket_end_date', true ));
		$ticket_end_time = trim(get_post_meta( $product->get_id(), 'saso_eventtickets_ticket_end_time', true ));
		$ret = "";
		if (!empty($ticket_start_date)) {
			$ticket_start_date_timestamp = strtotime($ticket_start_date." ".$ticket_start_time);
			$ticket_end_date_timestamp = strtotime($ticket_end_date." ".$ticket_end_time);
			$ret .= date($date_format, $ticket_start_date_timestamp);
			if (!empty($ticket_start_time)) $ret .= " ".date($time_format, $ticket_start_date_timestamp);
			if (!empty($ticket_end_date) || !empty($ticket_end_time)) $ret .= " - ";
			if (!empty($ticket_end_date)) $ret .= date($date_format, $ticket_end_date_timestamp);
			if (!empty($ticket_end_time)) $ret .= " ".date($time_format, $ticket_end_date_timestamp);
		}
		return $ret;
	}

	private function getOrderItem($order, $metaObj) {
		$order_item = null;
		foreach ( $order->get_items() as $item_id => $item ) {
			if ($metaObj['woocommerce']['item_id'] == $item_id) {
				$order_item = $item;
				break;
			}
		}
		return $order_item;
	}

	private function outputTicketInfo($forPDFOutput=false) {
		$codeObj = $this->getCodeObj();
		$metaObj = $codeObj['metaObj'];
		$order = $this->getOrder();

		if ($forPDFOutput == false) {
			do_action( $this->MAIN->_do_action_prefix.'trackIPForTicketView', $codeObj );
		}

		$ticket_id = $this->getCore()->getTicketId($codeObj, $metaObj);
		$date_time_format = $this->MAIN->getOptions()->getOptionDateTimeFormat();

		// suche item in der order
		$order_item = $this->getOrderItem($order, $metaObj);
		if ($order_item == null) throw new Exception("#8004 Order item not found");
		$product = $order_item->get_product();
		$is_variation = $product->get_type() == "variation" ? true : false;
		$product_parent = $product;
		$product_parent_id = $product->get_parent_id();
		$saso_eventtickets_is_date_for_all_variants = true;
		if ($is_variation && $product_parent_id > 0) {
			$product_parent = $this->get_product( $product_parent_id );
			$saso_eventtickets_is_date_for_all_variants = get_post_meta( $product_parent->get_id(), 'saso_eventtickets_is_date_for_all_variants', true ) == "yes" ? true : false;
		}

		// zeige Produkt title
		if (!$forPDFOutput) {
			echo '<h3 style="color:black;text-align:center;">'.htmlentities($this->getAdminSettings()->getOptionValue("wcTicketHeading")).'</h3>';
		}
		echo '<table style="padding:0;margin:0;" class="ticket_content_upper"><tr valign="top"><td style="padding:0;margin:0;'.($forPDFOutput ? '' : 'background-color:white;border:0;').'">';
		echo '<h4 style="color:black;">'.esc_html($product_parent->get_Title()).'</h4>';
		if ($is_variation && $this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketPDFDisplayVariantName') && count($product->get_attributes()) > 0) {
			echo "<p>";
			foreach($product->get_attributes() as $k => $v){
				echo $v." ";
			}
			echo "</p>";
		}

		$location = trim(get_post_meta( $product_parent->get_id(), 'saso_eventtickets_event_location', true ));

		// zeige datum
		$tmp_product = $product_parent;
		if (!$saso_eventtickets_is_date_for_all_variants) $tmp_product = $product; // unter Umständen die Variante
		$ticket_start_date = trim(get_post_meta( $tmp_product->get_id(), 'saso_eventtickets_ticket_start_date', true ));
		$ticket_end_date = trim(get_post_meta( $tmp_product->get_id(), 'saso_eventtickets_ticket_end_date', true ));
		$ticket_end_time = trim(get_post_meta( $tmp_product->get_id(), 'saso_eventtickets_ticket_end_time', true ));
		$ticket_end_date_timestamp = strtotime(trim($ticket_end_date." ".$ticket_end_time));

		$wcTicketHideDateOnPDF = $this->getOptions()->isOptionCheckboxActive('wcTicketHideDateOnPDF');
		if ($wcTicketHideDateOnPDF == false && !empty($ticket_start_date)) {
			echo '<p>';
			echo self::displayTicketDateAsString($tmp_product, $this->MAIN->getOptions()->getOptionDateFormat(), $this->MAIN->getOptions()->getOptionTimeFormat());
			if (!empty($ticket_end_date) && $ticket_end_date_timestamp < time()) echo ' <span style="color:red;">'.wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransExpired")).'</span>';
			if (!empty($location)) echo "<br>".wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransLocation"))." <b>".wp_kses_post($location)."</b>";
			echo '</p>';
		} elseif(!empty($location)) {
			echo '<p>';
			echo wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransLocation"))." <b>".wp_kses_post($location)."</b>";
			echo '</p>';
		}

		// zeige optionales produkt ticket notes
		$wcTicketPDFStripHTML = intval($this->getAdminSettings()->getOptionValue("wcTicketPDFStripHTML"));
		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDisplayShortDesc')) {
			$short_desc = $product->get_short_description();
			if ($is_variation) $short_desc = $product->get_description();
			if (!empty($short_desc)) {
				if ($wcTicketPDFStripHTML == 3) {
					echo htmlentities($short_desc);
				} elseif ($wcTicketPDFStripHTML == 2) {
					echo wp_filter_nohtml_kses($short_desc);
				} else {
					echo wp_kses_post($short_desc);
				}
				echo '<br>';
			}
		}
		$ticket_info = trim(get_post_meta( $product_parent->get_id(), 'saso_eventtickets_ticket_is_ticket_info', true ));
		if (!empty($ticket_info)) {
			echo '<p>';
			if ($wcTicketPDFStripHTML == 3) {
				echo htmlentities(nl2br($ticket_info));
			} elseif ($wcTicketPDFStripHTML == 2) {
				echo wp_filter_nohtml_kses(nl2br($ticket_info));
			} else {
				echo wp_kses_post( nl2br($ticket_info) );
			}
			echo '</p>';
		}
		echo "</td></tr></table>";
		if ($forPDFOutput) echo "<br><br>";

		// order details
		echo '<table style="width:100%;padding:0;margin:0;">';
		echo '<tr valign="top">';
		echo '<td style="color:black;width:50%;padding:0;padding-right:5px;margin:0;'.($forPDFOutput ? '' : 'background-color:white;border:0;').'">';
		if (!$this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayCustomer')) {
			echo "<p>";
			echo '<b>'.wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransCustomer")).'</b><br>';
			echo wp_kses_post(trim($order->get_formatted_billing_address())).'<br>';
			echo "</p>";
		}

		echo '</td><td style="color:black;width:50%;padding:0;margin:0;'.($forPDFOutput ? '' : 'background-color:white;border:0;').'">';

		if (!$this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayPayment')) {
			$order_date_paid = $order->get_date_paid();
			$order_date_paid_text = "-";
			$order_date_completed = $order->get_date_completed();
			$order_date_completed_text = "-";
			if (!empty($order_date_paid)) {
				$order_date_paid_text = date($date_time_format, strtotime($order->get_date_paid()));
			}
			if (!empty($order_date_completed)) {
				$order_date_completed_text = date($date_time_format, strtotime($order->get_date_completed()));
			}
			echo "<p>";
			echo '<b>'.wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetail")).'</b><br>';
			echo wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailPaidAt")).' <b>'.esc_html($order_date_paid_text).'</b><br>';
			echo wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailCompletedAt")).' <b>'.esc_html($order_date_completed_text).'</b><br>';
			$payment_method = $order->get_payment_method_title();
			if (!empty($payment_method)) {
				echo wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailPaidVia")).' <b>'.esc_html($payment_method).' (#'.esc_html($order->get_transaction_id()).')</b><br>';
			} else {
				echo wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailFreeTicket")).'<br>';
			}
			$coupons = $order->get_coupon_codes();
			if (count($coupons) > 0) {
				echo wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPaymentDetailCouponUsed")).' <b>'.esc_html(implode(", ", $coupons)).'</b><br>';
			}
			echo "</p>";
		}
		echo '</td></tr></table>';

		if (!empty($metaObj['user']['value']) && $this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDisplayTicketUserValue')) {
			echo '<div>'.wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransDisplayTicketUserValue")).' '.esc_html($metaObj['user']['value']).'</div>';
		}

		if (!empty($metaObj['wc_ticket']['name_per_ticket'])) {
			$label = esc_attr(trim(get_post_meta($product_parent->get_id(), "saso_eventtickets_request_name_per_ticket_label", true)));
			if (empty($label)) $label = "Name for the ticket {count}:";
			$order_quantity = $order_item->get_quantity();
			$ticket_pos = "";
			if ($order_quantity > 1) {
				// ermittel ticket pos
				$codes = explode(",", $order_item->get_meta('_saso_eventtickets_product_code', true));
				$ticket_pos = $this->ermittelCodePosition($codeObj['code_display'], $codes);
			}
			echo str_replace("{count}", $ticket_pos, $label)." ".esc_attr($metaObj['wc_ticket']['name_per_ticket'])."<br>";
		}

		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDisplayPurchasedItemFromOrderOnTicket')) {
			if (count($order->get_items()) > 1) {
				echo '<br><b>'.__('Additional order items').'</b><br>';
				foreach ( $order->get_items() as $item_id => $item ) {
					if ($item_id == $metaObj['woocommerce']['item_id']) continue;
					echo $item->get_quantity()."x ".esc_html($item->get_name())."<br>";
				}
			}
		}

		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDisplayCustomerNote')) {
			$customer_note = trim($order->get_customer_note());
			if (!empty($customer_note)) {
				echo '<br><i>"'.esc_html($customer_note).'"</i>';
			}
		}

		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDisplayPurchasedTicketQuantity')) {
			$order_quantity = $order_item->get_quantity();
			$text_ticket_amount = wp_kses_post($this->MAIN->getOptions()->getOptionValue('wcTicketPrefixTextTicketQuantity'));
			$ticket_pos = 1;
			if ($order_quantity > 1) {
				// ermittel ticket pos
				$codes = explode(",", $order_item->get_meta('_saso_eventtickets_product_code', true));
				$ticket_pos = $this->ermittelCodePosition($codeObj['code_display'], $codes);
			}
			$text_ticket_amount = str_replace("{TICKET_POSITION}", $ticket_pos, $text_ticket_amount);
			$text_ticket_amount = str_replace("{TICKET_TOTAL_AMOUNT}", $order_quantity, $text_ticket_amount);
			echo "<br>".$text_ticket_amount;
		}

		$saso_eventtickets_ticket_max_redeem_amount = intval(get_post_meta( $product_parent->get_id(), 'saso_eventtickets_ticket_max_redeem_amount', true ));
		if ($saso_eventtickets_ticket_max_redeem_amount > 1) {
			if ($forPDFOutput) {
				$text_redeem_amount = wp_kses_post($this->MAIN->getOptions()->getOptionValue('wcTicketTransRedeemMaxAmount'));
				$text_redeem_amount = str_replace("{MAX_REDEEM_AMOUNT}", $saso_eventtickets_ticket_max_redeem_amount, $text_redeem_amount);
			} else {
				$text_redeem_amount = wp_kses_post($this->MAIN->getOptions()->getOptionValue('wcTicketTransRedeemedAmount'));
				$text_redeem_amount = str_replace("{MAX_REDEEM_AMOUNT}", $saso_eventtickets_ticket_max_redeem_amount, $text_redeem_amount);
				$text_redeem_amount = str_replace("{REDEEMED_AMOUNT}", count($metaObj['wc_ticket']['stats_redeemed']), $text_redeem_amount);
			}
			echo "<br>".$text_redeem_amount;
		}

		echo '<br><br>';
		echo '<table style="width:100%;padding:0;margin:0;">';
		echo '<tr valign="top">';
		echo '<td style="color:black;width:50%;padding:0;padding-right:5px;margin:0;'.($forPDFOutput ? '' : 'background-color:white;border:0;').'">';

			// zeige Ticket nummer
			echo wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransTicket"))." <b>".$codeObj['code_display']."</b>";

			$wcTicketDontDisplayPrice = $this->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayPrice');
			if (!$wcTicketDontDisplayPrice) {
				// zeige gezahlten preis und product preis
				$paid_price = $order_item->get_subtotal() / $order_item->get_quantity();
				$product_price = $product->get_price();
				echo '<br>'.wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransPrice"))."<b> ".wc_price($paid_price, ['decimals'=>2])."</b>";
				if ($product_price != $paid_price) {
					echo " (".wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransProductPrice"))." ".wc_price($product_price, ['decimals'=>2]).")";
				}
			}

		echo '</td><td style="color:black;width:50%;padding:0;margin:0;'.($forPDFOutput ? '' : 'background-color:white;border:0;').'">';

			$listObj = null;
			if ($this->getAdminSettings()->getOptionValue("wcTicketDisplayTicketListName")) {
				$listObj = $this->getAdminSettings()->getList(['id'=>$codeObj['list_id']]);
				echo wp_kses_post($listObj['name'])."<br>";
			}
			if ($this->getAdminSettings()->getOptionValue("wcTicketDisplayTicketListDesc")) {
				if ($listObj == null) $listObj = $this->getAdminSettings()->getList(['id'=>$codeObj['list_id']]);
				$list_metaObj = $this->getCore()->encodeMetaValuesAndFillObjectList($listObj['meta']);
				echo wp_kses_post( nl2br($list_metaObj['desc']) );
			}

		echo '</td></tr></table>';

		// zeige BTN zum Redeem - oder wenn schon redeem, Meldung dazu
		if (!$forPDFOutput) {
			if (!empty($metaObj['wc_ticket']['redeemed_date'])) {
				echo '<center>';
				echo '<h4 style="color:red;">'.wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransTicketRedeemed"))."</h4>";
				echo wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransRedeemDate"))." ".date($date_time_format, strtotime($metaObj['wc_ticket']['redeemed_date']))."<br>";
				if (!$this->isScanner()) {
					if ($ticket_end_date_timestamp > time()) {
						echo '<h5 style="font-weight:bold;color:green;">'.wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransTicketValid")).'</h5>';
						echo '<form method="get"><input type="submit" value="'.esc_attr($this->getAdminSettings()->getOptionValue("wcTicketTransRefreshPage")).'"></form>';
					}
				}
				echo '</center>';
			} else {
				if (!$this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontShowRedeemBtnOnTicket')) {
					// zeige Redeem button
					if (!$this->isScanner()) {
						if ($ticket_end_date_timestamp > time()) {
							echo '
							<script>
							function redeem_ticket() {
								if (confirm("'.esc_attr($this->getAdminSettings()->getOptionValue("wcTicketTransRedeemQuestion")).'")) {
									return true;
								}
								return false;
							}
							</script>
							';
							echo '<center><form onsubmit="return redeem_ticket()" method="post"><input type="hidden" name="action" value="redeem"><input type="submit" class="button-primary" value="'.esc_attr($this->getAdminSettings()->getOptionValue("wcTicketTransBtnRedeemTicket")).'"></form></center>';
						} else {
							if ($ticket_end_date_timestamp) {
								echo '<center><span style="color:red;font-weight:bold;">'.wp_kses_post($this->getAdminSettings()->getOptionValue("wcTicketTransTicketExpired")).'</span></center>';
							}
						}
					}
				}
			}
			// zeige QR code zum scannen für admin
			if (!$this->isScanner()) {
				echo '<div id="qrcode" style="margin-top:3em;text-align:center;"></div>';
				echo '<script>jQuery("#qrcode").qrcode("'.$ticket_id.'");</script>';
			}
			// zeige eingabe code an für admin (order_id, product_id, code)
			echo '<p style="text-align:center;">'.$ticket_id.'</p>';
			$wcTicketDontDisplayPDFButtonOnDetail = $this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayPDFButtonOnDetail');
			$wcTicketDontDisplayICSButtonOnDetail = $this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayICSButtonOnDetail');
			$wcTicketBadgeDisplayButtonOnDetail = $this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketBadgeDisplayButtonOnDetail');
			if (!$wcTicketDontDisplayPDFButtonOnDetail || !$wcTicketDontDisplayICSButtonOnDetail || $wcTicketBadgeDisplayButtonOnDetail) {
				$url = $metaObj['wc_ticket']['_url'];
				echo '<p style="text-align:center;">';
				if (!$wcTicketDontDisplayPDFButtonOnDetail) {
					$dlnbtnlabel = $this->MAIN->getOptions()->getOptionValue('wcTicketLabelPDFDownload');
					echo '<a class="button" target="_blank" href="'.$url.'?pdf">'.$dlnbtnlabel.'</a> ';
				}
				if (!$wcTicketDontDisplayICSButtonOnDetail) {
					$dlnbtnlabel = $this->MAIN->getOptions()->getOptionValue('wcTicketLabelICSDownload');
					//echo '<a class="button" target="_blank" href="'.$metaObj['wc_ticket']['_url'].'?ics">'.$dlnbtnlabel.'</a>';
					echo '<a class="button" target="_blank" href="'.$url.'?ics">'.$dlnbtnlabel.'</a> ';
				}
				if ($wcTicketBadgeDisplayButtonOnDetail) {
					$dlnbtnlabel = $this->MAIN->getOptions()->getOptionValue('wcTicketBadgeLabelDownload');
					echo '<a class="button" target="_blank" href="'.$url.'?badge">'.$dlnbtnlabel.'</a>';
				}
				echo "</p>";
			}
		} // end forPDFOutput
	}

	/**
	 * welche position in den erstellten tickets für das order item hat der code
	 * @param $codes array mit den codes
	 */
	private function ermittelCodePosition($code, $codes) {
		$pos = array_search($code, $codes);
		if ($pos === false) return 1;
		return $pos + 1;
	}

	public function getMaxRedeemAmountOfTicket($codeObj) {
		$codeObj = $this->setMetaObj($codeObj);
		$metaObj = $codeObj['metaObj'];
		$max_redeem_amount = 1;
		$product_id = intval($metaObj['woocommerce']['product_id']);
		if ($product_id > 0) {
			$product = $this->get_product( $product_id );
			$is_variation = $product->get_type() == "variation" ? true : false;
			$product_parent_id = $product->get_parent_id();
			if ($is_variation && $product_parent_id > 0) {
				$product = $this->get_product( $product_parent_id );
			}
			$max_redeem_amount = intval(get_post_meta( $product->get_id(), 'saso_eventtickets_ticket_max_redeem_amount', true ));
		}
		return $max_redeem_amount;
	}

	private function redeemTicket($codeObj = null) {
		global $sasoEventtickets;
		$this->redeem_successfully = false;
		if ($codeObj == null) {
			$codeObj = $this->getCodeObj();
		}
		$metaObj = $codeObj['metaObj'];

		$max_redeem_amount = $this->getMaxRedeemAmountOfTicket($codeObj);

		if ($metaObj['wc_ticket']['redeemed_date'] == "" || $max_redeem_amount > 0) {
			$order = $this->getOrder();
			$is_paid = $this->isPaid($order);
			if (!$is_paid && $this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketAllowRedeemOnlyPaid')) {
				throw new Exception(esc_html__("Order is not paid. And the option is active to allow only paid ticket to be redeemed is active.", 'event-tickets-with-ticket-scanner'));
			}

			if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketDontAllowRedeemTicketBeforeStart')) {
				// ermittel product
				$order_item = $this->getOrderItem($order, $metaObj);
				if ($order_item == null) throw new Exception(esc_html__("Can not find the product for this ticket.", 'event-tickets-with-ticket-scanner'));
				$product = $order_item->get_product();
				$is_variation = $product->get_type() == "variation" ? true : false;
				$product_parent = $product;
				$product_parent_id = $product->get_parent_id();
				$tmp_prod = $product;
				$saso_eventtickets_is_date_for_all_variants = true;
				if ($is_variation && $product_parent_id > 0) {
					$product_parent = $this->get_product( $product_parent_id );
					$saso_eventtickets_is_date_for_all_variants = get_post_meta( $product_parent->get_id(), 'saso_eventtickets_is_date_for_all_variants', true ) == "yes" ? true : false;
					if ($saso_eventtickets_is_date_for_all_variants) {
						$tmp_prod = $product_parent;
					}
				}
				$ret = $this->calcDateStringAllowedRedeemFrom($tmp_prod->get_id());
				if ($ret['redeem_allowed_from_timestamp'] > time()) throw new Exception(esc_html__("Too early. Ticket cannot be redeemed yet.", 'event-tickets-with-ticket-scanner'));
			}

			$user_id = $order->get_user_id();
			$user_id = intval($user_id);
			$data = [
				'code'=>$codeObj['code'],
				'userid'=>$user_id,
				'redeemed_by_admin'=>1
			];
			$sasoEventtickets->getAdmin()->executeJSON('redeemWoocommerceTicketForCode', $data, true);
			$this->redeem_successfully = true;
		}
	}

	private function executeRequestScanner() {
		if (isset($_POST['action']) && $_POST['action'] == "redeem" || (isset($_GET['redeemauto']) && isset($_GET['code']))) {
			if (!isset($_POST['code']) && !isset($_GET['code']) ) throw new Exception("#8008 ".esc_html__('Ticket number to redeem is missing', 'event-tickets-with-ticket-scanner'));
			$this->redeemTicket();
			$this->codeObj = null;
		}
	}

	private function executeRequest() {
		global $sasoEventtickets;
		// auswerten $this->getParts()['_request']
		//if ($this->getParts()['_request'] == "action=redeem") {
		if (isset($_POST['action']) && $_POST['action'] == "redeem") {
			// redeem ausführen
			$order = $this->getOrder();
			if ($this->isPaid($order)) {
				$codeObj = $this->getCodeObj();
				$metaObj = $codeObj['metaObj'];
				//
				if ($metaObj['wc_ticket']['redeemed_date'] == "") {
					$user_id = get_current_user_id();
					if (empty($user_id)) {
						$user_id = $order->get_user_id();
					}
					$user_id = intval($user_id);
					$data = [
						'code'=>$codeObj['code'],
						'userid'=>$user_id
					];
					$sasoEventtickets->getAdmin()->executeJSON('redeemWoocommerceTicketForCode', $data, true);

					// check if redirection is activated
					if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketRedirectUser')) {
						// redirect
						$url = $this->getAdminSettings()->getOptionValue('wcTicketRedirectUserURL');
						$url = $this->getCore()->replaceURLParameters($url, $this->codeObj);
						if (!empty($url)) {
							header('Location: '.$url);
							exit;
						}
					}

					$this->codeObj = null;
				}
			} else {
				throw new Exception(esc_html__("Order not marked as paid. Ticket not redeemed.", 'event-tickets-with-ticket-scanner'));
			}
		}
	}

	public function addMetaTags() {
		echo "\n<!-- Meta TICKET EVENT -->\n";
        echo '<meta property="og:title" content="'.esc_attr__("Ticket Info", 'event-tickets-with-ticket-scanner').'" />';
        echo '<meta property="og:type" content="article" />';
        //echo '<meta property="og:description" content="'.$this->getPageDescription().'" />';
		echo '<style>
			div.ticket_content p {font-size:initial !important;margin-bottom:1em !important;}
			</style>';
        echo "\n<!-- Ende Meta TICKET EVENT -->\n\n";
	}

	private function isPDFRequest() {
		if (isset($_GET['pdf'])) return true;
		$this->getParts();
		if ($this->parts != null && isset($this->parts['_isPDFRequest'])) {
			return $this->parts['_isPDFRequest'];

		}
		return false;
	}

	private function isICSRequest() {
		if (isset($_GET['ics'])) return true;
		$this->getParts();
		if ($this->parts != null && isset($this->parts['_isICSRequest'])) {
			return $this->parts['_isICSRequest'];
		}
		return false;
	}

	private function isBadgeRequest() {
		if (isset($_GET['badge'])) return true;
		$this->getParts();
		if ($this->parts != null && isset($this->parts['_isBadgeRequest'])) {
			return $this->parts['_isBadgeRequest'];
		}
		return false;
	}

	private function isOnePDFRequest() {
		$parts = $this->getParts();
		// bsp order-395-3477288899
		if ($parts['idcode'] == "order") return true;
		return false;
	}

	private function initOnePDFOutput() {
		$parts = $this->getParts();
		if (count($parts) > 2) {
			$order_id = intval($parts['order_id']);
			$order = wc_get_order($order_id);
			$idcode = $order->get_meta('_saso_eventtickets_order_idcode');
			if (!empty($idcode) && $idcode == $parts['code']) {
				$this->outputPDFTicketsForOrder($order);
			} else {
				echo "Wrong ticket code";
			}
		}
	}

	public function output() {
		header('HTTP/1.1 200 OK');
		if (class_exists( 'WooCommerce' )) {

			if (!$this->isScanner()) {
				if($this->isPDFRequest()) {
					try {
						$this->outputPDF();
						exit;
					} catch (Exception $e) {}
				} elseif ($this->isICSRequest()) {
					$this->sendICSFile();
					exit;
				} elseif ($this->isBadgeRequest()) {
					$this->sendBadgeFile();
					exit;
				} elseif ($this->isOnePDFRequest()) {
					$this->initOnePDFOutput();
					exit;
				}
			}

			$js_url = "jquery.qrcode.min.js?_v=".$this->MAIN->getPluginVersion();
			wp_enqueue_script(
				'ajax_script',
				plugins_url( "3rd/".$js_url,__FILE__ ),
				array('jquery', 'jquery-ui-dialog')
			);
			/*
			if ($this->isScanner()) {
				$js_url = plugin_dir_url(__FILE__)."3rd/html5-qrcode.min.js?_v=".$this->MAIN->getPluginVersion();
				wp_register_script('html5-qrcode', $js_url);
				wp_enqueue_script('html5-qrcode');
			}
			*/
			get_header();
			echo '<div style="width: 100%; justify-content: center;align-items: center;position: relative;">';
			echo '<div class="ticket_content" style="background-color:white;color:black;padding:15px;display:block;position: relative;left: 0;right: 0;margin: auto;text-align:left;max-width:640px;border:1px solid black;">';

			try {
				if ($this->isScanner()) { // old approach
					$this->executeRequestScanner();
					$this->outputTicketScanner();
				} else {
					$this->executeRequest();
					$this->outputTicketInfo();
				}
			} catch(Exception $e) {
				echo '<h1 style="color:red;">Error</h1>';
				echo $e->getMessage();
			}

			echo "</div>";
			echo "</div>";

		} else {
			get_header();
			echo '<h1 style="color:red;">'.esc_html__('No WooCommerce Support Found', 'event-tickets-with-ticket-scanner').'</h1>';
			echo '<p>'.esc_html__('Please contact us for a solution.', 'event-tickets-with-ticket-scanner').'</p>';
		}
		get_footer();
	}
}
?>