<?php
include(plugin_dir_path(__FILE__)."init_file.php");
class sasoEventtickets_Core {
	private $MAIN;

	private $_CACHE_list = [];

	public $ticket_url_path_part = "ticket";

	public function __construct($MAIN) {
		if ($MAIN->getDB() == null) throw new Exception("#9999 DB needed");
		$this->MAIN = $MAIN;
	}

	private function getBase() {
		return $this->MAIN->getBase();
	}
	private function getDB() {
		return $this->MAIN->getDB();
	}

	public function clearCode($code) {
		return str_replace(" ","",str_replace(":","",str_replace("-", "", $code)));
	}

	public function getListById($id) {
		$sql = "select * from ".$this->getDB()->getTabelle("lists")." where id = ".intval($id);
		$ret = $this->getDB()->_db_datenholen($sql);
		if (count($ret) == 0) throw new Exception("#9232 not found");
		return $ret[0];
	}

	public function getCodesByRegUserId($user_id) {
		$user_id = intval($user_id);
		if ($user_id <= 0) return [];
		$sql = "select a.* from ".$this->getDB()->getTabelle("codes")." a where user_id = ".$user_id;
		return $this->getDB()->_db_datenholen($sql);
	}

	public function retrieveCodeByCode($code, $mitListe=false) {
		$code = $this->clearCode($code);
		$code = $this->getDB()->reinigen_in($code);
		if (empty($code)) throw new Exception("#203 code empty");
		if ($mitListe) {
			$sql = "select a.*, b.name as list_name from ".$this->getDB()->getTabelle("codes")." a
					left join ".$this->getDB()->getTabelle("lists")." b on a.list_id = b.id
					where code = '".$code."'";
		} else {
			$sql = "select a.* from ".$this->getDB()->getTabelle("codes")." a where code = '".$code."'";
		}
		$ret = $this->getDB()->_db_datenholen($sql);
		if (count($ret) == 0) throw new Exception("#204 code not found");
		return $ret[0];
	}

	public function checkCodesSize() {
		if ($this->isCodeSizeExceeded()) throw new Exception("#208 too many codes. Unlimited codes only with premium");
	}
	public function isCodeSizeExceeded() {
		return $this->getBase()->premiumCheck_isAllowedAddingCode($this->getDB()->getCodesSize()) == false;
	}

	public function retrieveCodeById($id, $mitListe=false) {
		$id = intval($id);
		if ($id == 0) throw new Exception("#220 id of code empty");
		if ($mitListe) {
			$sql = "select a.*, b.name as list_name from ".$this->getDB()->getTabelle("codes")." a
					left join ".$this->getDB()->getTabelle("lists")." b on a.list_id = b.id
					where a.id = ".$id;
		} else {
			$sql = "select a.* from ".$this->getDB()->getTabelle("codes")." a where a.id = ".$id;
		}
		$ret = $this->getDB()->_db_datenholen($sql);
		if (count($ret) == 0) throw new Exception("#221 code not found");
		return $ret[0];
	}

	public function getMetaObject() {
		$metaObj = [
			'validation'=>['first_success'=>'', 'first_ip'=>'', 'last_success'=>'', 'last_ip'=>'']
			,'user'=>[
				'reg_approved'=>0,
				'reg_request'=>'',
				'value'=>'',
				'reg_ip'=>'',
				'reg_userid'=>0, '
				_reg_username'=>'']
			,'used'=>[
				'reg_ip'=>'',
				'reg_request'=>'',
				'reg_userid'=>0,
				'_reg_username'=>'']
			,'confirmedCount'=>0
			,'woocommerce'=>[
					'order_id'=>0, 'product_id'=>0,
					'creation_date'=>0, 'item_id'=>0,
					'user_id'=>0] // product code for sale
			,'wc_rp'=>['order_id'=>0, 'product_id'=>0, 'creation_date'=>0, 'item_id'=>0] // restriction purchase used
			,'wc_ticket'=>[
					'is_ticket'=>0,
					'ip'=>'',
					'userid'=>0,
					'_username'=>'',
					'redeemed_date'=>'',
					'redeemed_by_admin'=>0,
					'set_by_admin'=>0,
					'set_by_admin_date'=>'',
					'idcode'=>'',
					'_url'=>'',
					'_public_ticket_id'=>'',
					'stats_redeemed'=>[],
					'name_per_ticket'=>''
					] // ticket purchase ; stats_redeemed is only used if the ticket can be redeemed more than once
			];

		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'getMetaObject')) {
			$metaObj = $this->MAIN->getPremiumFunctions()->getMetaObject($metaObj);
		}

