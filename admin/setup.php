<?php
/* Copyright (C) 2026 Anatole Conseil
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup easymodelepdf
 * \brief   EasyModelePdf setup page — drag & drop custom PDF models, manage them.
 */

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $conf, $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/easymodelepdf/lib/easymodelepdf.lib.php');

$langs->loadLangs(array("admin", "other", "easymodelepdf@easymodelepdf"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');       // model name (llx_document_model.nom)
$parentkey = GETPOST('parentkey', 'alpha'); // key of emp_supported_types()

$map = emp_supported_types();
$typeinfo = ($parentkey && array_key_exists($parentkey, $map)) ? $map[$parentkey] : null;

// Load lang files used by type labels
foreach ($map as $info) {
	$langs->load($info['langfile']);
}


/*
 * Actions
 */

if ($action == 'upload' && !empty($_FILES['modelfiles'])) {
	$datadir = emp_data_dir();
	$nbok = 0;

	// Normalize $_FILES for single/multiple
	$files = array();
	if (is_array($_FILES['modelfiles']['name'])) {
		foreach ($_FILES['modelfiles']['name'] as $i => $name) {
			$files[] = array(
				'name' => $name,
				'tmp_name' => $_FILES['modelfiles']['tmp_name'][$i],
				'error' => $_FILES['modelfiles']['error'][$i],
				'size' => $_FILES['modelfiles']['size'][$i],
			);
		}
	} else {
		$files[] = $_FILES['modelfiles'];
	}

	foreach ($files as $f) {
		$filename = dol_sanitizeFileName($f['name']);
		if ($f['error'] !== UPLOAD_ERR_OK || empty($f['tmp_name'])) {
			setEventMessages($langs->trans("EmpErrUpload", $filename), null, 'errors');
			continue;
		}
		if ($f['size'] > 4 * 1024 * 1024) {
			setEventMessages($langs->trans("EmpErrTooBig", $filename), null, 'errors');
			continue;
		}

		$err = '';
		$analysis = emp_analyze_model_file($f['tmp_name'], $filename, $err);
		if ($analysis === false) {
			setEventMessages($err, null, 'errors');
			continue;
		}

		$info = $analysis['typeinfo'];
		$storedir = $datadir.'/'.$info['dir'];
		if (dol_mkdir($storedir) < 0 && !is_dir($storedir)) {
			setEventMessages($langs->trans("EmpErrMkdir", $storedir), null, 'errors');
			continue;
		}
		$storedfile = $storedir.'/'.$filename;
		$isupdate = file_exists($storedfile);
		if (!move_uploaded_file($f['tmp_name'], $storedfile)) {
			setEventMessages($langs->trans("EmpErrCopy", $storedfile), null, 'errors');
			continue;
		}
		dolChmod($storedfile);

		if (!emp_deploy_file($storedfile, $info['dir'], $err)) {
			setEventMessages($err, null, 'warnings');
		}

		$nbok++;
		setEventMessages($langs->trans($isupdate ? "EmpFileUpdated" : "EmpFileInstalled", $filename, $langs->trans($info['label'])), null, 'mesgs');
	}
} elseif ($action == 'set' && $typeinfo) {
	// Enable model in llx_document_model
	$ret = addDocumentModel($value, $typeinfo['type'], $value, '');
	if ($ret > 0) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
} elseif ($action == 'del' && $typeinfo) {
	$ret = delDocumentModel($value, $typeinfo['type']);
	if ($ret > 0) {
		if (getDolGlobalString($typeinfo['const']) == $value) {
			dolibarr_del_const($db, $typeinfo['const'], $conf->entity);
		}
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
} elseif ($action == 'setdoc' && $typeinfo) {
	// Set as default model for the object type (and make sure it is enabled)
	if (dolibarr_set_const($db, $typeinfo['const'], $value, 'chaine', 0, '', $conf->entity)) {
		$conf->global->{$typeinfo['const']} = $value;
	}
	delDocumentModel($value, $typeinfo['type']);
	addDocumentModel($value, $typeinfo['type'], $value, '');
	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
} elseif ($action == 'unsetdoc' && $typeinfo) {
	dolibarr_del_const($db, $typeinfo['const'], $conf->entity);
	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
} elseif ($action == 'deletefile' && $typeinfo) {
	$filename = 'pdf_'.dol_sanitizeFileName($value).'.modules.php';
	$storedfile = emp_data_dir().'/'.$typeinfo['dir'].'/'.$filename;
	$deployedfile = emp_deploy_dir($typeinfo['dir']).'/'.$filename;

	delDocumentModel($value, $typeinfo['type']);
	if (getDolGlobalString($typeinfo['const']) == $value) {
		dolibarr_del_const($db, $typeinfo['const'], $conf->entity);
	}
	$nbdel = 0;
	foreach (array($storedfile, $deployedfile) as $todel) {
		if (file_exists($todel) && @unlink($todel)) {
			$nbdel++;
		}
	}
	if ($nbdel) {
		setEventMessages($langs->trans("EmpFileDeleted", $filename), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("EmpErrDelete", $filename), null, 'errors');
	}
} elseif ($action == 'download' && $typeinfo) {
	$filename = 'pdf_'.dol_sanitizeFileName($value).'.modules.php';
	$storedfile = emp_data_dir().'/'.$typeinfo['dir'].'/'.$filename;
	if (file_exists($storedfile)) {
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Content-Length: '.filesize($storedfile));
		readfile($storedfile);
		exit;
	}
	setEventMessages($langs->trans("EmpErrCannotRead", $filename), null, 'errors');
} elseif ($action == 'redeploy') {
	$errors = array();
	$count = emp_deploy_all($errors);
	foreach ($errors as $err) {
		setEventMessages($err, null, 'errors');
	}
	setEventMessages($langs->trans("EmpRedeployed", $count), null, 'mesgs');
}


/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans("EmpSetup"), '', '', 0, 0, '', '', '', 'mod-easymodelepdf page-admin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("EmpSetup"), $linkback, 'title_setup');

$head = easymodelepdfAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("Module185165Name"), -1, 'easymodelepdf@easymodelepdf');

print '<span class="opacitymedium">'.$langs->trans("EmpSetupIntro").'</span><br><br>';

// Warn if the module directory is not writable (deployment impossible)
$moduleroot = emp_module_root().'/core/modules';
if (!is_writable($moduleroot)) {
	print '<div class="warning">'.$langs->trans("EmpWarnNotWritable", $moduleroot).'</div><br>';
}

// ---- Drop zone ----
print '<form id="empuploadform" method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="upload">';
print '<input type="file" id="empfileinput" name="modelfiles[]" multiple accept=".php" style="display:none">';
print '<div id="empdropzone" style="border:2px dashed var(--colortopbordertitle1,#888); border-radius:8px; padding:40px 20px; text-align:center; cursor:pointer; margin-bottom:20px;">';
print img_picto('', 'download', 'class="pictofixedwidth"').' ';
print '<span class="opacitymedium">'.$langs->trans("EmpDropZoneText").'</span>';
print '<br><span class="opacitymedium small">'.$langs->trans("EmpDropZoneHint").'</span>';
print '</div>';
print '</form>';

print '<script>
(function() {
	var dz = document.getElementById("empdropzone");
	var input = document.getElementById("empfileinput");
	var form = document.getElementById("empuploadform");
	dz.addEventListener("click", function() { input.click(); });
	input.addEventListener("change", function() { if (input.files.length) form.submit(); });
	["dragenter", "dragover"].forEach(function(ev) {
		dz.addEventListener(ev, function(e) { e.preventDefault(); e.stopPropagation(); dz.style.background = "rgba(128,128,128,0.15)"; });
	});
	["dragleave", "drop"].forEach(function(ev) {
		dz.addEventListener(ev, function(e) { e.preventDefault(); e.stopPropagation(); dz.style.background = ""; });
	});
	dz.addEventListener("drop", function(e) {
		if (e.dataTransfer && e.dataTransfer.files.length) {
			input.files = e.dataTransfer.files;
			form.submit();
		}
	});
})();
</script>';

// ---- Carried models, grouped by object type ----
$models = emp_list_models($db, $conf);

if (empty($models)) {
	print '<br><span class="opacitymedium">'.$langs->trans("EmpNoModelYet").'</span>';
} else {
	print load_fiche_titre($langs->trans("EmpCarriedModels"), '', '');

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("File").'</td>';
	print '<td>'.$langs->trans("Name").'</td>';
	print '<td>'.$langs->trans("Type").'</td>';
	print '<td class="center" width="80">'.$langs->trans("EmpDeployed").'</td>';
	print '<td class="center" width="80">'.$langs->trans("Status").'</td>';
	print '<td class="center" width="80">'.$langs->trans("Default").'</td>';
	print '<td class="center" width="100">'.$langs->trans("Action").'</td>';
	print '</tr>';

	foreach ($models as $parent => $list) {
		$info = $map[$parent];

		foreach ($list as $m) {
			$urlbase = $_SERVER["PHP_SELF"].'?token='.newToken().'&parentkey='.urlencode($parent).'&value='.urlencode($m['name']);

			print '<tr class="oddeven">';
			print '<td>'.dol_escape_htmltag($m['file']).'</td>';
			print '<td>'.dol_escape_htmltag($m['name']).'</td>';
			print '<td>'.$langs->trans($info['label']).'</td>';

			// Deployed in module tree?
			print '<td class="center">';
			print $m['deployed'] ? img_picto($langs->trans("Yes"), 'tick') : img_picto($langs->trans("No"), 'warning');
			print '</td>';

			// Enabled (llx_document_model)
			print '<td class="center">';
			if ($m['enabled']) {
				print '<a class="reposition" href="'.$urlbase.'&action=del">'.img_picto($langs->trans("Enabled"), 'switch_on').'</a>';
			} else {
				print '<a class="reposition" href="'.$urlbase.'&action=set">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
			}
			print '</td>';

			// Default model for the object type
			print '<td class="center">';
			if ($m['isdefault']) {
				print '<a class="reposition" href="'.$urlbase.'&action=unsetdoc">'.img_picto($langs->trans("Default"), 'on').'</a>';
			} else {
				print '<a class="reposition" href="'.$urlbase.'&action=setdoc">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
			}
			print '</td>';

			// Download / delete
			print '<td class="center nowraponall">';
			print '<a href="'.$urlbase.'&action=download">'.img_picto($langs->trans("Download"), 'download').'</a>';
			print ' &nbsp; ';
			print '<a href="'.$urlbase.'&action=deletefile" onclick="return confirm(\''.dol_escape_js($langs->transnoentities("EmpConfirmDelete", $m['file'])).'\');">'.img_picto($langs->trans("Delete"), 'delete').'</a>';
			print '</td>';

			print '</tr>';
		}
	}
	print '</table>';
	print '</div><br>';

	print '<div class="center">';
	print '<a class="button reposition" href="'.$_SERVER["PHP_SELF"].'?action=redeploy&token='.newToken().'">'.$langs->trans("EmpRedeploy").'</a>';
	print '</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
