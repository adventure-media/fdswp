<?php
include(plugin_dir_path(__FILE__)."init_file.php");
if (!defined('SASO_EVENTTICKETS_PLUGIN_MIN_WC_VER')) define( 'SASO_EVENTTICKETS_PLUGIN_MIN_WC_VER', '4.0' );

class sasoEventtickets_WC {
	private $MAIN;
	private $meta_key_codelist_restriction = 'saso_eventtickets_list_sale_restriction';
	private $meta_key_codelist_restriction_order_item = '_saso_eventticket_list_sale_restriction';
	private $_containsProductsWithRestrictions = null;
	private $js_inputType = 'serialcoderestriction';

	private $_isTicket;
	private $_product;

	private $_attachments = [];

	public function __construct($MAIN) {
		$this->MAIN = $MAIN;
		$this->initHandlers();
	}

	public function initHandlers() {
		add_filter( 'woocommerce_email_attachments', [$this, 'woocommerce_email_attachments'], 10, 3);
		add_action( 'woocommerce_before_cart_table', [$this, 'woocommerce_before_cart_table'], 20, 4 );
		add_action( 'woocommerce_cart_updated', [$this, 'woocommerce_cart_updated']);
		add_action( 'woocommerce_checkout_create_order_line_item', [$this, 'woocommerce_checkout_create_order_line_item'], 20, 4 );
		add_action( 'woocommerce_check_cart_items', [$this, 'woocommerce_check_cart_items'] );
		add_action( 'woocommerce_new_order', [$this, 'woocommerce_new_order'], 10, 1);
		if ($this->getOptions()->isOptionCheckboxActive('wcRestrictPurchase')) {
			add_action( 'woocommerce_checkout_update_order_meta', [$this, 'woocommerce_checkout_update_order_meta'], 20, 2);
			// erlaube ajax nonpriv und registriere handler
			if (wp_doing_ajax()) {
				add_action('wp_ajax_nopriv_'.$this->getPrefix().'_executeWCFrontend', [$this,'executeWCFrontend']); // nicht angemeldete user, sollen eine antwort erhalten
				add_action('wp_ajax_'.$this->getPrefix().'_executeWCFrontend', [$this,'executeWCFrontend']); // nicht angemeldete user, sollen eine antwort erhalten
			}
		}
		if (is_admin() && $this->getOptions()->isOptionCheckboxActive('wcRestrictFreeCodeByOrderRefund')) {
			add_action( 'woocommerce_delete_order_item', [$this, 'woocommerce_delete_order_item'], 20, 1);
			add_action( 'woocommerce_delete_order', [$this, 'woocommerce_delete_order'], 10, 1 );
			add_action( 'woocommerce_delete_order_refund', [$this, 'woocommerce_delete_order_refund'], 10, 1 );
		}
		if (is_admin()) {
			add_filter('woocommerce_product_data_tabs', [$this, 'woocommerce_product_data_tabs'], 98 );
			add_action('woocommerce_product_data_panels', [$this, 'woocommerce_product_data_panels'] );
			add_action('woocommerce_process_product_meta', [$this, 'woocommerce_process_product_meta'], 10, 2 );
			add_action('add_meta_boxes', [$this, 'wc_order_add_meta_boxes']);
			add_filter('manage_edit-product_columns', [$this, 'manage_edit_product_columns']);
			add_action('manage_product_posts_custom_column', [$this, 'manage_product_posts_custom_column'], 2);
			add_filter("manage_edit-product_sortable_columns", [$this, 'manage_edit_product_sortable_columns']);
		} else {
			add_action('woocommerce_single_product_summary', [$this, 'woocommerce_single_product_summary']);
		}
		add_action('woocommerce_order_status_changed', [$this, 'woocommerce_order_status_changed'], 10, 3);
		add_filter('woocommerce_order_item_display_meta_key', [$this, 'woocommerce_order_item_display_meta_key'], 20, 3 );
		add_filter('woocommerce_order_item_display_meta_value', [$this, 'woocommerce_order_item_display_meta_value'], 20, 3);
		add_action('wpo_wcpdf_after_item_meta', [$this, 'wpo_wcpdf_after_item_meta'], 20, 3 );
		add_action('woocommerce_order_item_meta_start', [$this, 'woocommerce_order_item_meta_start'], 201, 4);
		add_action('woocommerce_product_after_variable_attributes', [$this, 'woocommerce_product_after_variable_attributes'], 10, 3);
		add_action('woocommerce_save_product_variation',[$this, 'woocommerce_save_product_variation'], 10 ,2 );
		add_action('woocommerce_email_order_meta', [$this, 'woocommerce_email_order_meta'], 10, 4 );
	}

    private function getPrefix() {
        return $this->getMain()->getPrefix();
    }
    public function setProduct($product) {
        $this->_product = $product;
    }
    private function getProduct() {
        if ($this->_product == null) {
            $this->setProduct(wc_get_product());
        }
        return $this->_product;
    }
    private function isTicket() {
        if ($this->_isTicket == null) {
            $product = $this->getProduct();
            $this->_isTicket = get_post_meta($product->get_id(), 'saso_eventtickets_is_ticket', true) == "yes";
        }
        return $this->_isTicket;
    }

	function wc_get_lists() {
		$lists = $this->getAdmin()->getLists();
		$dropdown_list = array(''=>esc_attr__('Deactivate auto-generating ticket', 'event-tickets-with-ticket-scanner'));
		foreach ($lists as $key => $list) {
			$dropdown_list[$list['id']] = $list['name'];
		}

		return $dropdown_list;
	}

	function wc_get_lists_sales_restriction() {
		$lists = $this->getAdmin()->getLists();
		$dropdown_list = array(''=>esc_attr__('No restriction applied', 'event-tickets-with-ticket-scanner'), '0'=>esc_attr__('Accept any existing code without limitation to a code list', 'event-tickets-with-ticket-scanner'));
		foreach ($lists as $key => $list) {
			$dropdown_list[$list['id']] = $list['name'];
		}

		return $dropdown_list;
	}

