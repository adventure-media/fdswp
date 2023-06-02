<?php
include(plugin_dir_path(__FILE__)."init_file.php");
class sasoEventtickets_AdminSettings {
	private $MAIN;

	public function __construct($MAIN) {
		$this->MAIN = $MAIN;
	}
	public static function plugin_uninstall(){
		//delete_option
		//delete tabellen
	}
	public function getCore() {
		return $this->MAIN->getCore();
	}
	private function getBase() {
		return $this->MAIN->getBase();
	}
	private function getDB() {
		return $this->MAIN->getDB();
	}
	public function executeJSON($a, $data=[], $just_ret=false) {
		$ret = "";
		$justJSON = false;
		try {
			switch (trim($a)) {
				case "getLists":
					$ret = $this->getLists();
					break;
				case "getList":
					$ret = $this->getList($data);
					break;
				case "addList":
					$ret = $this->addList($data);
					break;
				case "editList":
					$ret = $this->editList($data);
					break;
				case "removeList":
					$ret = $this->removeList($data);
					break;
				case "getCodes":
					$ret = $this->getCodes($data, $_GET);
					$justJSON = true;
					break;
				case "addCode":
					$ret = $this->addCode($data);
					break;
				case "addCodes":
					$ret = $this->addCodes($data);
					break;
				case "editCode":
					$ret = $this->editCode($data);
					break;
				case "removeCode":
					$ret = $this->removeCode($data);
					break;
				case "removeCodes":
					$ret = $this->removeCodes($data);
					break;
				case "emptyTableLists":
					$ret = $this->emptyTableLists($data);
					break;
				case "emptyTableCodes":
					$ret = $this->emptyTableCodes($data);
					break;
				case "exportTableCodes":
					$ret = $this->exportTableCodes($data);
					break;
				case "premium":
					$ret = $this->executeJSONPremium($data);
					break;
				case "removeWoocommerceOrderInfoFromCode":
					$ret = $this->removeWoocommerceOrderInfoFromCode($data);
					break;
				case "removeWoocommerceRstrPurchaseInfoFromCode":
					$ret = $this->removeWoocommerceRstrPurchaseInfoFromCode($data);
					break;
				case "setWoocommerceTicketForCode":
					$ret = $this->setWoocommerceTicketForCode($data);
					break;
				case "redeemWoocommerceTicketForCode":
					$ret = $this->redeemWoocommerceTicketForCode($data);
					break;
				case "removeRedeemWoocommerceTicketForCode":
					$ret = $this->removeRedeemWoocommerceTicketForCode($data);
					break;
				case "removeRedeemWoocommerceTicketForCodeBulk":
					$ret = $this->removeRedeemWoocommerceTicketForCodeBulk($data);
					break;
				case "removeWoocommerceTicketForCode":
					$ret = $this->removeWoocommerceTicketForCode($data);
					break;
				case "getOptions":
					$ret = $this->getOptions();
					break;
				case "changeOption":
					$ret = $this->changeOption($data);
					break;
				case "getMetaOfCode":
					$ret = $this->getMetaOfCode($data);
					break;
				case "removeUserRegistrationFromCode":
					$ret = $this->removeUserRegistrationFromCode($data);
					break;
				case "editUseridForUserRegistrationFromCode":
					$ret = $this->editUseridForUserRegistrationFromCode($data);
					break;
				case "removeUsedInformationFromCode":
					$ret = $this->removeUsedInformationFromCode($data);
					break;
				case "removeUsedInformationFromCodeBulk":
					$ret = $this->removeUsedInformationFromCodeBulk($data);
					break;
				case "editUseridForUsedInformationFromCode":
					$ret = $this->editUseridForUsedInformationFromCode($data);
					break;
				case "editTicketMetaEntry":
					$ret = $this->editTicketMetaEntry($data);
					break;
				case "repairTables":
					$ret = $this->repairTables($data);
					break;
				case "getMediaData":
					$ret = $this->getMediaData($data);
					break;
				case "getSupportInfos":
					$ret = $this->getSupportInfos($data);
					break;
				case "downloadPDFTicket":
					$ret = $this->downloadPDFTicket($data);
					break;
				case "downloadPDFTicketBadge":
					$ret = $this->downloadPDFTicketBadge($data);
					break;
				case "testing":
					$ret = $this->testing($data);
					break;
				default:
					throw new Exception(sprintf(esc_html__('function "%s" not implemented', 'event-tickets-with-ticket-scanner'), $a));
			}
		} catch(Exception $e) {
			if ($just_ret) throw $e;
			return wp_send_json_error ($e->getMessage());
		}
		if ($just_ret) return $ret;
		if ($justJSON) return wp_send_json($ret);
		else return wp_send_json_success( $ret );
	}
	private function executeJSONPremium($data) {
		if (!$this->MAIN->isPremium()) throw new Exception("#9001 premium is not active");
		if (!isset($data['c'])) throw new Exception("#9002 premium action is missing");
		return $this->MAIN->getPremiumFunctions()->executeJSON($data['c'], $data);
	}

	private function repairTables() {
		$this->getDB()->installiereTabellen(true);
		do_action($this->MAIN->_do_action_prefix."repairTables", true);

		// aktualisiere nicht erfasste userid für orders
		$sql = "select * from ".$this->getDB()->getTabelle("codes")." where
			order_id != 0 and
			json_extract(meta, '$.wc_ticket.is_ticket') = 1 and
			json_extract(meta, '$.woocommerce.user_id') is null
		";
		$d = $this->getDB()->_db_datenholen($sql);
		if (count($d) > 0) {
			foreach($d as $codeObj) {
				$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
				$order = wc_get_order( $codeObj['order_id'] );
				$metaObj['woocommerce']['user_id'] = intval($order->get_user_id());
				$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);
				$this->getDB()->update("codes", ["meta"=>$codeObj['meta']], ['id'=>$codeObj['id']]);
			}
		}

