<?php
/* Copyright (C) 2026 Nicolas Zaoui <nz@anatoleconseil.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * \file    pdf_kmtotal_expensereport.modules.php
 * \ingroup expensereport
 * \brief   Standard expense report PDF plus a "Total km" row under the totals box.
 *          Example model for EasyModelePdf: drop it on the module's setup page.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/expensereport/doc/pdf_standard_expensereport.modules.php';

/**
 * Standard expense report PDF with the mileage total (sum of EX_KME lines).
 */
class pdf_kmtotal_expensereport extends pdf_standard_expensereport
{
	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'kmtotal_expensereport';
		$this->description = 'Standard expense report with total kilometers';
	}

	/**
	 * Page footer. On the last page (free text shown) also prints the km total
	 * right under the TotalTTC box drawn by the parent write_file().
	 *
	 * @param TCPDF         $pdf          PDF
	 * @param ExpenseReport $object       Object
	 * @param Translate     $outputlangs  Lang
	 * @param int           $hidefreetext 1 = intermediate page, 0 = last page
	 * @return int
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		global $conf;

		if (empty($hidefreetext)) {
			$km = 0;
			$amount = 0;
			foreach ($object->lines as $line) {
				if ($line->type_fees_code == 'EX_KME') {
					$km += (float) $line->qty;
					$amount += (float) $line->total_ttc;
				}
			}
			if ($km > 0) {
				// Same geometry as parent write_file(): totals box starts at bottomlasttab + 5,
				// three 5mm rows (HT, VAT, TTC) above us. heightforinfotot=40, footer=marge_basse+12.
				$heightforfreetext = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5);
				$bottomlasttab = $this->page_hauteur - 40 - $heightforfreetext - ($this->marge_basse + 12) + 1;
				$posy = $bottomlasttab + 5 + 5 + 5 + 5;
				if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT')) {
					$posy -= 5;
				}
				$pdf->SetFont('', '', 10);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->SetXY(120, $posy);
				$pdf->MultiCell(40, 5, 'Total km', 1, 'L');
				$pdf->SetXY(160, $posy);
				$pdf->MultiCell($this->page_largeur - $this->marge_gauche - 160, 5, price($km, 0, $outputlangs, 1, 0, ($km == floor($km) ? 0 : 1)).' km ('.price($amount, 0, $outputlangs, 1, -1, -1, $conf->currency).')', 1, 'C');
			}
		}
		return parent::_pagefoot($pdf, $object, $outputlangs, $hidefreetext);
	}
}
