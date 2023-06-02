<?php
include(plugin_dir_path(__FILE__)."init_file.php");
class sasoEventtickets_TicketBadge {
    private $MAIN;

    private $codeObj;

    private $html;
    private $size_width;
    private $size_height;

    private $filepath = "";

	public static function Instance($html="") {
		static $inst = null;
        if ($inst === null) {
            $inst = new sasoEventtickets_TicketBadge($html);
        }
        return $inst;
	}

    public function __construct($html="") {
		global $sasoEventtickets;
		$this->MAIN = $sasoEventtickets;
    }

    private function setTemplate($html) {
        $this->html = trim($html);
        return $this;
    }

    public function getTemplate() {
        if (empty($this->html)) {
            return $this->getDefaultTemplate();
        }
        return $this->html;
    }

    public function getDefaultTemplate() {
        $html = '
        <b>{OPTIONS.wcTicketHeading}</b>
        <h2>{PRODUCT.name}</h2>
        <b>{TICKET.PRODUCT.ticket_start_date}</b>
        <h4>{ORDER.billing.first_name} {ORDER.billing.last_name}</h4>
        <br><center>{QRCODE_INLINE}</center>
        <br>{TICKET.code_display}
        <br>{TICKET.meta.wc_ticket._public_ticket_id}
        ';
        return $html;
    }

