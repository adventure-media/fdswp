<?php

use setasign\Fpdi\Tcpdf\Fpdi;

include(plugin_dir_path(__FILE__)."init_file.php");
class sasoEventtickets_PDF {
    private $parts = [];
    private $filemode;
    private $filepath;
    private $filename;
    private $orientation = "P";
    private $page_format = 'A4';
	private $qr;
	private $isRTL = false;
	private $background_image = null;
	private $fontSize = 10;

	private $is_own_page_format = false;
	private $size_width = 210;
	private $size_height = 297;

	private $attach_pdfs = [];

	private $qr_values = [
		'pos'=>['x'=>150, 'y'=>10],
		'size'=>['width'=>50, 'height'=>50],
		'style'=>['position'=>'C'],
		'align'=>'N'
	];

    public function __construct($parts=[], $filemode="I", $filename="PDF.pdf") {
        if (is_array($parts)) $this->setParts($parts);
		// $this->initVars();
		$this->setFilemode($filemode);
		$this->setFilename($filename);
        $this->_loadLibs();
		//$this->initQR();
    }

	private function initVars() {
		/*
		$this->filepath = __DIR__."/pdfouts/";
		if (!file_exists($this->filepath)) {
			//mkdir($this->filepath, 0777);
			//chmod($this->filepath, 0777);
		}
		*/
	}

	public function setAdditionalPDFsToAttachThem($pdfs) {
		if (!is_array($pdfs)) {
			$pdfs = [$pdfs];
		}
		$this->attach_pdfs = $pdfs;
	}

	public function setBackgroundImage($background_image=null) {
		$this->background_image = $background_image;
	}

	public function setQRParams($data) {
		foreach ($data as $key => $value) {
			$this->qr_values[$key] = $value;
		}
	}

	public function setFontSize($number=10) {
		$this->fontSize = intval($number);
	}

	public function initQR() {
		$this->qr = [
			"text"=>"",
			"type"=>"QRCODE,Q",
			"size"=>["width"=>$this->qr_values['size']['width'], "height"=>$this->qr_values['size']['height']],
			"pos"=>["x"=>$this->qr_values['pos']['x'], "y"=>$this->qr_values['pos']['y']],
			"align"=>$this->qr_values['align'],
			"style"=> [
				'position'=>$this->qr_values['style']['position'],
				'border' => 0,
				'vpadding' => 'auto',
				'hpadding' => 'auto',
				'fgcolor' => array(0,0,0),
				'bgcolor' => false, //array(255,255,255)
				'module_width' => 1, // width of a single module in points
				'module_height' => 1 // height of a single module in points
			]
		];
	}

	public function setSize($w, $h) {
		$this->is_own_page_format = true;
		$this->size_width = intval($w);
		$this->size_height = intval($h);
	}
	public function setRTL($rtl=false) {
		$this->isRTL = $rtl;
	}
	public function setQRCodeContent($qr) {
		if ($this->qr == null) {
			$this->initQR();
		}
		foreach ($qr as $key => $value) {
			$this->qr[$key] = $value;
		}
	}
    public function setPageFormat($format) {
        $this->page_format = trim($format);
    }
	public function setOrientation($value){
		// L oder P
		$this->orientation = addslashes(trim($value));
	}
	public function setFilemode($m) {
		$this->filemode = strtoupper($m);
	}

	public function getFilemode() {
		return $this->filemode;
	}
	public function setFilepath($path) {
		$this->filepath = trim($path);
	}
	public function setFilename($p) {
		$this->filename = trim($p);
	}

	public function getFullFilePath() {
		return $this->filepath.$this->filename;
	}
    public function setParts($parts=[]) {
		$this->parts = [];
		foreach($parts as $part) {
			$this->addPart($part);
		}
	}
	public function addPart($part) {
		$teile = explode('{PAGEBREAK}', $part);
		foreach($teile as $teil) {
			$this->parts[] = $teil;
		}
	}

	private function getParts() {
		return $this->parts;
	}

