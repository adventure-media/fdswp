<?php
include(plugin_dir_path(__FILE__)."init_file.php");
if (!class_exists('SASO_EVENTTICKETS', false)) {
    class SASO_EVENTTICKETS {
		static $DB;
		/**
		 * @param $plugin_dir_path plugin_dir_path(__FILE__)
		 */
		public static function getDB($plugin_dir_path, $className, $MAIN) {
			if (self::$DB == null) {
				if (!class_exists($className)) {
					include_once $plugin_dir_path."db.php";
				}
				self::$DB = new $className($MAIN);
				self::$DB->installiereTabellen(); // schützt sich selbst mit eigener option-var
			}
			return self::$DB;
		}
		public static function getMediaData($mediaid) {
			$mediaid = intval($mediaid);
			$filelocation = wp_get_original_image_path($mediaid);
			$meta = wp_get_attachment_metadata( $mediaid );
			$url = wp_get_attachment_url($mediaid);
			$titel = get_the_title($mediaid);
			$suffix = strtolower(substr(strrchr($url, '.'),1));
			if ($suffix == "pdf") {
				$filelocation = get_attached_file($mediaid);
			}
			return ['title'=>$titel,'location'=>$filelocation,'meta'=>$meta,'url'=>$url, "suffix"=>$suffix];
		}
		public static function getRESTPrefixURL() {
			return basename(dirname(__FILE__));
		}
		public static function getRequestPara($name, $def=null) {
			$ret = null;
			if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				if (isset($_POST[$name])) $ret = $_POST[$name];
				if ($ret == null && isset($_GET[$name])) $ret = $_GET[sanitize_text_field($name)];
			}
			if ($_SERVER['REQUEST_METHOD'] === 'GET') {
				if (isset($_GET[$name])) $ret = $_GET[sanitize_text_field($name)];
			}
			if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
				$putdata = fopen("php://input", "r");
				$para = [];
				parse_str($putdata, $para);
				if (isset($para[$name])) $ret = $para[sanitize_text_field($name)];
				else $ret = $para;
			}
			return $ret;
		}
		public static function issetRPara($name) {
			if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				if (isset($_POST[$name])) return true;
				if (isset($_GET[$name])) return true;
				return false;
			}
			if ($_SERVER['REQUEST_METHOD'] === 'GET') {
				if (isset($_GET[$name])) return true;
				return false;
			}
			return false;
		}
		public static function PasswortGenerieren($anzahl=8) {
			$werte = array_merge(array(2,3,4,5,6,7,8,9), array("a","b","c","d","e","f","g","h","j","k","m","n","p","q","r","s","t","w","x","y","z"));
			$pw = "";
			for ($a=0;$a<$anzahl;$a++):
				shuffle($werte);
				$zufallszahl = rand(0, count($werte)-1);
				$buchstabe = $werte[$zufallszahl];
				if ($a == 0 && $buchstabe == ".")
					$buchstabe = "a"; // weil man den Punkt am Anfang nicht sieht
				$pw .= $buchstabe;
			endfor;
			return $pw;
		}
		public static function _basics_sendeDateiCSVvonDBdaten($daten, $filename, $delimiter=";") {
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Content-Description: File Transfer');
			header('Content-type: text/csv');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header('Expires: 0');
			header('Pragma: public');

			ob_end_clean();
			$out = fopen('php://output', 'w');

			if (count($daten) > 0) {
				fputcsv($out, array_keys($daten[0]), $delimiter);
				foreach($daten as $value) {
					fputcsv($out, array_values($value), $delimiter);
				}
			} else {
				fputcsv($out, array("no data"), $delimiter);
			}
			fclose($out);
		}
		public static function sendeDaten($daten, $name, $type)
		{
			#header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			//header('Content-Description: File Transfer');
			header('Content-type: '.$type);
			header('Content-Disposition: inline; filename="'.$name.'"');
			header('Expires: 0');
			header('Pragma: public');
			echo $daten;
		}
		public static function sendeDatei($datei, $bandbreitekontrolle=1, $bandbreite=256, $contenttype=false, $range_start=0, $range_stop=0)
		{
			//existiert die Datei
			if (!file_exists($datei)) {
				// fehler header obliegt dem aufrufer,
				// da unter umständen,
				// was anderes gesendet werden soll
				return false;
			}

			header("Accept-Ranges: bytes");

			if (is_array($contenttype)) {
				if (isset($contenttype['Content-Type'])) {
					header ("Content-Type: ".$contenttype['Content-Type']);
				}
			} else if ($contenttype) {
				$vdatei = $datei;
				switch(substr($vdatei,0,1)){
					case "/":
					case "\\":
						break;
					default:
						switch(substr($vdatei,0,2)){
							case "./":
							case ".\\":
								$vdatei = substr($vdatei,1);
						}
						$vdatei = dirslash(dirname(__FILE__)).$vdatei;
				}

				if (function_exists("finfo_open")){
					$finfo = finfo_open(FILEINFO_MIME_TYPE);
					$mime = finfo_file($finfo, $vdatei);
				} else {
				   $mime = "application/octet-stream";
				}
				header ("Content-Type: ".$mime);
			}

			// range_start und range_stop legen die virtuelle dateigrösse fest
			$von = 0;
			  $size = filesize($datei);
			  if ($range_start > 0)
				   $size -= $range_start;
			  if ($range_stop > $size)
				  $range_stop = 0;
			  if ($range_stop > 0)
				   $size = $range_stop-$range_start + 1;

			//check if http_range is sent by browser (or download manager)
			if(isset($_SERVER['HTTP_RANGE'])) {
				list($a, $range)=explode("=",$_SERVER['HTTP_RANGE']);
				//if yes, download missing part
				list($von,$bis)=explode("-",$range);
				$bis = intval($bis);
				$von = intval($von);
				if ($bis == 0 || $bis < $von || $bis > $size)
					$bis = $size - 1; // bis zum ende
				$range_stop = $bis;
				 //str_replace($range, "-", $range);
				 //$size2=$size-1;
				//$size2=$size;
				 //$new_length=$size2-$range;
				 $new_length = $bis - $von + 1;
				 header("HTTP/1.1 206 Partial Content");
				 header("Content-Length: $new_length");
				 header("Content-Range: bytes ".$von."-".$bis."/".$size);
			} else {
				 $size2=$size;
				 $range_stop = $size - 1;
				 //header("Content-Range: bytes 0-".$size2."/".$size);
				 header("Content-Length: ".$size2);
			}
			header("Content-Transfer-Encoding: binary");
			//open the file
			$fp=fopen($datei,"rb");
			if (!$fp)
				return false;

			// ist die Dateigrösse virtuell angepasst,
			// dann hier noch ein eventuelles http-range korrigieren
			if ($range_start > 0)
				$von += $range_start;

			//seek to start of missing part
			fseek($fp,$von);

			//start buffered download
			$a=0;
			$buffersize = 4096;
			$bandbreite = intval($bandbreite);
			if ($bandbreite < 1)
				$bandbreite = 128; // 32*4*1024 = 128kb
			$wartezeit = $bandbreite * 1000 / $buffersize;

			$gesendetbytes = 0;
			while(!feof($fp))
			{
				if (connection_aborted()) {
					fclose($fp);
					return false;
				}
				//reset time limit for big files
				@set_time_limit(0);
				//print(fread($fp,1024*4));
				//flush();
				// neue buffersize berechnen
		//		if ($buffersize > $range_stop - $gesendetbytes)
		//			$buffersize = $range_stop - $gesendetbytes;
				echo (fread($fp, $buffersize));
				$gesendetbytes += $buffersize;
				if ($range_stop > 0 && $gesendetbytes >= $size)
				  break; // vorzeitig fertig;
				if ($bandbreitekontrolle == 1):
					if ($a<1):
						sleep(1);
						$a=$wartezeit; // wartezeit bevor ich wieder ne sekunde warte
					endif;
					$a--;
				endif;
			}
			fclose($fp);
			return true;
		}

		public static function setRestRoutesTicket() {
			$prefix = SASO_EVENTTICKETS::getRESTPrefixURL();
			register_rest_route($prefix.'/ticket/scanner', '/ping', [
				['methods'=>WP_REST_SERVER::READABLE, 'callback'=>'SASO_EVENTTICKETS::rest_ping', 'permission_callback'=>function(){return true;}]
			]);
			register_rest_route($prefix.'/ticket/scanner', '/retrieve_ticket', [
				['methods'=>WP_REST_SERVER::READABLE, 'callback'=>'SASO_EVENTTICKETS::rest_retrieve_ticket', 'args'=>['code'=>['required'=>true]], 'permission_callback'=>'SASO_EVENTTICKETS::rest_permission_callback']
			]);
			register_rest_route($prefix.'/ticket/scanner', '/redeem_ticket', [
				['methods'=>WP_REST_SERVER::READABLE, 'callback'=>'SASO_EVENTTICKETS::rest_redeem_ticket', 'args'=>['code'=>['required'=>true]], 'permission_callback'=>'SASO_EVENTTICKETS::rest_permission_callback']
			]);
			register_rest_route($prefix.'/ticket/scanner', '/downloadPDFTicketBadge', [
				['methods'=>WP_REST_SERVER::READABLE, 'callback'=>'SASO_EVENTTICKETS::rest_downloadPDFTicketBadge', 'args'=>['code'=>['required'=>true]], 'permission_callback'=>'SASO_EVENTTICKETS::rest_permission_callback']
			]);
		}
		public static function rest_permission_callback($web_request) {
			try {
				include_once plugin_dir_path(__FILE__)."sasoEventtickets_Ticket.php";
				$ticket = sasoEventtickets_Ticket::Instance($_SERVER["REQUEST_URI"]);
				wp_create_nonce( 'wp_rest' );
				return $ticket->rest_permission_callback($web_request);
			} catch (Exception $e) {
				wp_send_json_error($e->getMessage());
			}
			return false;
		}
		public static function rest_ping($web_request) {
			try {
				include_once plugin_dir_path(__FILE__)."sasoEventtickets_Ticket.php";
				$ticket = sasoEventtickets_Ticket::Instance($_SERVER["REQUEST_URI"]);
				$ret = $ticket->rest_ping($web_request);
				$ret['nonce'] = wp_create_nonce( 'wp_rest' );
				wp_send_json_success($ret);
			} catch (Exception $e) {
				wp_send_json_error($e->getMessage());
			}
		}
		public static function rest_retrieve_ticket($web_request) {
			try {
				include_once plugin_dir_path(__FILE__)."sasoEventtickets_Ticket.php";
				$ticket = sasoEventtickets_Ticket::Instance($_SERVER["REQUEST_URI"]);
				$ret = $ticket->rest_retrieve_ticket($web_request);
				$ret['nonce'] = wp_create_nonce( 'wp_rest' );
				wp_send_json_success($ret);
			} catch (Exception $e) {
				wp_send_json_error($e->getMessage());
			}
		}
		public static function rest_redeem_ticket($web_request) {
			try {
				include_once plugin_dir_path(__FILE__)."sasoEventtickets_Ticket.php";
				$ticket = sasoEventtickets_Ticket::Instance($_SERVER["REQUEST_URI"]);
				$ret = $ticket->rest_redeem_ticket($web_request);
				$ret['nonce'] = wp_create_nonce( 'wp_rest' );
				wp_send_json_success($ret);
			} catch (Exception $e) {
				wp_send_json_error($e->getMessage());
			}
		}
		public static function rest_downloadPDFTicketBadge($web_request) {
			try {
				$a = isset($_REQUEST['action']) ? $_REQUEST['action'] : "";
				global $sasoEventtickets;
				$sasoEventtickets->getAdmin()->executeJSON($a, $_REQUEST, true);

				//$ret['nonce'] = wp_create_nonce( 'wp_rest' );
				//wp_send_json_success($ret);
			} catch (Exception $e) {
				wp_send_json_error($e->getMessage());
			}
		}
		public static function isOrderPaid($order) {
			$order_status = $order->get_status();
			// lade wann das ticket als bezahlt gilt und erstelle evtl auch dann erst das ticket auf den order items. Oder als eigene fkt

			//$ok_order_statuses = ['wc-completed', 'completed'];
			$ok_order_statuses = wc_get_is_paid_statuses(); // array( 'processing', 'completed' )
			return in_array($order_status, $ok_order_statuses);
		}
	}
}
?>