    public function getReplacementTagsExplanation() {
        $text = "Values from the <b>option</b> area can be referenced with the mentioned tag next to the label of the option.<br>
        {QRCODE_INLINE} = add the public ticket number as a QR Code.<br>
        <b>TICKET</b><ul>
        <li>{TICKET.id}</li>
        <li>{TICKET.time}</li>
        <li>{TICKET.code}</li>
        <li>{TICKET.code_display}</li>
        <li>{TICKET.cvv}</li>
        </ul>
        <b>TICKET meta</b>
        <ul>";
        $metaObj = $this->MAIN->getCore()->getMetaObject();
        foreach($metaObj as $key => $value) {
            $name = "TICKET.meta.".$key;
            if (is_array($value)) {
                foreach($value as $k => $v) {
                    $text .= '<li>{'.$name.".".$k."}</li>";
                }
            }
        }
        $text = "
        <b>Order</b>
        <ul>
        <li>{ORDER.id}</li>
        <li>{ORDER.formatted_order_total}</li>
        <li>{ORDER.cart_tax}</li>
        <li>{ORDER.currency}</li>
        <li>{ORDER.item_count}</li>
        <li>{ORDER.item_total}</li>
        <li>{ORDER.coupon_codes}</li>
        <li>{ORDER.shipping_method}</li>
        <li>{ORDER.shipping_to_display}</li>
        <li>{ORDER.date.created}</li>
        <li>{ORDER.date.paid}</li>
        <li>{ORDER.date.completed}</li>
        <li>{ORDER.customer_id}</li>
        <li>{ORDER.user_id}</li>
        <li>{ORDER.customer_ip_address}</li>
        <li>{ORDER.customer_note}</li>
        <li>{ORDER.billing.first_name}</li>
        <li>{ORDER.billing.last_name}</li>
        <li>{ORDER.billing.company}</li>
        <li>{ORDER.billing.address_1}</li>
        <li>{ORDER.billing.address_2}</li>
        <li>{ORDER.billing.city}</li>
        <li>{ORDER.billing.state}</li>
        <li>{ORDER.billing.postcode}</li>
        <li>{ORDER.billing.country}</li>
        <li>{ORDER.billing.email}</li>
        <li>{ORDER.billing.phone}</li>
        <li>{ORDER.shipping_address}</li>
        <li>{ORDER.formatted_billing_full_name</li>
        <li>{ORDER.formatted_shipping_full_name}</li>
        <li>{ORDER.formatted_billing_address}</li>
        <li>{ORDER.formatted_shipping_address}</li>
        <li>{ORDER.payment_method}</li>
        <li>{ORDER.payment_method_title}</li>
        <li>{ORDER.transaction_id}</li>
        <li>{ORDER.status}</li>
        </ul>
        <p>If you need a meta value of the order, like an additional field. Get the field name and create the code like this: {ORDER.get_meta.YOURFIELDNAME}.<br>
        This will call the get_meta('YOURFIELDNAME').</p>
        <b>Product</b>
        <ul>
        <li>{PRODUCT.id}</li>
        <li>{PRODUCT.name}</li>
        <li>{PRODUCT.slug}</li>
        <li>{PRODUCT.date.created}</li>
        <li>{PRODUCT.date.modified}</li>
        <li>{PRODUCT.status}</li>
        <li>{PRODUCT.description}</li>
        <li>{PRODUCT.short_description}</li>
        <li>{PRODUCT.sku}</li>
        <li>{PRODUCT.price}</li>
        <li>{PRODUCT.regular_price}</li>
        <li>{PRODUCT.sale_price}</li>
        <li>{PRODUCT.stock_quantity}</li>
        <li>{PRODUCT.categories}</li>
        <li>{PRODUCT.average_rating}</li>
        </ul>
        ";
        return $text;
    }

    public function setHTMLAndRender($codeObj, $html_to_render, $width=0, $height=0, $filemode="I") {
        $this->setHTMLToRender($codeObj, $html_to_render, $width, $height);
        return $this->renderPDF($filemode);
    }

    public function downloadPDFTicketBadge($codeObj) {
		$html = $this->MAIN->getOptions()->getOptionValue("wcTicketBadgeText", "");
		$w = $this->MAIN->getOptions()->getOptionValue("wcTicketBadgeSizeWidth", 0);
		$h = $this->MAIN->getOptions()->getOptionValue("wcTicketBadgeSizeHeight", 0);
		$this->setHTMLAndRender($codeObj, $html, $w, $h);
        exit;
    }
    public function getPDFTicketBadgeFilepath($codeObj, $filepath) {
        $this->filepath = trim($filepath);
		$html = $this->MAIN->getOptions()->getOptionValue("wcTicketBadgeText", "");
		$w = $this->MAIN->getOptions()->getOptionValue("wcTicketBadgeSizeWidth", 0);
		$h = $this->MAIN->getOptions()->getOptionValue("wcTicketBadgeSizeHeight", 0);
		return $this->setHTMLAndRender($codeObj, $html, $w, $h, "F");
    }

    private function setHTMLToRender($codeObj, $html_to_render, $width=0, $height=0) {
        $this->codeObj = $codeObj;
        $this->setTemplate($html_to_render);
        $this->size_width = intval($width);
        $this->size_height = intval($height);
    }

    private function renderPDF($filemode="I") {
		$metaObj = $this->MAIN->getCore()->encodeMetaValuesAndFillObject($this->codeObj['meta'], $this->codeObj);
		$ticket_id = $this->MAIN->getCore()->getTicketId($this->codeObj, $metaObj);

        $width = $this->size_width > 0 ? $this->size_width : 80;
        $height = $this->size_height > 0 ? $this->size_height : 120;

        $html = $this->replacePlaceholder();
		if (!class_exists('sasoEventtickets_PDF')) {
			require_once("sasoEventtickets_PDF.php");
		}
		$pdf = new sasoEventtickets_PDF();
		$pdf->setFilemode($filemode);

        $pdf->setFilepath($this->filepath);
		$filename = "ticketbadge_".$this->codeObj['order_id']."_".$ticket_id.".pdf";
		$pdf->setFilename($filename);

        $teile = explode("{QRCODE_INLINE}", $html);
		$pdf->addPart($teile[0]);
        if (strpos(" ".$html, "{QRCODE_INLINE}")) {
		    $pdf->addPart("{QRCODE_INLINE}");
        }
		if (count($teile) > 1) {
			$teile[0] = "";
			$pdf->addPart(trim(join("", $teile)));
		}
		$pdf->setQRCodeContent(["text"=>$ticket_id]);
		$pdf->setSize($width, $height);
		$pdf->render();
        if ($pdf->getFilemode() == "F") {
			return $pdf->getFullFilePath();
		} else {
			exit;
		}
    }

    private function replacePlaceholder() {
        $codeObj = $this->codeObj;

        $html = " ".trim($this->getTemplate());

        $date_time_format = $this->MAIN->getOptions()->getOptionDateTimeFormat();
        $is_expired = $this->MAIN->getCore()->checkCodeExpired($codeObj);
        $date_time_format = $this->MAIN->getOptions()->getOptionDateTimeFormat();
        $date_format = $this->MAIN->getOptions()->getOptionDateFormat();
        $time_format = $this->MAIN->getOptions()->getOptionTimeFormat();

        $matches = [];
        if (preg_match_all('/\{OPTIONS\..*?\}/', $html, $matches)) {
            foreach($matches[0] as $item) {
                $key = substr(substr($item, 9), 0, -1);
                $value = $this->MAIN->getOptions()->getOptionValue($key, "");
                $html = str_replace($item, $value, $html);
            }
        }

        $metaObj = null;
        $product = null;

        if (strpos($html, "{SITE_URL}")) $html = str_replace("{SITE_URL}", site_url(), $html);
        if (strpos($html, "{TICKET.")) {
            $metaObj = $this->MAIN->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
            $codeObj['meta'] = $metaObj;

            $product_id = intval($metaObj['woocommerce']['product_id']);
            $product = wc_get_product( $product_id );
            $is_variation = $product->get_type() == "variation" ? true : false;
            $product_parent = $product;
            $product_parent_id = $product->get_parent_id();
            $saso_eventtickets_is_date_for_all_variants = true;
            if ($is_variation && $product_parent_id > 0) {
                $product_parent = wc_get_product( $product_parent_id );
                $saso_eventtickets_is_date_for_all_variants = get_post_meta( $product_parent->get_id(), 'saso_eventtickets_is_date_for_all_variants', true ) == "yes" ? true : false;
            }
            $product_for_dates = $product_parent;
            if (!$saso_eventtickets_is_date_for_all_variants) $product_for_dates = $product; // unter Umständen die Variante
            $product_dates = $this->MAIN->getTicketHandler()->calcDateStringAllowedRedeemFrom($product_for_dates->get_id());

            $matches = [];
            if (preg_match_all('/\{TICKET\.PRODUCT\..*?\}/', $html, $matches)) {
                foreach($matches[0] as $item) {
                    $key = substr(substr($item, 16), 0, -1);
                    $value = $this->getValueOfArrayByPlaceholder($key, $product_dates);
                    if ($key == "ticket_start_date" || $key == "ticket_end_date") {
                        $value = @date($date_format, strtotime($value));
                    }
                    if ($key == "ticket_start_time" || $key == "ticket_end_time") {
                        $value = @date($date_format, strtotime($product_dates['ticket_start_date']." ".$value));
                    }
                    $html = str_replace($item, wp_kses_post($value), $html);
                }
            }

            $matches = [];
            if (preg_match_all('/\{TICKET\..*?\}/', $html, $matches)) {
                foreach($matches[0] as $item) {
                    $key = substr(substr($item, 8), 0, -1);
                    $value = $this->getValueOfArrayByPlaceholder($key, $codeObj);
                    $html = str_replace($item, wp_kses_post($value), $html);
                }
            }
        }
        if (strpos($html, "{ORDER.")) {
            $order_id = intval($codeObj['order_id']);
            $order = wc_get_order($order_id);
            if ($order != null) {
                $matches = [];
                if (preg_match_all('/\{ORDER\..*?\}/', $html, $matches)) {
                    foreach($matches[0] as $item) {
                        $key = substr(substr($item, 7), 0, -1);
                        if (is_array($order) && isset($order[$key])) {
                            $value = $this->getValueOfArrayByPlaceholder($key, $order);
                        } else {
                            $value = $this->getValueOfWCObject($key, $order);
                        }
                        if ($key == "date.paid" || $key == "date.completed" || $key == "date.created") {
                            $value = date($date_time_format, strtotime($value));
                        }
                        $html = str_replace($item, wp_kses_post($value), $html);
                    }
                }
            }
        }
        if (strpos($html, "{PRODUCT.")) {
            if ($product == null) {
                if ($metaObj == null) {
                    $metaObj = $this->MAIN->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
                }
                $product_id = intval($metaObj['woocommerce']['product_id']);
                $product = wc_get_product( $product_id );
            }

            if ($product != null) {
                $is_variation = $product->get_type() == "variation" ? true : false;
                $product_parent = $product;
                $product_parent_id = $product->get_parent_id();
                $saso_eventtickets_is_date_for_all_variants = true;
                if ($is_variation && $product_parent_id > 0) {
                    $product_parent = wc_get_product( $product_parent_id );
                    $saso_eventtickets_is_date_for_all_variants = get_post_meta( $product_parent->get_id(), 'saso_eventtickets_is_date_for_all_variants', true ) == "yes" ? true : false;
                }
                $product_for_dates = $product_parent;
                if (!$saso_eventtickets_is_date_for_all_variants) $product_for_dates = $product; // unter Umständen die Variante

                $matches = [];
                if (preg_match_all('/\{PRODUCT\..*?\}/', $html, $matches)) {
                    foreach($matches[0] as $item) {
                        $key = substr(substr($item, 9), 0, -1);
                        $value = $this->getValueOfWCObject($key, $product);
                        if ($key == "date.modified" || $key == "date.created") {
                            $value = date($date_time_format, strtotime($value));
                        }
                        $html = str_replace($item, wp_kses_post($value), $html);
                    }
                }
            }
        }

        return trim($html);
    }

    private function getValueOfArrayByPlaceholder($key, $object) {
        $value = "";
        $parts = explode(".", $key);
        $obj = $object;
        foreach($parts as $part) {
            if (isset($obj[$part])) {
                $obj = $obj[$part];
                $value = $obj;
            }
        }
        return $value;
    }

    private function getValueOfWCObject($key, $object) {
        $value = "";
        $method = "get_".str_replace(".", "_", $key);
        //if (method_exists($object, $method) && is_callable($object, $method)) {
        if (method_exists($object, $method)) {
            $value = $object->$method();
            if (is_array($value)) {
                $value = join(", ", $value);
            }
        } else {
            $teile = explode(".", $key);
            if (count($teile) > 1) { // min 2 elemente
                if ($teile[0] == "get_meta") {
                    $value = $object->get_meta($teile[1]);
                }
                /*
                $method = "get_".$teile[0];
                array_shift($teile);
                if (method_exists($object, $method)) {
                    $value = $object->get_meta("_billing_flexfbil");
                    $v = $object->$method();
                    if (is_array($v)) {
                        $value = $this->getValueOfArrayByPlaceholder(join('.', $teile), $v);
                    }
                }
                */
            }
        }
        return $value;
    }

}
?>