    private function _loadLibs() {
		// Include the main TCPDF library (search the library on the following directories).
		/*
		spl_autoload_register(function($class_name){
			$datei = "vendors/TCPDF/".$class_name.".php";
			if (!file_exists($datei)) {
				$datei = "vendors/TCPDF/".strtolower($class_name).".php";
				if (!file_exists($datei)) {
					$datei = "vendors/TCPDF/".str_replace("\\", "/", $class_name).".php";
					if (!file_exists($datei)) throw new Exception("class not found for autoloading: ".$class_name." in ".$datei);
				}
			}
			include_once($datei);
		});
		*/

		// always load alternative config file for examples
		require_once('vendors/TCPDF/config/tcpdf_config.php');

		// Include the main TCPDF library (search the library on the following directories).
		$tcpdf_include_dirs = array(
			plugin_dir_path(__FILE__).'vendors/TCPDF/tcpdf.php',
			realpath(dirname(__FILE__) . '/vendors/TCPDF/tcpdf.php'),// True source file
			realpath('vendors/TCPDF/tcpdf.php'),// Relative from $PWD
			'/usr/share/php/tcpdf/tcpdf.php',
			'/usr/share/tcpdf/tcpdf.php',
			'/usr/share/php-tcpdf/tcpdf.php',
			'/var/www/tcpdf/tcpdf.php',
			'/var/www/html/tcpdf/tcpdf.php',
			'/usr/local/apache2/htdocs/tcpdf/tcpdf.php'
		);
		foreach ($tcpdf_include_dirs as $tcpdf_include_path) {
			if (@file_exists($tcpdf_include_path)) {
				require_once($tcpdf_include_path);
				break;
			}
		}

		require_once('vendors/FPDI-2.3.7/src/autoload.php');
		require_once("vendors/fpdf185/fpdf.php");
		//require_once("vendors/FPDI-2.3.7/src/Tcpdf/Fpdi.php");

	}

	private function prepareOutputBuffer() {
		if ($this->filemode != "F") ob_clean();
		if ($this->filemode != "F") ob_start();
	}
	private function cleanOutputBuffer() {
		if ($this->filemode != "F") {
			$output_level = ob_get_level();
			for ($a=0;$a<$output_level;$a++) {
				ob_end_clean();
			}
		}
	}
	private function outputPDF($pdf) {
		if ($this->filemode == "F") {
			$pdf->Output($this->filepath.$this->filename, $this->filemode);
		} else {
			$pdf->Output($this->filename, $this->filemode);
		}
	}

	private function getFormat() {
		$format = $this->page_format;
		if ($this->is_own_page_format) {
			$format = [$this->size_width, $this->size_height];
		}
		return $format;
	}

	private function checkFilePath() {
		if (empty($this->filepath)) $this->filepath = get_temp_dir();
	}

	private function attachPDFs($pdf, $pdf_filelocations=[]) {
		if (count($pdf_filelocations) > 0) {
			foreach($pdf_filelocations as $pdf_filelocation) {
				// mergen und entsprechend dem filemode senden
				$pagenumbers = $pdf->setSourceFile($pdf_filelocation);
				for ($a=1;$a<=$pagenumbers;$a++) {
					$tplIdx = $pdf->importPage($a);
					$pdf->AddPage();
					$pdf->useTemplate($tplIdx,0,0,null,null,true);
				}
			}
		}
		return $pdf;
	}

	public function mergeFiles($pdf_filelocations=[]) {
		if (count($pdf_filelocations) == 0) throw new Exception("no files to merge");
		$this->prepareOutputBuffer();
		$this->checkFilePath();
		$format = $this->getFormat();
		$pdf = new FPDI($this->orientation, PDF_UNIT, $format, true, 'UTF-8', false, false);
		$pdf = $this->attachPDFs($pdf, $pdf_filelocations);

		$this->cleanOutputBuffer();
		$this->outputPDF($pdf);
	}

