# ChangeLog

## 0.1.1

- New: the delete confirmation warns when objects still use the model and when it is the
  default model of its object type.
- New: collapsible list of the PDF models available in Dolibarr core and other enabled
  modules, downloadable as starting points for a custom model.
- Fix: shipment models were always rejected (core parent class is `ModelePdfExpedition`,
  lookup is now case-insensitive like PHP class names).
- Fix: files with a PHP syntax error were accepted and deployed (breaking the core
  document model admin pages); they are now rejected at upload.

## 0.1.0

- Initial version: drag & drop upload of pdf_*.modules.php files, auto-detection of
  the target object from the ModelePDF* parent class, class/file name validation,
  storage under DOL_DATA_ROOT + deployment into the module tree, enable/default/
  download/delete management, redeploy at module activation.