	public function woocommerce_product_after_variable_attributes($loop, $variation_data, $variation) {
		echo '<div class="form-row form-row-full form-field">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_saso_eventtickets_is_not_ticket[' . $loop . ']',
				'label'       => __( 'This variation is NOT a ticket product', 'event-tickets-with-ticket-scanner' ),
				'desc_tip'    => 'true',
				'description' => __( 'This allows you to exclude a variation to be a ticket', 'event-tickets-with-ticket-scanner' ),
				'value'       => get_post_meta( $variation->ID, '_saso_eventtickets_is_not_ticket', true )
			)
		);
		echo '</div>';
	}

	public function woocommerce_save_product_variation($variation_id, $i) {
		$key = '_saso_eventtickets_is_not_ticket';
		if( isset($_POST[$key]) && isset($_POST[$key][$i]) ) {
			update_post_meta( $variation_id, $key, 'yes');
		} else {
			delete_post_meta( $variation_id, $key );
		}
	}

	public function woocommerce_product_data_tabs($tabs) {
		//unset( $tabs['inventory'] );

		$tabs['saso_eventtickets_code_woo'] = array(
			'label'    	=> _x('Event Tickets', 'label', 'event-tickets-with-ticket-scanner'),
			'title'    	=> _x('Event Tickets', 'title', 'event-tickets-with-ticket-scanner'),
			'target'   	=> 'saso_eventtickets_wc_product_data',
			'class'		=> ['show_if_simple', 'show_if_variable']
		);
		return $tabs;
	}

	/**
	 * product tab content
	 */
	public function woocommerce_product_data_panels() {
		$lists = $this->getAdmin()->getLists();
		$product = wc_get_product(get_the_ID());
		$is_variable = $product->get_type() == "variable" ? true : false;

		wp_register_script(
			'SasoEventticketsValidator_WC_backend',
			trailingslashit( plugin_dir_url( __FILE__ ) ) . 'wc_backend.js?_v='.$this->getMain()->getPluginVersion(),
			array( 'jquery', 'jquery-blockui', 'wp-i18n'),
			(current_user_can("administrator") ? time() : $this->getMain()->getPluginVersion()),
			true );
		wp_set_script_translations('SasoEventticketsValidator_WC_backend', 'event-tickets-with-ticket-scanner');
		wp_localize_script(
 			'SasoEventticketsValidator_WC_backend',
			'Ajax_sasoEventtickets_wc', // name der js variable
 			[
 				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'_plugin_home_url' =>plugins_url( "",__FILE__ ),
				'prefix'=>$this->getMain()->getPrefix(),
				'nonce' => wp_create_nonce( $this->getMain()->getPrefix() ),
 				'action' => $this->getMain()->getPrefix().'_executeWCBackend',
				'product_id'=>isset($_GET['post']) ? intval($_GET['post']) : 0,
				'order_id'=>0,
				'scope'=>'product',
				'_doNotInit'=>true,
            	'_max'=>$this->getMain()->getBase()->getMaxValues(),
            	'_isPremium'=>$this->getMain()->isPremium(),
            	'_isUserLoggedin'=>is_user_logged_in(),
            	'_backendJS'=>trailingslashit( plugin_dir_url( __FILE__ ) ) . 'backend.js?_v='.$this->getMain()->getPluginVersion(),
            	'_premJS'=>$this->getMain()->isPremium() ? $this->getMain()->getPremiumFunctions()->getJSBackendFile() : '',
            	'_divAreaId'=>'saso_eventtickets_list_format_area',
            	'formatterInputFieldDataId'=>'saso_eventtickets_list_formatter_values'
 			] // werte in der js variable
 			);
      	wp_enqueue_script('SasoEventticketsValidator_WC_backend');
		$js_url = "jquery.qrcode.min.js?_v=".$this->MAIN->getPluginVersion();
		wp_enqueue_script(
			'ajax_script2',
			plugins_url( "3rd/".$js_url,__FILE__ ),
			array('jquery', 'jquery-ui-dialog')
		);
		wp_enqueue_style($this->getMain()->getPrefix()."_backendcss", plugins_url( "",__FILE__ ).'/css/styles_backend.css');

		echo '<div id="saso_eventtickets_wc_product_data" class="panel woocommerce_options_panel hidden">';

		if (!$this->getMain()->isPremium()) {
			$max_values = $this->getMain()->getMaxValues();
			echo '<p style="color:red;">'.sprintf(/* translators: %d: amount of maximum ticket that can be created */__('With the free basic plugin, you can only <b>create up to %d tickets!</b><br>Make sure your are not selling more tickets :)', 'event-tickets-with-ticket-scanner'), intval($max_values['codes_total'])).'<br>'.sprintf(/* translators: 1: start of a-tag 2: end of a-tag */__('Here you can purchase the %1$spremium plugin%2$s for unlimited tickets.', 'event-tickets-with-ticket-scanner'), '<a target="_blank" href="https://vollstart.de/event-tickets-with-ticket-scanner/">', '</a>').'</p>';
		}

		echo '<div class="options_group">';
		woocommerce_wp_checkbox([
			'id'          => 'saso_eventtickets_is_ticket',
			'value'       => get_post_meta( get_the_ID(), 'saso_eventtickets_is_ticket', true ),
			'label'       => __('Is a ticket sales', 'event-tickets-with-ticket-scanner'),
			'description' => __('Activate this, to generate a ticket number', 'event-tickets-with-ticket-scanner')
		]);
		echo "<p><b>Important:</b> You need to choose a list below, to activate the ticket sale for this product.</p>";
		woocommerce_wp_text_input([
			'id'				=> 'saso_eventtickets_event_location',
			'value'       		=> get_post_meta( get_the_ID(), 'saso_eventtickets_event_location', true ),
			'label'       		=> wp_kses_post($this->getOptions()->getOptionValue("wcTicketTransLocation")),
			'type'				=> 'text',
			'description' 		=> __('This will be also in the cal entry file.', 'event-tickets-with-ticket-scanner'),
			'desc_tip'    		=> true
		]);
		woocommerce_wp_text_input([
			'id'				=> 'saso_eventtickets_ticket_start_date',
			'value'       		=> get_post_meta( get_the_ID(), 'saso_eventtickets_ticket_start_date', true ),
			'label'       		=> __('Start date event', 'event-tickets-with-ticket-scanner'),
			'type'				=> 'date',
			'custom_attributes'	=> ['data-type'=>'date'],
			'description' 		=> __('Set this to have this printed on the ticket and prevent too early redeemed tickets. Tickets can be redeemed from that day on.', 'event-tickets-with-ticket-scanner'),
			'desc_tip'    		=> true
		]);
		woocommerce_wp_text_input([
			'id'				=> 'saso_eventtickets_ticket_start_time',
			'value'       		=> get_post_meta( get_the_ID(), 'saso_eventtickets_ticket_start_time', true ),
			'label'       		=> __('Start time', 'event-tickets-with-ticket-scanner'),
			'type'				=> 'time',
			'description' 		=> __('Set this to have this printed on the ticket.', 'event-tickets-with-ticket-scanner'),
			'desc_tip'    		=> true
		]);
		woocommerce_wp_text_input([
			'id'				=> 'saso_eventtickets_ticket_end_date',
			'value'       		=> get_post_meta( get_the_ID(), 'saso_eventtickets_ticket_end_date', true ),
			'label'       		=> __('End date event', 'event-tickets-with-ticket-scanner'),
			'type'				=> 'date',
			'custom_attributes'	=> ['data-type'=>'date'],
			'description' 		=> __('Set this to have this printed on the ticket and prevent later the ticket to be still valid. Tickets cannot be redeemed after that day.', 'event-tickets-with-ticket-scanner'),
			'desc_tip'    		=> true
		]);
		woocommerce_wp_text_input([
			'id'				=> 'saso_eventtickets_ticket_end_time',
			'value'       		=> get_post_meta( get_the_ID(), 'saso_eventtickets_ticket_end_time', true ),
			'label'       		=> __('End time', 'event-tickets-with-ticket-scanner'),
			'type'				=> 'time',
			'description' 		=> __('Set this to have this printed on the ticket.', 'event-tickets-with-ticket-scanner'),
			'desc_tip'    		=> true
		]);
		if (true || $is_variable) {
			woocommerce_wp_checkbox([
				'id'          => 'saso_eventtickets_is_date_for_all_variants',
				'value'       => get_post_meta( get_the_ID(), 'saso_eventtickets_is_date_for_all_variants', true ),
				'label'       => __('Date is for all variants', 'event-tickets-with-ticket-scanner'),
				'description' => __('Activate this, to have the entered date printed on all product variants. No effect on simple products.', 'event-tickets-with-ticket-scanner')
			]);
		}
		$max_redeem_amount = intval(get_post_meta( get_the_ID(), 'saso_eventtickets_ticket_max_redeem_amount', true ));
		if ($max_redeem_amount < 1) $max_redeem_amount = 1;
		woocommerce_wp_text_input([
			'id'				=> 'saso_eventtickets_ticket_max_redeem_amount',
			'value'       		=> $max_redeem_amount,
			'label'       		=> __('Max. redeem operations', 'event-tickets-with-ticket-scanner'),
			'type'				=> 'number',
			'custom_attributes'	=> ['step'=>'1', 'min'=>'1'],
			'description' 		=> __('How often do you allow to redeem the ticket?', 'event-tickets-with-ticket-scanner'),
			'desc_tip'    		=> true
		]);
		woocommerce_wp_textarea_input([
			'id'          => 'saso_eventtickets_ticket_is_ticket_info',
			'value'       => get_post_meta( get_the_ID(), 'saso_eventtickets_ticket_is_ticket_info', true ),
			'label'       => __('Print this on the ticket', 'event-tickets-with-ticket-scanner'),
			'description' => __('This optional information will be displayed on the ticket detail page.', 'event-tickets-with-ticket-scanner'),
			'desc_tip'    => true
		]);
		/*
		woocommerce_wp_checkbox( array(
			'id'          => 'saso_eventtickets_ticket_is_RTL',
			'value'       => get_post_meta( get_the_ID(), 'saso_eventtickets_ticket_is_RTL', true ),
			'label'       => __('Text is RTL', 'event-tickets-with-ticket-scanner'),
			'description' => __('Activate this, to use language from right to left like on arabic language.', 'event-tickets-with-ticket-scanner')
		));
		*/
		echo '</div>';

		echo '<div class="options_group">';
		woocommerce_wp_checkbox([
			'id'          => 'saso_eventtickets_request_name_per_ticket',
			'value'       => get_post_meta( get_the_ID(), 'saso_eventtickets_request_name_per_ticket', true ),
			'label'       => __('Request a value for each ticket', 'event-tickets-with-ticket-scanner'),
			'description' => __('Activate this, so that your customer can add a value for each ticket. This could be the name or any other value, defined by you. This value will be printed on the ticket. The value is limited to max 140 letters.', 'event-tickets-with-ticket-scanner')
		]);
		woocommerce_wp_text_input([
			'id'          => 'saso_eventtickets_request_name_per_ticket_label',
			'value'       => get_post_meta( get_the_ID(), 'saso_eventtickets_request_name_per_ticket_label', true ),
			'label'       => __('Label for the value', 'event-tickets-with-ticket-scanner'),
			'description' => __('This is how your customer understand what value should be entered.', 'event-tickets-with-ticket-scanner'),
			'placeholder' => 'Name for the ticket {count}:',
			'desc_tip'    => true
		]);
		woocommerce_wp_checkbox([
			'id'          => 'saso_eventtickets_request_name_per_ticket_mandatory',
			'value'       => get_post_meta( get_the_ID(), 'saso_eventtickets_request_name_per_ticket_mandatory', true ),
			'label'       => __('The value for each ticket is mandatory', 'event-tickets-with-ticket-scanner'),
			'description' => __('Activate this, so that your customer has to enter a value.', 'event-tickets-with-ticket-scanner')
		]);
		echo '</div>';

		echo '<div class="options_group">';
		if (count($lists) == 0) {
			echo "<p><b>".esc_html__('You have no lists created!', 'event-tickets-with-ticket-scanner')."</b><br>".esc_html__('You need to create a list first within the event tickets admin area, to choose a list from.', 'event-tickets-with-ticket-scanner')."</b></p>";
		}
		woocommerce_wp_select( array(
			'id'          => 'saso_eventtickets_list',
			'value'       => get_post_meta( get_the_ID(), 'saso_eventtickets_list', true ),
			'label'       => __('List', 'event-tickets-with-ticket-scanner'),
			'description' => __('Choose a list to activate auto-generating ticket numbers/codes for each sold item', 'event-tickets-with-ticket-scanner'),
			'desc_tip'    => true,
			'options'     => $this->wc_get_lists()
		) );
		echo '</div>';

		echo '<div class="options_group">';
		woocommerce_wp_checkbox( array(
			'id'            => 'saso_eventtickets_list_formatter',
			'label'			=> __('Use format settings', 'event-tickets-with-ticket-scanner'),
			'description'   => __('If active, then the format below will be used to generate ticket numbers during a purchase of this product.', 'event-tickets-with-ticket-scanner'),
			'value'         => get_post_meta( get_the_ID(), 'saso_eventtickets_list_formatter', true )
		) );
		echo '<input data-id="saso_eventtickets_list_formatter_values" name="saso_eventtickets_list_formatter_values" type="hidden" value="'.esc_js(get_post_meta( get_the_ID(), 'saso_eventtickets_list_formatter_values', true )).'">';
		echo '<div style="padding-top:10px;padding-left:10%;padding-right:20px;"><b>'.esc_html__('The ticket number format settings.', 'event-tickets-with-ticket-scanner').'</b><br><i>'.esc_html__('This will override an existing and active global "serial code formatter pattern for new sales" and also any format settings from the group.', 'event-tickets-with-ticket-scanner').'</i><div id="saso_eventtickets_list_format_area"></div></div>';
		echo '</div>';

		/*
		echo '<div class="options_group">';
		if (version_compare( WC_VERSION, SASO_EVENTTICKETS_PLUGIN_MIN_WC_VER, '<' )) {
			echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'For the Code List for sale restriction the plugin requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is not supported.', 'event-tickets-with-ticket-scanner' ), SASO_EVENTTICKETS_PLUGIN_MIN_WC_VER, WC_VERSION ) . '</strong></p></div>';
			echo '<p><strong>' . sprintf( esc_html__( 'For the Code List for sale restriction the plugin requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is not supported.', 'event-tickets-with-ticket-scanner' ), SASO_EVENTTICKETS_PLUGIN_MIN_WC_VER, WC_VERSION ) . '</strong></p>';
		} else {
			woocommerce_wp_select( array(
				'id'          => 'saso_eventtickets_list_sale_restriction',
				'value'       => get_post_meta( get_the_ID(), 'saso_eventtickets_list_sale_restriction', true ),
				'label'       => 'Code List for sale restriction ',
				'description' => 'Choose a code list to restrict the sale of this product to be done only with a working code from this list',
				'desc_tip'    => true,
				'options'     => $this->wc_get_lists_sales_restriction()
			) );
		}
		echo '</div>';
		*/

		if ($this->getMain()->isPremium() && method_exists($this->getMain()->getPremiumFunctions(), 'saso_eventtickets_wc_product_panels')) {
			$this->getMain()->getPremiumFunctions()->saso_eventtickets_wc_product_panels(get_the_ID());
		}

		echo '</div>';
	}

	public function woocommerce_process_product_meta( $id, $post ) {
		$key = 'saso_eventtickets_list';
		if( !empty( $_POST[$key] ) ) {
			update_post_meta( $id, $key, sanitize_text_field($_POST[$key]) );
		} else {
			delete_post_meta( $id, $key );
		}

		// damit nicht alte Eintragungen gelöscht werden - so kann der kunde upgrade machen und alles ist noch da
		if (version_compare( WC_VERSION, SASO_EVENTTICKETS_PLUGIN_MIN_WC_VER, '>=' )) {
			$key = 'saso_eventtickets_list_sale_restriction';
			if( $_POST[$key] == '0' || !empty( $_POST[$key] ) ) {
				update_post_meta( $id, $key, sanitize_text_field($_POST[$key]) );
			} else {
				delete_post_meta( $id, $key );
			}
		}

		$key = 'saso_eventtickets_is_ticket';
		if( isset( $_POST[$key] ) ) {
			update_post_meta( $id, $key, 'yes' );
		} else {
			delete_post_meta( $id, $key );
		}

		$key = 'saso_eventtickets_event_location';
		if( !empty( $_POST[$key] ) ) {
			update_post_meta( $id, $key, sanitize_text_field($_POST[$key]) );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_ticket_start_date';
		if( !empty( $_POST[$key] ) ) {
			update_post_meta( $id, $key, sanitize_text_field($_POST[$key]) );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_ticket_start_time';
		if( !empty( $_POST[$key] ) ) {
			update_post_meta( $id, $key, sanitize_text_field($_POST[$key]) );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_ticket_end_date';
		if( !empty( $_POST[$key] ) ) {
			update_post_meta( $id, $key, sanitize_text_field($_POST[$key]) );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_ticket_end_time';
		if( !empty( $_POST[$key] ) ) {
			update_post_meta( $id, $key, sanitize_text_field($_POST[$key]) );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_is_date_for_all_variants';
		if( isset( $_POST[$key] ) ) {
			update_post_meta( $id, $key, 'yes' );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_ticket_max_redeem_amount';
		if( !empty( $_POST[$key] ) ) {
			$value = intval($_POST[$key]);
			if ($value < 1) $value = 1;
			update_post_meta( $id, $key, $value );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_ticket_is_ticket_info';
		if( !empty( $_POST[$key] ) ) {
			update_post_meta( $id, $key, wp_kses_post($_POST[$key]) );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_request_name_per_ticket';
		if( isset( $_POST[$key] ) ) {
			update_post_meta( $id, $key, 'yes' );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_request_name_per_ticket_label';
		if( !empty( $_POST[$key] ) ) {
			update_post_meta( $id, $key, sanitize_text_field($_POST[$key]) );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_request_name_per_ticket_mandatory';
		if( isset( $_POST[$key] ) ) {
			update_post_meta( $id, $key, 'yes' );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_ticket_is_RTL';
		if( isset( $_POST[$key] ) ) {
			update_post_meta( $id, $key, 'yes' );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_list_formatter';
		if( isset( $_POST[$key] ) ) {
			update_post_meta( $id, $key, 'yes' );
		} else {
			delete_post_meta( $id, $key );
		}
		$key = 'saso_eventtickets_list_formatter_values';
		if( !empty( $_POST[$key] ) ) {
			update_post_meta( $id, $key, sanitize_text_field($_POST[$key]) );
		} else {
			delete_post_meta( $id, $key );
		}

		if ($this->getMAIN()->isPremium() && method_exists($this->getMAIN()->getPremiumFunctions(), 'saso_eventtickets_wc_save_fields')) {
			$this->getMAIN()->getPremiumFunctions()->saso_eventtickets_wc_save_fields($id, $post);
		}
	}

	public function hasTicketsInOrder($order) {
		$items = $order->get_items();
		// check if order contains tickets
		foreach($items as $item_id => $item) {
			if (get_post_meta($item->get_product_id(), 'saso_eventtickets_is_ticket', true) == "yes") {
				return true;
			}
		}
		return false;
	}

	public function getTicketsFromOrder($order) {
		$tickets = [];
		$items = $order->get_items();
		// check if order contains tickets
		foreach($items as $item_id => $item) {
			if (get_post_meta($item->get_product_id(), 'saso_eventtickets_is_ticket', true) == "yes") {
				$codes = wc_get_order_item_meta($item_id , '_saso_eventtickets_product_code',true);
				$tickets[$item->get_product_id()] = ['quantity'=>$item->get_quantity(), "codes"=>$codes];
			}
		}
		return $tickets;
	}

	public function woocommerce_email_attachments($attachments, $email_id, $order) {
		if ( ! is_a( $order, 'WC_Order' ) || ! isset( $email_id ) ) {
			return $attachments;
		}

		$this->_attachments = [];

		// ics file anhängen
		$wcTicketAttachICSToMail = $this->getOptions()->isOptionCheckboxActive('wcTicketAttachICSToMail');
		if ($wcTicketAttachICSToMail) {
			// $email_id == 'customer_on_hold_order'
			if (
				$email_id == 'customer_completed_order' ||
				$email_id == 'customer_note' ||
				$email_id == 'customer_invoice' ||
				$email_id == 'customer_processing_order'
				){
				$tickets = $this->getTicketsFromOrder($order);
				// get ticket date if set
				$dirname = get_temp_dir(); // pfad zu den dateien
				if (wp_is_writable($dirname)) {
					$dirname .=  trailingslashit($this->getPrefix());
					if (!file_exists($dirname)) {
						// mkdir if not exists
						wp_mkdir_p($dirname);
					}
					foreach($tickets as $product_id => $ticket) {
						try {
							$product = wc_get_product( $product_id );
							$contents = sasoEventtickets_Ticket::generateICSFile($product);
							// save file
							$file = $dirname."ics_".$product_id.".ics";
							$ret = file_put_contents( $file, $contents );
							// add attachments
							$this->_attachments[] = $file;
						} catch(Exception $e) {}
					}
				}
			}
		}

		$wcTicketBadgeAttachFileToMail = $this->getOptions()->isOptionCheckboxActive('wcTicketBadgeAttachFileToMail');
		if ($wcTicketBadgeAttachFileToMail) {
			$allowed_emails = $this->getOptions()->getOptionValue("wcTicketAttachTicketToMailOf");
			if (in_array($email_id, $allowed_emails)) {
				$badgeHandler = $this->MAIN->getTicketBadgeHandler();
				$tickets = $this->getTicketsFromOrder($order);
				if (count($tickets)>0) {
					$dirname = get_temp_dir(); // pfad zu den dateien
					if (wp_is_writable($dirname)) {
						$dirname .=  trailingslashit($this->getPrefix());
						if (!file_exists($dirname)) {
							wp_mkdir_p($dirname);
						}
						$attachments_badges = [];
						foreach($tickets as $product_id => $ticket) {
							try {
								$codes = [];
								if (!empty($ticket['codes'])) {
									$codes = explode(",", $ticket['codes']);
								}
								foreach($codes as $code) {
									try {
										$codeObj = $this->getCore()->retrieveCodeByCode($code);
									} catch (Exception $e) {
										continue;
									}
									$attachments_badges[] = $badgeHandler->getPDFTicketBadgeFilepath($codeObj, $dirname);
								}

								$wcTicketBadgeAttachFileToMailAsOnePDF = $this->getOptions()->getOptionValue("wcTicketBadgeAttachFileToMailAsOnePDF");
								if ($wcTicketBadgeAttachFileToMailAsOnePDF && count($attachments_badges) > 1) {
									$filename = "ticketbadges_".$codeObj['order_id'].".pdf";
									$this->_attachments[] = $this->MAIN->getCore()->mergePDFs($attachments_badges, $filename, "F", false);
								} else {
									$this->_attachments = array_merge($this->_attachments, $attachments_badges);
								}

							} catch(Exception $e) {}
						}
					}
				}
			}
		}

		$_attachments = apply_filters( $this->getMain()->_add_filter_prefix.'woocommerce_email_attachments', $attachments, $email_id, $order );
		if (count($_attachments) > 0) {
			$this->_attachments = array_merge($this->_attachments, $_attachments);
		}

		// anhängen
		foreach($this->_attachments as $item) {
			if (file_exists($item)) $attachments[] = $item;
		}

		// add hook, um die attachments zu löschen
		if (count($this->_attachments) > 0) {
			add_action( 'wp_mail_succeeded', [$this, 'wp_mail_succeeded'], 10, 1 );
			add_action( 'wp_mail_failed', [$this, 'wp_mail_failed'], 10, 1 );
		}

		return $attachments;
	}

	public function woocommerce_order_status_changed($order_id,$old_status,$new_status) {
		if ($new_status != "refunded" && $new_status != "cancelled" && $new_status != "wc-refunded" && $new_status != "wc-cancelled") {
			$this->add_serialcode_to_order($order_id); // vlt wurden manuel produkte hinzugefügt
		}
		if ($new_status == "cancelled" || $new_status == "wc-cancelled" || $new_status == "wc-refunded" || $new_status == "refunded") {
			if ($this->getOptions()->isOptionCheckboxActive('wcRestrictFreeCodeByOrderRefund')) {
				$order = wc_get_order( $order_id );
				foreach ( $order->get_items() as $item_id => $item ) {
					$this->woocommerce_delete_order_item($item_id);
				}
			}
		}
	}

	public function woocommerce_order_item_display_meta_key( $display_key, $meta, $item ) {
		// display within the order

		if ( is_admin() && $item->get_type() === 'line_item'){
			// Change displayed label for specific order item meta key
			if($meta->key === '_saso_eventtickets_product_code' ) {
				$isTicket = $item->get_meta('_saso_eventtickets_is_ticket') == 1 ? true : false;
				if ($isTicket) {
					$display_key = __("Ticket number", 'event-tickets-with-ticket-scanner');
				} else {
					$display_key = _x("Code", "noun", 'event-tickets-with-ticket-scanner');
				}
			}
			if($meta->key === '_saso_eventticket_code_list' ) {
				$display_key = __("List ID", 'event-tickets-with-ticket-scanner');
			}
			if ($meta->key === "_saso_eventtickets_is_ticket") {
				$display_key = __("Is Ticket", 'event-tickets-with-ticket-scanner');
			}

			// label for purchase restriction code
			if($meta->key === $this->meta_key_codelist_restriction_order_item ) {
				$display_key = esc_attr($this->getOptions()->getOptionValue('wcRestrictPrefixTextCode'));
			}
		}

		return $display_key;
	}

	public function woocommerce_order_item_display_meta_value($meta_value, $meta, $item) {
		// zeigen in der Order den Wert an

		if( is_admin() && $item->get_type() === 'line_item') {
			if ($meta->key === '_saso_eventtickets_product_code' ) {
				$codes = explode(",", $meta_value);
				$codes_ = [];
				foreach($codes as $c) {
					$codes_[] = '<a target="_blank" href="admin.php?page=event-tickets-with-ticket-scanner&code='.urlencode($c).'">'.$c.'</a>';
				}
				$meta_value = implode(", ", $codes_);
			}
			if ($meta->key === '_saso_eventtickets_is_ticket' ) {
				$meta_value = $meta_value == 1 ? "Yes" : "No";
			}
		}

		return $meta_value;
	}

	public function manage_edit_product_columns($columns) {
		$new_columns = (is_array($columns)) ? $columns : array();
		$new_columns['SASO_EVENTTICKETS_LIST_COLUMN'] = _x('Ticket List', 'label', 'event-tickets-with-ticket-scanner');
		return $new_columns;
	}
	public function manage_product_posts_custom_column($column) {
		global $post;

		if ($column == 'SASO_EVENTTICKETS_LIST_COLUMN') {
			$code_list_ids = get_post_meta($post->ID, 'saso_eventtickets_list', true);

			$lists = $this->getAdmin()->getLists();
			$dropdown_list = array('' => '-');
			foreach ($lists as $key => $list) {
				$dropdown_list[$list['id']] = $list['name'];
			}

			if (isset($code_list_ids) && !empty($code_list_ids)) {
				echo !empty( $dropdown_list[$code_list_ids]) ? esc_html($dropdown_list[$code_list_ids]) : '-';
			} else {
				echo "-";
			}
		}
	}
	public function manage_edit_product_sortable_columns($columns) {
		$custom = array(
			'SASO_EVENTTICKETS_LIST_COLUMN' => 'saso_eventtickets_list'
		);
		return wp_parse_args($custom, $columns);
	}

	public function wpo_wcpdf_after_item_meta( $template_type, $item, $order ) {
		$isPaid = SASO_EVENTTICKETS::isOrderPaid($order);
		if ($isPaid) {
			$code = wc_get_order_item_meta($item['item_id'] , $this->meta_key_codelist_restriction_order_item, true);
			if (!empty($code)) {
				if (!$this->getOptions()->isOptionCheckboxActive('wcRestrictDoNotPutOnPDF')) {
					$preText = $this->getOptions()->getOptionValue('wcRestrictPrefixTextCode');
					echo '<div class="product-serial-code">'.esc_html($preText).' '. esc_attr($code).'</div>';
				}
			}

			$code = wc_get_order_item_meta($item['item_id'] , '_saso_eventtickets_product_code',true);
			if (!empty($code)) {

				if (!$this->getOptions()->isOptionCheckboxActive('wcassignmentDoNotPutOnPDF')) {
					$code_ = explode(",", $code);
					array_walk($code_, "trim");

					$isTicket = wc_get_order_item_meta($item['item_id'] , '_saso_eventtickets_is_ticket',true) == 1 ? true : false;
					$key = 'wcassignmentPrefixTextCode';
					if ($isTicket) $key = 'wcTicketPrefixTextCode';
					$preText = $this->getOptions()->getOptionValue($key);

					$wcassignmentDoNotPutCVVOnPDF = $this->getOptions()->isOptionCheckboxActive('wcassignmentDoNotPutCVVOnPDF');

					if ($isTicket) {
						if ($this->getOptions()->isOptionCheckboxActive('wcTicketDisplayDateOnMail')) {
							$product_id = $item['product_id'];
							$product = wc_get_product( $product_id );
							$date_str = sasoEventtickets_Ticket::displayTicketDateAsString($product);
							if (!empty($date_str)) echo $date_str."<br>";
						}

						$wcTicketBadgeLabelDownload = $this->MAIN->getOptions()->getOptionValue('wcTicketBadgeLabelDownload');
						$code_size = count($code_);
						$counter = 0;
						$mod = 40;
						foreach($code_ as $c) {
							if (!empty($c)) {
								$counter++;

								$codeObj = $this->getCore()->retrieveCodeByCode($c);
								$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
								$url = $metaObj['wc_ticket']['_url'];
								//echo '<p class="product-serial-code">'.esc_html($preText).' <b>'.esc_html($c).'</b>';
								echo '<br>'.esc_html($preText).' <b>'.esc_html($c).'</b>';
								if (!empty($codeObj['cvv']) && !$wcassignmentDoNotPutCVVOnPDF) {
									echo " CVV: <b>".esc_html($codeObj['cvv']).'</b>';
								}

								if (!$this->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayDetailLinkOnMail')) {
									$mod = 8;
									if (!empty($url)) {
										echo '<br><b>'.esc_html__('Ticket Detail', 'event-tickets-with-ticket-scanner').':</b> ' . esc_url($url) . '<br>';
									}
								}

								if (!$this->getOptions()->isOptionCheckboxActive('wcTicketBadgeAttachLinkToMail')) {
									$mod = 8;
									if (!empty($url)) {
										echo '<br><b>'.esc_html($wcTicketBadgeLabelDownload).':</b> ' . esc_url($url) . '?badge<br>';
									}
								}
								//echo '</p>';

								if ($code_size > $mod && $counter % $mod == 0) {
									echo '<div style="page-break-before: always;"></div>';
								}
							}
						}

					} else {
						$sep = $this->getOptions()->getOptionValue('wcassignmentDisplayCodeSeperatorPDF');
						$ccodes = [];
						foreach($code_ as $c) {
							if (!empty($c)) {
								if (!$wcassignmentDoNotPutCVVOnPDF) {
									$codeObj = $this->getCore()->retrieveCodeByCode($c);
									if (!empty($codeObj['cvv'])) {
										$ccodes[] = esc_html($c." CVV: ".$codeObj['cvv']);
									} else {
										$ccodes[] = esc_html($c);
									}
								} else {
									$ccodes[] = esc_html($c);
								}
							}
						}
						$code_text = implode($sep, $ccodes);
						echo '<div class="product-serial-code">'.esc_html($preText).' '. esc_html($code_text).'</div>';
					}
				}
			}
		} // not paid
	}

	public function woocommerce_order_item_meta_start($item_id, $item, $order, $plain_text=false) {

		$this->add_serialcode_to_order($order->get_id()); // falls noch welche fehlen, dann vor der E-Mail noch hinzufügen

		$isPaid = SASO_EVENTTICKETS::isOrderPaid($order);
		if ($isPaid) {
			$sale_restriction_code = wc_get_order_item_meta($item_id , $this->meta_key_codelist_restriction_order_item, true);
			if (!empty($sale_restriction_code)) {
				$preText = $this->getOptions()->getOptionValue('wcRestrictPrefixTextCode');
				if ($plain_text) {
					echo "\n".esc_html($preText).' '. esc_attr($sale_restriction_code);
				} else {
					echo '<div class="product-restriction-serial-code">'.esc_html($preText).' '. esc_attr($sale_restriction_code).'</div>';
				}
			}

			$displaySerial = false;
			$code = "";
			$preText = "";
			if (!$this->getOptions()->isOptionCheckboxActive('wcassignmentDoNotPutOnEmail')) {
				$isTicket = wc_get_order_item_meta($item_id , '_saso_eventtickets_is_ticket',true) == 1 ? true : false;
				if ($isTicket) {
					$code = wc_get_order_item_meta($item_id , '_saso_eventtickets_product_code',true);
					if (!empty($code)) {
						$preText = $this->getOptions()->getOptionValue('wcTicketPrefixTextCode');
						$displaySerial = true;
					}
				} else { // serial?
					/*
					$code = wc_get_order_item_meta($item_id , '_saso_eventtickets_product_code',true);
					if (!empty($code)) {
						$preText = $this->getOptions()->getOptionValue('wcassignmentPrefixTextCode');
						$displaySerial = true;
					}
					*/
				}
			}
			if ($displaySerial) {
				$code_ = explode(",", $code);
				array_walk($code_, "trim");
				if ($isTicket) {
					$wcassignmentDoNotPutCVVOnEmail = $this->getOptions()->isOptionCheckboxActive('wcassignmentDoNotPutCVVOnEmail');
					$wcTicketDontDisplayDetailLinkOnMail = $this->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayDetailLinkOnMail');
					$wcTicketDontDisplayPDFButtonOnMail = $this->getOptions()->isOptionCheckboxActive('wcTicketDontDisplayPDFButtonOnMail');
					$wcTicketBadgeAttachLinkToMail = $this->getOptions()->isOptionCheckboxActive('wcTicketBadgeAttachLinkToMail');

					if ($this->getOptions()->isOptionCheckboxActive('wcTicketDisplayDateOnMail')) {
						$product = $item->get_product();
						$date_str = sasoEventtickets_Ticket::displayTicketDateAsString($product);
						if (!empty($date_str)) echo "<br>".$date_str;
					}

					foreach($code_ as $c) {
						if (!empty($c)) {
							$cvv = "";
							$url = "";
							try { // kann sein, dass keine free tickets mehr verfügbar sind
								$codeObj = $this->getCore()->retrieveCodeByCode($c);
								$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
								$url = $metaObj['wc_ticket']['_url'];
								$cvv = $codeObj['cvv'];
							} catch (Exception $e) {}

							if ($plain_text) {
								echo "\n".esc_html($preText).' '.esc_attr($c);
								if (!empty($cvv) && !$wcassignmentDoNotPutCVVOnEmail) {
									echo "\nCVV: ".esc_html($cvv);
								}
								if (!empty($url) && !$wcTicketDontDisplayDetailLinkOnMail) {
									echo "\n".esc_html__('Ticket Detail', 'event-tickets-with-ticket-scanner').": " . esc_url($url);
								}
								if (!empty($url) && !$wcTicketDontDisplayPDFButtonOnMail) {
									$dlnbtnlabel = $this->getOptions()->getOptionValue('wcTicketLabelPDFDownload');
									echo "\n" . esc_html($dlnbtnlabel) . " " . esc_url($url).'?pdf';
								}
								if (!empty($url) && $wcTicketBadgeAttachLinkToMail ) {
									$dlnbtnlabel = $this->getOptions()->getOptionValue('wcTicketBadgeLabelDownload');
									echo "\n" . esc_html($dlnbtnlabel) . " " . esc_url($url).'?badge';
								}
							} else {
								echo '<div class="product-serial-code">';
								if (!empty($url) && !$wcTicketDontDisplayPDFButtonOnMail) {
									$dlnbtnlabel = $this->getOptions()->getOptionValue('wcTicketLabelPDFDownload');
									echo '<a target="_blank" href="'.esc_url($url).'?pdf"><b>'.esc_html($dlnbtnlabel).'</b></a> ';
								}
								echo esc_html($preText)." ";
								if (empty($url) || $wcTicketDontDisplayDetailLinkOnMail) {
									echo esc_html($c);
								} else {
									echo '<a target="_blank" href="'.esc_url($url).'">'.esc_html($c).'</a> ';
								}
								if (!empty($cvv) && !$wcassignmentDoNotPutCVVOnEmail) {
									echo "CVV: ".esc_html($cvv);
								}
								if (!empty($url) && $wcTicketBadgeAttachLinkToMail ) {
									$dlnbtnlabel = $this->getOptions()->getOptionValue('wcTicketBadgeLabelDownload');
									echo '<a target="_blank" href="'.esc_url($url).'?badge"><b>'.esc_html($dlnbtnlabel).'</b></a> ';
								}
								echo '</div>';
							}
						}
					}
				} else { // serial
					/*
					$sep = $this->getOptions()->getOptionValue('wcassignmentDisplayCodeSeperator');
					$ccodes = [];
					foreach($code_ as $c) {
						if (!$wcassignmentDoNotPutCVVOnEmail) {
							$codeObj = $this->getCore()->retrieveCodeByCode($c);
							if (!empty($codeObj['cvv'])) {
								$ccodes[] = esc_html($c." CVV: ".$codeObj['cvv']);
							} else {
								$ccodes[] = esc_html($c);
							}
						} else {
							$ccodes[] = esc_html($c);
						}
					}
					$code_text = implode($sep, $ccodes);
					if ($plain_text) {
						echo "\n".esc_html($preText).' '.esc_attr($code_text);
					} else {
						echo '<div class="product-serial-code">'.esc_html($preText).' '.esc_html($code_text).'</div>';
					}
					*/
				}
			}
		} // not paid
	}

	public function woocommerce_email_order_meta ($order, $sent_to_admin, $plain_text, $email) {
		$wcTicketDisplayDownloadAllTicketsPDFButtonOnMail = $this->getOptions()->isOptionCheckboxActive('wcTicketDisplayDownloadAllTicketsPDFButtonOnMail');
		if ($wcTicketDisplayDownloadAllTicketsPDFButtonOnMail) {
			if ($this->hasTicketsInOrder($order)) {
				$url = $this->getCore()->getOrderTicketsURL($order);
				$dlnbtnlabel = $this->getOptions()->getOptionValue('wcTicketLabelPDFDownload');
				$dlnbtnlabelHeading = $this->getOptions()->getOptionValue('wcTicketLabelPDFDownloadHeading');
				echo '<h2>'.esc_html($dlnbtnlabelHeading).'</h2>';
				echo '<p><a target="_blank" href="'.esc_url($url).'"><b>'.esc_html($dlnbtnlabel).'</b></a></p>';
			}
		}
	}

	private function delete_woocommerce_email_attachments() {
		$dirname = get_temp_dir().$this->getPrefix(); // pfad zu den dateien
		foreach($this->_attachments as $item) {
			try {
				if (file_exists($item) && dirname($item) == $dirname) @unlink($item);
			} catch(Exception $e) {}
		}
		$this->_attachments = [];
	}
	public function wp_mail_failed($wp_error) {
		$this->delete_woocommerce_email_attachments();
	}
	public function wp_mail_succeeded($mail_data) {
		$this->delete_woocommerce_email_attachments();
	}

	public function woocommerce_new_order($order_id) {
		if (WC() != null && WC()->session != null) {
			WC()->session->__unset('saso_eventtickets_request_name_per_ticket');
		}
	}

    public function wc_order_add_meta_boxes() {
		global $post_type;
		global $post;

		if( $post_type == 'product' ) {
        	if( $this->isTicket() == false ) return;

			add_meta_box(
				$this->getPrefix()."_wc_product_webhook", // Unique ID
				esc_html_x('Event Tickets', 'title', 'event-tickets-with-ticket-scanner'),  // Box title
				[$this, 'wc_product_display_side_box'],  // Content callback, must be of type callable
				$post_type,
				'side',
				'high'
			);
		} elseif ($post_type == "shop_order") {
			$order = wc_get_order( $post->ID );
			if ($this->hasTicketsInOrder($order)) {
				$this->wc_order_addJSFileAndHandlerBackend($order);
				add_meta_box(
					$this->getPrefix()."_wc_order_webhook_basic", // Unique ID
					esc_html_x('Event Tickets', 'title', 'event-tickets-with-ticket-scanner'),  // Box title
					[$this, 'wc_order_display_side_box'],  // Content callback, must be of type callable
					$post_type,
					'side',
					'high'
				);
			}
		}
    }

    public function wc_product_display_side_box() {
        ?>
        <p>Download Event Flyer</p>
        <button disabled data-id="<?php echo esc_attr($this->getPrefix()."btn_download_flyer"); ?>" class="button button-primary">Download Event Flyer</button>
		<p>Download ICS File (cal file)</p>
		<button disabled data-id="<?php echo esc_attr($this->getPrefix()."btn_download_ics"); ?>" class="button button-primary">Download ICS File</button>
		<p>Display all Tickets Infos</p>
		<button disabled data-id="<?php echo esc_attr($this->getPrefix()."btn_download_ticket_infos"); ?>" class="button button-primary">Print Ticket Infos</button>
        <?php
		do_action( $this->getMain()->_do_action_prefix.'wc_product_display_side_box', [] );
    }

	public function wc_order_display_side_box() {
		?>
        <p>Download All Tickets in one PDF</p>
        <button disabled data-id="<?php echo esc_attr($this->getPrefix()."btn_download_alltickets_one_pdf"); ?>" class="button button-primary">Download Tickets</button>
		<?php
		do_action( $this->getMain()->_do_action_prefix.'wc_order_display_side_box', [] );
	}

	private function wc_order_addJSFileAndHandlerBackend($order) {
		$tickets = $this->getTicketsFromOrder($order);
		wp_enqueue_media(); // damit der media chooser von wordpress geladen wird
		wp_register_script(
			$this->getMain()->getPrefix().'WC_Order_Ajax_Backend_Basic',
			trailingslashit( plugin_dir_url( __FILE__ ) ) . 'wc_backend.js?_v='.$this->getMain()->getPluginVersion(),
			array( 'jquery', 'jquery-blockui', 'wp-i18n' ),
			(current_user_can("administrator") ? time() : $this->getMain()->getPluginVersion()),
			true );
		wp_set_script_translations($this->getMain()->getPrefix().'WC_Order_Ajax_Backend_Basic', 'event-tickets-with-ticket-scanner');
		wp_localize_script(
			$this->getMain()->getPrefix().'WC_Order_Ajax_Backend_Basic',
			'Ajax_sasoEventtickets_wc', // name der js variable
 			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'_plugin_home_url' =>plugins_url( "",__FILE__ ),
				'prefix'=>$this->getMain()->getPrefix(),
				'nonce' => wp_create_nonce( $this->getMain()->getPrefix() ),
				'action' => $this->getMain()->getPrefix().'_executeWCBackend',
				'product_id'=>0,
 				'order_id'=>isset($_GET['post']) ? intval($_GET['post']) : 0,
				'scope'=>'order',
				'_backendJS'=>trailingslashit( plugin_dir_url( __FILE__ ) ) . 'backend.js?_v='.$this->getMain()->getPluginVersion(),
				'tickets'=>$tickets
 			] // werte in der js variable
 			);
      	wp_enqueue_script($this->getMain()->getPrefix().'WC_Order_Ajax_Backend_Basic');
 	}

	function add_serialcode_to_order($order_id) {

		if ( ! $order_id ) return;

		// Getting an instance of the order object
		$order = wc_get_order( $order_id );

		$create_tickets = SASO_EVENTTICKETS::isOrderPaid($order);
		$ok_order_statuses = $this->getOptions()->getOptionValue('wcTicketAddToOrderOnlyWithOrderStatus');
		if (is_array($ok_order_statuses) && count($ok_order_statuses) > 0) {
			$order_status = $order->get_status();
			$create_tickets = in_array($order_status, $ok_order_statuses);
		}
		if ($create_tickets == false) {
			if (isset($_REQUEST['a_sngmbh']) && $_REQUEST['a_sngmbh'] == "premium" && isset($_REQUEST['data']) && isset($_REQUEST['data']['c']) && $_REQUEST['data']['c'] == "requestSerialsForOrder") {
				// premium add btn on order details overwrite the false
				$create_tickets = true;
			}
		}

		if ($create_tickets) {
			foreach ( $order->get_items() as $item_id => $item ) {
				$product_id = $item->get_product_id();
				if( $product_id ){
					$isTicket = get_post_meta($product_id, 'saso_eventtickets_is_ticket', true) == "yes";
					if ($isTicket) {
						$variation_id = $item->get_variation_id();
						if ($variation_id > 0) {
							// check ob diese variation vom ticket ausgeschlossen ist
							if (get_post_meta($variation_id, '_saso_eventtickets_is_not_ticket', true) == "yes") {
								continue;
							}
						}
						$code_list_id = get_post_meta($product_id, 'saso_eventtickets_list', true);
						if (!empty($code_list_id)) {
							$this->add_serialcode_to_order_forItem($order_id, $order, $item_id, $item, $code_list_id, '_saso_eventtickets_product_code', '_saso_eventticket_code_list');
						}
					}
				}
			} // end foreach
		}

		if (isset(WC()->session)) {
			if (!WC()->session->has_session()) {
				if (method_exists(WC()->session, '__unset')) {
					WC()->session->__unset('saso_eventtickets_request_name_per_ticket');
				} else {
					if (method_exists(WC()->session, '__isset')) {
						if (WC()->session->__isset('saso_eventtickets_request_name_per_ticket')) {
							if (WC()->session->__isset('saso_eventtickets_request_name_per_ticket')) {
								WC()->session->set('saso_eventtickets_request_name_per_ticket', []);
							}
						}
					}
				}
			}
		}
	}

	function add_serialcode_to_order_forItem($order_id, $order, $item_id, $item, $saso_eventtickets_list, $codeName, $codeListName) {
		$ret = [];
		$product_id = $item->get_product_id();
		if ($saso_eventtickets_list) {

			$quantity = $item->get_quantity();
			$existingCode = wc_get_order_item_meta($item_id , $codeName, true);
			if (!empty($existingCode)) {
				$codes = explode(",", $existingCode);
				$quantity = $quantity - count($codes);
			}

			if ($quantity > 0) {

				$product_formatter_values = "";
				if (get_post_meta($product_id, 'saso_eventtickets_list_formatter', true) == "yes") {
					$product_formatter_values = get_post_meta( $product_id, 'saso_eventtickets_list_formatter_values', true );
				}

				$values = [];
				$namesPerTicket = wc_get_order_item_meta($item_id, 'saso_eventtickets_request_name_per_ticket', true);
				if ($namesPerTicket != null && is_array($namesPerTicket) && count($namesPerTicket) > 0) {
					$values = $namesPerTicket;
				}

				$codes = [];
				for($a=0;$a<$quantity;$a++) {
					$namePerTicket = "";
					if (isset($values[$a])) {
						$namePerTicket = $values[$a];
					}
					$newcode = $this->getAdmin()->addCodeFromListForOrder($saso_eventtickets_list, $order_id, $product_id, $item_id, $product_formatter_values);
					try {
						$this->getAdmin()->setWoocommerceTicketInfoForCode($newcode, $namePerTicket);
					} catch(Exception $e) {
						// error handling
						$order = wc_get_order( $order_id );
						$order->add_order_note(esc_html__("Free ticket numbers used up. Added:", 'event-tickets-with-ticket-scanner')." ".esc_html($newcode));
						// for now ignoring them
					}
					$codes[] = $newcode;

				} // end for quantity
				if (count($codes) > 0) {
					$ret = $codes;
					wc_add_order_item_meta($item_id , $codeName, implode(",", $codes) ) ;
					wc_add_order_item_meta($item_id , $codeListName, $saso_eventtickets_list ) ;
				}

				wc_delete_order_item_meta( $item_id, '_saso_eventtickets_is_ticket' );
				wc_add_order_item_meta($item_id , '_saso_eventtickets_is_ticket', 1, true ) ;
			}
		}
		return $ret;
	}

	private function getMain() {
		if ($this->MAIN == null) {
			global $sasoEventtickets;
			$this->MAIN = $sasoEventtickets;
		}
		return $this->MAIN;
	}
	private function getAdmin() {
		return $this->getMain()->getAdmin();
	}
	private function getFrontend() {
		return $this->getMain()->getFrontend();
	}
	private function getCore() {
		return $this->getMain()->getCore();
	}
	private function getOptions() {
		return $this->getMain()->getOptions();
	}

	public function executeJSON($a, $data=[], $just_ret=false) {
		$ret = "";
		$justJSON = false;
		try {
			switch (trim($a)) {
				case "downloadFlyer":
					$ret = $this->downloadFlyer($data);
					break;
				case "downloadICSFile":
					$ret = $this->downloadICSFile($data);
					break;
				case "downloadTicketInfosOfProduct":
					$ret = $this->downloadTicketInfosOfProduct($data);
					break;
				case "downloadAllTicketsAsOnePDF":
					$ret = $this->downloadAllTicketsAsOnePDF($data);
					break;
				default:
					throw new Exception(sprintf(/* translators: %s: name of called function */esc_html__('function "%s" in wc backend not implemented', 'event-tickets-with-ticket-scanner'), $a));
			}
		} catch(Exception $e) {
			if ($just_ret) throw $e;
			return wp_send_json_error ($e->getMessage());
		}
		if ($just_ret) return $ret;
		if ($justJSON) return wp_send_json($ret);
		else return wp_send_json_success( $ret );
	}

	private function downloadAllTicketsAsOnePDF($data, $filemode="I") {
		$order_id = intval($data['order_id']);
		if ($order_id > 0) {
			$order = wc_get_order( $order_id );
			$ticketHandler = $this->MAIN->getTicketHandler();
			$ticketHandler->outputPDFTicketsForOrder($order);
			exit;
		}
	}

	private function downloadTicketInfosOfProduct($data) {
		$product_id = intval($data['product_id']);
		$product = [];
		if ($product_id > 0){
			$daten = $this->getAdmin()->getCodesByProductId($product_id);
			$productObj = wc_get_product( $product_id );
			if ($productObj != null) {
				$product['name'] = $productObj->get_name();
			}
		}
		return ['ticket_infos'=>$daten, 'product'=>$product];
	}

	private function downloadICSFile($data) {
		$product_id = intval($data['product_id']);
		$this->MAIN->getTicketHandler()->sendICSFileByProductId($product_id);
		exit;
	}

	private function downloadFlyer($data) {
		if (!isset($data['product_id'])) throw new Exception(esc_html__("Product Id for the event flyer is missing", 'event-tickets-with-ticket-scanner'));
		$product_id = intval($data['product_id']);
		// init PDF
		if (!class_exists('sasoEventtickets_PDF')) {
			require_once("sasoEventtickets_PDF.php");
		}
		$pdf = new sasoEventtickets_PDF();

		// lade product
		$product = wc_get_product( $product_id );
		$titel = $product->get_name();
		$short_desc = $product->get_short_description();
		$location = trim(get_post_meta( $product_id, 'saso_eventtickets_event_location', true ));
		$ticket_start_date = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_start_date', true ));
		$ticket_start_time = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_start_time', true ));
		$ticket_end_date = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_end_date', true ));
		$ticket_end_time = trim(get_post_meta( $product_id, 'saso_eventtickets_ticket_end_time', true ));
		$event_url = get_permalink( $product->get_id() );

		$event_date = "";
		if (!empty($ticket_start_date)) {
			$event_date = '<br><p style="text-align:center;">';
			$event_date .= $ticket_start_date;
			if (!empty($ticket_start_time)) $event_date .= " ".$ticket_start_time;
			if (!empty($ticket_end_date) || !empty($ticket_end_time)) $event_date .= " - ";
			if (!empty($ticket_end_date))  $event_date .= $ticket_end_date;
			if (!empty($ticket_end_time)) $event_date .= " ".$ticket_end_time;
			$event_date .= '</p>';
		}

		$pdf->setFilemode('I');

		$wcTicketFlyerBanner = $this->getOptions()->getOptionValue('wcTicketFlyerBanner');
		if (!empty($wcTicketFlyerBanner) && intval($wcTicketFlyerBanner) >0) {
			$option_wcTicketFlyerBanner = $this->getOptions()->getOption('wcTicketFlyerBanner');
			$mediaData = SASO_EVENTTICKETS::getMediaData($wcTicketFlyerBanner);
			$width = "600";
			if (isset($option_wcTicketFlyerBanner['additional']) && isset($option_wcTicketFlyerBanner['additional']['min']) && isset($option_wcTicketFlyerBanner['additional']['min']['width'])) {
				$width = $option_wcTicketFlyerBanner['additional']['min']['width'];
			}
			if (!empty($mediaData['location']) && file_exists($mediaData['location'])) {
				$pdf->addPart('<img width="21cm" src="'.$mediaData['location'].'"><br>');
			}
		}
		$pdf->addPart('<h1 style="text-align:center;">'.esc_html($titel).'</h1>');
		if (!empty($event_date)) {
			$pdf->addPart($event_date);
		}
		if (!empty($location)) {
			$pdf->addPart(wp_kses_post($this->getOptions()->getOptionValue("wcTicketTransLocation"))." <b>".wp_kses_post($location)."</b>");
		}
		$pdf->addPart('{QRCODE_INLINE}');
		$pdf->addPart('<p style="font-size:9pt;text-align:center;">'.esc_url($event_url).'</p>');
		$pdf->addPart('<br><p style="text-align:center;">'.wp_kses_post($short_desc).'</p>');
		$wcTicketFlyerDontDisplayPrice = $this->getOptions()->isOptionCheckboxActive('wcTicketFlyerDontDisplayPrice');
		if (!$wcTicketFlyerDontDisplayPrice) {
			$pdf->addPart('<br><br><p style="text-align:center;font-size:18pt;">'.wc_price($product->get_price(), ['decimals'=>2]).'</p>');
		}
		$wcTicketFlyerDontDisplayBlogName = $this->getOptions()->isOptionCheckboxActive('wcTicketFlyerDontDisplayBlogName');
		if (!$wcTicketFlyerDontDisplayBlogName) {
			$pdf->addPart('<br><br><div style="text-align:center;font-size:10pt;"><b>'.get_bloginfo("name").'</b></div>');
		}
		$wcTicketFlyerDontDisplayBlogDesc = $this->getOptions()->isOptionCheckboxActive('wcTicketFlyerDontDisplayBlogDesc');
		if (!$wcTicketFlyerDontDisplayBlogDesc) {
			if ($wcTicketFlyerDontDisplayBlogName) $pdf->addPart('<br>');
			$pdf->addPart('<div style="text-align:center;font-size:10pt;">'.get_bloginfo("description").'</div>');
		}
		if (!$this->getOptions()->isOptionCheckboxActive('wcTicketFlyerDontDisplayBlogURL')) {
			$pdf->addPart('<br><div style="text-align:center;font-size:10pt;">'.site_url().'</div>');
		}
		$wcTicketFlyerLogo = $this->getOptions()->getOptionValue('wcTicketFlyerLogo');
		if (!empty($wcTicketFlyerLogo) && intval($wcTicketFlyerLogo) >0) {
			$option_wcTicketFlyerLogo = $this->getOptions()->getOption('wcTicketFlyerLogo');
			$mediaData = SASO_EVENTTICKETS::getMediaData($wcTicketFlyerLogo);
			$width = "200";
			if (isset($option_wcTicketFlyerLogo['additional']) && isset($option_wcTicketFlyerLogo['additional']['max']) && isset($option_wcTicketFlyerLogo['additional']['max']['width'])) {
				$width = $option_wcTicketFlyerLogo['additional']['max']['width'];
			}
			if (!empty($mediaData['location']) && file_exists($mediaData['location'])) {
				$pdf->addPart('<br><br><p style="text-align:center;"><img width="'.$width.'" src="'.$mediaData['location'].'"></p>');
			}
		}
		$pdf->addPart('<br><p style="text-align:center;font-size:9pt;">powered by Event Tickets With Ticket Scanner Plugin for Wordpress</p>');

		$pdf->setQRParams(['style'=>['position'=>'C']]);
		$pdf->setQRCodeContent(["text"=>$event_url]);
		$wcTicketFlyerBG = $this->getOptions()->getOptionValue('wcTicketFlyerBG');
		if (!empty($wcTicketFlyerBG) && intval($wcTicketFlyerBG) >0) {
			$mediaData = SASO_EVENTTICKETS::getMediaData($wcTicketFlyerBG);
			if (!empty($mediaData['location']) && file_exists($mediaData['location'])) {
				$pdf->setBackgroundImage($mediaData['location']);
			}
		}
		$pdf->render();
		exit;
	}

	private function containsProductsWithRestrictions() {
		if ($this->_containsProductsWithRestrictions == null) {
			$this->_containsProductsWithRestrictions = false;
	    	// loop through cart items and check if a restriction is set
		    foreach(WC()->cart->get_cart() as $cart_item ) {
		        // Check cart item for defined product Ids and applied coupon
		        $saso_eventtickets_list = get_post_meta($cart_item['product_id'], $this->meta_key_codelist_restriction, true);

		       	if (!empty($saso_eventtickets_list)) {
					$this->_containsProductsWithRestrictions = true;
					break;
		       	}
		    }
		}
		return $this->_containsProductsWithRestrictions;
	}

	// add all filter and actions, if we are displaying the cart, checkout and have products with restrictions
	function woocommerce_before_cart_table() {
		add_action( 'woocommerce_after_cart_item_name', [$this, 'woocommerce_after_cart_item_name'], 10, 2 );
		if ($this->containsProductsWithRestrictions()) {
			$this->addJSFileAndHandler();
		}
	}

	private function addJSFileAndHandler() {
		// erstmal ist diese fkt nur für sales restriction
		if (version_compare( WC_VERSION, SASO_EVENTTICKETS_PLUGIN_MIN_WC_VER, '<' )) return;

		wp_register_script(
			'SasoEventticketsValidator_WC_frontend',
			trailingslashit( plugin_dir_url( __FILE__ ) ) . 'wc_frontend.js?_v='.$this->getMain()->getPluginVersion(),
			array( 'jquery', 'jquery-blockui', 'wp-i18n' ),
			(current_user_can("administrator") ? time() : $this->getMain()->getPluginVersion()),
			true );
		wp_set_script_translations('SasoEventticketsValidator_WC_frontend', 'event-tickets-with-ticket-scanner');
		wp_localize_script(
 			'SasoEventticketsValidator_WC_frontend',
			'phpObject', // name der js variable
 			[
 				'ajaxurl' => admin_url( 'admin-ajax.php' ),
 				'inputType' => $this->js_inputType,
 				'action' => $this->getPrefix().'_executeWCFrontend'
 			] // werte in der js variable
 			);
      	wp_enqueue_script('SasoEventticketsValidator_WC_frontend');
 	}

	public function executeWCFrontend() {
		// Do a nonce check
 		if( ! SASO_EVENTTICKETS::issetRPara('security') || ! wp_verify_nonce(SASO_EVENTTICKETS::getRequestPara('security'), 'woocommerce-cart') ) {
 			wp_send_json( ['nonce_fail' => 1] );
 			exit;
 		}
		if (!SASO_EVENTTICKETS::issetRPara('a')) return wp_send_json_error("a not provided");

		$ret = "";
		$justJSON = false;
		$a = trim(SASO_EVENTTICKETS::getRequestPara('a'));
		try {
			switch ($a) {
				case "updateSerialCodeToCartItem":
					$ret = $this->wc_frontend_updateSerialCodeToCartItem();
					break;
				default:
					throw new Exception(sprintf(/* translators: %s: name of called function */esc_html__('function "%s" not implemented', 'event-tickets-with-ticket-scanner'), $a));
			}
		} catch(Exception $e) {
			return wp_send_json_error (['msg'=>$e->getMessage()]);
		}
		if ($justJSON) return wp_send_json($ret);
		else return wp_send_json_success( $ret );

	}

	private function wc_frontend_updateSerialCodeToCartItem() {
		// Save the code to the cart meta
 		$cart = WC()->cart->cart_contents;
 		$cart_item_id = sanitize_key(SASO_EVENTTICKETS::getRequestPara('cart_item_id'));
 		$code = sanitize_key(SASO_EVENTTICKETS::getRequestPara('code'));
		$code = strtoupper($code);

 		$cart_item = $cart[$cart_item_id];
 		$cart_item[$this->meta_key_codelist_restriction_order_item] = $code;

 		WC()->cart->cart_contents[$cart_item_id] = $cart_item;
 		WC()->cart->set_session();

		$check_values = [];
		switch($this->check_code_for_cartitem($cart_item, $code)) {
			case 0:
				$check_values['isEmpty'] = true;
				break;
			case 1:
				$check_values['isValid'] = true;
				break;
			case 2:
				$check_values['isUsed'] = true;
				break;
			case 3: // not valid
			case 4: // no code list
			default:
				$check_values['notValid'] = true;
		}

 		wp_send_json( ['success' => 1, 'code'=>esc_attr(strtoupper($code)), 'check_values'=>$check_values] );
 		exit;
	}

	// speicher custom field aus dem cart - wird auch aufgerufen, wenn man den warenkorb aufruft und warenkorb updates macht
	function woocommerce_cart_updated( ) {
		if ( isset( $_POST['saso_eventtickets_request_name_per_ticket'] ) ) { // wenn der warenkorb aktualisiert wird und das feld gesendet wird
			$values = [];
			$cart = WC()->cart;
			foreach( $cart->get_cart() as $cart_item ) {
				if (isset($_POST['saso_eventtickets_request_name_per_ticket'][$cart_item['key']])) {
					$value = $_POST['saso_eventtickets_request_name_per_ticket'][$cart_item['key']];
					$values[$cart_item['key']] = $value;
				}
			}
			if (count($values) > 0) {
				WC()->session->set('saso_eventtickets_request_name_per_ticket',	$values);
			} else {
				WC()->session->__unset('saso_eventtickets_request_name_per_ticket');
			}
		}
	}

	// zeige eingabe maske für das Produkt, wenn es eine purchase restriction mit codes hat
	function woocommerce_after_cart_item_name( $cart_item, $cart_item_key ) {
 		$saso_eventtickets_list = get_post_meta($cart_item['product_id'], $this->meta_key_codelist_restriction, true);
 		if (!empty($saso_eventtickets_list)) {
	 		$code = isset( $cart_item[$this->meta_key_codelist_restriction_order_item] ) ? $cart_item[$this->meta_key_codelist_restriction_order_item] : '';
	 		$infoLabel = $this->getOptions()->getOptionValue('wcRestrictCartInfo');
	 		$fieldPlaceholder = $this->getOptions()->getOptionValue('wcRestrictCartFieldPlaceholder');
	 		$html = '<div><small>'.esc_attr($infoLabel).'<br></small>
	 					<input
	 						type="text"
							maxlength="140"
	 						placeholder="%s"
	 						data-input-type="%s"
	 						data-cart-item-id="%s"
	 						value="%s"
	 						class="input-text text" /></div>';
	 		printf(
	 			str_replace("\n", "", $html),
	 			esc_attr($fieldPlaceholder),
	 			esc_attr($this->js_inputType),
	 			esc_attr($cart_item_key),
	 			wc_clean($code)
	 		);
 		}

		$saso_eventtickets_request_name_per_ticket = get_post_meta($cart_item['product_id'], "saso_eventtickets_request_name_per_ticket", true) == "yes";
		if ($saso_eventtickets_request_name_per_ticket) {
			$anzahl = intval($cart_item["quantity"]);
			if ($anzahl > 0) {
				$valueArray = WC()->session->get("saso_eventtickets_request_name_per_ticket");

				$label = esc_attr(trim(get_post_meta($cart_item['product_id'], "saso_eventtickets_request_name_per_ticket_label", true)));
				if (empty($label)) $label = "Name for the ticket {count}:";
				for ($a=0;$a<$anzahl;$a++) {
					$value = "";
					if ($valueArray != null && isset($valueArray[$cart_item_key]) && isset($valueArray[$cart_item_key][$a])) {
						$value = trim($valueArray[$cart_item_key][$a]);
					}
					$html = '<div><small>'.str_replace("{count}", $a+1, $label).'<br></small>
							<input type="text" data-input-type="text"
								name="saso_eventtickets_request_name_per_ticket[%s][]"
								value="%s"
								class="input-text text" /></div>';
					printf(
						str_replace("\n", "", $html),
						esc_attr($cart_item_key),
						esc_attr($value)
					);
				}
			}
		}
	}

	private function check_code_for_cartitem($cart_item, $code) {
		$ret = 0; // empty
		if (!empty($code)) {
	        // Check cart item for defined product Ids and applied coupon
			$saso_eventtickets_list_id = get_post_meta($cart_item['product_id'], $this->meta_key_codelist_restriction, true);
			if (!empty($saso_eventtickets_list_id)) {
				try {
					$codeObj = $this->getCore()->retrieveCodeByCode($code);
					if ($codeObj['aktiv'] != 1) throw new Exception("not valid");
					if ($saso_eventtickets_list_id != "0" && $codeObj['list_id'] != $saso_eventtickets_list_id) throw new Exception("from wrong list");
					if ($this->getFrontend()->isUsed($codeObj)) {
						return 2; // isUsed
					} else {
						return 1; // ok
					}
				} catch(Exception $e) {
					return 3; // notValid
				}
			} else {
				return 4; // code has no code list -> notValid
			}
		}
		return $ret;
	}

	function woocommerce_check_cart_items() {
		$cart_items = WC()->cart->get_cart();
		if ($this->containsProductsWithRestrictions()) {
		    // loop through cart items and check if a restriction is set
		    foreach($cart_items as $cart_item ) {

				$code = isset( $cart_item[$this->meta_key_codelist_restriction_order_item] ) ? $cart_item[$this->meta_key_codelist_restriction_order_item] : '';
				$code = strtoupper($code);
				switch($this->check_code_for_cartitem($cart_item, $code)) {
					case 0:
						wc_add_notice( sprintf(/* translators: %s: name of product */ __('The product "%s" requires a restriction code for checkout.', 'event-tickets-with-ticket-scanner'), esc_html($cart_item['data']->get_name()) ), 'error' );
						break;
					case 1: // valid
						break;
					case 2:
						wc_add_notice( sprintf(/* translators: 1: restriction code number 2: name of product */ __('The restriction code "%1$s" for product "%2$s" is already used.', 'event-tickets-with-ticket-scanner'), esc_attr($code), esc_html($cart_item['data']->get_name()) ), 'error' );
						break;
					case 3: // not valid
					case 4: // no code list
					default:
						wc_add_notice( sprintf(/* translators: 1: restriction code number 2: name of product */ __('The restriction code "%1$s" for product "%2$s" is not valid.', 'event-tickets-with-ticket-scanner'), esc_attr($code), esc_html($cart_item['data']->get_name()) ), 'error' );
				}

		    } // end loop cart item
	 	} // end if containsProductsWithRestrictions

		// check if ticket name value is needed and mandatory
		$valueArray = WC()->session->get("saso_eventtickets_request_name_per_ticket");
		foreach($cart_items as $cart_item ) {
			$saso_eventtickets_request_name_per_ticket = get_post_meta($cart_item['product_id'], "saso_eventtickets_request_name_per_ticket", true) == "yes";
			if ($saso_eventtickets_request_name_per_ticket) {
				$saso_eventtickets_request_name_per_ticket_mandatory = get_post_meta($cart_item['product_id'], "saso_eventtickets_request_name_per_ticket_mandatory", true) == "yes";
				if ($saso_eventtickets_request_name_per_ticket_mandatory) {
						$anzahl = intval($cart_item["quantity"]);
						if ($anzahl > 0) {
							for ($a=0;$a<$anzahl;$a++) {
								$value = "";
								if ($valueArray != null && isset($valueArray[$cart_item['key']]) && isset($valueArray[$cart_item['key']][$a])) {
									$value = trim($valueArray[$cart_item['key']][$a]);
								}
								if (empty($value)) {
									wc_add_notice( sprintf(/* translators: %s: name of product */ __('The product "%s" requires a value for checkout.', 'event-tickets-with-ticket-scanner'), esc_html($cart_item['data']->get_name()) ), 'error' );
									break;
								}
							}
						}
				}
			}
		}
	}

	private function setTicketNamesToOrderItem($item, $cart_item_key) {
		if (WC() != null && WC()->session != null) {
			$valueArray = WC()->session->get("saso_eventtickets_request_name_per_ticket");
			if ($valueArray != null && isset($valueArray[$cart_item_key]) && isset($valueArray[$cart_item_key])) {
				$value = $valueArray[$cart_item_key];
				$item->update_meta_data("saso_eventtickets_request_name_per_ticket", $value);
				//wc_delete_order_item_meta($item_id, "saso_eventtickets_request_name_per_ticket");
				//wc_add_order_item_meta($item_id, "saso_eventtickets_request_name_per_ticket", $value);
			}
		}
	}

	//The next step is to save the data to the order when it is processed to be paid
	function woocommerce_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {

		$this->setTicketNamesToOrderItem($item, $cart_item_key);

		if ( empty( $values[$this->meta_key_codelist_restriction_order_item] ) ) {
			return;
		}

		// speicher purchase restriction code zum order_item
		$code = $values[$this->meta_key_codelist_restriction_order_item];
		$item->add_meta_data( $this->meta_key_codelist_restriction_order_item, $code );

		$codeObj = null;
		try {
			$codeObj = $this->getCore()->retrieveCodeByCode($code);
		} catch(Exception $e) {
			if(isset($_GET['VollstartValidatorDebug'])) {
				var_dump($e);
			}
		}

		// set as used
		if ($this->getOptions()->isOptionCheckboxActive('oneTimeUseOfRegisterCode')) {
			try {
				if ($codeObj == null) {
					$codeObj = $this->getCore()->retrieveCodeByCode($code);
				}
				$rc_v = $this->getOptions()->getOptionValue('wcRestrictOneTimeUsage');
				if ($rc_v == 1) {
					$codeObj = $this->getFrontend()->markAsUsed($codeObj);
				} else if ($rc_v == 2) {
					$codeObj = $this->getFrontend()->markAsUsed($codeObj, true);
				}
			} catch(Exception $e){
				if(isset($_GET['VollstartValidatorDebug'])) {
					var_dump($e);
				}
			}
		}

		$this->getCore()->triggerWebhooks(16, $codeObj);
	}

	public function woocommerce_single_product_summary() {
		if ($this->getOptions()->isOptionCheckboxActive('wcTicketDisplayDateOnPrdDetail')) {
			global $product;
			$date_str = sasoEventtickets_Ticket::displayTicketDateAsString($product);
			if (!empty($date_str)) echo "<br>".$date_str;
		}
	}

	function woocommerce_checkout_update_order_meta($order_id, $address_data) {
		if ($this->containsProductsWithRestrictions()) {
			$order = wc_get_order( $order_id );
			foreach ( $order->get_items() as $item_id => $item ) {
				$code = wc_get_order_item_meta($item_id , $this->meta_key_codelist_restriction_order_item, true);
				// speicher orderid und order item id zum code
				if (!empty($code)) {
					$product_id = $item->get_product_id();
					$order_id = $order->get_id();
					$list_id = get_post_meta($product_id, $this->meta_key_codelist_restriction, true);
					$this->getAdmin()->addRetrictionCodeToOrder($code, $list_id, $order_id, $product_id, $item_id);
				}
			}
		}
	}

	function woocommerce_delete_order_item($item_get_id) {
		$code = wc_get_order_item_meta($item_get_id , $this->meta_key_codelist_restriction_order_item, true);
		if (!empty($code)) {
			$data = ['code'=>$code];
			// remove used info
			$this->getAdmin()->removeUsedInformationFromCode($data);
			$this->getAdmin()->removeWoocommerceOrderInfoFromCode($data);
			$this->getAdmin()->removeWoocommerceRstrPurchaseInfoFromCode($data);
			// nur zur sicherheit
			$this->deleteRestrictionEntryOnOrderItem($item_get_id);
			// add note to order
			$order_id = wc_get_order_id_by_order_item_id($item_get_id);
			$order = wc_get_order( $order_id );
			$order->add_order_note( sprintf(/* translators: %s: restriction code number */esc_html__('Order item deleted. Free restriction code: %s for next usage.', 'event-tickets-with-ticket-scanner'), esc_attr($code)) );
		}
		if ($this->getOptions()->isOptionCheckboxActive('wcRestrictFreeCodeByOrderRefund')) {
			$code_value = wc_get_order_item_meta($item_get_id , "_saso_eventtickets_product_code", true);
			if (!empty($code_value)) {
				$codes = explode(",", $code_value);
				foreach($codes as $code) {
					$code = trim($code);
					if (!empty($code)) {
						$data = ['code'=>$code];
						// remove used info
						$this->getAdmin()->removeUsedInformationFromCode($data);
						$this->getAdmin()->removeWoocommerceOrderInfoFromCode($data);
						$this->getAdmin()->removeWoocommerceRstrPurchaseInfoFromCode($data);
						// nur zur sicherheit
						$this->deleteCodesEntryOnOrderItem($item_get_id);
						// add note to order
						$order_id = wc_get_order_id_by_order_item_id($item_get_id);
						$order = wc_get_order( $order_id );
						$order->add_order_note( sprintf(/* translators: %s: ticket number */esc_html__('Order item deleted. Free ticket number: %s for next usage.', 'event-tickets-with-ticket-scanner'), esc_attr($code)) );
					}
				}
			}
		}
	}

	function woocommerce_delete_order( $id ) {
		$order = wc_get_order( $id );
		foreach ( $order->get_items() as $item_id => $item ) {
			$this->woocommerce_delete_order_item($item_id);
		}
	}

	function woocommerce_delete_order_refund( $id ) {
		$order = wc_get_order( $id );
		foreach ( $order->get_items() as $item_id => $item ) {
			$this->woocommerce_delete_order_item($item_id);
		}
	}

	function deleteCodesEntryOnOrderItem($item_id) {
		wc_delete_order_item_meta( $item_id, '_saso_eventtickets_product_code' );
		wc_delete_order_item_meta( $item_id, '_saso_eventticket_code_list' );
	}
	function deleteRestrictionEntryOnOrderItem($item_id) {
		wc_delete_order_item_meta( $item_id, $this->meta_key_codelist_restriction_order_item );
	}
}
?>