		return $metaObj;
	}
	public function encodeMetaValuesAndFillObject($metaValuesString, $codeObj=null) {
		$metaObj = $this->getMetaObject();
		if (!empty($metaValuesString)) {
			$metaObj = array_replace_recursive($metaObj, json_decode($metaValuesString, true));
		}
		if (isset($metaObj['user']['reg_userid']) && $metaObj['user']['reg_userid'] > 0) {
			$u = get_userdata($metaObj['user']['reg_userid']);
			if ($u === false) {
				$metaObj['user']['_reg_username'] = esc_html__("USERID DO NOT EXISTS", 'event-tickets-with-ticket-scanner');
			} else {
				$metaObj['user']['_reg_username'] = $u->first_name." ".$u->last_name." (".$u->user_login.")";
			}
		} else {
			$metaObj['user']['_reg_username'] = "";
		}
		if (isset($metaObj['used']['reg_userid']) && $metaObj['used']['reg_userid'] > 0) {
			$u = get_userdata($metaObj['used']['reg_userid']);
			if ($u === false) {
				$metaObj['used']['_reg_username'] = esc_html__("USERID DO NOT EXISTS", 'event-tickets-with-ticket-scanner');
			} else {
				$metaObj['used']['_reg_username'] = $u->first_name." ".$u->last_name." (".$u->user_login.")";
			}
		} else {
			$metaObj['used']['_reg_username'] = "";
		}
		if (isset($metaObj['wc_ticket']['userid']) && $metaObj['wc_ticket']['userid'] > 0) {
			$u = get_userdata($metaObj['wc_ticket']['userid']);
			if ($u === false) {
				$metaObj['wc_ticket']['_username'] = esc_html__("USERID DO NOT EXISTS", 'event-tickets-with-ticket-scanner');
			} else {
				$metaObj['wc_ticket']['_username'] = $u->first_name." ".$u->last_name." (".$u->user_login.")";
			}
		} else {
			$metaObj['wc_ticket']['_username'] = "";
		}
		if (isset($metaObj['wc_ticket']['redeemed_by_admin']) && $metaObj['wc_ticket']['redeemed_by_admin'] > 0) {
			$u = get_userdata($metaObj['wc_ticket']['redeemed_by_admin']);
			if ($u === false) {
				$metaObj['wc_ticket']['_redeemed_by_admin_username'] = esc_html__("USERID DO NOT EXISTS", 'event-tickets-with-ticket-scanner');
			} else {
				$metaObj['wc_ticket']['_redeemed_by_admin_username'] = $u->first_name." ".$u->last_name." (".$u->user_login.")";
			}
		} else {
			$metaObj['wc_ticket']['_redeemed_by_admin_username'] = "";
		}
		if (isset($metaObj['wc_ticket']['set_by_admin']) && $metaObj['wc_ticket']['set_by_admin'] > 0) {
			$u = get_userdata($metaObj['wc_ticket']['set_by_admin']);
			if ($u === false) {
				$metaObj['wc_ticket']['_set_by_admin_username'] = esc_html__("USERID DO NOT EXISTS", 'event-tickets-with-ticket-scanner');
			} else {
				$metaObj['wc_ticket']['_set_by_admin_username'] = $u->first_name." ".$u->last_name." (".$u->user_login.")";
			}
		} else {
			$metaObj['wc_ticket']['_set_by_admin_username'] = "";
		}
		if ($metaObj['wc_ticket']['is_ticket'] == 1 && $codeObj != null && is_array($codeObj)) {
			if (empty($metaObj['wc_ticket']['idcode']))	$metaObj['wc_ticket']['idcode'] = crc32($codeObj['id']."-".time());
			if (empty($metaObj['wc_ticket']['_public_ticket_id'])) $metaObj['wc_ticket']['_public_ticket_id'] = $this->getTicketId($codeObj, $metaObj);
			$metaObj['wc_ticket']['_url'] = $this->getTicketURL($codeObj, $metaObj);
		}

		// update validation fields
		if ($metaObj['confirmedCount'] > 0) {
			if (empty($metaObj['validation']['first_success'])) {
				// check used wert
				if ( !empty($metaObj['used']['reg_request']) ) {
					if (empty($metaObj['validation']['first_success'])) $metaObj['validation']['first_success'] = $metaObj['used']['reg_request'];
					if (empty($metaObj['validation']['first_ip'])) $metaObj['validation']['first_ip'] = $metaObj['used']['reg_ip'];
				} elseif (!empty($metaObj['user']['reg_request'])) { // check user reg wert
					if (empty($metaObj['validation']['first_success'])) $metaObj['validation']['first_success'] = $metaObj['user']['reg_request'];
					if (empty($metaObj['validation']['first_ip'])) $metaObj['validation']['first_ip'] = $metaObj['user']['reg_ip'];
				}
			}
		}

		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'encodeMetaValuesAndFillObject')) {
			$metaObj = $this->MAIN->getPremiumFunctions()->encodeMetaValuesAndFillObject($metaObj, $codeObj);
		}
		return $metaObj;
	}

	public function getMetaObjectKeyList($metaObj, $prefix="META_") {
		$keys = [];
		$prefix = strtoupper(trim($prefix));
		foreach(array_keys($metaObj) as $key) {
			$tag = $prefix.strtoupper($key);
			if (is_array($metaObj[$key])) {
				$_keys = $this->getMetaObjectKeyList($metaObj[$key], $tag."_");
				$keys = array_merge($keys, $_keys);
			} else {
				$keys[] = $tag;
			}
		}
		return $keys;
	}

	public function getMetaObjectAllowedReplacementTags() {
		$tags = [];
		$allowed_tags = [
			"USER_VALUE"=>esc_html__("Value given by the user during the code registration.", 'event-tickets-with-ticket-scanner'),
			"USER_REG_IP"=>esc_html__("IP address of the user, register to a code.", 'event-tickets-with-ticket-scanner'),
			"USER_REG_USERID"=>esc_html__("User id of the registered user to a code. Default will be 0.", 'event-tickets-with-ticket-scanner'),
			"USED_REG_IP"=>esc_html__("IP addres of the user that used the code.", 'event-tickets-with-ticket-scanner'),
			"CONFIRMEDCOUNT"=>esc_html__("Amount of how many times the code was validated successfully.", 'event-tickets-with-ticket-scanner'),
			"WOOCOMMERCE_ORDER_ID"=>esc_html__("WooCommerce order id assigned to the code.", 'event-tickets-with-ticket-scanner'),
			"WOOCOMMERCE_PRODUCT_ID"=>esc_html__("WooCommerce product id assigned to the code.", 'event-tickets-with-ticket-scanner'),
			"WOOCOMMERCE_CREATION_DATE"=>esc_html__("Creation date of the WooCommerce sales date.", 'event-tickets-with-ticket-scanner'),
			"WOOCOMMERCE_USER_ID"=>esc_html__("User id of the WooCommerce sales.", 'event-tickets-with-ticket-scanner'),
			"WC_RP_ORDER_ID"=>esc_html__("WooCommerce order id, that was purchases using this code as an allowance to purchase a restricted product.", 'event-tickets-with-ticket-scanner'),
			"WC_RP_PRODUCT_ID"=>esc_html__("WooCommerce product id that was restricted with this code.", 'event-tickets-with-ticket-scanner'),
			"WC_RP_CREATION_DATE"=>esc_html__("Creation date of the WooCommerce purchase using the allowance code.", 'event-tickets-with-ticket-scanner')
		];
		foreach($allowed_tags as $key => $value) {
			$tags[] = ["key"=>$key, "label"=>$value];
		}
		return $tags;
	}

	// returns a default meta object for a code list
	public function getMetaObjectList() {
		$metaObj = [
			'desc'=>'',
			'redirect'=>['url'=>''],
			'formatter'=>[
				'active'=>1,
				'format'=>'' // JSON mit den Format Werten
				]
		];
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'getMetaObjectList')) {
			$metaObj = $this->MAIN->getPremiumFunctions()->getMetaObjectList($metaObj);
		}
		return $metaObj;
	}

	public function encodeMetaValuesAndFillObjectList($metaValuesString) {
		$metaObj = $this->getMetaObjectList();
		if (!empty($metaValuesString)) {
			$metaObj = array_replace_recursive($metaObj, json_decode($metaValuesString, true));
		}
		return $metaObj;
	}

	public function json_encode_with_error_handling($object) {
		$json = json_encode($object, JSON_NUMERIC_CHECK);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new Exception(json_last_error_msg());
		}
		return $json;
	}

	public function getRealIpAddr() {
	    if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
	    {
	      $ip=sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
	    }
	    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
	    {
	      $ip=sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
	    }
	    else
	    {
	      $ip=sanitize_text_field($_SERVER['REMOTE_ADDR']);
	    }
	    return $ip;
	}

	public function triggerWebhooks($status, $codeObj) {
		$options = $this->MAIN->getOptions();
		if ($options->isOptionCheckboxActive('webhooksActiv')) {
			$optionname = "";
			switch($status) {
				case 0:
					$optionname = "webhookURLinvalid";
					break;
				case 1:
					$optionname = "webhookURLvalid";
					break;
				case 2:
					$optionname = "webhookURLinactive";
					break;
				case 3:
					$optionname = "webhookURLisregistered";
					break;
				case 4:
					$optionname = "webhookURLexpired";
					break;
				case 5:
					$optionname = "webhookURLmarkedused";
					break;
				case 6:
					$optionname = "webhookURLsetused";
					break;
				case 7:
					$optionname = "webhookURLregister";
					break;
				case 8:
					$optionname = "webhookURLipblocking";
					break;
				case 9:
					$optionname = "webhookURLipblocked";
					break;
				case 10:
					$optionname = "webhookURLaddwcinfotocode";
					break;
				case 11:
					$optionname = "webhookURLwcremove";
					break;
				case 12:
					$optionname = "webhookURLaddwcticketinfoset";
					break;
				case 13:
					$optionname = "webhookURLaddwcticketredeemed";
					break;
				case 14:
					$optionname = "webhookURLaddwcticketunredeemed";
					break;
				case 15:
					$optionname = "webhookURLaddwcticketinforemoved";
					break;
				case 16:
					$optionname = "webhookURLrestrictioncodeused";
					break;
			}
			if (!empty($optionname)) {
				$url = $options->getOption($optionname)['value'];
				if (!empty($url)) {
					$url = $this->replaceURLParameters($url, $codeObj);
					wp_remote_get($url);
				}
			}
		}
	}

	private function _getCachedList($list_id) {
		if (isset($this->_CACHE_list[$list_id])) return $this->_CACHE_list[$list_id];
		$this->_CACHE_list[$list_id] = $this->getListById($list_id);
		return $this->_CACHE_list[$list_id];
	}

	public function replaceURLParameters($url, $codeObj) {
		$url = str_replace("{CODE}", isset($codeObj['code']) ? $codeObj['code'] : '', $url);
		$url = str_replace("{CODEDISPLAY}", isset($codeObj['code_display']) ? $codeObj['code_display'] : '', $url);
		$url = str_replace("{IP}", $this->getRealIpAddr(), $url);
		$userid = '';
		if (is_user_logged_in()) {
			$userid = get_current_user_id();
		}
		$url = str_replace("{USERID}", $userid, $url);

		$listname = "";
		if (isset($codeObj['list_id']) && $codeObj['list_id'] > 0 && strpos($url, "{LIST}") !== false) {
			try {
				$listObj = $this->_getCachedList($codeObj['list_id']);
				$listname = $listObj['name'];
			} catch (Exception $e) {
			}
		}
		$url = str_replace("{LIST}", urlencode($listname), $url);

		$listdesc = "";
		if (isset($codeObj['list_id']) && $codeObj['list_id'] > 0 && strpos($url, "{LIST_DESC}") !== false) {
			try {
				$listObj = $this->_getCachedList($codeObj['list_id']);
				$metaObj = [];
				if (!empty($listObj['meta'])) $metaObj = $this->encodeMetaValuesAndFillObjectList($listObj['meta']);
				if (isset($metaObj['desc'])) $listdesc = $metaObj['desc'];
			} catch (Exception $e) {
			}
		}
		$url = str_replace("{LIST_DESC}", urlencode($listdesc), $url);

		$metaObj = [];
		if (!empty($codeObj['meta'])) $metaObj = $this->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		if (count($metaObj) > 0) $url = $this->_replaceTagsInTextWithMetaObjectsValues($url, $metaObj, "META_");

		return $url;
	}

	private function _replaceTagsInTextWithMetaObjectsValues($text, $metaObj, $prefix="") {
		$prefix = strtoupper(trim($prefix));
		foreach(array_keys($metaObj) as $key) {
			$tag = $prefix.strtoupper($key);
			if (is_array($metaObj[$key])) {
				$text = $this->_replaceTagsInTextWithMetaObjectsValues($text, $metaObj[$key], $tag."_");
			} else {
				$text = str_replace("{".$tag."}", urlencode($metaObj[$key]), $text);
			}
		}
		return $text;
	}

	public function checkCodeExpired($codeObj) {
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'checkCodeExpired')) {
			if ($this->MAIN->getPremiumFunctions()->checkCodeExpired($codeObj)) {
				return true;
			}
		}
		return false;
	}
	public function isCodeIsRegistered($codeObj) {
		$meta = [];
		if (!empty($codeObj['meta'])) $meta = $this->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		if (isset($meta['user']) && isset($meta['user']['value']) && !empty($meta['user']['value'])) {
			return true;
		}
		return false;
	}

	public function getTicketURLBase($defaultPath=false) {
		$path = plugin_dir_url(__FILE__).$this->ticket_url_path_part;
		if ($defaultPath == false) {
			$wcTicketCompatibilityModeURLPath = trim($this->MAIN->getOptions()->getOptionValue('wcTicketCompatibilityModeURLPath'));
			$wcTicketCompatibilityModeURLPath = trim(trim($wcTicketCompatibilityModeURLPath, "/"));
			if (!empty($wcTicketCompatibilityModeURLPath)) {
				$path = site_url()."/".$wcTicketCompatibilityModeURLPath;
			}
		}
		return $path."/";
	}
	public function getTicketId($codeObj, $metaObj) {
		if (isset($codeObj['code']) && isset($codeObj['order_id']) && isset($metaObj['wc_ticket']['idcode'])) {
			return $metaObj['wc_ticket']['idcode']."-".$codeObj['order_id']."-".$codeObj['code'];
		} else {
			return "";
		}
	}
	public function getTicketURL($codeObj, $metaObj) {
		$ticket_id = $this->getTicketId($codeObj, $metaObj);
		$baseURL = $this->getTicketURLBase();
		$url = $baseURL.$ticket_id;
		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketCompatibilityMode')) {
			$url = $baseURL."?code=".$ticket_id;
		}
		return $url;
	}
	public function getOrderTicketsURL($order) {
		if ($order == null) throw new Exception("Order empty - no order tickets PDF url created");
		$order_id = $order->get_id();
		$idcode = $order->get_meta('_saso_eventtickets_order_idcode');
		if (empty($idcode)) {
			$idcode = strtoupper(md5($order_id."-".time()."-".uniqid()));
			$order->update_meta_data( '_saso_eventtickets_order_idcode', $idcode );
			$order->save();
		}
		$baseURL = $this->getTicketURLBase();
		$ticket_id = "order-".$order_id."-".$idcode;
		$url = $baseURL.$ticket_id;
		if ($this->MAIN->getOptions()->isOptionCheckboxActive('wcTicketCompatibilityMode')) {
			$url = $baseURL."?code=".$ticket_id;
		}
		return $url;
	}
	public function getTicketURLPath($defaultPath=false) {
		$p = $this->getTicketURLBase($defaultPath);
		$teile = parse_url($p);
		return $teile['path'];
	}
	public function getTicketURLComponents($url) {
		$teile = explode("/", $url);
		$teile = array_reverse($teile);
		$ret = "";
		$request = "";
		$is_pdf_request = false;
		$is_ics_request = false;
		$is_badge_request = false;
		foreach($teile as $teil) {
			$teil = trim($teil);
			if (empty($teil)) continue;
			if (strtolower($teil) == "?pdf") continue;
			if (strtolower($teil) == "?ics") continue;
			if ($teil == $this->ticket_url_path_part) break;
			$ret = $teil;
			break;
		}
		if (isset($_GET['code'])) {
			$parts = explode("-", trim($_GET['code']));
			$t = explode("?", $url);
			if (count($t) > 1) {
				unset($t[0]);
				$request = join("&", $t);
			}
			$is_pdf_request = in_array("pdf", $t);
			$is_ics_request = in_array("ics", $t);
			$is_badge_request = in_array("badge", $t);
		} else {
			if (empty($ret)) throw new Exception("#9301 ticket id not found");
			$parts = explode("-", $ret);
			$t = explode("?", $parts[2]);
			$parts[2] = $t[0];
			if (count($t) > 1) {
				unset($t[0]);
				$request = join("&", $t);
			}
			$is_pdf_request = in_array("pdf", $t) || isset($_GET['pdf']);
			$is_ics_request = in_array("ics", $t) || isset($_GET['ics']);
			$is_badge_request = in_array("badge", $t) || isset($_GET['badge']);
		}
		if (count($parts) != 3) throw new Exception("#9302 ticket id not correct");
		$parts[2] = str_replace("?pdf", "", $parts[2]);
		$parts[2] = str_replace("?ics", "", $parts[2]);
		$parts_assoc = [
			"idcode"=>$parts[0],
			"order_id"=>$parts[1],
			"code"=>$parts[2],
			"_request"=>$request,
			"_isPDFRequest"=>$is_pdf_request,
			"_isICSRequest"=>$is_ics_request,
			"_isBadgeRequest"=>$is_badge_request
		];
		return $parts_assoc;
	}

	public function mergePDFs($filepaths, $filename, $filemode="I", $deleteFilesAfterMerge=true) {
		if (count($filepaths) > 0) {
			if (!class_exists('sasoEventtickets_PDF')) {
				require_once("sasoEventtickets_PDF.php");
			}
			$pdf = new sasoEventtickets_PDF();
			$pdf->setFilemode($filemode);
			$pdf->setFilename($filename);
			try {
				$pdf->mergeFiles($filepaths); // send file to browser if,filemode is I
			} catch(Exception $e) {}

			// clean up temp files
			if ($deleteFilesAfterMerge) {
				foreach($filepaths as $filepath) {
					if (file_exists($filepath)) {
						@unlink($filepath);
					}
				}
			}
			if ($pdf->getFilemode() == "F") {
				return $pdf->getFullFilePath();
			} else {
				exit;
			}
		}
	}

	public function my_upgrade_function( $upgrader_object, $options ) {
    	$current_plugin_path_name = plugin_basename( __FILE__ );
    	if ($options['action'] == 'update' && $options['type'] == 'plugin' ) {
			if (isset($options['plugins'])) {
				foreach($options['plugins'] as $each_plugin) {
					if ($each_plugin==$current_plugin_path_name) {
					// .......................... YOUR CODES .............

					}
				}
			}
    	}
	}

}
?>