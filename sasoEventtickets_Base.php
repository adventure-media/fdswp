<?php
include(plugin_dir_path(__FILE__)."init_file.php");
class sasoEventtickets_Base {
	private $_isPremInitialized = false;
	private $_maxValues = [];

	private $MAIN = null;

	public function __construct($MAIN) {
		$this->MAIN = $MAIN;
	}
	private function initPrem() {
		if ($this->_isPremInitialized == false) {
			$prem = $this->MAIN->getPremiumFunctions();
			if ($prem != null) {
				if ($this->MAIN->isPremium()) $this->_maxValues = $prem->maxValues();
			} else {
				$this->_maxValues = $this->MAIN->getMaxValues();
			}
			$this->_isPremInitialized = true;
		}
	}
	public function getOPTIONS() { // TODO: entfernen nach 2.0.11 prem
		return $this->MAIN->getOptions();
	}
	public function getCORE($DB=null) { // TODO: parameter kann nach der Prem Version 2.0.11 entfernt werden
		return $this->MAIN->getCore();
	}
	public function getTicketHandler() {
		return $this->MAIN->getTicketHandler();
	}
	public function getMaxValues() {
		$this->initPrem();
		return $this->_maxValues;
	}
	public function getMaxValue($key, $def = 1) {
		$maxValues = $this->getMaxValues();
		if (isset($maxValues[$key])) return $maxValues[$key];
		return $def;
	}
	public function premiumCheck_isAllowedAddingList($total) {
		if ($this->getMaxValue('lists') == 0) return true;
		if ($total > $this->getMaxValue('lists')) return false;
		return true;
	}
	public function premiumCheck_isAllowedAddingCode($total) {
		if ($this->getMaxValue('codes_total') == 0) return true;
		if ($total > $this->getMaxValue('codes_total')) return false;
		return true;
	}
}
?>