		return "tables repair executed at ".date("Y/m/d H:i:s");
	}

	private function getMediaData($data) {
		if (!isset($data['mediaid'])) throw new Exception("#9005 media id is missing");
		return SASO_EVENTTICKETS::getMediaData($data['mediaid']);
	}

	private function downloadPDFTicket($data) {
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$this->MAIN->getTicketHandler()->setCodeObj($codeObj);
		$this->MAIN->getTicketHandler()->outputPDF("I");
		die();
	}

	private function downloadPDFTicketBadge($data) {
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$badgeHandler = $this->MAIN->getTicketBadgeHandler();
		$badgeHandler->downloadPDFTicketBadge($codeObj);
		die();
	}

	private function getSupportInfos($data) {
		$codes_size = $this->getDB()->getCodesSize();
		$lists_size = $this->getDB()->_db_getRecordCountOfTable('lists');
		$ips_size = $this->getDB()->_db_getRecordCountOfTable('ips');
		return [
			"amount"=>["codes"=>$codes_size, "lists"=>$lists_size, "ips"=>$ips_size]
		];
	}

	public function getOptions() {
		global $wpdb, $wp_version ;
		$options = $this->MAIN->getOptions()->getOptions();

		// zeige die code object struktur an
		//$metaObj = $this->getCore()->getMetaObject();
		//$keys = $this->getCore()->getMetaObjectKeyList($metaObj, "");

		$tags = $this->getCore()->getMetaObjectAllowedReplacementTags();

		$pversions = $this->MAIN->getPluginVersions();
		$premium_db_version = '';
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'getDBVersion')) {
			$premium_db_version = $this->MAIN->getPremiumFunctions()->getDBVersion();
		}
		//$current = get_site_transient( 'update_core' );
		$mysql_version = 'N/A';
		if ( method_exists( $wpdb, 'db_version' ) ) {
			$mysql_version = preg_replace( '/[^0-9.].*/', '', $wpdb->db_version() );
		}
		$versions = [
			'php'=>phpversion(),
			'wp'=>$wp_version,
			'mysql'=>$mysql_version,
			'db'=>$this->getDB()->dbversion,
			'premium_db'=>$premium_db_version,
			'basic'=>$pversions['basic'],
			'premium'=>$pversions['premium'] != "" ? $pversions['premium'] : '',
			'premium_serial'=>$this->getOptionValue("serial", "-"),
			'is_wc_available'=>class_exists( 'WooCommerce' ) ? 1 : 0
		];
		$infos = [
			'ticket'=>[
				'ticket_base_url'=>$this->getCore()->getTicketURLBase(true),
				'ticket_detail_path'=>$this->getCore()->getTicketURLPath(true),
				'ticket_scanner_path'=>$this->getCore()->getTicketURLPath(true).'scanner/'
			],
			'site'=>[
				'is_multisite'=>is_multisite() ? 1 : 0,
				'home'=>home_url(),
				'network_home'=>network_home_url(),
				'site_url'=>site_url()
			]
		];
		if (is_admin()) {
			return ['options'=>$options, 'meta_tags_keys'=>$tags, 'versions'=>$versions, 'infos'=>$infos];
		}
		return ['options'=>$options, 'meta_tags_keys'=>$tags, 'versions'=>[], 'infos'=>[]];
	}
	public function changeOption($data) {
		$this->MAIN->getOptions()->changeOption($data);
	}
	public function getOptionValue($name, $defvalue="") {
		return $this->MAIN->getOptions()->getOptionValue($name, $defvalue);
	}
	public function isOptionCheckboxActive($name) {
		return $this->MAIN->getOptions()->isOptionCheckboxActive($name);
	}

	public function getMetaOfCode($data) {
		if (!isset($data['code'])) throw new Exception("#9101 code is missing");
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$codeObj = apply_filters( $this->MAIN->_do_action_prefix.'filter_updateExpirationInfo', $codeObj );
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);

		$max_redeem_amount = 1;
		if (class_exists( 'WooCommerce' )) {
			// load user info of woocommerce sale

			if ($metaObj["wc_ticket"]["is_ticket"]) {
				$max_redeem_amount = $this->MAIN->getTicketHandler()->getMaxRedeemAmountOfTicket($codeObj);
			}
		}
		$metaObj["wc_ticket"]["_max_redeem_amount"] = $max_redeem_amount;

		$url = $this->getOptionValue("qrDirectURL");
		if (!empty($url)) {
			$url = $this->getCore()->replaceURLParameters($url, $codeObj);
			$metaObj['_QR']['directURL'] = trim($url);
		}

		return $metaObj;
	}

	private function removeUserRegistrationFromCode($data) {
		if(!isset($data['code'])) throw new Exception("#9221 code missing");
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		$metaObj['user']['value'] = "";
		$metaObj['user']['reg_ip'] = "";
		$metaObj['user']['reg_approved'] = 0;
		$metaObj['user']['reg_request'] = "";
		$metaObj['user']['reg_userid'] = 0;
		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);
		$this->getDB()->update("codes", ["meta"=>$codeObj['meta'], "user_id"=>0], ['id'=>$codeObj['id']]);
		do_action( $this->MAIN->_do_action_prefix.'removeUserRegistrationFromCode', $data, $codeObj );
		return $codeObj;
	}
	private function editUseridForUserRegistrationFromCode($data) {
		if(!isset($data['code'])) throw new Exception("#9222 code missing");
		//if(!isset($data['value'])) throw new Exception("#9223 value missing");
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		// speicher neue registrierung
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		if (isset($data['value'])) $metaObj['user']['value'] = htmlentities(trim($data['value']));
		$metaObj['user']['reg_ip'] = $this->getCore()->getRealIpAddr();
		$metaObj['user']['reg_approved'] = 1; // auto approval
		if (empty($metaObj['user']['reg_request'])) $metaObj['user']['reg_request'] = date("Y-m-d H:i:s");
		if (isset($data['reg_userid'])) $metaObj['user']['reg_userid'] = intval($data['reg_userid']);
		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);
		$this->getDB()->update("codes", ["meta"=>$codeObj['meta'], "user_id"=>$metaObj['used']['reg_userid']], ['id'=>$codeObj['id']]);
		// send webhook if activated
		$this->getCore()->triggerWebhooks(7, $codeObj);
		do_action( $this->MAIN->_do_action_prefix.'editUseridForUserRegistrationFromCode', $data, $codeObj, $metaObj );
		return $codeObj;
	}
	public function removeUsedInformationFromCode($data) {
		if(!isset($data['code'])) throw new Exception("#9231 code missing");
		$codeObj = $this->getBase()->getCORE($this->getDB())->retrieveCodeByCode($data['code']);
		$metaObj = $this->getBase()->getCORE($this->getDB())->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		$metaObj['used']['reg_ip'] = "";
		$metaObj['used']['reg_request'] = "";
		$metaObj['confirmedCount'] = 0;
		$codeObj['meta'] = $this->getBase()->getCORE($this->getDB())->json_encode_with_error_handling($metaObj);
		$this->getDB()->update("codes", ["meta"=>$codeObj['meta']], ['id'=>$codeObj['id']]);
		do_action( $this->MAIN->_do_action_prefix.'removeUsedInformationFromCode', $data, $codeObj );
		return $codeObj;
	}
	private function removeUsedInformationFromCodeBulk($data) {
		if (!isset($data['codes'])) throw new Exception("#9235 codes are missing");
		if (!is_array($data['codes'])) throw new Exception("#9236 codes must be an array");
		set_time_limit(0);
		$ret = [];
		foreach($data['codes'] as $v) {
			$_data = ['code'=>$v];
			$ret[$v] = $this->removeUsedInformationFromCode($_data);
		}
		return ["count"=>count($data['codes']), "ret"=>$ret];
	}
	private function editUseridForUsedInformationFromCode($data) {
		if(!isset($data['code'])) throw new Exception("#9233 code missing");
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		// speicher neue registrierung
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		$metaObj['used']['reg_ip'] = $this->getCore()->getRealIpAddr();
		if (empty($metaObj['used']['reg_request'])) $metaObj['used']['reg_request'] = date("Y-m-d H:i:s");
		if (isset($data['reg_userid'])) $metaObj['used']['reg_userid'] = intval($data['reg_userid']);
		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);
		$this->getDB()->update("codes", ["meta"=>$codeObj['meta']], ['id'=>$codeObj['id']]);
		// send webhook if activated
		$this->getCore()->triggerWebhooks(6, $codeObj);
		do_action( $this->MAIN->_do_action_prefix.'editUseridForUsedInformationFromCode', $data, $codeObj );
		return $codeObj;
	}
	private function editTicketMetaEntry($data) {
		if(!isset($data['code'])) throw new Exception("#9234 code missing");
		$key = $data['key'];
		$value = trim($data['value']);
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		$is_changed = false;
		switch ($key) {
			case "wc_ticket.name_per_ticket":
				$is_changed = true;
				$metaObj['wc_ticket']['name_per_ticket'] = $value;
				break;
		}
		if ($is_changed) {
			$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);
			$this->getDB()->update("codes", ["meta"=>$codeObj['meta']], ['id'=>$codeObj['id']]);
		}
		return $codeObj;
	}

	private function _setMetaDataForList($data, $metaObj) {
		if (isset($data['meta']) && isset($data['meta']['desc'])) {
			$metaObj['desc'] = trim($data['meta']['desc']);
		}
		if (isset($data['meta']) && isset($data['meta']['formatter']['active'])) {
			$metaObj['formatter']['active'] = 0;
			if ($data['meta']['formatter']['active'] == 1) $metaObj['formatter']['active'] = 1;
		}
		if (isset($data['meta']) && isset($data['meta']['formatter']['format'])) {
			$metaObj['formatter']['format'] = trim($data['meta']['formatter']['format']);
		}
		if (isset($data['meta']) && isset($data['meta']['redirect']) && isset($data['meta']['redirect']['url'])) {
			$metaObj['redirect']['url'] = trim($data['meta']['redirect']['url']);
		}
		return $metaObj;
	}

	public function generateFirstCodeList() {
		$lists = $this->_getLists();
		if (count($lists) == 0) {
			$data = ['name'=>'Ticket list'];
			$this->_addList($data);
		}
	}

	public function _getList($data) {
		if (!isset($data['id'])) throw new Exception("#104 id is missing");
		$sql = "select * from ".$this->getDB()->getTabelle("lists")." where id = ".intval($data['id']);
		$ret = $this->getDB()->_db_datenholen($sql);
		if (count($ret) == 0) throw new Exception("#105 not found");
		return $ret[0];
	}
	public function getList($data) {
		add_filter( $this->MAIN->_add_filter_prefix.'getList', [$this, '_getList'], 10, 1 );
		return apply_filters( $this->MAIN->_add_filter_prefix.'getList', $data );
	}

	public function _getLists() {
		$sql = "select * from ".$this->getDB()->getTabelle("lists")." order by name asc";
		return $this->getDB()->_db_datenholen($sql);
	}

	public function getLists() {
		add_filter( $this->MAIN->_add_filter_prefix.'getLists', [$this, '_getLists'], 10 );
		return apply_filters( $this->MAIN->_add_filter_prefix.'getLists', 1);
	}

	public function _addList($data) {
		if (!isset($data['name']) || trim($data['name']) == "") throw new Exception("#101 name missing");
		if (!$this->getBase()->premiumCheck_isAllowedAddingList($this->getDB()->_db_getRecordCountOfTable('lists'))) throw new Exception("#108 too many codes. Unlimited codes only with premium");
		$listObj = ['meta'=>''];
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObjectList($listObj['meta']);

		$felder = ["name"=>$data['name'], "aktiv"=>1, "time"=>date("Y-m-d H:i:s")];

		$metaObj = $this->_setMetaDataForList($data, $metaObj);

		if ($this->MAIN->isPremium()) $felder = $this->MAIN->getPremiumFunctions()->setFelderListEdit($felder, $data, $listObj, $metaObj);
		if (isset($felder['meta']) && !empty($felder['meta'])) { // evtl gesetzt vom premium plugin
			$metaObj = array_replace_recursive($metaObj, json_decode($felder['meta'], true));
		}
		$felder["meta"] = $this->getCore()->json_encode_with_error_handling($metaObj);

		try {
			return $this->getDB()->insert("lists", $felder);
		} catch(Exception $e) {
			throw new Exception(__("Could not create code list. Name exists already.", 'event-tickets-with-ticket-scanner'));
		}
	}

	private function addList($data) {
		add_filter( $this->MAIN->_add_filter_prefix.'addList', [$this, '_addList'], 10, 1 );
		return apply_filters( $this->MAIN->_add_filter_prefix.'addList', $data);
	}

	public function _editList($data) {
		if (!isset($data['name']) || trim($data['name']) == "") throw new Exception("#102 name missing");
		if (!isset($data['id']) || intval($data['id']) == 0) throw new Exception("#103 id missing");
		$listObj = $this->getList($data);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObjectList($listObj['meta']);

		$felder["name"] = $data['name'];

		$metaObj = $this->_setMetaDataForList($data, $metaObj);

		if ($this->MAIN->isPremium()) $felder = $this->MAIN->getPremiumFunctions()->setFelderListEdit($felder, $data, $listObj, $metaObj);
		if (isset($felder['meta']) && !empty($felder['meta'])) { // evtl gesetzt vom premium plugin
			$metaObj = array_replace_recursive($metaObj, json_decode($felder['meta'], true));
		}
		$felder["meta"] = $this->getCore()->json_encode_with_error_handling($metaObj);

		$where = ["id"=>intval($data['id'])];
		return $this->getDB()->update("lists", $felder, $where);
	}

	public function editList($data) {
		add_filter( $this->MAIN->_add_filter_prefix.'editList', [$this, '_editList'], 10, 1 );
		return apply_filters( $this->MAIN->_add_filter_prefix.'editList', $data);
	}

	private function removeList($data) {
		if (!isset($data['id'])) throw new Exception("#106 id is missing");
		$sql = "update ".$this->getDB()->getTabelle("codes")." set list_id = 0 where list_id = ".intval($data['id']);
		$this->getDB()->_db_query($sql);
		$sql = "delete from ".$this->getDB()->getTabelle("lists")." where id = ".intval($data['id']);
		$ret = $this->getDB()->_db_query($sql);
		do_action( $this->MAIN->_do_action_prefix.'removeList', $data );
		return $ret;
	}

	private function getCode($data) {
		if (!isset($data['code']) || trim($data['code']) == "") throw new Exception("#202 code missing");
		return $this->getCore()->retrieveCodeByCode($data['code'], true);
	}
	public function getCodesByProductId($product_id) {
		$sql = "select a.*, order_id as o, b.name as list_name ";
		$sql .= " from ".$this->getDB()->getTabelle("codes")." a left join ".$this->getDB()->getTabelle("lists")." b on a.list_id = b.id";
		$sql .= " where";
		$sql .= " json_extract(a.meta, '$.woocommerce.product_id') = ".intval($product_id);
		$sql .= " order by a.time";
		$daten = $this->getDB()->_db_datenholen($sql);
		foreach($daten as $key => $item) {
			$daten[$key]['_customer_name'] = "";
			$metaObj = $this->MAIN->getCore()->encodeMetaValuesAndFillObject($item['meta']);
			$order_id = intval($metaObj['woocommerce']['order_id']);
			if ($order_id > 0) {
				$order = wc_get_order( $order_id );
				if ($order != null) {
					$daten[$key]['_customer_name'] = $order->get_billing_first_name()." ".$order->get_billing_last_name();
				}
			}
		}
		return $daten;
	}
	private function getCodes($data, $request) {
		$sql = "select a.*, order_id as o, b.name as list_name ";
		$sql .= " from ".$this->getDB()->getTabelle("codes")." a left join ".$this->getDB()->getTabelle("lists")." b on a.list_id = b.id";

		// für datatables
		$length = 0; // wieviele pro seite angezeigt werden sollen (limit)
		if (isset($request['length'])) $length = intval($request['length']);
		$draw = 1; // sequenz zähler, also fortlaufend für jede aktion auf der JS datentabelle
		if (isset($request['draw'])) $draw = intval($request['draw']);
		$start = 0;
		if (isset($request['start'])) $start = intval($request['start']);
		$order_column = "code";

		$displayAdminAreaColumnRedeemedInfo = $this->isOptionCheckboxActive('displayAdminAreaColumnRedeemedInfo');
		$displayAdminAreaColumnBillingName = $this->isOptionCheckboxActive('displayAdminAreaColumnBillingName');

		if (isset($request['order'])) {
			$order_columns = array('', '', 'code');
			if ($displayAdminAreaColumnBillingName) $order_columns[] = '';
			$order_columns[] = 'list_name';
			$order_columns[] = 'time';
			$order_columns[] = 'redeemed';
			if ($displayAdminAreaColumnRedeemedInfo) $order_columns[] = '';
			$order_columns[] = 'order_id';
			$order_columns[] = '';
			$order_columns[] = 'aktiv';
			$order_column = $order_columns[intval($request['order'][0]['column'])];
		}
		$order_dir = "asc";
		if (isset($request['order']) && $request['order'][0]['dir'] == 'desc') $order_dir = "desc";
		$search = "";
		if (isset($request['search'])) $search = $this->getDB()->reinigen_in($request['search']['value']);

		$where = "";
		if ($search != "") {
			$sql .= " where ";

			$nomatch = true;

			$matches = [];
			preg_match('/\s?LIST:\s*([0-9]*)/', $search, $matches);
			if ($matches && count($matches) > 1) {
				$list_id = intval($matches[1]);
				$where .= " a.list_id = ".$list_id." ";
				$nomatch = false;
			} else {
				preg_match('/\s?LIST:\s*\*/', $search, $matches);
				if ($matches && count($matches) > 1) {
					$where .= " a.list_id > 0 ";
					$nomatch = false;
				}
			}
			preg_match('/\s?ORDERID:\s*([0-9]+)/', $search, $matches);
			if ($matches && count($matches) > 1) {
				$order_id = intval($matches[1]);
				if ($nomatch == false) $where .= " and ";
				$where .= " a.order_id = ".$order_id." ";
				$nomatch = false;
			} else {
				preg_match('/\s?ORDERID:\s*\*/', $search, $matches);
				if ($matches && count($matches) > 0) {
					if ($nomatch == false) $where .= " and ";
					$where .= " a.order_id > 0 ";
					$nomatch = false;
				}
			}
			preg_match('/\s?CVV:\s*([^\s]*)/', $search, $matches);
			if ($matches && count($matches) > 1) {
				$cvv = $matches[1];
				if ($nomatch == false) $where .= " and ";
				$where .= " a.cvv = '".sanitize_text_field($cvv)."' ";
				$nomatch = false;
			}
			preg_match('/\s?STATUS:\s*([0-9]*)/', $search, $matches);
			if ($matches && count($matches) > 1) {
				$status = intval($matches[1]);
				if ($nomatch == false) $where .= " and ";
				$where .= " a.aktiv = ".$status." ";
				$nomatch = false;
			}
			preg_match('/\s?REDEEMED:\s*([0-1]*)/', $search, $matches);
			if ($matches && count($matches) > 1) {
				$status = intval($matches[1]);
				if ($nomatch == false) $where .= " and ";
				$where .= " a.redeemed = ".$status." ";
				$nomatch = false;
			}
			preg_match('/\s?USERID:\s*([0-9]*)/', $search, $matches);
			if ($matches && count($matches) > 1) {
				$user_id = intval($matches[1]);
				if ($nomatch == false) $where .= " and ";
				$where .= " a.user_id = ".$user_id." ";
				$nomatch = false;
			}
			preg_match('/\s?PRODUCTID:\s*([0-9]*)/', $search, $matches);
			if ($matches && count($matches) > 1) {
				$product_id = intval($matches[1]);
				if ($nomatch == false) $where .= " and ";
				$where .= " json_extract(a.meta, '$.woocommerce.product_id') = ".$product_id." ";
				$nomatch = false;
			}

			if ($nomatch) {
				$where .= " a.code like '%".$this->getCore()->clearCode($search)."%' or b.name like '%".$search."%' or a.time like '%".$search."%' ";
				if (intval($search) > 0) {
					$where .= " or a.order_id = ".intval($search)." ";
				}
			}

			$sql .= $where;
		}
		if ($order_column != "") $sql .= " order by ".$order_column." ".$order_dir;
		if ($length > 0)
			$sql .= " limit ".$start.", ".$length;

		$daten = $this->getDB()->_db_datenholen($sql);
		$recordsTotal = $this->getDB()->_db_getRecordCountOfTable('codes');
		$recordsTotalFilter = $recordsTotal;
		if (!empty($where)) {
			$sql = "select count(a.id) as anzahl
					from
					".$this->getDB()->getTabelle("codes")." a left join
					".$this->getDB()->getTabelle("lists")." b on a.list_id = b.id
					where ".$where;
			list($d) = $this->getDB()->_db_datenholen($sql);
			$recordsTotalFilter = $d['anzahl'];
		}
		$redeemedRecordsTotal = $this->getDB()->_db_getRecordCountOfTable('codes', 'redeemed = 1');
		$redeemedRecordsFiltered = $redeemedRecordsTotal;
		if (!empty($where)) {
			$sql = "select count(a.id) as anzahl
			from
			(select * from ".$this->getDB()->getTabelle("codes")." where redeemed = 1) a left join
			".$this->getDB()->getTabelle("lists")." b on a.list_id = b.id
			where ".$where;
			list($d) = $this->getDB()->_db_datenholen($sql);
			$redeemedRecordsFiltered = $d['anzahl'];
		}

		if ($displayAdminAreaColumnBillingName) {
			foreach($daten as $key => $item) {
				$daten[$key]['_customer_name'] = $this->getCustomerName($item['order_id']);
			}
		}
		if ($displayAdminAreaColumnRedeemedInfo) {
			$products_max_redeem_amounts = []; // cache
			foreach($daten as $key => $item) {
				if (!isset($item['meta']) || empty($item['meta'])) {
					$daten[$key]['meta'] = $this->getCore()->json_encode_with_error_handling($this->MAIN->getCore()->getMetaObject());
				}
				$redeemAmount = $this->getRedeemAmount($item, $products_max_redeem_amounts);
				$daten[$key]['_redeemed_counter'] = $redeemAmount['_redeemed_counter'];
				$daten[$key]['_max_redeem_amount'] = $redeemAmount['_max_redeem_amount'];
				$products_max_redeem_amounts = $redeemAmount['cache'];
			}
		}

		return ["draw"=>$draw,
				"recordsTotal"=>intval($recordsTotal),
				"recordsFiltered"=>intval($recordsTotalFilter),
				"redeemedRecordsTotal"=>intval($redeemedRecordsTotal),
				"redeemedRecordsFiltered"=>intval($redeemedRecordsFiltered),
				"data"=>$daten];
	}
	public function getCustomerName($order_id) {
		$ret = "";
		$order_id = intval($order_id);
		if ($order_id > 0) {
			$order = wc_get_order( $order_id );
			if ($order != null) {
				$ret = $order->get_billing_first_name()." ".$order->get_billing_last_name();
			}
		}
		return $ret;
	}
	public function getRedeemAmount($codeObj, $cache=[]) {
		$ret = ["_redeemed_counter"=>0, "_max_redeem_amount"=>0];
		if (isset($codeObj['metaObj'])) {
			$metaObj = $codeObj['metaObj'];
		} else {
			if (!isset($codeObj['meta'])) throw new Exception("meta object is missing: ".__METHOD__);
			$metaObj = $this->MAIN->getCore()->encodeMetaValuesAndFillObject($codeObj['meta']);
		}
		if ($metaObj['wc_ticket']['is_ticket'] == 1) {
			$ret['_redeemed_counter'] = count($metaObj['wc_ticket']['stats_redeemed']);
			$product_id = intval($metaObj['woocommerce']['product_id']);
			if ($product_id > 0) {
				if (!isset($cache[$product_id])) {
					$cache[$product_id] = intval(get_post_meta( $product_id, 'saso_eventtickets_ticket_max_redeem_amount', true ));
				}
				$ret['_max_redeem_amount'] = $cache[$product_id];
			}
		}
		$ret['cache'] = $cache;
		return $ret;
	}
	private function _addCode($newcode, $list_id=0) {
		$cvv = "";
		$teile = explode(";", $newcode);
		if (count($teile) > 1) {
			$newcode = trim($teile[0]);
			$cvv = trim($teile[1]);
		}
		$code = $this->getCore()->clearCode($newcode);
		try {
			$this->getCore()->retrieveCodeByCode($code, false);
		} catch(Exception $e) { // not found -> add new
			$this->getCore()->checkCodesSize();
			$felder = ["code"=>$code, "code_display"=>$newcode, "cvv"=>$cvv, "aktiv"=>1, "time"=>date("Y-m-d H:i:s"), "meta"=>"", "list_id"=>$list_id];
			return $this->getDB()->insert("codes", $felder);
		}
		throw new Exception("#205 ".__('code exists already', 'event-tickets-with-ticket-scanner'));
	}

	public function addCodeFromListForOrder($list_id, $order_id, $product_id = 0, $item_id = 0, $formatterValues="") {
		// item_id is in der order der eintrag
		$list_id = intval($list_id);
		if ($list_id == 0) throw new Exception("#602 list id invalid");
		$listObj = $this->getList(['id'=>$list_id]); // throws #104, #105
		// uses a list to define the autogenerated code, that will be taken (if not used codes exists) or store a new generated code to the list
		$data = ["code"=>"", "list_id"=>$list_id, "order_id"=>$order_id, "semaphorecode"=>""]; // semaphorecode wird beim speichern wieder frei gemacht
		$id = 0;
		// check if option is activate to reuse not purchased codes from the code list assigned to the woocommerce product
		if ($this->isOptionCheckboxActive('wcassignmentReuseNotusedCodes')) {
			// semaphore to prevent stealing code on heavy loaded servers
			$semaphorecode = md5(SASO_EVENTTICKETS::PasswortGenerieren() . microtime(). "_". rand());
			$rescueCounter = 0;
			while($rescueCounter < 50) {
				// get a code from the list with order_id = 0
				$sql = "select id from ".$this->getDB()->getTabelle("codes")." where
						order_id = 0 and semaphorecode = '' and
						list_id = ".$list_id."
						limit 1";
				$d = $this->getDB()->_db_datenholen($sql);
				if (count($d) == 0) {
					break; // if no unregistered code could be found => create a new one
				} else {
					//if (SASO_EVENTTICKETS::issetRPara('a') && SASO_EVENTTICKETS::getRequestPara('a') == 'testing') echo "<p>FOUND UNUSED</p>";
					// update it with a random code if not already a code is assigned
					$this->getDB()->update("codes", ['semaphorecode'=>$semaphorecode], ['id'=>$d[0]['id'], 'semaphorecode'=>'']);
					// retrieve the code again and check if the random code could be set
					$sql = "select id, code_display, semaphorecode from ".$this->getDB()->getTabelle("codes")." where id = ".$d[0]['id'];
					$d = $this->getDB()->_db_datenholen($sql);
					// if not managed to add the random code do it again
					if ($d[0]['semaphorecode'] == $semaphorecode) {
						if ($semaphorecode == "") break; // semaphorecode is empty => create a new serial
						// set the $id
						$id = intval($d[0]['id']);
						$data['code'] = $d[0]['code_display'];
						// too clean up again after the testing // $this->getDB()->update("codes", ['semaphorecode'=>''], ['id'=>$d[0]['id']]);
						break; // done
					}
				}
				$rescueCounter++;
			}
		}
		if (SASO_EVENTTICKETS::issetRPara('a') && SASO_EVENTTICKETS::getRequestPara('a') == 'testing') {
			/*
			echo(esc_html($id));
			if ($id > 0) {
				print_r($this->getCore()->retrieveCodeById($id, true));
			}

			$this->getDB()->update("codes", ['semaphorecode'=>""], ['list_id'=>$list_id, 'order_id'=>0]);
			$sql = "select * from ".$this->getDB()->getTabelle("codes")." where
					order_id = 0 and
					list_id = ".$list_id;
			$d = $this->getDB()->_db_datenholen($sql);
			print_r($d);
			if ($id == 0) {
				echo "no reusabel code found";
				exit;
			}
			*/
		}

		if ($id == 0 && empty($data['code'])) {

			// check if serial code formatter is active
			if (empty($formatterValues)) {
				$metaObj = $this->getCore()->encodeMetaValuesAndFillObjectList($listObj['meta']);
				if ($metaObj['formatter']['active'] == 1) {
					$formatterValues = stripslashes($metaObj['formatter']['format']);
				}
			}

			$counter = 0;
			while($counter < 100) {
				$counter++;
				$data["code"] = $this->generateCode($formatterValues);
				try {
					$id = $this->addCode($data);
					break;
				} catch(Exception $e) {
					// code exists already, try a new one
					if (substr($e->getMessage(), 0, 5) == "#208 ") { // no premium and limit exceeded
						$data["code"] = $this->getOptionValue('wcassignmentTextNoCodePossible', __("Please contact our support for the ticket/code", 'event-tickets-with-ticket-scanner'));
						return $data["code"];
					}
				}
			}
		}
		//if (SASO_EVENTTICKETS::issetRPara('a') && SASO_EVENTTICKETS::getRequestPara('a') == 'testing') exit;

		if ($id > 0) {
			$this->editCode($data); // order_id wird nicht beim anlegen gespeichert, deswegen hier nochmal ein update
			$codeObj = $this->addWoocommerceInfoToCode([
												'code'=>$data["code"],
												'list_id'=>$list_id,
												'order_id'=>$order_id,
												'product_id'=>$product_id,
												'item_id'=>$item_id
												]);
			if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'addCodeFromListForOrderAfter')) {
				$codeObj = $this->MAIN->getPremiumFunctions()->addCodeFromListForOrderAfter($codeObj);
			}

			return $data["code"];
		}
		throw new Exception("#601 ".__('code could not be generated and stored', 'event-tickets-with-ticket-scanner'));
	}
	private function generateCode($formatterValues="") {
		$code = implode('-', str_split(substr(strtoupper(md5(time()."_".rand())), 0, 20), 5));
		if (!empty($formatterValues) || $this->isOptionCheckboxActive("wcassignmentUseGlobalSerialFormatter")) {
			if (empty($formatterValues)) {
				$codeFormatterJSON = $this->getOptionValue('wcassignmentUseGlobalSerialFormatter_values');
			} else {
				$codeFormatterJSON = $formatterValues;
			}
			// check ob formatter infos gespeichert
			if (!empty($codeFormatterJSON)) {
				// check ob man das JSON erstellen kann
				$obj = json_decode($codeFormatterJSON, true);
				if (is_array($obj)) {
					// bauen den code
					$laenge = 0;
					if (isset($obj['input_amount_letters'])) $laenge = intval($obj['input_amount_letters']);
					if ($laenge == 0) $laenge = 12;
					$charset = join("", range("a", "z"));
					$letterStyle = 0;
					if (isset($obj['input_letter_style'])) $letterStyle = intval($obj['input_letter_style']);
					if ($letterStyle == 1) $charset = strtoupper($charset);
					if ($letterStyle == 3) $charset .= strtoupper($charset);
					$withnumbers = 0;
					if (isset($obj['input_include_numbers'])) $withnumbers = intval($obj['input_include_numbers']);
					if ($withnumbers == 2) $charset .= '0123456789';
					if ($withnumbers == 3) $charset = '0123456789';
					$exclusion = 0;
					if (isset($obj['input_letter_excl'])) $exclusion = intval($obj['input_letter_excl']);
					if ($exclusion == 2) {
						$charset = str_ireplace(['i','l','o','p','q'], "", $charset);
					}
					$letters = str_split($charset);
					$code = "";
					for ($a=0;$a<$laenge;$a++) {
				        shuffle($letters);
				        $zufallszahl = rand(0, count($letters)-1);
				        $buchstabe = $letters[$zufallszahl];
				        $code .= $buchstabe;
					}

					// add delimiter to the code
					$serial_delimiter = 0;
					if (isset($obj['input_serial_delimiter'])) $serial_delimiter = intval($obj['input_serial_delimiter']);
					if ($serial_delimiter > 0) {
						$serial_delimiter_space = 3;
						if (isset($obj['input_serial_delimiter_space'])) $serial_delimiter_space = intval($obj['input_serial_delimiter_space']);
						$delimiter = ['','-',' ',':'][$serial_delimiter - 1];
						$codeLetters = str_split($code);
						$chunks = array_chunk($codeLetters, $serial_delimiter_space);
						$codeChunks = [];
						foreach($chunks as $chunk) {
							$codeChunks[] = join("", $chunk);
						}
						$code = join($delimiter, $codeChunks);
					}

					// prefix
					if (isset($obj['input_prefix_codes'])) {
						$prefix_code = trim($obj['input_prefix_codes']);
						$prefix_code = str_replace(" ", "_", $prefix_code);
						//$prefix_code = str_replace()
						$code = $prefix_code.$code;
					}
				}
			}
		}
		return $code;
	}
	public function addRetrictionCodeToOrder($code, $list_id, $order_id, $product_id = 0, $item_id = 0) {
		if (!empty($code)) {
			return $this->addWoocommerceInfoToCode([
										'code'=>$code,
										'list_id'=>intval($list_id),
										'order_id'=>$order_id,
										'product_id'=>$product_id,
										'item_id'=>$item_id,
										'is_restriction_purchase'=>1
										]);
		}
	}
	private function addWoocommerceInfoToCode($data) {
		// $data = ['code'=>$data["code"], 'list_id'=>$list_id, 'order_id'=>$order_id, 'product_id'=>$product_id, 'item_id'=>$item_id]
		if (!isset($data['code'])) throw new Exception("#9601 code is missing");
		if (!isset($data['order_id'])) throw new Exception("#9602 order id is missing");
		//if (!isset($data['product_id'])) throw new Exception("#9603 product id is missing");
		//if (!isset($data['item_id'])) throw new Exception("#9604 item id is missing"); // position within the order
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);

		$key = 'woocommerce';
		if (isset($data['is_restriction_purchase']) && $data['is_restriction_purchase'] == 1) {
			$key = 'wc_rp';
		}

		$order = wc_get_order( $data['order_id'] );
		$metaObj[$key] = ['order_id'=>$data['order_id'], 'creation_date'=>date("Y-m-d H:i:s")];
		if (isset($data['product_id'])) $metaObj[$key]['product_id'] = $data['product_id'];
		if (isset($data['item_id'])) $metaObj[$key]['item_id'] = $data['item_id'];
		$metaObj[$key]['user_id'] = intval($order->get_user_id());

		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);
		$this->getDB()->update("codes", ["meta"=>$codeObj['meta']], ['id'=>$codeObj['id']]);

		$this->getCore()->triggerWebhooks(10, $codeObj);

		return $codeObj;
	}
	public function removeWoocommerceOrderInfoFromCode($data) {
		if (!isset($data['code'])) throw new Exception("#9611 code is missing");
		if (class_exists( 'WooCommerce' )) {
			// include Woocommerce file for delete
			if ( !function_exists( 'wc_get_order' ) ) {
				require_once ABSPATH . PLUGINDIR . '/woocommerce/includes/wc-order-functions.php';
			}
			// include Woocommerce file for delete
			if ( !function_exists( 'wc_delete_order_item_meta' ) ) {
				require_once ABSPATH . PLUGINDIR . '/woocommerce/includes/wc-order-item-functions.php';
			}
		}
		// lade code
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		if ($codeObj['order_id'] > 0 || $metaObj['woocommerce']['order_id'] > 0) {

			// extrahiere item id
			$item_id = isset($metaObj['woocommerce']['item_id']) ? $metaObj['woocommerce']['item_id'] : 0;
			if ($item_id > 0) {
				if (class_exists( 'WooCommerce' )) {
					$this->MAIN->getWC()->deleteCodesEntryOnOrderItem($item_id);
				}
				$this->removeWoocommerceTicketForCode($data);
			}

			/* delete all serial codes from the whole order
			$order_id = $codeObj['order_id'];
			if (class_exists( 'WooCommerce' )) {
				$order = wc_get_order( $order_id );
				if ($order) {
					foreach ( $order->get_items() as $item_id => $item ) {
						wc_delete_order_item_meta( $item_id, '_saso_eventtickets_product_code' );
						wc_delete_order_item_meta( $item_id, '_saso_eventticket_code_list' );
					}
				}
			}
			*/
		}
		// leere meta woocommerce
		$defMeta = $this->getCore()->getMetaObject();
		$metaObj['woocommerce'] = $defMeta['woocommerce'];
		$codeObj['order_id'] = 0;
		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);

		$felder = ['order_id'=>0, 'semaphorecode'=>'', "meta"=>$codeObj['meta']];
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'removeWoocommerceOrderInfoFromCode')) {
			$felder = $this->MAIN->getPremiumFunctions()->removeWoocommerceOrderInfoFromCode($codeObj, $felder, $data);
		}
		$where = ['id'=>intval($codeObj['id'])];

		$this->getDB()->update("codes", $felder, $where);
		$this->getCore()->triggerWebhooks(11, $codeObj);
		return $codeObj;
	}
	public function removeWoocommerceRstrPurchaseInfoFromCode($data) {
		if (!isset($data['code'])) throw new Exception("#9611 code is missing");
		// lade code
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);

		// purchase restrictions do not add an order_id to the code level
		if ($metaObj['wc_rp']['order_id'] > 0) {
			if (class_exists( 'WooCommerce' )) {
				// include Woocommerce file for delete
				if ( !function_exists( 'wc_get_order' ) ) {
					require_once ABSPATH . PLUGINDIR . '/woocommerce/includes/wc-order-functions.php';
				}
				if ( !function_exists( 'wc_delete_order_item_meta' ) ) {
					require_once ABSPATH . PLUGINDIR . '/woocommerce/includes/wc-order-item-functions.php';
				}
				// extrahiere item id
				$item_id = isset($metaObj['wc_rp']['item_id']) ? $metaObj['wc_rp']['item_id'] : 0;
				if ($item_id > 0) {
					$this->MAIN->getWC()->deleteRestrictionEntryOnOrderItem($item_id);
				}
			}
		}

		// leere meta wc_rp
		$defMeta = $this->getCore()->getMetaObject();
		$metaObj['wc_rp'] = $defMeta['wc_rp'];
		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);

		$felder = ["meta"=>$codeObj['meta']];
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'removeWoocommerceRstrPurchaseInfoFromCode')) {
			$felder = $this->MAIN->getPremiumFunctions()->removeWoocommerceRstrPurchaseInfoFromCode($codeObj, $felder, $data);
		}
		$where = ['id'=>intval($codeObj['id'])];

		$this->getDB()->update("codes", $felder, $where);
		return $codeObj;
	}
	private function removeRedeemWoocommerceTicketForCodeBulk($data) {
		if (!isset($data['codes'])) throw new Exception("#9237 codes are missing");
		if (!is_array($data['codes'])) throw new Exception("#9238 codes must be an array");
		set_time_limit(0);
		$ret = [];
		foreach($data['codes'] as $v) {
			$_data = ['code'=>$v];
			$ret[$v] = $this->removeRedeemWoocommerceTicketForCode($_data);
		}
		return ["count"=>count($data['codes']), "ret"=>$ret];
	}
	private function removeRedeemWoocommerceTicketForCode($data) {
		if (!isset($data['code'])) throw new Exception("#9621 code is missing");
		// lade code
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);

		// leere meta wc_ticket
		$defMeta = $this->getCore()->getMetaObject();
		$metaObj['used'] = $defMeta['used'];
		//$idcode = $metaObj['wc_ticket']['idcode'];
		//$_url = $metaObj['wc_ticket']['_url'];
		//$_public_ticket_id = $metaObj['wc_ticket']['_public_ticket_id'];
		//$namePerTicket = $metaObj['wc_ticket']['name_per_ticket'];
		//$metaObj['wc_ticket'] = $defMeta['wc_ticket'];
		//$metaObj['wc_ticket']['is_ticket'] = 1;
		//$metaObj['wc_ticket']['idcode'] = $idcode;
		//$metaObj['wc_ticket']['_url'] = $_url;
		//$metaObj['wc_ticket']['_public_ticket_id'] = $_public_ticket_id;
		$metaObj['wc_ticket']['ip'] = "";
		$metaObj['wc_ticket']['userid'] = 0;
		$metaObj['wc_ticket']['_username'] = "";
		$metaObj['wc_ticket']['redeemed_date'] = "";
		$metaObj['wc_ticket']['redeemed_by_admin'] = 0;
		$metaObj['wc_ticket']['set_by_admin'] = 0;
		$metaObj['wc_ticket']['set_by_admin_date'] = "";
		$metaObj['wc_ticket']['stats_redeemed'] = [];

		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);

		$felder = ["meta"=>$codeObj['meta'], "redeemed"=>0];
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'removeRedeemWoocommerceTicketForCode')) {
			$felder = $this->MAIN->getPremiumFunctions()->removeRedeemWoocommerceTicketForCode($codeObj, $felder, $data);
		}
		$where = ['id'=>intval($codeObj['id'])];

		$this->getDB()->update("codes", $felder, $where);
		$this->getCore()->triggerWebhooks(14, $codeObj);
		return $codeObj;
	}
	public function removeWoocommerceTicketForCode($data) {
		if (!isset($data['code'])) throw new Exception("#9625 code is missing");
		if (class_exists( 'WooCommerce' )) {
			// include Woocommerce file for delete
			if ( !function_exists( 'wc_get_order' ) ) {
				require_once ABSPATH . PLUGINDIR . '/woocommerce/includes/wc-order-functions.php';
			}
			if ( !function_exists( 'wc_delete_order_item_meta' ) ) {
				require_once ABSPATH . PLUGINDIR . '/woocommerce/includes/wc-order-item-functions.php';
			}
		}
		// lade code
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
		if ($codeObj['order_id'] > 0 || $metaObj['woocommerce']['order_id'] > 0) {
			if (class_exists( 'WooCommerce' )) {
				// extrahiere item id
				$item_id = isset($metaObj['woocommerce']['item_id']) ? $metaObj['woocommerce']['item_id'] : 0;
				if ($item_id > 0) {
					wc_delete_order_item_meta( $item_id, '_saso_eventtickets_is_ticket' );
				}
			}
		}

		$order_id = intval($metaObj['woocommerce']['order_id']);
		if (class_exists( 'WooCommerce' )) {
			$order = new WC_Order( $order_id );

			// set order note
			$order->add_order_note( "Ticket number: ".$codeObj['code_display']." for order item of product #".intval($metaObj['woocommerce']['product_id'])." removed." );

			$order->update_meta_data( '_saso_eventtickets_order_idcode', "" );
			$order->save();
		}

		// leere meta woocommerce
		$defMeta = $this->getCore()->getMetaObject();
		$idcode = $metaObj['wc_ticket']['idcode'];
		$metaObj['wc_ticket'] = $defMeta['wc_ticket'];
		$metaObj['wc_ticket']['idcode'] = $idcode;
		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);

		$felder = ["meta"=>$codeObj['meta']];
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'removeWoocommerceTicketForCode')) {
			$felder = $this->MAIN->getPremiumFunctions()->removeWoocommerceTicketForCode($codeObj, $felder, $data);
		}
		$where = ['id'=>intval($codeObj['id'])];

		$this->getDB()->update("codes", $felder, $where);
		$this->getCore()->triggerWebhooks(15, $codeObj);
		return $codeObj;
	}
	private function redeemWoocommerceTicketForCode($data) {
		if (!isset($data['code'])) throw new Exception("#9622 code is missing");
		// lade code
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$codeObj = apply_filters( $this->MAIN->_do_action_prefix.'filter_setExpirationDateFromDays', $codeObj );
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);

		$max_redeem_amount = $this->MAIN->getTicketHandler()->getMaxRedeemAmountOfTicket($codeObj);
		if ($max_redeem_amount > 1) {
			$redeemed_counter = count($metaObj['wc_ticket']['stats_redeemed']);
			if ($redeemed_counter >= $max_redeem_amount) {
				throw new Exception(sprintf(/* translators: 1: redeemed ticket counter 2: max redeem possible */esc_html__('Ticket cannot be redeemed anymore. All redeem operations are used up. %1$d of %2$d.', 'event-tickets-with-ticket-scanner'), $redeemed_counter, $max_redeem_amount));
			}
		}

		if (empty($metaObj['wc_ticket']['redeemed_date']) || $max_redeem_amount > 1) {
			$stat_redeem = [];
			$metaObj['wc_ticket']['redeemed_date'] = date("Y-m-d H:i:s");
			$metaObj['wc_ticket']['ip'] = $this->getCore()->getRealIpAddr();
			if (isset($data['userid'])) $metaObj['wc_ticket']['userid'] = intval($data['userid']);
			if (is_admin() || isset($data['redeemed_by_admin'])) {
				// kann sein, dass der admin nicht eingeloggt ist (externer mitarbeiter)
				$metaObj['wc_ticket']['redeemed_by_admin'] = get_current_user_id();
			}

			$stat_redeem['redeemed_date'] = $metaObj['wc_ticket']['redeemed_date'];
			$stat_redeem['ip'] = $metaObj['wc_ticket']['ip'];
			$stat_redeem['userid'] = $metaObj['wc_ticket']['userid'];
			$stat_redeem['redeemed_by_admin'] = $metaObj['wc_ticket']['redeemed_by_admin'];
			$metaObj['wc_ticket']['stats_redeemed'][] = $stat_redeem;

			// set the last usage
			$metaObj['used']['reg_ip'] = $this->getCore()->getRealIpAddr();
			$metaObj['used']['reg_request'] = $metaObj['wc_ticket']['redeemed_date'];
			if (isset($data['userid'])) $metaObj['used']['reg_userid'] = intval($data['userid']);

			$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);

			$felder = ["meta"=>$codeObj['meta'], "redeemed"=>1];
			if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'redeemWoocommerceTicketForCode')) {
				$felder = $this->MAIN->getPremiumFunctions()->redeemWoocommerceTicketForCode($codeObj, $felder, $data);
			}
			$where = ['id'=>intval($codeObj['id'])];

			$this->getDB()->update("codes", $felder, $where);
			$this->getCore()->triggerWebhooks(13, $codeObj);
		}
		return $codeObj;
	}
	private function setWoocommerceTicketForCode($data) {
		if (!isset($data['code'])) throw new Exception("#9623 code is missing");
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);

		if ($metaObj['wc_ticket']['is_ticket'] == 1) return $codeObj; // früher abbruch

		// check order
		if (!isset($metaObj['woocommerce']) || !isset($metaObj['woocommerce']['order_id']) || $metaObj['woocommerce']['order_id'] == 0) throw new Exception("#9624 code is not bound to an order");
		// check if woocommerce exists
		if ( ! class_exists( 'WooCommerce' ) )  throw new Exception("#9625 WooCommerce missing or not active");

		if ( !function_exists( 'wc_get_order' ) ) {
		    require_once ABSPATH . PLUGINDIR . '/woocommerce/includes/wc-order-functions.php';
		}
		// include Woocommerce file for delete
		if ( !function_exists( 'wc_add_order_item_meta' ) || !function_exists('wc_delete_order_item_meta')) {
			require_once ABSPATH . PLUGINDIR . '/woocommerce/includes/wc-order-item-functions.php';
		}

		$order_id = intval($metaObj['woocommerce']['order_id']);
		$order = new WC_Order( $order_id );

		// set order note
		$order->add_order_note( sprintf(/* translators: %1$s: ticket number */esc_html__('Order item changed to ticket with ticket number: %1$s', 'event-tickets-with-ticket-scanner'), $codeObj['code_display']));

		// set
		$item_id = intval($metaObj['woocommerce']['item_id']);
		wc_delete_order_item_meta( $item_id, '_saso_eventtickets_is_ticket' );
		wc_add_order_item_meta( $item_id, '_saso_eventtickets_is_ticket', '1', true);

		// set codeobj meta and set webhook trigger

		$metaObj['wc_ticket']['set_by_admin'] = get_current_user_id();
		$metaObj['wc_ticket']['set_by_admin_date'] = date("d.m.Y H:i:s");

		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);

		$felder = ["meta"=>$codeObj['meta']];
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'setWoocommerceTicketForCode')) {
			$felder = $this->MAIN->getPremiumFunctions()->setWoocommerceTicketForCode($codeObj, $felder, $code);
		}
		$where = ['id'=>intval($codeObj['id'])];

		$this->getDB()->update("codes", $felder, $where);

		return $this->setWoocommerceTicketInfoForCode($data['code']);
	}
	public function setWoocommerceTicketInfoForCode($code, $namePerTicket="") {
		$codeObj = $this->getCore()->retrieveCodeByCode($code);
		$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);

		$metaObj['wc_ticket']['is_ticket'] = 1;
		$namePerTicket = trim($namePerTicket);
		if (!empty($namePerTicket)) {
			$metaObj['wc_ticket']['name_per_ticket'] = substr($namePerTicket, 0, 140);
		}
		if (empty($metaObj['wc_ticket']['idcode']))	$metaObj['wc_ticket']['idcode'] = crc32($codeObj['id']."-".time());
		$metaObj['wc_ticket']['_url'] = $this->getCore()->getTicketURL($codeObj, $metaObj);
		$metaObj['wc_ticket']['_public_ticket_id'] = $this->getCore()->getTicketId($codeObj, $metaObj);

		$codeObj['meta'] = $this->getCore()->json_encode_with_error_handling($metaObj);

		$felder = ["meta"=>$codeObj['meta']];
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'setWoocommerceTicketInfoForCode')) {
			$felder = $this->MAIN->getPremiumFunctions()->setWoocommerceTicketInfoForCode($codeObj, $felder, $code);
		}
		$where = ['id'=>intval($codeObj['id'])];

		$this->getDB()->update("codes", $felder, $where);
		$this->getCore()->triggerWebhooks(12, $codeObj);
		return $codeObj;
	}
	public function addCode($data) {
		if (!isset($data['code']) || trim($data['code']) == "") throw new Exception("#201 code missing");
		$id = $this->_addCode($data['code'], isset($data['list_id']) ? intval($data['list_id']) : 0);
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'updateCodeMetas')) {
			$this->MAIN->getPremiumFunctions()->updateCodeMetas($data['code'], $data);
		}
		return $id;
	}
	public function addCodes($data) {
		if (!isset($data['codes'])) throw new Exception("#211 codes missing");
		if (!is_array($data['codes'])) throw new Exception("#212 codes must be an array");

		$ret = ['ok'=>[], 'notok'=>[]];

		foreach($data['codes'] as $v) {
			$ok = false;
			try {
				$id = $this->_addCode($v, isset($data['list_id']) ? intval($data['list_id']) : 0);
				$ret['ok'][] = $v;
				$ok = true;
			} catch (Exception $e) {
				$ret['notok'][] = $v;
			}
			try {
				if ($ok && $this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'updateCodeMetas')) {
					$this->MAIN->getPremiumFunctions()->updateCodeMetas($v, $data);
				}
			} catch (Exception $e) {}
		}

		$ret['total_size'] = $this->getDB()->getCodesSize();
		return $ret;
	}
	private function editCode($data) {
		if (!isset($data['code']) || trim($data['code']) == "") throw new Exception("#206 code missing");
		$data['code'] = $this->getCore()->clearCode($data['code']);
		$codeObj = $this->getCore()->retrieveCodeByCode($data['code']);
		$felder = [];
		if (isset($data['list_id'])) $felder['list_id'] = intval($data['list_id']);
		if (isset($data['cvv'])) $felder['cvv'] = trim($data['cvv']);
		if (isset($data['code_display'])) $felder['code_display'] = trim($data['code_display']);
		if (isset($data['order_id'])) $felder['order_id'] = intval($data['order_id']);
		if (isset($data['aktiv']) && (intval($data['aktiv']) == 1 || intval($data['aktiv']) == 2)) $felder['aktiv'] = intval($data['aktiv']);
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'setFelderCodeEdit')) {
			$felder = $this->MAIN->getPremiumFunctions()->setFelderCodeEdit($felder, $data, $codeObj);
		}
		if (count($felder) > 0) {
			$where = ["code"=>$this->getDB()->reinigen_in($data['code'])];
			return $this->getDB()->update("codes", $felder, $where);
		}
		return "nothing to update";
	}
	public function removeCode($data) {
		if (!isset($data['code'])) throw new Exception("#207 code is missing");

		// entferne code from produkt
		$this->removeRedeemWoocommerceTicketForCode($data);
		$this->removeWoocommerceOrderInfoFromCode($data); // remove order info
		$this->removeWoocommerceRstrPurchaseInfoFromCode($data); // remove wc_rp info

		$code = $this->getCore()->clearCode($data['code']);
		$sql = "delete from ".$this->getDB()->getTabelle("codes")." where code = '".$this->getDB()->reinigen_in($code)."'";
		$ret = 1;
		//$ret = $this->getDB()->_db_query($sql);

		return $ret;
	}
	private function removeCodes($data) {
		if (!isset($data['ids'])) throw new Exception("#209 ids are missing");
		if (!is_array($data['ids'])) throw new Exception("#210 ids must be an array");
		foreach($data['ids'] as $v) {
			$sql = "delete from ".$this->getDB()->getTabelle("codes")." where id = '".intval($v)."'";
			$this->getDB()->_db_query($sql);
		}
		return count($data['ids']);
	}
	private function emptyTableLists($data) {
		$sql = "update ".$this->getDB()->getTabelle("codes")." set list_id = 0";
		$this->getDB()->_db_query($sql);
		$sql = "delete from ".$this->getDB()->getTabelle("lists");
		return $this->getDB()->_db_query($sql);
	}
	private function emptyTableCodes($data) {
		$sql = "delete from ".$this->getDB()->getTabelle("codes");
		return $this->getDB()->_db_query($sql);
	}
	private function exportTableCodes($data) {
		$delimiters = [',',';','|'];
		$filesuffixes = ['.csv','.txt'];
		$orderbys = ['time', 'code', 'code_display', 'list_name'];
		$orderbydirections = ['asc','desc'];
		$delimiter = $delimiters[0];
		$filesuffix = $filesuffixes[0];
		$orderby = $orderbys[0];
		$orderbydirection = $orderbydirections[0];
		if (isset($data['delimiter']) && isset($delimiters[$data['delimiter']-1])) $delimiter = $delimiters[$data['delimiter']-1];
		if (isset($data['filesuffix']) && isset($filesuffixes[$data['filesuffix']-1])) $filesuffix = $filesuffixes[$data['filesuffix']-1];
		if (isset($data['orderby']) && isset($orderbys[$data['orderby']-1])) $orderby = $orderbys[$data['orderby']-1];
		if (isset($data['orderbydirection']) && isset($orderbydirections[$data['orderbydirection']-1])) $orderbydirection = $orderbydirections[$data['orderbydirection']-1];
		set_time_limit(0);
		// hole daten
		$sql = "select a.*, b.name as list_name from ".$this->getDB()->getTabelle("codes")." a left join ".$this->getDB()->getTabelle("lists")." b on a.list_id = b.id";
		if (isset($data['listchooser']) && !empty($data['listchooser']) && intval($data['listchooser']) > 0) {
			$sql .= " where a.list_id = ".intval($data['listchooser']);
		}
		$sql .= " order by ".$orderby." ".$orderbydirection;
		if (isset($data['rangestart'])) {
			$sql .= " limit ".intval($data['rangestart']);
			if (isset($data['rangeamount'])) {
				$sql .= ", ".intval($data['rangeamount']);
			}
		}
		$daten = $this->getDB()->_db_datenholen($sql);
		foreach($daten as &$row) {
			$row = $this->transformMetaObjectToExportColumn($row);
		}
		// sende csv datei
		$filename = "export_codes_sasoEventtickets_".date("YmdHis").$filesuffix;
		SASO_EVENTTICKETS::_basics_sendeDateiCSVvonDBdaten($daten, $filename, $delimiter);
		exit;
	}
	private function getExportColumnFields() {
		$fields = [
			'meta_validation', 'meta_validation_first_success', 'meta_validation_first_ip', 'meta_validation_last_success', 'meta_validation_last_ip',
			'meta_user', 'meta_user_reg_approved', 'meta_user_reg_request_date', 'meta_user_value', 'meta_user_reg_ip', 'meta_user_reg_userid',
			'meta_expireDate',
			'meta_used', 'meta_used_reg_ip', 'meta_used_reg_request_date', 'meta_used_reg_userid',
			'meta_confirmedCount',
			'meta_woocommerce', 'meta_woocommerce_order_id', 'meta_woocommerce_product_id', 'meta_woocommerce_creation_date', 'meta_woocommerce_item_id', 'meta_woocommerce_user_id',
			'meta_wc_rp', 'meta_wc_rp_order_id', 'meta_wc_rp_product_id', 'meta_wc_rp_creation_date', 'meta_wc_rp_item_id',
			'meta_wc_ticket', 'meta_wc_ticket_is_ticket', 'meta_wc_ticket_ip', 'meta_wc_ticket_userid', 'meta_wc_ticket_redeemed_date', 'meta_wc_ticket_redeemed_by_admin', 'meta_wc_ticket_set_by_admin', 'meta_wc_ticket_set_by_admin_date', 'meta_wc_ticket_idcode', 'meta_wc_ticket_stats_redeemed', 'meta_wc_ticket_public_ticket_id'
			];
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'getExportColumnFields')) {
			$fields = $this->MAIN->getPremiumFunctions()->getExportColumnFields($fields);
		}
		return $fields;
	}
	public function transformMetaObjectToExportColumn($row) {
		$fields = $this->getExportColumnFields();
		foreach($fields as $v) {
			$row[$v] = '';
		}
		// nehme meta object
		if (!empty($row['meta'])) {
			$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($row['meta']);
			// zerlege das object und inhalte in einzelne spalten
			if (isset($metaObj['validation'])) {
				if (!empty($metaObj['validation']['first_success'])) $row['meta_validation_first_success'] = $metaObj['validation']['first_success'];
				if (!empty($metaObj['validation']['first_ip'])) $row['meta_validation_first_ip'] = $metaObj['validation']['first_ip'];
				if (!empty($metaObj['validation']['last_success'])) $row['meta_validation_last_success'] = $metaObj['validation']['last_success'];
				if (!empty($metaObj['validation']['last_ip'])) $row['meta_validation_last_ip'] = $metaObj['validation']['last_ip'];
			}
			if (isset($metaObj['user'])) {
				if (!empty($metaObj['user']['reg_approved'])) $row['meta_user_reg_approved'] = $metaObj['user']['reg_approved'];
				if (!empty($metaObj['user']['reg_request'])) $row['meta_user_reg_request_date'] = $metaObj['user']['reg_request'];
				if (!empty($metaObj['user']['reg_userid'])) $row['meta_user_reg_userid'] = $metaObj['user']['reg_userid'];
				if (!empty($metaObj['user']['value'])) $row['meta_user_value'] = $metaObj['user']['value'];
				if (!empty($metaObj['user']['reg_ip'])) $row['meta_user_reg_ip'] = $metaObj['user']['reg_ip'];
				if (!empty($row['meta_user_reg_request_date'])) $row['meta_user'] = 1;
			}
			if (isset($metaObj['expireDate'])) $row['meta_expireDate'] = $metaObj['expireDate'];
			if (isset($metaObj['used'])) {
				if (!empty($metaObj['used']['reg_ip'])) $row['meta_used_reg_ip'] = $metaObj['used']['reg_ip'];
				if (!empty($metaObj['used']['reg_request'])) $row['meta_used_reg_request_date'] = $metaObj['used']['reg_request'];
				if (!empty($metaObj['used']['reg_userid'])) $row['meta_used_reg_userid'] = $metaObj['used']['reg_userid'];
				if (!empty($row['meta_used_req_request_date'])) $row['meta_used'] = 1;
			}
			if (isset($metaObj['confirmedCount'])) $row['meta_confirmedCount'] = $metaObj['confirmedCount'];
			if (isset($metaObj['woocommerce'])) {
				if (!empty($metaObj['woocommerce']['order_id'])) $row['meta_woocommerce_order_id'] = $metaObj['woocommerce']['order_id'];
				if (!empty($metaObj['woocommerce']['product_id'])) $row['meta_woocommerce_product_id'] = $metaObj['woocommerce']['product_id'];
				if (!empty($metaObj['woocommerce']['creation_date'])) $row['meta_woocommerce_creation_date'] = $metaObj['woocommerce']['creation_date'];
				if (!empty($metaObj['woocommerce']['item_id'])) $row['meta_woocommerce_item_id'] = $metaObj['woocommerce']['item_id'];
				if (!empty($metaObj['woocommerce']['user_id'])) $row['meta_woocommerce_user_id'] = $metaObj['woocommerce']['user_id'];
			}
			if (isset($metaObj['wc_rp'])) {
				if (!empty($metaObj['wc_rp']['order_id'])) $row['meta_wc_rp_order_id'] = $metaObj['wc_rp']['order_id'];
				if (!empty($metaObj['wc_rp']['product_id'])) $row['meta_wc_rp_product_id'] = $metaObj['wc_rp']['product_id'];
				if (!empty($metaObj['wc_rp']['creation_date'])) $row['meta_wc_rp_creation_date'] = $metaObj['wc_rp']['creation_date'];
				if (!empty($metaObj['wc_rp']['item_id'])) $row['meta_wc_rp_item_id'] = $metaObj['wc_rp']['item_id'];
			}
			if (isset($metaObj['wc_ticket'])) {
				if (!empty($metaObj['wc_ticket']['is_ticket'])) $row['meta_wc_ticket_is_ticket'] = $metaObj['wc_ticket']['is_ticket'];
				if (!empty($metaObj['wc_ticket']['ip'])) $row['meta_wc_ticket_ip'] = $metaObj['wc_ticket']['ip'];
				if (!empty($metaObj['wc_ticket']['userid'])) $row['meta_wc_ticket_userid'] = $metaObj['wc_ticket']['userid'];
				if (!empty($metaObj['wc_ticket']['redeemed_date'])) $row['meta_wc_ticket_redeemed_date'] = $metaObj['wc_ticket']['redeemed_date'];
				if (!empty($metaObj['wc_ticket']['redeemed_by_admin'])) $row['meta_wc_ticket_redeemed_by_admin'] = $metaObj['wc_ticket']['redeemed_by_admin'];
				if (!empty($metaObj['wc_ticket']['set_by_admin'])) $row['meta_wc_ticket_redeemed_by_admin'] = $metaObj['wc_ticket']['set_by_admin'];
				if (!empty($metaObj['wc_ticket']['set_by_admin_date'])) $row['meta_wc_ticket_set_by_admin_date'] = $metaObj['wc_ticket']['set_by_admin_date'];
				if (!empty($metaObj['wc_ticket']['idcode'])) $row['meta_wc_ticket_idcode'] = $metaObj['wc_ticket']['idcode'];
				if (!empty($metaObj['wc_ticket']['stats_redeemed'])) $row['meta_wc_ticket_stats_redeemed'] = $this->getCore()->json_encode_with_error_handling($metaObj['wc_ticket']['stats_redeemed']);
				if (!empty($metaObj['wc_ticket']['_public_ticket_id'])) $row['meta_wc_ticket_public_ticket_id'] = $metaObj['wc_ticket']['_public_ticket_id'];
			}
		}
		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'transformMetaObjectToExportColumn')) {
			$row = $this->MAIN->getPremiumFunctions()->transformMetaObjectToExportColumn($row);
		}
		return $row;
	}

	public function performJobsAfterDBUpgraded($dbversion="", $dbversion_pre="") {
		if (empty($dbversion)) {
			$dbversion = $this->getDB()->dbversion;
		}

		if (version_compare( $dbversion, '1.0', '>' ) && version_compare( $dbversion, '2.0', '<' )) {
			$sql = "select id, meta from ".$this->getDB()->getTabelle("codes")." where redeemed = 0 and meta != ''";
			$d = $this->getDB()->_db_datenholen($sql);
			foreach($d as $codeObj) {
				try {
					$metaObj = $this->getCore()->encodeMetaValuesAndFillObject($codeObj['meta'], $codeObj);
					if (!empty($metaObj['wc_ticket']['redeemed_date'])) {
						// update code
						$this->getDB()->update("codes", ["redeemed"=>1], ['id'=>$codeObj['id']]);
					}
				} catch (Exception $e) {
					//var_dump($e->getMessage());
				}
			}
		}

		if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'performJobsAfterDBUpgraded')) {
			$this->MAIN->getPremiumFunctions()->performJobsAfterDBUpgraded($dbversion, $dbversion_pre);
		}
		do_action( $this->MAIN->_do_action_prefix.'performJobsAfterDBUpgraded', $dbversion, $dbversion_pre );
	}

	function show_user_profile($profileuser) {
		// zeigt infos im user profile an
		if ($this->isOptionCheckboxActive("wcTicketUserProfileDisplayRegisteredNumbers")) {
			echo "<h3>Event Tickets Registered</h3>";
			//print_r($profileuser);
			$user_id = intval($profileuser->ID);
			$ret =  $this->MAIN->getMyCodeText($user_id);
			if (empty($ret)) echo "-";
			else echo $ret;
		}
		if ($this->isOptionCheckboxActive("wcTicketUserProfileDisplayBoughtNumbers")) {
			echo "<h3>Event Tickets Numbers</h3>";
			$sql = "select * from ".$this->getDB()->getTabelle("codes")." where
				json_extract(meta, '$.wc_ticket.is_ticket') = 1 and
				json_extract(meta, '$.woocommerce.user_id') = ".$user_id;
			$d = $this->getDB()->_db_datenholen($sql);
			foreach($d as $item) {
				echo $item['code']."<br>";
			}
		}
	}

	private function testing($data) {
		if ( get_option('permalink_structure') ) { echo 'permalinks enabled'; }
		//print_r($data);

		//$ret = $this->MAIN->getOptions()->isOptionCheckboxActive("wcassignmentReuseNotusedCodes");
		//echo $ret;

		//$ret = $this->MAIN->getOptions()->getOption("wcassignmentReuseNotusedCodes");
		//print_r($ret);

		//$ret = $this->getOptionValue("wcTicketAttachTicketToMailOf");
		//print_r($ret);

		//print_r($this->getCore()->retrieveCodeByCode("UJDWTMEGC"));

		//$this->performJobsAfterDBUpgraded("1.1", "1.0");

		// formatter vom product
		//$formatter = get_post_meta( 23, 'saso_eventtickets_list_formatter_values', true );

		// formatter von code list
		//$listObj = $this->getList(['id'=>2]);
		//$metaObj = $this->getCore()->encodeMetaValuesAndFillObjectList($listObj['meta']);
		//$formatter = stripslashes($metaObj['formatter']['format']);
		//return $this->generateCode($formatter);

		/*
		$code = $this->addCodeFromListForOrder(2, 1, 2, 3);

		echo esc_html($code);
		echo "<br>Display Code";
		$sql = "select * from ".$this->getDB()->getTabelle("codes")." where
				code = '". $this->getDB()->reinigen_in($code) ."'";
		$d = $this->getDB()->_db_datenholen($sql);
		print_r($d);
		echo "<br>Reverting order setting";
		$this->getDB()->update("codes", ['semaphorecode'=>"", "order_id"=>0], ["code"=>$code]);
		*/
	}
}
?>