    public function render() {
		$this->prepareOutputBuffer();
		$this->checkFilePath();
		$format = $this->getFormat();

		//$pdf = new TCPDF($this->orientation, PDF_UNIT, $this->page_format, true, 'UTF-8', false, false);
		//$pdf = new TCPDF($this->orientation, PDF_UNIT, $format, true, 'UTF-8', false, false);
		//$tcpdf = new Fpdi();
		$pdf = new FPDI($this->orientation, PDF_UNIT, $format, true, 'UTF-8', false, false);
		//$pdf->error = function ($msg) {throw new Exception("PDF-Parser: ".$msg);};

        $preferences = [
            //'HideToolbar' => true,
            //'HideMenubar' => true,
            //'HideWindowUI' => true,
            //'FitWindow' => true,
            'CenterWindow' => true,
            //'DisplayDocTitle' => true,
            //'NonFullScreenPageMode' => 'UseNone', // UseNone, UseOutlines, UseThumbs, UseOC
            //'ViewArea' => 'CropBox', // CropBox, BleedBox, TrimBox, ArtBox
            //'ViewClip' => 'CropBox', // CropBox, BleedBox, TrimBox, ArtBox
            'PrintArea' => 'CropBox', // CropBox, BleedBox, TrimBox, ArtBox
            //'PrintClip' => 'CropBox', // CropBox, BleedBox, TrimBox, ArtBox
            'PrintScaling' => 'None', // None, AppDefault
            'Duplex' => 'DuplexFlipLongEdge', // Simplex, DuplexFlipShortEdge, DuplexFlipLongEdge
            'PickTrayByPDFSize' => true,
            //'PrintPageRange' => array(1,1,2,3),
            //'NumCopies' => 2
        ];
        if ($this->orientation == "L") $preferences['Duplex'] = "DuplexFlipShortEdge";
        $pdf->setViewerPreferences($preferences);
		$pdf->SetAutoPageBreak(TRUE, 5);
		//$pdf->SetFont('helvetica', '', "10pt");
		$pdf->SetFont('dejavusans', '', $this->fontSize."pt");

		// set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		$pdf->setJPEGQuality(90);

		//$pdf->addFormat("custom", $this->size_width, $this->size_height);

		// set margins
		//$pdf->SetMargins(0, 0, 0);
		//$pdf->SetMargins(PDF_MARGIN_LEFT, 17, 10);
		//$pdf->SetHeaderMargin(10);
		//$pdf->SetFooterMargin(10);

		$pdf->SetPrintHeader(false);
		$pdf->SetPrintFooter(false);


		$page_parts = $this->getParts();
		// Print text using writeHTMLCell()
		$pdf->AddPage();

		$w_image = $this->orientation == "L" ? $this->size_height : $this->size_width;
		$h_image = $this->orientation == "L" ? $this->size_width : $this->size_height;

		// background image
		if ($this->background_image != null) {
			$pdf->SetAutoPageBreak(false, 0);
			$pdf->Image($this->background_image, 0, 0, $w_image, $h_image, '', '', '', false, 300, '', false, false, 1, 'CM');
			$pdf->SetAutoPageBreak(TRUE, 5);
			$pdf->setPageMark();
		}

		if ($this->isRTL) {
			$pdf->setRTL(true);
		}

		$pdf->SetFont('dejavusans', '', $this->fontSize."pt");

		foreach($page_parts as $p) {
			try {
				if ($p == "{PAGEBREAK}") {
					$pdf->AddPage();
					continue;
				}
				$teile = explode('{PAGEBREAK}', $p);
				$counter = 0;
				foreach($teile as $teil) {
					$counter++;
					if ($counter > 1) $pdf->AddPage();
					if ($teil == "{QRCODE}") {
						if (!empty($this->qr['text'])) {
							$pdf->write2DBarcode($this->qr['text'], $this->qr['type'], $this->qr['pos']['x'], $this->qr['pos']['y'], $this->qr['size']['width'], $this->qr['size']['height'], $this->qr['style'], $this->qr['align']);
						}
					} else if ($teil == "{QRCODE_INLINE}") {
						if (!empty($this->qr['text'])) {
							$pdf->write2DBarcode($this->qr['text'], $this->qr['type'], '', '', $this->qr['size']['width'], $this->qr['size']['height'], $this->qr['style'], $this->qr['align']);
						}
					} else {
						//$pdf->writeHTMLCell(0, 0, '', '', $teil, 0, 1, 0, true, '', true);
						$pdf->writeHTML($teil, true, false, true, false, '');
					}
				}
			} catch(Exception $e) {}
		}

		/*
		$pdf->StartTransform();
		$pdf->Rotate(-90);
		$pdf->SetFont('dejavusans', '', "6pt");
		//$pdf->Cell(0,0,'This is a sample data',0,1,'L');
		$pdf->MultiCell(0, 0, '[DEFAULT] ', 0, 'L', 0, 1, '', '');
		$pdf->StopTransform();
		*/

		$pdf = $this->attachPDFs($pdf, $this->attach_pdfs);

		$this->cleanOutputBuffer();
		$this->outputPDF($pdf);
    }

}
?>