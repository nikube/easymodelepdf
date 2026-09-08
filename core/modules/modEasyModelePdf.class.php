<?php
/* Copyright (C) 2026 Anatole Conseil
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/modEasyModelePdf.class.php
 * \ingroup easymodelepdf
 * \brief   Module descriptor for EasyModelePdf
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modEasyModelePdf
 *
 * EasyModelePdf: carrier for custom PDF document models (pdf_*.modules.php).
 * Drop a model file on the setup page: the module detects the target object
 * from the ModelePDF* parent class, validates the "class = file name" rule,
 * stores the file under DOL_DATA_ROOT (survives upgrades) and deploys it into
 * its own core/modules/<object>/doc/ so Dolibarr picks it up natively.
 */
class modEasyModelePdf extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Reserved range 185150–185169 (Anatole Conseil) — next free after creditmanager (185164)
		$this->numero = 185165;
		$this->rights_class = 'easymodelepdf';
		$this->family = 'technic';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "EasyModelePdf - Drag & drop installer for custom PDF document models";
		$this->descriptionlong = "Carry your hand-written PDF document models (pdf_*.modules.php) without building a module for each customer: drop the file on the setup page, EasyModelePdf detects the target object (order, invoice, proposal, shipment...), checks the class/file name rule, stores the file safely and deploys it so it shows up in the native document model lists.";
		$this->editor_name = 'Anatole Conseil';
		$this->editor_url = '';
		$this->version = '0.1.1';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-file-pdf';

		// 'models' => 1 adds /easymodelepdf/ to $conf->modules_parts['models'] so the
		// core scans easymodelepdf/core/modules/<object>/doc/ for document models.
		$this->module_parts = array(
			'models' => 1,
		);

		// Data directories created on activation (source of truth for carried models)
		$this->dirs = array('/easymodelepdf', '/easymodelepdf/models');
		$this->config_page_url = array('setup.php@easymodelepdf');

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('easymodelepdf@easymodelepdf');
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(19, 0);
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		$this->const = array();

		if (!isset($conf->easymodelepdf)) {
			$conf->easymodelepdf = new stdClass();
		}
		if (!isset($conf->easymodelepdf->enabled)) {
			$conf->easymodelepdf->enabled = 0;
		}
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		// Admin-only setup page, no own permissions needed
		$this->rights = array();
		$this->menu = array();
	}

	/**
	 * Function called when module is enabled.
	 * Redeploys carried models from DOL_DATA_ROOT into the module tree,
	 * so a module upgrade (zip replace) never loses them.
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		dol_include_once('/easymodelepdf/lib/easymodelepdf.lib.php');
		$errors = array();
		emp_deploy_all($errors);
		if (!empty($errors)) {
			$this->error = implode(', ', $errors);
			// Non fatal: module stays usable, models can be redeployed from setup page
		}

		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param string $options Options when disabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
