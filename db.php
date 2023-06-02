<?php
include(plugin_dir_path(__FILE__)."init_file.php");
class sasoEventticketsDB extends sasoEventtickets_DB {
	public $dbversion = '1.4';
	public function __construct($MAIN) {
		$this->MAIN = $MAIN;
		parent::$dbprefix = "saso_eventtickets_";
		$this->_tabellen = ['lists', 'codes', 'ips'];
		$this->init();
	}

	protected function _system_installiereTabellen() {
		$tabellen = [];
		$tabellen[] = [
			"sql"=>
				"CREATE TABLE ".$this->getTabelle('lists')." (
				id int(32) unsigned NOT NULL auto_increment,
				time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				name varchar(255) NOT NULL DEFAULT '',
				aktiv int(1) unsigned NOT NULL DEFAULT 0,
				meta longtext NOT NULL DEFAULT '',
				PRIMARY KEY (id)) ".$this->getCharsetCollate().";",
			"additional"=>[
				"CREATE UNIQUE INDEX idx1 ON ".$this->getTabelle('lists')." (name)"
			]
		];
		$tabellen[] = [
			"sql"=>
				"CREATE TABLE ".$this->getTabelle('codes')." (
				id int(32) unsigned NOT NULL auto_increment,
				time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				code varchar(150) NOT NULL DEFAULT '',
				code_display varchar(250) NOT NULL DEFAULT '',
				cvv varchar(50) NOT NULL DEFAULT '',
				meta longtext NOT NULL DEFAULT '',
				aktiv int(1) unsigned NOT NULL DEFAULT 0,
				redeemed int(1) unsigned NOT NULL DEFAULT 0,
				list_id int(32) unsigned NOT NULL DEFAULT 0,
				user_id int(32) unsigned NOT NULL DEFAULT 0,
				order_id int(32) unsigned NOT NULL DEFAULT 0,
				semaphorecode varchar(50) NOT NULL DEFAULT '',
				PRIMARY KEY (id)) ".$this->getCharsetCollate().";",
			"additional"=>[
				"CREATE UNIQUE INDEX idx1 ON ".$this->getTabelle('codes')." (code)",
				"CREATE INDEX idx2 ON ".$this->getTabelle('codes')." (time)",
				"CREATE INDEX idx3 ON ".$this->getTabelle('codes')." (order_id)",
				"CREATE INDEX idx4 ON ".$this->getTabelle('codes')." (user_id)",
				"CREATE INDEX idx5 ON ".$this->getTabelle('codes')." (redeemed)"
			]
		];
		$tabellen[] = [
			"sql"=>
				"CREATE TABLE ".$this->getTabelle('ips')." (
				id int(32) unsigned NOT NULL auto_increment,
				time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				code varchar(150) NOT NULL DEFAULT '',
				valid int(1) NOT NULL DEFAULT 0,
				ip varchar(40) NOT NULL DEFAULT '',
				action varchar(150) NOT NULL DEFAULT 'Validation',
				PRIMARY KEY (id)) ".$this->getCharsetCollate().";",
			"additional"=>[
				"CREATE INDEX idx1 ON ".$this->getTabelle('ips')." (code,time)",
				"CREATE INDEX idx2 ON ".$this->getTabelle('ips')." (ip,time)"
			]
		];

		return $tabellen;
	}
}

class sasoEventtickets_DB {
	// irgendwann nach dem https://codex.wordpress.org/Creating_Tables_with_Plugins
	//https://tobier.de/wordpress-plugin-erstellen-datenbank/
	public $dbversion;
	protected static $dbprefix;
	protected $_tabellen = [];
	private $tabellen;
	protected $callerValue = "basic";

	protected $MAIN;

	public function __construct($MAIN) {
		$this->MAIN = $MAIN;
		$this->init();
	}
	protected function init() {
		$this->tabellen = [];
		foreach($this->_tabellen as $t) {
			$this->tabellen[$t] = $this->getPrefix().$t;
		}
	}

	public function getTabelle($tabelle) {
		return $this->tabellen[$tabelle];
	}

	private function getAdminSettings() {
		return $this->MAIN->getAdmin();
	}

