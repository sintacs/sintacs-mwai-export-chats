<?php
require_once('tcpdf/tcpdf.php');

Class MwaiTCPDF extends TCPDF {
    public function Footer() {
        // Position 15 mm vom unteren Rand entfernt setzen
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('helvetica', 'I', 8);
        // Seitennummer
        $pageNumber = $this->getAliasNumPage().' von '.$this->getAliasNbPages();
        $this->Cell(0, 10, $pageNumber, 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}