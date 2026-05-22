<?php
// Loaded lazily inside GapNext_Export_Pdf::build() — AFTER tcpdf.php is required.
// Defining GapNext_TCPDF in a separate file keeps it outside any class body,
// which is required since PHP 8.0 ("Class declarations may not be nested").
if ( ! defined( 'ABSPATH' ) ) exit;
if ( class_exists( 'GapNext_TCPDF', false ) ) return;

class GapNext_TCPDF extends TCPDF {
    public $logo_path        = '';
    public $standard_name    = '';
    public $company_name     = '';
    public $report_date      = '';
    public $consultant_label = '';
    public $footer_text      = '';
    public $is_demo          = false;

    public function Header() {
        $logo_w = 8;
        if ( $this->logo_path && file_exists( $this->logo_path ) ) {
            $this->Image( $this->logo_path, 10, 6, $logo_w, 0, '', '', '', false, 150 );
        }
        // Title and company centered
        $this->SetFont( 'helvetica', 'B', 9 );
        $this->SetTextColor( 30, 64, 175 );
        $this->SetXY( 10, 6 );
        $this->Cell( 190, 4, $this->standard_name, 0, 2, 'C' );
        $this->SetFont( 'helvetica', '', 7 );
        $this->SetTextColor( 100, 116, 139 );
        $this->SetX( 10 );
        $this->Cell( 190, 4, $this->company_name, 0, 0, 'C' );
        // Separator with 3mm gap below text
        $this->SetDrawColor( 200, 210, 220 );
        $this->SetLineWidth( 0.2 );
        $this->Line( 10, 17, 200, 17 );
        $this->SetDrawColor( 0, 0, 0 );
    }

    public function Footer() {
        if ( $this->is_demo ) {
            $this->StartTransform();
            $this->Rotate( 45, 105, 148 );
            $this->SetAlpha( 0.12 );
            $this->SetFont( 'helvetica', 'B', 60 );
            $this->SetTextColor( 30, 64, 175 );
            $this->Text( 25, 120, 'GapNext DEMO' );
            $this->StopTransform();
            $this->SetAlpha( 1 );
        }

        $this->SetY( -12 );
        // Thin gray separator
        $this->SetDrawColor( 200, 210, 220 );
        $this->SetLineWidth( 0.2 );
        $this->Line( 10, $this->GetY(), 200, $this->GetY() );
        $this->Ln( 2 );
        $this->SetFont( 'helvetica', '', 7 );
        $this->SetTextColor( 100, 116, 139 );
        // Left: date
        $this->SetX( 10 );
        $this->Cell( 50, 5, $this->report_date, 0, 0, 'L' );
        // Center: confidential text
        $this->SetX( 60 );
        $this->Cell( 90, 5, $this->footer_text, 0, 0, 'C' );
        // Right: page number
        $this->SetX( 150 );
        $this->Cell( 50, 5, $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R' );
    }
}