	public function reinigen_in($text, $len=0, $addsl=1, $utf=1, $html=0) {
		$text = trim($text);
		if ($len > 0)
		    $text = substr($text, 0, $len);
		if ($utf == 1)
		    $text = utf8_decode($text); // die zeichen sind utf kodiert
		if ($html == 1)
		    $text = htmlentities($text); // zerstört HTML zeug im text
		if ($addsl == 1)
			$text = addslashes($text);
		return $text;
	}

	private function getPrefix() {
		global $wpdb;
		return $wpdb->prefix . self::$dbprefix;
	}

	protected function getCharsetCollate() {
		global $wpdb;
		return $wpdb->get_charset_collate();
	}

	public function _db_datenholen($sql) {
	  	global $wpdb;
	  	//update_option( self::$dbprefix."db_version", "1.4" );
	  	//$installed_ver = get_option( self::$dbprefix."db_version" );
	  	//if ($installed_ver != $this->dbversion) $this->installiereTabellen();
	  	//echo $installed_ver;
	  	return $wpdb->get_results($sql, ARRAY_A);
	}

	public function _db_getRecordCountOfTable($tabelle, $where="") {
		$sql = "select count(*) as anzahl from ".$this->getTabelle($tabelle);
		if ($where != "") $sql .= " where ".$where;
		list($d) = $this->_db_datenholen($sql);
		return $d['anzahl'];
	}

	public function getCodesSize() {
		return $this->_db_getRecordCountOfTable('codes');
	}

	public function insert($tabelle, $felder=[]) {
		global $wpdb;
		if (count($felder) == 0) throw new Exception("no fields provided");
		$wpdb->insert( $this->getTabelle($tabelle), $felder );
		return $wpdb->insert_id;
	}

	public function update($tabelle, $felder, $where) {
		global $wpdb;
		if (count($felder) == 0) throw new Exception("no fields provided");
		if (count($where) == 0) throw new Exception("no where fields provided");
		return $wpdb->update( $this->getTabelle($tabelle), $felder, $where);
	}

	public function _db_query($sql) {
		global $wpdb;
  	    $erg = $wpdb->query($sql);
		if ($erg):
			if (strtolower(substr($sql, 0, 6)) == "insert") {
				return $wpdb->insert_id;
			}
			return $erg;
		else:
			if (!empty($wpdb->last_error)) {
				$this->installiereTabellen(true);
				echo $wpdb->last_error;
				wp_die($wpdb->last_error);
			}
		endif;
		return $erg;
	}

	public function installiereTabellen($force=false) {
		global $wpdb;
		if (empty($this->dbversion)) throw new Exception("dbversion is not set");
		if (empty(self::$dbprefix)) throw new Exception("dbprefix is not set");

		$installed_ver = get_option( self::$dbprefix."db_version" );

		if ($force || $installed_ver != $this->dbversion ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			$tabellen = $this->_system_installiereTabellen(); // array
			foreach($tabellen as $tabelle)  {
				dbDelta( $tabelle['sql'] ); // tabelle erstellen
				if (isset($tabelle['additional'])) {
					$wpdb->suppress_errors = true;
					foreach($tabelle['additional'] as $sql) {
						//echo $sql;
						$wpdb->query($sql); // zusätzlich sql wie index
					}
					$wpdb->suppress_errors = false;
				}
			}

			update_option( self::$dbprefix."db_version", $this->dbversion );
			if ($this->callerValue == "basic") {
				$this->getAdminSettings()->performJobsAfterDBUpgraded($this->dbversion, $installed_ver);
			} else { // wenn für die prem DB dann direkt aufruf
				if ($this->MAIN->isPremium() && method_exists($this->MAIN->getPremiumFunctions(), 'performJobsAfterPremDBUpgraded')) {
					$this->MAIN->getPremiumFunctions()->performJobsAfterPremDBUpgraded($this->dbversion, $installed_ver);
				}
			}
		}
	}
	public static function plugin_deactivated() {
		delete_option(self::$dbprefix."db_version");
	}
	public static function plugin_uninstall(){
		self::plugin_deactivated();
		//delete tabellen
		/*
		global $wpdb;
		foreach($this->tabellen as $key => $value) {
			$wpdb->query("DROP TABLE IF EXISTS ".$value);
		}
		*/
	}
	protected function _system_installiereTabellen()
	{
		throw new Exception("overwrite this function");
	}
}
?>