<?php
/* Copyright (C) 2026 Anatole Conseil
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/easymodelepdf.lib.php
 * \ingroup easymodelepdf
 * \brief   Library for EasyModelePdf: supported types map, model file analysis, deploy helpers.
 */

/**
 * Prepare admin pages header
 *
 * @return array Array of tabs
 */
function easymodelepdfAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("easymodelepdf@easymodelepdf");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/easymodelepdf/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'easymodelepdf@easymodelepdf');

	return $head;
}

/**
 * Map of supported PDF model parent classes.
 *
 * Key = abstract parent class in Dolibarr core (what the uploaded model must extend).
 * Values verified against Dolibarr v23 core:
 *  - dir:      subdir of core/modules/ where the model file must live (scanned with '' and '/doc' suffix)
 *  - type:     value of llx_document_model.type used by addDocumentModel()/delDocumentModel()
 *  - const:    constant holding the default model name for the object
 *  - langfile: lang file to load for the label
 *  - label:    translation key for the object type
 *
 * @return array<string,array{dir:string,type:string,const:string,langfile:string,label:string}>
 */
function emp_supported_types()
{
	return array(
		'ModelePDFCommandes' => array('dir' => 'commande', 'type' => 'order', 'const' => 'COMMANDE_ADDON_PDF', 'langfile' => 'orders', 'label' => 'CustomersOrders'),
		'ModelePDFFactures' => array('dir' => 'facture', 'type' => 'invoice', 'const' => 'FACTURE_ADDON_PDF', 'langfile' => 'bills', 'label' => 'BillsCustomers'),
		'ModelePDFPropales' => array('dir' => 'propale', 'type' => 'propal', 'const' => 'PROPALE_ADDON_PDF', 'langfile' => 'propal', 'label' => 'Proposals'),
		'ModelePdfExpedition' => array('dir' => 'expedition', 'type' => 'shipping', 'const' => 'EXPEDITION_ADDON_PDF', 'langfile' => 'sendings', 'label' => 'Shipments'),
		'ModelePDFDeliveryOrder' => array('dir' => 'delivery', 'type' => 'delivery', 'const' => 'DELIVERY_ADDON_PDF', 'langfile' => 'deliveries', 'label' => 'Deliveries'),
		'ModelePDFSuppliersOrders' => array('dir' => 'supplier_order', 'type' => 'order_supplier', 'const' => 'COMMANDE_SUPPLIER_ADDON_PDF', 'langfile' => 'orders', 'label' => 'SuppliersOrders'),
		'ModelePDFSuppliersInvoices' => array('dir' => 'supplier_invoice', 'type' => 'invoice_supplier', 'const' => 'INVOICE_SUPPLIER_ADDON_PDF', 'langfile' => 'bills', 'label' => 'BillsSuppliers'),
		'ModelePDFContract' => array('dir' => 'contract', 'type' => 'contract', 'const' => 'CONTRACT_ADDON_PDF', 'langfile' => 'contracts', 'label' => 'Contracts'),
		'ModelePDFFicheinter' => array('dir' => 'fichinter', 'type' => 'ficheinter', 'const' => 'FICHEINTER_ADDON_PDF', 'langfile' => 'interventions', 'label' => 'Interventions'),
		'ModelePDFProjects' => array('dir' => 'project', 'type' => 'project', 'const' => 'PROJECT_ADDON_PDF', 'langfile' => 'projects', 'label' => 'Projects'),
		'ModelePDFTask' => array('dir' => 'project/task', 'type' => 'project_task', 'const' => 'PROJECT_TASK_ADDON_PDF', 'langfile' => 'projects', 'label' => 'Tasks'),
		'ModelePDFStock' => array('dir' => 'stock', 'type' => 'stock', 'const' => 'STOCK_ADDON_PDF', 'langfile' => 'stocks', 'label' => 'Stock'),
		'ModelePDFMo' => array('dir' => 'mrp', 'type' => 'mrp', 'const' => 'MRP_MO_ADDON_PDF', 'langfile' => 'mrp', 'label' => 'Mos'),
		'ModelePDFBom' => array('dir' => 'bom', 'type' => 'bom', 'const' => 'BOM_ADDON_PDF', 'langfile' => 'mrp', 'label' => 'BOMs'),
		'ModelePDFTicket' => array('dir' => 'ticket', 'type' => 'ticket', 'const' => 'TICKET_ADDON_PDF', 'langfile' => 'ticket', 'label' => 'Tickets'),
	);
}

/**
 * Directory (source of truth) where uploaded model files are stored.
 * Survives module upgrades: init() redeploys from here into the module tree.
 *
 * @return string Absolute path (no trailing slash)
 */
function emp_data_dir()
{
	return DOL_DATA_ROOT.'/easymodelepdf/models';
}

