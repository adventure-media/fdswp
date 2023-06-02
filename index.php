<?php
/**
 * Plugin Name: Event Tickets with Ticket Scanner
 * Plugin URI: https://nikolov.org/wp-plugins/event-tickets-with-ticket-scanner/
 * Description: You can create and generate tickets and codes. You can redeem the tickets at entrance using the built-in ticket scanner. You customer can download a PDF with the ticket information. The Premium allows you also to activate user registration and more. This allows your user to register them self to a ticket.
 * Version: 1.4.7
 * Author: Saso Nikolov
 * Author URI: https://nikolov.org/wp-plugins
 * Text Domain: event-tickets-with-ticket-scanner
  *
 * Event Tickets with Ticket Scanner is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */
// https://semver.org/
// https://developer.wordpress.org/plugins/security/securing-output/
// https://developer.wordpress.org/plugins/security/securing-input/

include(plugin_dir_path(__FILE__)."init_file.php");

if (!defined('SASO_EVENTTICKETS_PLUGIN_VERSION'))
	define('SASO_EVENTTICKETS_PLUGIN_VERSION', '1.4.7');
if (!defined('SASO_EVENTTICKETS_PLUGIN_DIR_PATH'))
	define('SASO_EVENTTICKETS_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));

include_once plugin_dir_path(__FILE__)."SASO_EVENTTICKETS.php";

class sasoEventtickets {
	private $_js_version;
	private $_js_file = 'saso-eventtickets-validator.js';
	private $_js_nonce = 'sasoEventtickets';
	public $_do_action_prefix = 'saso_eventtickets_';
	public $_add_filter_prefix = 'saso_eventtickets_';
	protected $_prefix = 'sasoEventtickets';
	protected $_shortcode = 'sasoEventTicketsValidator';
	protected $_shortcode_mycode = 'sasoEventTicketsValidator_code';
	protected $_shortcode_ticket_scanner = 'sasoEventTicketsValidator_ticket_scanner';
	protected $_divId = 'sasoEventtickets';

	private $_isPrem = null;
	private $_premium_plugin_name = 'event-tickets-with-ticket-scanner-premium';
	private $_premium_function_file = 'sasoEventtickets_PremiumFunctions.php';
	private $PREMFUNCTIONS = null;
	private $BASE = null;
	private $CORE = null;
	private $ADMIN = null;
	private $FRONTEND = null;
	private $OPTIONS = null;
	private $WC = null;

	private $isAllowedAccess = null;

	public function __construct() {
		$this->_js_version = $this->getPluginVersion();
		$this->initHandlers();
	}
	public function initHandlers() {
		add_action( 'init', [$this, 'load_plugin_textdomain'] );
		//if (defined( 'WP_DEBUG')) $this->_js_version = time();
		add_action( 'upgrader_process_complete', [$this, 'my_upgrade_function'], 10, 2);
		//add_action('admin_init', [$this, 'initialize_plugin']);
		if (is_admin()) { // called in backend admin, admin-ajax!
			$this->init_backend();
		} else { // called in front end
			$this->init_frontend();
		}
		add_action( 'plugins_loaded', [$this, 'WooCommercePluginLoaded'], 20, 0 );
  		if (basename($_SERVER['SCRIPT_NAME']) == "admin-ajax.php") {
			add_action('wp_ajax_nopriv_'.$this->_prefix.'_executeFrontend', [$this,'executeFrontend_a'], 10, 0); // nicht angemeldete user, sollen eine antwort erhalten
			add_action('wp_ajax_'.$this->_prefix.'_executeFrontend', [$this,'executeFrontend_a'], 10, 0); // falls eingeloggt ist
			add_action('wp_ajax_'.$this->_prefix.'_executeWCBackend', [$this,'executeWCBackend'], 10, 0); // falls eingeloggt ist
		}
	}
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'event-tickets-with-ticket-scanner', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
	public function getPluginPath() {
		return SASO_EVENTTICKETS_PLUGIN_DIR_PATH;
	}
	public function getPluginVersion() {
		return SASO_EVENTTICKETS_PLUGIN_VERSION;
	}
	public function getPluginVersions() {
		$ret = ['basic'=>SASO_EVENTTICKETS_PLUGIN_VERSION, 'premium'=>'', 'debug'=>''];
		if (defined('SASO_EVENTTICKETS_PREMIUM_PLUGIN_VERSION')) {
			$ret['premium'] = SASO_EVENTTICKETS_PREMIUM_PLUGIN_VERSION;
		}
		if (defined('WP_DEBUG') && WP_DEBUG) {
			$ret['debug'] = '<span style="color:red;">'.esc_html__('is active', 'event-tickets-with-ticket-scanner').'</span>';
		}
		return $ret;
	}
	public function getDB() {
		return SASO_EVENTTICKETS::getDB(plugin_dir_path(__FILE__), "sasoEventticketsDB", $this);
	}
	public function getBase() {
		if ($this->BASE == null) {
			if (!class_exists('sasoEventtickets_Base')) {
				include_once plugin_dir_path(__FILE__)."sasoEventtickets_Base.php";
			}
			$this->BASE = new sasoEventtickets_Base($this);
		}
		return $this->BASE;
	}
	public function getCore() {
		if ($this->CORE == null) {
			if (!class_exists('sasoEventtickets_Core')) {
				include_once plugin_dir_path(__FILE__)."sasoEventtickets_Core.php";
			}
			$this->CORE = new sasoEventtickets_Core($this);
		}
		return $this->CORE;
	}
	public function getAdmin() {
		if ($this->ADMIN == null) {
			if (!class_exists('sasoEventtickets_AdminSettings')) {
				include_once plugin_dir_path(__FILE__)."sasoEventtickets_AdminSettings.php";
			}
			$this->ADMIN = new sasoEventtickets_AdminSettings($this);
		}
		return $this->ADMIN;
	}
	public function getFrontend() {
		if ($this->FRONTEND == null) {
			if (!class_exists('sasoEventtickets_Frontend')) {
				include_once plugin_dir_path(__FILE__)."sasoEventtickets_Frontend.php";
			}
			$this->FRONTEND = new sasoEventtickets_Frontend($this);
		}
		return $this->FRONTEND;
	}
	public function getOptions() {
		if ($this->OPTIONS == null) {
			if (!class_exists('sasoEventtickets_Options')) {
				include_once plugin_dir_path(__FILE__)."sasoEventtickets_Options.php";
			}
			$this->OPTIONS = new sasoEventtickets_Options($this, $this->_prefix);
			$this->OPTIONS->initOptions();
		}
		return $this->OPTIONS;
	}
	public function getWC() {
		if ($this->WC == null) {
			if (!class_exists('sasoEventtickets_WC')) {
				include_once dirname(__FILE__).'/woocommerce-hooks.php';
			}
			$this->WC = new sasoEventtickets_WC($this);
		}
		return $this->WC;
	}
	public function getTicketHandler() {
		if (!class_exists('sasoEventtickets_Ticket')) {
			include_once plugin_dir_path(__FILE__)."sasoEventtickets_Ticket.php";
		}
		return sasoEventtickets_Ticket::Instance($_SERVER["REQUEST_URI"]);
	}
	public function getTicketBadgeHandler() {
		if (!class_exists('sasoEventtickets_TicketBadge')) {
			include_once plugin_dir_path(__FILE__)."sasoEventtickets_TicketBadge.php";
		}
		return sasoEventtickets_TicketBadge::Instance();
	}
	public function getPremiumFunctions() {
		if ($this->_isPrem == null && $this->PREMFUNCTIONS == null) {
			$this->_isPrem = false;
			$premPluginFolder = $this->getPremiumPluginFolder();
			$file = $premPluginFolder.$this->_premium_function_file;
			$premiumFile = plugin_dir_path(__FILE__)."../".$file;
			if (file_exists($premiumFile)) { // check ob active ist nicht nötig, das das getPremiumPluginFolder schon macht
				if (!class_exists('sasoEventtickets_PremiumFunctions')) {
					include_once $premiumFile;
				}
				$this->PREMFUNCTIONS = new sasoEventtickets_PremiumFunctions($this, plugin_dir_path(__FILE__), $this->_prefix, $this->getDB());
				$this->_isPrem = $this->PREMFUNCTIONS->isPremium();
			}
		}
		return $this->PREMFUNCTIONS;
	}
	private function getPremiumPluginFolder() {
		$plugins = get_option('active_plugins', []);
		$premiumFile = "";
		foreach($plugins as $plugin) {
			if (strpos(" ".$plugin, $this->_premium_plugin_name) > 0) {
				$premiumFile = plugin_dir_path($plugin);
				break;
			}
		}
		return $premiumFile;
	}
	public function isPremium() {
		if ($this->_isPrem == null) $this->getPremiumFunctions();
		return $this->_isPrem;
	}
	public function getPrefix() {
		return $this->_prefix;
	}

	public function getMaxValues() {
		return ['storeip'=>false,'allowuserreg'=>false,'codes_total'=>50,'codes'=>50,'lists'=>5];
	}
	public function my_upgrade_function( $upgrader_object, $options ) {
		$this->getCore()->my_upgrade_function( $upgrader_object, $options );
	}
	/**
	* check for ticket detail page request
	*/
	private function wc_checkTicketDetailPage() {
		include_once("SASO_EVENTTICKETS.php");
		// /wp-content/plugins/serial-codes-generator-and-validator/ticket/
		$p = $this->getCore()->getTicketURLPath(true);
		$t = explode("/", $_SERVER["REQUEST_URI"]);

		if ($t[count($t)-2] != "scanner") {
			if(substr($_SERVER["REQUEST_URI"], 0, strlen($p)) == $p) {
				$this->getTicketHandler()->initFilterAndActions();
			} else {
				$wcTicketCompatibilityModeURLPath = trim($this->getOptions()->getOptionValue('wcTicketCompatibilityModeURLPath'));
				$wcTicketCompatibilityModeURLPath = trim(trim($wcTicketCompatibilityModeURLPath, "/"));
				if (!empty($wcTicketCompatibilityModeURLPath)) {
					$pos = strpos($_SERVER["REQUEST_URI"], $wcTicketCompatibilityModeURLPath);
					if ($pos > 0) {
						$this->getTicketHandler()->initFilterAndActions();
					}
				}
			}
		}

		if ($t[count($t)-2] == "scanner") {
			if(substr($_SERVER["REQUEST_URI"], 0, strlen($p)) == $p) {
				//$this->replacingShortcodeTicketScanner();
				$this->getTicketHandler()->initFilterAndActionsTicketScanner();
			} else {
				$wcTicketCompatibilityModeURLPath = trim($this->getOptions()->getOptionValue('wcTicketCompatibilityModeURLPath'));
				$wcTicketCompatibilityModeURLPath = trim(trim($wcTicketCompatibilityModeURLPath, "/"));
				if (!empty($wcTicketCompatibilityModeURLPath)) {
					$pos = strpos($_SERVER["REQUEST_URI"], $wcTicketCompatibilityModeURLPath."/scanner/");
					if ($pos > 0) {
						$this->getTicketHandler()->initFilterAndActionsTicketScanner();
					}
				}
			}
		}

		add_action('rest_api_init', function () {
			SASO_EVENTTICKETS::setRestRoutesTicket();
		});
	}
	public function WooCommercePluginLoaded() {
		$this->getWC(); // um die wc handler zu laden
		// set routing -- NEEDS to be replaced by add_rewrite_rule later
		$this->wc_checkTicketDetailPage();
		// set all WC handler
			// sind noch alle in woocommerce-hooks.php
	}
	private function init_frontend() {
		add_shortcode($this->_shortcode, [$this, 'replacingShortcode']);
		add_shortcode($this->_shortcode_mycode, [$this, 'replacingShortcodeMyCode']);
		add_shortcode($this->_shortcode_ticket_scanner, [$this, 'replacingShortcodeTicketScanner']);
	}
	private function init_backend() {
		add_action('admin_menu', [$this, 'register_options_page']);
		register_activation_hook(__FILE__, [$this, 'plugin_activated']);
		register_deactivation_hook( __FILE__, [$this, 'plugin_deactivated'] );
		//register_uninstall_hook( __FILE__, 'sasoEventticketsDB::plugin_uninstall' );  // MUSS NOCH GETESTE WERDEN
		add_action( 'plugins_loaded', [$this, 'plugins_loaded'] );
		add_action( 'show_user_profile', [$this, 'show_user_profile'] );

		if (basename($_SERVER['SCRIPT_NAME']) == "admin-ajax.php") {
			add_action('wp_ajax_'.$this->_prefix.'_executeAdminSettings', [$this,'executeAdminSettings_a'], 10, 0);
		}
	}
	public function plugin_deactivated(){
		sasoEventticketsDB::plugin_deactivated();
	}
	public static function plugin_uninstall(){
    	sasoEventticketsDB::plugin_uninstall();
		sasoEventtickets_AdminSettings::plugin_uninstall();
	}
	public function plugin_activated($is_network_wide=false) { // und auch für updates, macht es einfacher
		$this->getDB(); // um installiere Tabellen auszuführen
    	update_option('SASO_EVENTTICKETS_PLUGIN_VERSION', SASO_EVENTTICKETS_PLUGIN_VERSION);
		$this->getAdmin()->generateFirstCodeList();
		do_action( $this->_do_action_prefix.'activated' );
	}
	public function plugins_loaded() {
		if (SASO_EVENTTICKETS_PLUGIN_VERSION !== get_option('SASO_EVENTTICKETS_PLUGIN_VERSION', '')) $this->plugin_activated(); // vermutlich wurde die aktivierung übersprungen, bei änderungen direkt an den files
	}
    public function initialize_plugin() {
		$this->getDB(); // um installiere Tabellen auszuführen
		do_action( $this->_do_action_prefix.'initialized' );
    }
	function show_user_profile($profileuser) {
		return $this->getAdmin()->show_user_profile($profileuser);
	}
	function register_options_page() {
	  	add_options_page(__('Event Tickets', 'event-tickets-with-ticket-scanner'), 'Event Tickets', 'manage_options', 'event-tickets-with-ticket-scanner', [$this,'options_page']);
	  	add_menu_page( __('Event Tickets', 'event-tickets-with-ticket-scanner'), 'Event Tickets', 'manage_options', 'event-tickets-with-ticket-scanner', [$this,'options_page'], plugins_url( "",__FILE__ )."/img/icon_event-tickets-with-ticket-scanner_18px.gif", null );
	}

	function options_page() {
		$allowed = $this->isUserAllowedToAccessAdminArea();
		if ( !current_user_can( 'manage_options' ) || !$allowed )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'event-tickets-with-ticket-scanner' ) );
		}

		// einbinden das js starter skript
		$js_url = $this->_js_file."?_v=".$this->_js_version;
		if (defined( 'WP_DEBUG')) $js_url .= '&debug=1';

		wp_enqueue_media(); // um die js wp.media lib zu laden
		wp_register_script( 'ajax_script_backend', plugins_url( $js_url,__FILE__ ) );
        wp_enqueue_script(
            'ajax_script_backend',
            plugins_url( $js_url,__FILE__ ),
            array('jquery', 'jquery-ui-dialog', 'wp-i18n')
        );
		$js_url = "jquery.qrcode.min.js?_v=".$this->_js_version;
		wp_enqueue_script(
			'ajax_script2',
			plugins_url( "3rd/".$js_url,__FILE__ ),
			array('jquery', 'jquery-ui-dialog')
		);

		wp_set_script_translations('ajax_script_backend', 'event-tickets-with-ticket-scanner', __DIR__.'/languages');
		wp_enqueue_style("wp-jquery-ui-dialog");

		// per script eine variable einbinden, die url hat den wp-admin prefix
		// damit im backend.js dann die richtige callback url genutzt werden kann
        wp_localize_script(
            'ajax_script_backend',
            'Ajax_'.$this->_prefix, // name der injected variable
            array(
            	'_plugin_home_url' =>plugins_url( "",__FILE__ ),
            	'_action' => $this->_prefix.'_executeAdminSettings',
            	'_max'=>$this->getBase()->getMaxValues(),
            	'_isPremium'=>$this->isPremium(),
            	'_isUserLoggedin'=>is_user_logged_in(),
            	'_premJS'=>$this->isPremium() ? $this->getPremiumFunctions()->getJSBackendFile() : '',
                'url'   => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( $this->_js_nonce ),
                'ajaxActionPrefix' => $this->_prefix,
                'divPrefix' => $this->_prefix,
                'divId' => $this->_divId,
                'jsFiles' => plugins_url( 'backend.js?_v='.$this->_js_version,__FILE__ )
            )
        );

		$versions = $this->getPluginVersions();
		$versions_tail = $versions['basic'].($versions['premium'] != "" ? ', Premium: '.$versions['premium'] : '');
		if ($versions['debug'] != "") $versions_tail .= ', DEBUGMODE: '.$versions['debug'].', LANG: '.determine_locale();
		?>
		<div style="padding-top:10px;">
			<div style="width:100%;clear:both;">
				<div style="height:50px;padding-top:25px;width:180px;float:left;">
					<img style="height:35px;" src="<?php echo plugins_url( "",__FILE__ ); ?>/img/logo_event-tickets-with-ticket-scanner.gif">
				</div>
				<div style="float:left;margin-bottom:10px;">
					<h2 style="margin-bottom:5px;">Event Tickets With WooCommerce <sup><?php _e('Version', 'event-tickets-with-ticket-scanner'); ?>: <?php echo $versions_tail; ?></sup></h2>
					<!--<p><?php _e('Shortcode to display the restriction code check for the user', 'event-tickets-with-ticket-scanner'); ?>: <b>[<?php echo $this->_shortcode; ?>]</b>. <a href="#shortcodedetails"><?php _e('Learn here more about the possible parameters.', 'event-tickets-with-ticket-scanner'); ?></a></p>-->
					<?php _e('If you like our plugin, then please give us a', 'event-tickets-with-ticket-scanner'); ?> <a target="_blank" href="https://wordpress.org/support/plugin/event-tickets-with-ticket-scanner/reviews?rate=5#new-post">★★★★★ 5-Star Rating</a>.
					</div>
				</div>
			</div>
			<div style="clear:both;" id="<?php echo esc_attr($this->_divId); ?>"></div>
			<div style="margin-top:100px;">
				<hr>
				<a name="shortcodedetails"></a>
				<h3>Documentation</h3>
				<p><span class="dashicons dashicons-external"></span><a href="https://vollstart.de/event-tickets-with-ticket-scanner/docs/" target="_blank">Click here, to visit the documentation of this plugin.</a></p>
				<h3><?php _e('Plugin Rating', 'event-tickets-with-ticket-scanner'); ?></h3>
				<p><?php _e('If you like our plugin, then please give us a', 'event-tickets-with-ticket-scanner'); ?> <a target="_blank" href="https://wordpress.org/support/plugin/event-tickets-with-ticket-scanner/reviews?rate=5#new-post">★★★★★ 5-Star Rating</a>.</p>
				<h3><?php _e('Ticket Sale option', 'event-tickets-with-ticket-scanner'); ?></h3>
				<p><?php _e('You can use this plugin to sell tickets and even redeem them. Check out the documentation for', 'event-tickets-with-ticket-scanner'); ?> <a target="_blank" href="https://vollstart.de/event-tickets-with-ticket-scanner/docs/#ticket"><?php _e('more details here', 'event-tickets-with-ticket-scanner'); ?></a>.</p>
				<h3><?php _e('Premium Homepage', 'event-tickets-with-ticket-scanner'); ?></h3>
				<p><?php _e('You can find more details about the', 'event-tickets-with-ticket-scanner'); ?> <a target="_blank" href="https://vollstart.de/event-tickets-with-ticket-scanner/"><?php _e('premium version here', 'event-tickets-with-ticket-scanner'); ?></a>.</p>
				<!--
				<h3>Shortcode parameter In- & Output</h3>
				<a href="https://vollstart.de/event-tickets-with-ticket-scanner/docs/" target="_blank">Click here for more help about the options</a>
				<p>You can use your own HTML input, output and trigger component. If you add the parameters (all 3 mandatory to use this feature), then the default input area will not be rendered.</p>
				<ul>
					<li><b>inputid</b><br>inputid="html-element-id". The value of this component will be taken. It need to be an HTML input element. We will access the value-parameter of it.</li>
					<li><b>triggerid</b><br>triggerid="html-element-id". The onclick event of this component will be replaced by our function to call the server validation with the code.</li>
					<li><b>outputid</b><br>outputid="html-element-id". The content of this component will be replaced by the server result after the check . We will use the innerHTML property of it, so use a DIV, SPAN, TD or similar for best results.</li>
				</ul>
				<h3>Shortcode parameter Javascript</h3>
				<p>You can add your Javascript function name. Both parameters are optional and not required. If functions will be called before the code is sent to the server or displaying the result.</p>
				<ul>
					<li><b>jspre</b><br>jspre="function-name". The function will be called. The input parameter will be the code. If your function returns a value, than this returned value will be used otherwise the entered code will be used.</li>
					<li><b>jsafter</b><br>jsafter="function-name". The function will be called. The input parameter will be the result JSON object from the server.</li>
				</ul>
				-->
				<h3><?php _e('Shortcode to display the assigned tickets and codes of an user within a page', 'event-tickets-with-ticket-scanner'); ?></h3>
				<b>[<?php echo esc_html($this->_shortcode_mycode); ?>]</b>
				<h3><?php _e('Shortcode to display the ticket scanner within a page', 'event-tickets-with-ticket-scanner'); ?></h3>
				<?php _e('Useful if you cannot open the ticket scanner due to security issues.', 'event-tickets-with-ticket-scanner'); ?><br>
				<b>[<?php echo esc_html($this->_shortcode_ticket_scanner); ?>]</b>
				<h3><?php _e('PHP Filters', 'event-tickets-with-ticket-scanner'); ?></h3>
				<p><?php _e('You can use PHP code to register your filter functions for the validation check.', 'event-tickets-with-ticket-scanner'); ?>
				<a href="https://vollstart.de/event-tickets-with-ticket-scanner/docs/#filters" target="_blank"><?php _e('Click here for more help about the functions', 'event-tickets-with-ticket-scanner'); ?></a>
				</p>
				<ul>
					<li>add_filter('<?php echo $this->_add_filter_prefix.'beforeCheckCodePre'; ?>', 'myfunc', 20, 1)</li>
					<li>add_filter('<?php echo $this->_add_filter_prefix.'beforeCheckCode'; ?>', 'myfunc', 20, 1)</li>
					<li>add_filter('<?php echo $this->_add_filter_prefix.'afterCheckCodePre'; ?>', 'myfunc', 20, 1)</li>
					<li>add_filter('<?php echo $this->_add_filter_prefix.'afterCheckCode'; ?>', 'myfunc', 20, 1)</li>
				</ul>
				<p style="text-align:center;"><a target="_blank" href="https://vollstart.de">VOLLSTART</a> - More plugins: <a target="_blank" href="https://wordpress.org/plugins/serial-codes-generator-and-validator/">Serial Code Validator</a></p>
			</div>
	  	</div>
		<?php
		do_action( $this->_do_action_prefix.'options_page' );
	}

	private function isUserAllowedToAccessAdminArea() {
		if ($this->isAllowedAccess != null) return $this->isAllowedAccess;
		if ($this->getOptions()->isOptionCheckboxActive('allowOnlySepcificRoleAccessToAdmin')) {
			// check welche rollen
			$user = wp_get_current_user();
			$user_roles = (array) $user->roles;
			if (in_array("administrator", $user_roles)) {
				$this->isAllowedAccess = true;
			} else {
				$adminAreaAllowedRoles = $this->getOptions()->getOptionValue('adminAreaAllowedRoles');
				foreach($adminAreaAllowedRoles as $role_name) {
					if (in_array($role_name, $user_roles)) {
						$this->isAllowedAccess = true;
						break;
					};
				}
			}
		} else {
			$this->isAllowedAccess = true;
		}
		return $this->isAllowedAccess;
	}

	public function executeAdminSettings_a() {
		if (!SASO_EVENTTICKETS::issetRPara('a_sngmbh')) return wp_send_json_success("a_sngmbh not provided");
		return $this->executeAdminSettings(SASO_EVENTTICKETS::getRequestPara('a_sngmbh')); // to prevent WP adds parameters
	}

	public function executeAdminSettings($a=0, $data=null) {
		if ($this->isUserAllowedToAccessAdminArea()) {
			if ($a == 0 && !SASO_EVENTTICKETS::issetRPara('a_sngmbh')) return wp_send_json_success("a not provided");

			if ($data == null) {
				$data = SASO_EVENTTICKETS::issetRPara('data') ? SASO_EVENTTICKETS::getRequestPara('data') : [];
			}
			if ($a == 0 || empty($a) || trim($a) == "") {
				$a = SASO_EVENTTICKETS::getRequestPara('a_sngmbh');
			}
			do_action( $this->_do_action_prefix.'executeAdminSettings', $a, $data );
			return $this->getAdmin()->executeJSON($a, $data);
		}
	}

	public function executeFrontend_a() {
		return $this->executeFrontend(); // to prevent WP adds parameters
	}

	public function executeWCBackend() {
		if (!SASO_EVENTTICKETS::issetRPara('a_sngmbh')) return wp_send_json_success("a_sngmbh not provided");
		$data = SASO_EVENTTICKETS::issetRPara('data') ? SASO_EVENTTICKETS::getRequestPara('data') : [];
		return $this->getWC()->executeJSON(SASO_EVENTTICKETS::getRequestPara('a_sngmbh'), $data);
	}

	public function executeFrontend($a=0, $data=null) {
		$sasoEventtickets_Frontend = $this->getFrontend();
		if (!SASO_EVENTTICKETS::issetRPara('a_sngmbh')) return wp_send_json_success("a not provided");

		if ($data == null) {
			$data = SASO_EVENTTICKETS::issetRPara('data') ? SASO_EVENTTICKETS::getRequestPara('data') : [];
		}
		if ($a == 0 || empty($a) || trim($a) == "") {
			$a = SASO_EVENTTICKETS::getRequestPara('a_sngmbh');
		}
		do_action( $this->_do_action_prefix.'executeFrontend', $a, $data );
		return $sasoEventtickets_Frontend->executeJSON($a, $data);
	}

	public function replacingShortcode($attr=[], $content = null, $tag = '') {
		add_filter( $this->_add_filter_prefix.'replaceShortcode', [$this, 'replaceShortcode'], 10, 3 );
		$ret = apply_filters( $this->_add_filter_prefix.'replaceShortcode', $attr, $content, $tag );
		return $ret;
	}

	public function setTicketScannerJS() {
		// könnte man auch auf ajax umstellen, damit es nicht den rest-service nutzt und den normalen ticket scanner ganz abschalten.??
		$js_url = "jquery.qrcode.min.js?_v=".$this->getPluginVersion();
		wp_enqueue_script(
			'ajax_script',
			plugins_url( "3rd/".$js_url,__FILE__ ),
			array('jquery', 'jquery-ui-dialog')
		);

		$js_url = plugin_dir_url(__FILE__)."3rd/html5-qrcode.min.js?_v=".$this->getPluginVersion();
		wp_register_script('html5-qrcode', $js_url);
		wp_enqueue_script('html5-qrcode');

		$js_url = "ticket_scanner.js?_v=".$this->getPluginVersion();
		if (defined('WP_DEBUG')) $js_url .= time();
		$js_url = plugins_url( $js_url,__FILE__ );

		wp_register_script('ajax_script_ticket_scanner', $js_url);
		wp_enqueue_script(
			'ajax_script_ticket_scanner',
			$js_url,
			array('jquery', 'jquery-ui-dialog', 'wp-i18n')
		);
		wp_set_script_translations('ajax_script_ticket_scanner', 'event-tickets-with-ticket-scanner', __DIR__.'/languages');
        wp_localize_script(
            'ajax_script_ticket_scanner',
            'Ajax_'.$this->_prefix, // name der injected variable
            array(
            	'_plugin_home_url' =>plugins_url( "",__FILE__ ),
            	'_action' => $this->_prefix.'_executeAdminSettings',
            	'_isPremium'=>$this->isPremium(),
            	'_isUserLoggedin'=>is_user_logged_in(),
				'_userId'=>get_current_user_id(),
				'_restPrefixUrl'=>SASO_EVENTTICKETS::getRESTPrefixURL(),
				'_siteUrl'=>get_site_url(),
                'url'   => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'ajaxActionPrefix' => $this->_prefix,
				'IS_PRETTY_PERMALINK_ACTIVATED' => get_option('permalink_structure') ? true :false
            )
        );
	}

	public function replacingShortcodeTicketScanner($attr=[], $content = null, $tag = '') {
		$this->setTicketScannerJS();
		return '
		<center>
        <div style="width:90%;max-width:1024px;">
            <div style="width: 100%; justify-content: center;align-items: center;position: relative;">
                <div class="ticket_content" style="background-color:white;color:black;padding:15px;display:block;position: relative;left: 0;right: 0;margin: auto;text-align:left;border:1px solid black;">
                    <div id="ticket_scanner_info_area"></div>
                    <div id="ticket_info_retrieved" style="padding-top:20px;padding-bottom:20px;"></div>
                    <div id="ticket_info"></div>
                    <div id="ticket_info_btns" style="padding-top:20px;padding-bottom:20px;"></div>
                    <div id="reader_output"></div>
                    <div id="reader" style="width:100%"></div>
                </div>
            </div>
        </div>
        </center>
		';
	}

	public function getMyCodeText($user_id) {
		$ret = '';
		// check ob eingeloggt
		$pre_text = $this->getOptions()->getOptionValue('userDisplayCodePrefix', '');
		if (!empty($pre_text)) $pre_text .= " ";
		//userDisplayCodePrefixAlways

		if ($user_id > 0) {
			// lade codes mit user_id
			$codes = $this->getCore()->getCodesByRegUserId($user_id);
			if (count($codes) > 0) {
				$myCodes = [];
				foreach($codes as $codeObj) {
					$_c = $codeObj['code_display'];
					if ($codeObj['aktiv'] == 1) {
						if ($this->getCore()->checkCodeExpired($codeObj)) {
							$_c .= ' EXPIRED';
						}
					} else if ($codeObj['aktiv'] == 0) {
						$_c .= ' DISABLED';
					} else if ($codeObj['aktiv'] == 2) {
						$_c .= ' REPORTED AS STOLEN';
					}
					$myCodes[] = $_c;
				}
				// ersetze text
				$ret .= $pre_text;
				$sep = $this->getOptions()->getOptionValue('userDisplayCodeSeperator', ', ');
				$ret .= implode($sep, $myCodes);
			}
		}
		if (empty($ret) && $this->getOptions()->isOptionCheckboxActive('userDisplayCodePrefixAlways')) {
			$ret .= $pre_text;
		}
		return $ret;
	}

	public function replacingShortcodeMyCode($attr=[], $content = null, $tag = '') {
		$user_id = get_current_user_id();
		return $this->getMyCodeText($user_id);
	}

	public function replaceShortcode($attr=[], $content = null, $tag = '') {
		// einbinden das js starter skript
		$js_url = $this->_js_file."?_v=".$this->_js_version;
		if (defined( 'WP_DEBUG')) $js_url .= '&debug=1';
		$userDivId = !isset($attr['divid']) || trim($attr['divid']) == "" ? '' : trim($attr['divid']);

		$attr = array_change_key_case( (array) $attr, CASE_LOWER );

      	wp_enqueue_script(
            'ajax_script_validator',
            plugins_url( $js_url,__FILE__ ),
            array('jquery', 'wp-i18n')
        );

		$vars = array(
				'shortcode_attr'=>json_encode($attr),
            	'_plugin_home_url' =>plugins_url( "",__FILE__ ),
            	'_action' => $this->_prefix.'_executeFrontend',
            	'_isPremium'=>$this->isPremium(),
            	'_isUserLoggedin'=>is_user_logged_in(),
            	'_premJS'=>$this->isPremium() ? $this->getPremiumFunctions()->getJSFrontFile() : '',
                'url'   => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( $this->_js_nonce ),
                'ajaxActionPrefix' => $this->_prefix,
                'divPrefix' => $userDivId == "" ? $this->_prefix : $userDivId,
                'divId' => $this->_divId,
                'jsFiles' => plugins_url( 'validator.js?_v='.$this->_js_version, __FILE__ )
            );
		$vars['_messages'] = [
			'msgCheck0'=>$this->getOptions()->getOptionValue('textValidationMessage0'),
			'msgCheck1'=>$this->getOptions()->getOptionValue('textValidationMessage1'),
			'msgCheck2'=>$this->getOptions()->getOptionValue('textValidationMessage2'),
			'msgCheck3'=>$this->getOptions()->getOptionValue('textValidationMessage3'),
			'msgCheck4'=>$this->getOptions()->getOptionValue('textValidationMessage4'),
			'msgCheck5'=>$this->getOptions()->getOptionValue('textValidationMessage5'),
			'msgCheck6'=>$this->getOptions()->getOptionValue('textValidationMessage6')
		];

		if ($this->isPremium()) $this->getPremiumFunctions()->addJSFrontFile();

		wp_set_script_translations('ajax_script_validator', 'event-tickets-with-ticket-scanner', __DIR__.'/languages');

        wp_localize_script(
            'ajax_script_validator',
            'Ajax_'.$this->_prefix, // name der injected variable
            $vars
        );
        $ret = '';
        if (!isset($attr['divid']) || trim($attr['divid']) == "") {
        	$ret = '<div id="'.$this->_divId.'">'.__('...loading...', 'event-tickets-with-ticket-scanner').'</div>';
        }
		return $ret;
	}
}
/**
 * Proper ob_end_flush() for all levels
 *
 * This replaces the WordPress `wp_ob_end_flush_all()` function
 * with a replacement that doesn't cause PHP notices.
 */
remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
add_action( 'shutdown', function() {
   while ( @ob_end_flush() );
} );
$sasoEventtickets = new sasoEventtickets();
?>