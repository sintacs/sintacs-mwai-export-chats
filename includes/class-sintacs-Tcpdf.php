<?php
namespace SintacsMwaiExportChats;

require_once(plugin_dir_path(dirname(__FILE__)) . 'vendor/tecnickcom/tcpdf/tcpdf.php');

class SintacsTCPDFPlugin extends \TCPDF {
    public function Footer() {
        // Set position 15 mm from the bottom edge
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $pageNumber = $this->getAliasNumPage().' von '.$this->getAliasNbPages();
        $this->Cell(0, 10, $pageNumber, 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}