/**
 * Root directory of this module on disk.
 * Derived from this file's location (lib/..) — dol_buildpath() cannot be used
 * for deploy paths: it falls back to the main htdocs root when the target
 * subdirectory does not exist yet.
 *
 * @return string Absolute path (no trailing slash)
 */
function emp_module_root()
{
	return dirname(__DIR__);
}

/**
 * Directory inside the module tree where a model must be deployed so that
 * Dolibarr (via $conf->modules_parts['models']) can find and instantiate it.
 *
 * @param string $dirkey 'dir' value from emp_supported_types()
 * @return string Absolute path (no trailing slash)
 */
function emp_deploy_dir($dirkey)
{
	return emp_module_root().'/core/modules/'.$dirkey.'/doc';
}

/**
 * Analyze an uploaded PDF model file: find the class it declares and the
 * ModelePDF* parent it extends, and enforce the "class name = file name" rule
 * (Dolibarr instantiates the class from the file name, a mismatch fails silently).
 *
 * @param string $filepath Absolute path to the file to analyze (tmp uploaded file)
 * @param string $filename Original file name (pdf_xxx.modules.php)
 * @param string $error    Filled with a translated error message on failure
 * @return array{classname:string,parent:string,name:string,typeinfo:array}|false
 */
function emp_analyze_model_file($filepath, $filename, &$error)
{
	global $langs;
	$langs->load("easymodelepdf@easymodelepdf");

	if (!preg_match('/^pdf_[A-Za-z0-9_\-]+\.modules\.php$/', $filename)) {
		$error = $langs->trans("EmpErrBadFileName", $filename);
		return false;
	}

	$content = file_get_contents($filepath);
	if ($content === false || $content === '') {
		$error = $langs->trans("EmpErrCannotRead", $filename);
		return false;
	}

	// Expected class name: file name without ".modules.php"
	$expectedclass = substr($filename, 0, -12);

	// Tokenize and collect "class X extends Y" declarations (robust to comments/strings)
	try {
		$tokens = token_get_all($content, TOKEN_PARSE); // TOKEN_PARSE: throws ParseError on syntax errors
	} catch (Throwable $e) {
		$error = $langs->trans("EmpErrSyntax", $filename, $e->getMessage().' (line '.$e->getLine().')');
		return false;
	}
	$classes = array(); // classname => parent (or '')
	$n = is_array($tokens) ? count($tokens) : 0;
	for ($i = 0; $i < $n; $i++) {
		if (!is_array($tokens[$i]) || $tokens[$i][0] != T_CLASS) {
			continue;
		}
		// Skip ::class constant and anonymous classes
		$classname = '';
		$parent = '';
		for ($j = $i + 1; $j < $n; $j++) {
			if (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT))) {
				continue;
			}
			if (is_array($tokens[$j]) && $tokens[$j][0] == T_STRING) {
				$classname = $tokens[$j][1];
			}
			break;
		}
		if ($classname === '') {
			continue;
		}
		// Look for "extends Parent" before the opening brace
		for ($j = $j + 1; $j < $n; $j++) {
			if ($tokens[$j] === '{') {
				break;
			}
			if (is_array($tokens[$j]) && $tokens[$j][0] == T_EXTENDS) {
				for ($k = $j + 1; $k < $n; $k++) {
					if (is_array($tokens[$k]) && in_array($tokens[$k][0], array(T_WHITESPACE, T_COMMENT))) {
						continue;
					}
					if (is_array($tokens[$k]) && $tokens[$k][0] == T_STRING) {
						$parent = $tokens[$k][1];
					}
					break;
				}
				break;
			}
		}
		$classes[$classname] = $parent;
	}

	if (empty($classes)) {
		$error = $langs->trans("EmpErrNoClass", $filename);
		return false;
	}

	// The gotcha check: the file MUST declare a class named like the file
	if (!array_key_exists($expectedclass, $classes)) {
		$error = $langs->trans("EmpErrClassMismatch", $filename, $expectedclass, implode(', ', array_keys($classes)));
		return false;
	}

	$parent = $classes[$expectedclass];
	$map = emp_supported_types();
	// PHP class names are case-insensitive (core: ModelePdfExpedition vs ModelePDFCommandes)
	foreach (array_keys($map) as $key) {
		if (strcasecmp($key, $parent) === 0) {
			$parent = $key;
			break;
		}
	}
	if (empty($parent) || !array_key_exists($parent, $map)) {
		$error = $langs->trans("EmpErrUnsupportedParent", $filename, ($parent ? $parent : '?'), implode(', ', array_keys($map)));
		return false;
	}

	return array(
		'classname' => $expectedclass,
		'parent' => $parent,
		'name' => substr($expectedclass, 4), // model name as stored in llx_document_model (strip "pdf_")
		'typeinfo' => $map[$parent],
	);
}

