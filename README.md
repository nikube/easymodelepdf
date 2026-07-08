# EasyModelePdf

Drag & drop installer for custom Dolibarr PDF document models.

## The problem

When you write a custom PDF model (`pdf_mymodel.modules.php`) for a customer, Dolibarr
gives you no way to install it: you have to build a carrier module by hand for each
customer, declare `module_parts['models']`, copy the file into
`core/modules/<object>/doc/`... and remember that the class name must match the file
name or the model silently never shows up.

## What it does

Activate EasyModelePdf, open its setup page, and drop your `pdf_*.modules.php` files:

- **Auto-detection** — the target object (order, invoice, proposal, shipment, contract,
  intervention, project, task, stock, MO, BOM, ticket, supplier order/invoice, delivery)
  is detected from the `ModelePDF*` parent class the model extends.
- **Validation** — file name pattern, PHP class found, and the *class = file name* rule
  enforced with a clear error message instead of a silent failure.
- **Safe storage** — the uploaded file is stored under `DOL_DATA_ROOT/easymodelepdf/models/`
  (source of truth), then deployed into the module's own `core/modules/<object>/doc/`.
  A module upgrade never loses your models: they are redeployed at activation
  (and on demand with the *Redeploy* button).
- **Management** — from the same page: enable/disable the model (`llx_document_model`),
  set it as default for its object type, download it back, delete it.

The installed models also show up in the native document model lists
(Setup → Modules → Orders/Invoices/... ) like any core model.

## Requirements

- Dolibarr 19.0+
- PHP 7.4+ with the `tokenizer` extension (always bundled)
- The web server must be able to write inside the module directory
  (`custom/easymodelepdf/core/modules/`) to deploy models.

## Security notes

Uploading a PHP file is code deployment. The upload is restricted to Dolibarr
**admin** users, CSRF-protected, and the file is parsed (tokenizer) to check it
declares the expected model class. Only deploy models you trust.

## License

GPL-3.0-or-later
