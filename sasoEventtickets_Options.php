<?php
include(plugin_dir_path(__FILE__)."init_file.php");
class sasoEventtickets_Options {
	private $_options;
	private $MAIN;
	private $_prefix;
	public function __construct($MAIN, $_prefix) {
		$this->MAIN = $MAIN;
		$this->_prefix = $_prefix;
	}
	private function getBase() {
		return $this->MAIN->getBase();
	}
	public function initOptions(){
		$this->_options = [];


		$this->_options[] = $this->getOptionsObject('h99', esc_html__("Display options", 'event-tickets-with-ticket-scanner'),"","heading");
		$this->_options[] = $this->getOptionsObject('displayDateFormat', esc_html__("Your own date format", 'event-tickets-with-ticket-scanner'), esc_html__("If left empty, default will be 'Y/m/d'. Using the php date function format. Y=year, m=month, d=day H:hours, i:minutes, s=seconds", 'event-tickets-with-ticket-scanner'),"text", "Y/m/d", [], true);
		$this->_options[] = $this->getOptionsObject('displayTimeFormat', esc_html__("Your own time format", 'event-tickets-with-ticket-scanner'), esc_html__("If left empty, default will be 'H:i'. Using the php date function format. H=hours with leading 0, i=minutes with leading zero, s=seconds", 'event-tickets-with-ticket-scanner'),"text", "H:i", [], true);
		$this->_options[] = $this->getOptionsObject('displayAdminAreaColumnRedeemedInfo', esc_html__("Display a column with the information how often the ticket is redeemed", 'event-tickets-with-ticket-scanner'), esc_html__("If active, then a new column within the admin area for each ticket will be shown with the redeem ticket information. This feature can be very slow.", 'event-tickets-with-ticket-scanner'), "checkbox");
		$this->_options[] = $this->getOptionsObject('displayAdminAreaColumnBillingName', esc_html__("Display a column with the name of the buyer", 'event-tickets-with-ticket-scanner'), __("If active, then a new column within the admin area for each ticket will be shown with the billing name. <b>This feature can be very slow.</b>", 'event-tickets-with-ticket-scanner'),"checkbox");

		$this->_options[] = $this->getOptionsObject('h0a', "Access","","heading");
		$this->_options[] = $this->getOptionsObject('allowOnlySepcificRoleAccessToAdmin', "Allow only specific roles access to the admin area","If active, then only the administrator and the choosen roles area allowed to access this admin area.","checkbox", "", [], true);
		$all_roles = wp_roles()->roles;
		$editable_roles = apply_filters('editable_roles', $all_roles);
		$additional = [ "multiple"=>1, "values"=>[["label"=>"No role execept Administrator allowed", "value"=>"-"]] ];
		foreach($editable_roles as $key => $value) {
			if ($key == "administrator") continue;
			$additional['values'][] = ["label"=>$value['name'], "value"=>$key];
		}
		$this->_options[] = $this->getOptionsObject('adminAreaAllowedRoles', "Allow the specific role to access the backend of the event ticket", "If a role is chosen, then the user with this role is allowed to access the event ticket admin area. This will not exclude the 'administrator', if the option is activated.", "dropdown",	"-", $additional, false);

		$this->MAIN->getTicketHandler()->getOptionsRawObject();
		$_options = $this->MAIN->getTicketHandler()->getOptionsRawObject();
		foreach($_options as $o) {
			$this->_options[] = $this->getOptionsObject(
				$o['key'], $o['label'], $o['desc'], $o['type'],
				isset($o['def']) ? $o['def'] : null,
				isset($o['additional']) ? $o['additional'] : [],
				isset($o['isPublic']) ? $o['isPublic'] : false
			);
		}

		//$this->_options[] = $this->getOptionsObject('deleteTables', "Delete Tables with deletion of plugin");
		$this->_options[] = $this->getOptionsObject('h0', __("Validator Form for ticket number check", 'event-tickets-with-ticket-scanner'),"","heading");
		$this->_options[] = $this->getOptionsObject('textValidationButtonLabel', __("Your own check button label", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Check'", 'event-tickets-with-ticket-scanner'),"text", "Check", [], true);
		$this->_options[] = $this->getOptionsObject('textValidationInputPlaceholder', esc_html__("Your own input field placeholder text", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'XXYYYZZ'", 'event-tickets-with-ticket-scanner'),"text", __("XXYYYZZ", 'event-tickets-with-ticket-scanner'), [], true);
		$this->_options[] = $this->getOptionsObject('textValidationBtnBgColor', esc_html__("Your own background color of the button", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be <span style='color:#007bff;'>'#007bff'</span>", 'event-tickets-with-ticket-scanner'),"text", "", [], true);
		$this->_options[] = $this->getOptionsObject('textValidationBtnBrdColor', esc_html__("Your own border color of the button", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be <span style='color:#007bff;'>'#007bff'</span>", 'event-tickets-with-ticket-scanner'),"text", "", [], true);
		$this->_options[] = $this->getOptionsObject('textValidationBtnTextColor', esc_html__("Your own text color of the button", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'white'", 'event-tickets-with-ticket-scanner'),"text", "", [], true);

		$this->_options[] = $this->getOptionsObject('h1', "Validation Messages","","heading");
		$this->_options[] = $this->getOptionsObject('textValidationMessage1', __("Your own 'Ticket confirmed' message", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Ticket confirmed'", 'event-tickets-with-ticket-scanner'),"text", __("Ticket confirmed", 'event-tickets-with-ticket-scanner'), [], true);
		$this->_options[] = $this->getOptionsObject('textValidationMessage0', __("Your own 'Ticket not found' message", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Ticket not found'", 'event-tickets-with-ticket-scanner'),"text", __("Ticket not found", 'event-tickets-with-ticket-scanner'), [], true);
		$this->_options[] = $this->getOptionsObject('textValidationMessage2', __("Your own 'Ticket inactive' message", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Please contact support for further investigation'", 'event-tickets-with-ticket-scanner'),"text", __("Please contact support for further investigation", 'event-tickets-with-ticket-scanner'), [], true);
		$this->_options[] = $this->getOptionsObject('textValidationMessage3', __("Your own 'Ticket is already registered to a user' message", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Is registered to a user'", 'event-tickets-with-ticket-scanner'),"text", __("Is registered to a user", 'event-tickets-with-ticket-scanner'), [], true);
		$this->_options[] = $this->getOptionsObject('textValidationMessage4', __("Your own 'Ticket expired' message", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Ticket expired'", 'event-tickets-with-ticket-scanner'),"text", __("Ticket expired", 'event-tickets-with-ticket-scanner'), [], true);
		$this->_options[] = $this->getOptionsObject('textValidationMessage6', __("Your own 'Ticket and CVV is not valid' message", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Ticket and CVV is not valid'.", 'event-tickets-with-ticket-scanner'),"text", __("Ticket and CVV is not valid", 'event-tickets-with-ticket-scanner'), [], true);
		$this->_options[] = $this->getOptionsObject('textValidationMessage7', __("Your own 'Ticket stolen' message", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Ticket stolen'. You could set it to be more precise e.g.: 'The Ticket is reported as stolen'", 'event-tickets-with-ticket-scanner'),"text", __("Ticket is stolen", 'event-tickets-with-ticket-scanner'), [], true);
		$this->_options[] = $this->getOptionsObject('textValidationMessage8', __("Your own 'Ticket is redeemed' message", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Ticket is redeemed'", 'event-tickets-with-ticket-scanner'), "text",__("Ticket is redeemed", 'event-tickets-with-ticket-scanner'), [], true);

		$this->_options[] = $this->getOptionsObject('h2', __("Logged in user only", 'event-tickets-with-ticket-scanner'),"","heading");
		$this->_options[] = $this->getOptionsObject('onlyForLoggedInWPuser', __("Allow only logged in wordpress user to enter a ticket number for validation", 'event-tickets-with-ticket-scanner'), __("If active and the user is not logged in, then the input fields will be disabled", 'event-tickets-with-ticket-scanner'), "checkbox", "", [], true);
		$this->_options[] = $this->getOptionsObject('onlyForLoggedInWPuserMessage', __("Your own 'Only for logged in user' message", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'You need to log in to use the ticket validator'", 'event-tickets-with-ticket-scanner'),"text", __("You need to log in to use the ticket validator", 'event-tickets-with-ticket-scanner'), [], true);

		/* // brauchen wir später mit der Übertragung und auch gut, wenn einer Tickets ohne order verkauft. erstmal deaktivieren
		$this->_options[] = $this->getOptionsObject('h5', "Register user to ticket","Useful, if you are selling tickets for guest and do not have their name on it","heading");
		$this->_options[] = $this->getOptionsObject('allowUserRegisterCode', "Allow your users to register themself for a code.","If active, the user will get the option to register with an 'email address' (or your registration value text) to the code. <b>IMPORTANT</b>: If activate, the redirect option will executed after the registration.", "checkbox", "", [], true);
		$this->_options[] = $this->getOptionsObject('textRegisterButton', "Your own button label 'Register for this code'","If left empty, default will be 'Register for this code'","text", "Register for this code", [], true);
		$this->_options[] = $this->getOptionsObject('textRegisterValue', "Your own label for the user registration value question","If left empty, default will be 'Enter your email address'","text", "Enter your email address", [], true);
		$this->_options[] = $this->getOptionsObject('textRegisterSaved', "Your own message for the 'user registration value is stored' operation","If left empty, default will be 'Your code is registered to you'","text", "Your code is registered to you", [], true);
		$this->_options[] = $this->getOptionsObject('allowUserRegisterCodeWPuserid', "Track wordpress userid","If active and the user is logged in, then the userid will be stored to the registration information.");
		$this->_options[] = $this->getOptionsObject('allowUserRegisterSkipValueQuestion', "Skip asking for the registration value, if the user is logged in","If active and the user is logged in, then question of 'Register for this code' will be not shown and the 'is stored text' will be displayed immediately.", "checkbox", "", [], true);

		$this->_options[] = $this->getOptionsObject('h6', "Display registered information of a ticket","","heading");
		$this->_options[] = $this->getOptionsObject('displayUserRegistrationOfCode', "Display the collected information of a registration to a ticket.", 'Usefull if your codes are certificatins and you want if somebody type in the ticket number to see who it belongs to.');
		$this->_options[] = $this->getOptionsObject('displayUserRegistrationPreText', "Your own pre-text for the display of the collected information","If not empty, it will be added one line above the registered information to the ticket","text", "");
		$this->_options[] = $this->getOptionsObject('displayUserRegistrationAfterText', "Your own after-text for the display of the collected information","If not empty, it will be added one line below the registered information to the ticket","text", "");
		*/

		$this->_options[] = $this->getOptionsObject('h8', __("User redirection", 'event-tickets-with-ticket-scanner'),"","heading");
		$this->_options[] = $this->getOptionsObject('userJSRedirectActiv', __("Activate redirect the user after a valid ticket was found.", 'event-tickets-with-ticket-scanner'), __("If active, the user will be redirected to the URL your provide below.", 'event-tickets-with-ticket-scanner'), "checkbox", "", [], true);
		$this->_options[] = $this->getOptionsObject('userJSRedirectIfSameUserRegistered', __("Redirect already registered tickets and the user is the same.", 'event-tickets-with-ticket-scanner'), __("If active, the user will be redirected to the URL your provide below, even if the ticket is registered already and user checking is the same user that is registered to the ticket. It will not be executed, if the 'one time usage restriction is active'. The user needs to be logged in for the system to recognize the user.", 'event-tickets-with-ticket-scanner'), "checkbox", "", [], true);
		$this->_options[] = $this->getOptionsObject('userJSRedirectURL', __("URL to redirect the user, if the ticket is valid.", 'event-tickets-with-ticket-scanner'), __("The URL can be relative like '/page/' or absolute 'https//domain/url/'.<br>You can use these placeholder for your URL:<ul><li><b>{USERID}</b>: Will be replaced with the userid if the user is loggedin or empty</li><li><b>{CODE}</b>: Will be replaced with the ticket number (without the delimiters)</li><li><b>{CODEDISPLAY}</b>: Will be replaced with the ticket number (WITH the delimiters)</li><li><b>{IP}</b>: The IP address of the user</li><li><b>{LIST}</b>: Name of the list if assigned</li><li><b>{LIST_DESC}</b>: Description of the assigned list</li><li><a href='#replacementtags'>More tags here</a></li></ul>", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('userJSRedirectBtnLabel', __("Button label to click for the user to be redirected", 'event-tickets-with-ticket-scanner'), __("Only if filled out, the button will be displayed. If you left this field empty, then the user will be redirected immediately if the ticket is valid, without a button to click.", 'event-tickets-with-ticket-scanner'),"text", "");

		$this->_options[] = $this->getOptionsObject('h9', __("Webhooks", 'event-tickets-with-ticket-scanner'),"","heading");
		$this->_options[] = $this->getOptionsObject('webhooksActiv', __("Activate webhooks to call a service with the validation check.", 'event-tickets-with-ticket-scanner'), __("If active, each validation request from a user will trigger an URL from the server side to another URL. Be carefull. This could slow down the validation check. It depends how fast your service URLs are responding.", 'event-tickets-with-ticket-scanner')."<br>".__("The URL can be relative like '/page/' or absolute 'https//domain/url/'.<br>You can use these placeholder for your URL:<ul><li><b>{USERID}</b>: Will be replaced with the userid if the user is loggedin or empty</li><li><b>{CODE}</b>: Will be replaced with the ticket number (without the delimiters)</li><li><b>{CODEDISPLAY}</b>: Will be replaced with the ticket number (WITH the delimiters)</li><li><b>{IP}</b>: The IP address of the user</li><li><b>{LIST}</b>: Name of the list if assigned</li><li><b>{LIST_DESC}</b>: Description of the assigned list</li><li><a href='#replacementtags'>More tags here</a></li></ul>", 'event-tickets-with-ticket-scanner'));
		$this->_options[] = $this->getOptionsObject('webhookURLinactive', __("URL to your service if the checked ticket <b>is inactive</b>.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLvalid', __("URL to your service if the checked ticket <b>is valid</b>.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLinvalid', __("URL to your service if the checked ticket <b>is invalid</b> (not found).", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLregister', __("URL to your service if <b>someone register to this ticket</b>.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLisregistered', __("URL to your service if the checked ticket is already <b>registered to someone</b>.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLsetused', __("URL to your service if the checked ticket is valid and is <b>marked to be used the first time</b>.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLmarkedused', __("URL to your service if the checked ticket is already <b>marked as used and checked again</b>.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLrestrictioncodeused', __("URL to your service if an order item is bought using a restriction code.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		//$this->_options[] = $this->getOptionsObject('webhookURLaddwcinfotocode', __("URL to your service if a code received WooCommerce data, if a 'code was purchased'.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		//$this->_options[] = $this->getOptionsObject('webhookURLwcremove', __("URL to your service if the WooCommerce data is removed from the code.'.", 'event-tickets-with-ticket-scanner'),__("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLaddwcticketinfoset', __("URL to your service if the WooCommerce ticket data is set for this ticket number.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLaddwcticketredeemed', __("URL to your service if the WooCommerce ticket is redeemed.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLaddwcticketunredeemed', __("URL to your service if the WooCommerce ticket is un-redeemed.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");
		$this->_options[] = $this->getOptionsObject('webhookURLaddwcticketinforemoved', __("URL to your service if the WooCommerce ticket data is removed from the ticket number.", 'event-tickets-with-ticket-scanner'), __("Only triggered, if not empty.", 'event-tickets-with-ticket-scanner'), "text", "");

		$this->_options[] = $this->getOptionsObject('h10', __("Woocommerce product ticket assignment", 'event-tickets-with-ticket-scanner'),"","heading");
		if (!$this->MAIN->isPremium()) {
			$this->_options[] = $this->getOptionsObject('wcassignmentTextNoCodePossible', __("Text that will be used, if you do not have <b>premium</b> and run out of free ticket amount. This text will be added to the WooCoomerce purchase information instead of the ticket number", 'event-tickets-with-ticket-scanner'), __("If left empty, default will be 'Please contact our support for the ticket'", 'event-tickets-with-ticket-scanner'),"text", __("Please contact our support for the ticket", 'event-tickets-with-ticket-scanner'), [], true);
		}
		$this->_options[] = $this->getOptionsObject('wcRestrictFreeCodeByOrderRefund', __("Clear the ticket number if the order was deleted, canceled or refunded", 'event-tickets-with-ticket-scanner'), __("If the order is deleted, cancelled or the status is set to 'refunded', then the WooCommerce order information is removed from the ticket number(s). If the option 'one time usage' is active, then the ticket number will be unmarked as used.", 'event-tickets-with-ticket-scanner'), "checkbox", true, []);
		$this->_options[] = $this->getOptionsObject('wcassignmentReuseNotusedCodes', __("Reuse ticket from the ticket list assigned to the woocommerce product, that are not already used by a woocommerce purchase.", 'event-tickets-with-ticket-scanner'),__("If active, the system will try to use an existing ticket from the ticket list that is free. If no free code could be found, a new ticket will be created and assigned to the purchase.", 'event-tickets-with-ticket-scanner'), "checkbox", true, []);
		$this->_options[] = $this->getOptionsObject('wcassignmentDoNotPutCVVOnEmail', __("Do not print the ticket number CVV on the confirmation to the customer.", 'event-tickets-with-ticket-scanner'), __("If active, the assigned CVV will not be printed on the email", 'event-tickets-with-ticket-scanner'), "checkbox", "", []);
		$this->_options[] = $this->getOptionsObject('wcassignmentDoNotPutCVVOnPDF', __("Do not print the ticket number CVV on the PDF invoice woocommerce purchase.", 'event-tickets-with-ticket-scanner'), __("If active, the assigned CVV will not be printed on the PDF", 'event-tickets-with-ticket-scanner'), "checkbox", "", []);
		$this->_options[] = $this->getOptionsObject('wcassignmentDoNotPutOnEmail', __("Do not put the ticket in the emails to the customer", 'event-tickets-with-ticket-scanner'), __("If active, the assigned ticket number and other ticket related information will not be put in the email", 'event-tickets-with-ticket-scanner'), "checkbox", "", []);
		$this->_options[] = $this->getOptionsObject('wcassignmentDoNotPutOnPDF', __("Do not print the ticket on the PDF invoice woocommerce purchase.", 'event-tickets-with-ticket-scanner'), __("If active, the assigned ticket will not be printed on the PDF", 'event-tickets-with-ticket-scanner'), "checkbox", "", []);
		$this->_options[] = $this->getOptionsObject('wcassignmentUseGlobalSerialFormatter', __("Set the ticket number formatter pattern for new sales.", 'event-tickets-with-ticket-scanner'), __("If active, the a new ticket will generated using the following settings", 'event-tickets-with-ticket-scanner'), "checkbox", "", []);
		$this->_options[] = $this->getOptionsObject('wcassignmentUseGlobalSerialFormatter_values', "","", "text", "", ["doNotRender"=>1]);

		$this->_options[] = $this->getOptionsObject('h13', __("Display ticket number to your loggedin user", 'event-tickets-with-ticket-scanner'), sprintf(/* translators: %s: shortcode */__("You can display the tickets assigned to an user with this shortcode %s.", 'event-tickets-with-ticket-scanner'), '<b>[sasoEventTicketsValidator_code]</b>'),"heading");
		$this->_options[] = $this->getOptionsObject('userDisplayCodePrefix', __("Text that will be added before the ticket number(s) for the user are displayed.", 'event-tickets-with-ticket-scanner'), "","text", __("Your ticket number(s):", 'event-tickets-with-ticket-scanner'), [], false);
		$this->_options[] = $this->getOptionsObject('userDisplayCodePrefixAlways', __("Display the prefix text always.", 'event-tickets-with-ticket-scanner'), __("If active, your prefix text will be rendered always. Even if the user is not logged in or do not have any tickets assigned to her yet.", 'event-tickets-with-ticket-scanner'), "checkbox", "", []);
		$this->_options[] = $this->getOptionsObject('userDisplayCodeSeperator', __("Text or letter to be used as a seperator for ticket numbers of the user.", 'event-tickets-with-ticket-scanner'), __("If the user has more than one ticket number assigned to her, then this text will be used to seperate them for display the numbers. If left empty, then it will be ', ' as a default.", 'event-tickets-with-ticket-scanner'),"text", ", ");

		$this->_options[] = $this->getOptionsObject('h14', __("QR code", 'event-tickets-with-ticket-scanner'), __("You can generate QR code images for your ticket numbers.", 'event-tickets-with-ticket-scanner'),"heading");
		$this->_options[] = $this->getOptionsObject('qrDirectURL', __("URL for the QR image.", 'event-tickets-with-ticket-scanner'), __("The URL should be absolute, if you like to provide the generated QR image to your customers. The image can be retrieved within the event ticket area. The ticket number detail contains a button for it.<br>You can use these placeholder for your URL:<ul><li><b>{CODE}</b>: Will be replaced with the number (without the delimiters)</li><li><b>{CODEDISPLAY}</b>: Will be replaced with the number (WITH the delimiters)</li><b>{LIST}</b>: Name of the list if assigned</li><li><b>{LIST_DESC}</b>: Description of the assigned list</li><li><a href='#replacementtags'>You could use more tags.</a> But it is not recommend, since the QR code is generated within the admin area.</li></ul>", 'event-tickets-with-ticket-scanner'), "text", "");

		if ($this->MAIN->isPremium()) {
			$this->_options = $this->MAIN->getPremiumFunctions()->_initOptions($this->_options);
		}
	}
	public function getOptionsObject($key, $label, $desc="",$type="checkbox",$def=null,$additional=[], $isPublic=false) {
		if ($def == null) {
			switch($type) {
				case "number":
				case "checkbox":
					$def = 0;
					break;
				default:
					$def = "";
			}
		}
		return ['key'=>$key,'id'=>$this->_prefix.$key,'label'=>$label,'desc'=>$desc,'value'=>0,'type'=>$type,'default'=>$def,'additional'=>$additional, 'isPublic'=>$isPublic, '_isLoaded'=>false];
	}
	public function getOptions() {
		foreach($this->_options as $idx => $option) {
			if ($option['_isLoaded'] == false) {
				/*
				$defv = ($option['type'] == "text") ? "" : 0;
				if ($option['type'] == "number") $defv = 0;
				if (is_numeric($defv)) {
					$option['value'] = $defv;
				}
				*/
				$v = get_option( $option['id'], $option['default']);
				if (!is_array($v)) {
					$v = stripslashes($v);
				}
				$option['value'] = $v;
				$option['_isLoaded'] = true;
				$this->_options[$idx] = $option;
			}
		}
		return $this->_options;
	}
	public function getOptionsOnlyPublic() {
		$ret = [];
		$options = $this->getOptions();
		foreach($options as $option) {
			if ($option['isPublic'] == true) {
				$ret[] = $option;
			}
		}
		return $ret;
	}
	public function getOption($key) {
		$o = null;
		$key = trim($key);
		if (empty($key)) return $o;
		$options = $this->getOptions();
		foreach($options as $option) {
			if ($option['key'] === $key) {
				$o = $option;
				break;
			}
		}
		return $o;
	}
	private function _setOptionValuesByKey($key, $field, $value) {
		foreach ($this->_options as $idx => $value) {
			if ($value['key'] == $key) {
				$this->_options[$idx][$field] = $value;
				break;
			}
		}
	}
	public function changeOption($data) {
		$option = $this->getOption($data['key']);
		if ($option != null) {
			if ($option['type'] == "checkbox") {
				$v = intval($data['value']);
			} else {
				if (is_array($data['value'])) {
					array_walk($data['value'], "trim");
				} else {
					$data['value'] = trim($data['value']);
				}
				$v = $data['value'];
			}
			update_option($option['id'], $v);
			$this->_setOptionValuesByKey($data['key'], 'value', $v);
		}
		do_action( $this->MAIN->_do_action_prefix.'changeOption', $data);
	}
	public function getOptionValue($name, $def="") {
		$option = $this->getOption($name);
		if ($option == null) return $def;
		return $this->_getOptionValue($option);
	}
	private function _getOptionValue($option) {
		$ret = "";
		if (is_array($option['value'])) {
			$ret = $option['value'];
			if (count($option['value']) == "") $ret = $option['default'];
		} else {
			$ret = empty(trim($option['value'])) ? $option['default'] : $option['value'];
		}
		return $ret;
	}
	public function isOptionCheckboxActive($optionname) {
		$option = $this->getOption($optionname);
		if ($option == null || intval($this->_getOptionValue($option)) != 1) return false;
		return true;
	}

	public function getOptionDateFormat() {
		$date_format = $this->getOptionValue('displayDateFormat');
		try {
			$d = date($date_format);
		} catch(Exception $e) {
			$date_format = 'Y/m/d';
		}
		return $date_format;
	}
	public function getOptionTimeFormat() {
		$date_format = $this->getOptionValue('displayTimeFormat');
		try {
			$d = date($date_format);
		} catch(Exception $e) {
			$date_format = 'H:i';
		}
		return $date_format;
	}
	public function getOptionDateTimeFormat() {
		$date_format = $this->getOptionDateFormat();
		$time_format = $this->getOptionTimeFormat();
		try {
			$d = date($date_format." ".$time_format);
		} catch(Exception $e) {
			$date_format = 'Y/m/d H:i';
		}
		return $date_format." ".$time_format;
	}

}
?>