/**
 * Copy one stored model file into the module tree so Dolibarr can scan it.
 *
 * @param string $srcfile Absolute path of the stored model file
 * @param string $dirkey  'dir' value from emp_supported_types()
 * @param string $error   Filled with a translated error message on failure
 * @return bool
 */
function emp_deploy_file($srcfile, $dirkey, &$error)
{
	global $langs;

	$deploydir = emp_deploy_dir($dirkey);
	if (dol_mkdir($deploydir) < 0 && !is_dir($deploydir)) {
		$error = $langs->trans("EmpErrMkdir", $deploydir);
		return false;
	}
	$dest = $deploydir.'/'.basename($srcfile);
	if (!@copy($srcfile, $dest)) {
		$error = $langs->trans("EmpErrCopy", $dest);
		return false;
	}
	dolChmod($dest);
	return true;
}

/**
 * Redeploy every stored model into the module tree.
 * Called at module activation (init) and available from the setup page,
 * so a module upgrade (zip replace) never loses the carried models.
 *
 * @param array $errors Filled with translated error messages
 * @return int Number of files deployed
 */
function emp_deploy_all(&$errors = array())
{
	$count = 0;
	$datadir = emp_data_dir();
	if (!is_dir($datadir)) {
		return 0;
	}
	foreach (emp_supported_types() as $info) {
		$typedir = $datadir.'/'.$info['dir'];
		if (!is_dir($typedir)) {
			continue;
		}
		foreach (glob($typedir.'/pdf_*.modules.php') as $srcfile) {
			$err = '';
			if (emp_deploy_file($srcfile, $info['dir'], $err)) {
				$count++;
			} else {
				$errors[] = $err;
			}
		}
	}
	return $count;
}

/**
 * List carried models, grouped by parent class key, with runtime status.
 *
 * @param DoliDB $db   Database handler
 * @param Conf   $conf Dolibarr conf
 * @return array<string,array<int,array{file:string,name:string,storedfile:string,deployed:bool,enabled:bool,isdefault:bool}>>
 */
function emp_list_models($db, $conf)
{
	$out = array();
	$datadir = emp_data_dir();

	foreach (emp_supported_types() as $parent => $info) {
		$typedir = $datadir.'/'.$info['dir'];
		if (!is_dir($typedir)) {
			continue;
		}
		$files = glob($typedir.'/pdf_*.modules.php');
		if (empty($files)) {
			continue;
		}

		// Enabled models of this type in llx_document_model
		$enabled = array();
		$sql = "SELECT nom FROM ".MAIN_DB_PREFIX."document_model";
		$sql .= " WHERE type = '".$db->escape($info['type'])."' AND entity = ".((int) $conf->entity);
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$enabled[] = $obj->nom;
			}
		}

		foreach ($files as $storedfile) {
			$file = basename($storedfile);
			$name = substr($file, 4, -12); // strip "pdf_" and ".modules.php"
			$out[$parent][] = array(
				'file' => $file,
				'name' => $name,
				'storedfile' => $storedfile,
				'deployed' => file_exists(emp_deploy_dir($info['dir']).'/'.$file),
				'enabled' => in_array($name, $enabled),
				'isdefault' => (getDolGlobalString($info['const']) == $name),
			);
		}
	}
	return $out;
}

/**
 * List PDF models available in Dolibarr core and other modules (starting points to customize).
 * Scans the same directories as the core admin pages ('' and '/doc' suffix, every models root),
 * excluding this module's own tree (carried models are already listed by emp_list_models()).
 *
 * @param Conf $conf Dolibarr conf
 * @return array<string,array<int,array{file:string,path:string,origin:string}>> Keyed by parent class
 */
function emp_list_available_models($conf)
{
	$out = array();
	$dirmodels = array('/');
	if (!empty($conf->modules_parts['models']) && is_array($conf->modules_parts['models'])) {
		$dirmodels = array_merge($dirmodels, $conf->modules_parts['models']);
	}
	foreach (emp_supported_types() as $parent => $info) {
		foreach ($dirmodels as $reldir) {
			if (trim($reldir, '/') == 'easymodelepdf') {
				continue;
			}
			foreach (array('', '/doc') as $suffix) {
				$dir = dol_buildpath($reldir.'core/modules/'.$info['dir'].$suffix, 0);
				$files = glob($dir.'/pdf_*.modules.php');
				if (empty($files)) {
					continue;
				}
				foreach ($files as $path) {
					$out[$parent][] = array(
						'file' => basename($path),
						'path' => $path,
						'origin' => (trim($reldir, '/') ? trim($reldir, '/') : 'core'),
					);
				}
			}
		}
	}
	return $out;
}

/**
 * Send a model file to the browser as an attachment and exit.
 *
 * @param string $path     Absolute path of the file
 * @param string $filename File name presented to the browser
 * @return void
 */
function emp_send_file($path, $filename)
{
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Content-Length: '.filesize($path));
	readfile($path);
	exit;
}
