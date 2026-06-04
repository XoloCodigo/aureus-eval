# xolocodigo/petfood

Custom plugin for the **petfood manufacturing ERP base** built on AureusERP.

## Purpose

This plugin is the home for **all client-specific and Mexican-market localization code** for the petfood manufacturing ERP. It exists to keep:

- **Industry-specific functionality** (immutable stock ledger, BOM versioning, bidirectional lot genealogy, quality control templates, batch-level traceability for NOM/SENASICA compliance).
- **Mexican-market localization** (RFC/CURP/NSS/CLABE fields, validators, calendario LFT, employment types LFT, MXN seed, MX address format with Colonia, IMSS employer registration fields).
- **Client deliverables** that benefit from the AureusERP base but aren't generic enough to upstream.

…in **new files only**, so that `git merge upstream/master` is always conflict-free.

## Hard rules

- **NEVER edit files under `plugins/webkul/`** for client-specific functionality. Use observers, traits, Filament render hooks, or own resources instead.
- **NEVER edit `config/` of the skeleton** for client-specific data. Use a seeder in this plugin that registers config at runtime.
- Edits to `app/Providers/Filament/AdminPanelProvider.php` are NOT needed — this plugin self-registers via `Panel::configureUsing()` in its `PackageServiceProvider`.
- The ONE edit allowed outside this plugin is registering `PetFoodServiceProvider` in `bootstrap/providers.php` (standard Laravel skeleton, low conflict risk on upstream sync).

## Structure

```
plugins/xolocodigo/petfood/
├── composer.json          # Auto-merged into root composer.json via wikimedia/composer-merge-plugin
├── README.md              # This file
├── database/
│   ├── migrations/        # PetFood + Mexican-market migrations
│   ├── seeders/           # Mexican defaults (MXN, calendar LFT, employment types, etc.)
│   └── factories/
├── resources/
│   ├── lang/en/           # Plugin labels (translated to es later)
│   └── views/             # Filament Blade views if needed
└── src/
    ├── PetFoodServiceProvider.php   # Extends Webkul\PluginManager\PackageServiceProvider
    ├── PetFoodPlugin.php            # Implements Filament\Contracts\Plugin
    ├── Filament/
    │   ├── Resources/     # Auto-discovered
    │   └── Pages/         # Auto-discovered
    ├── Models/
    ├── Enums/
    ├── Policies/
    ├── Services/          # Core business logic (e.g. StockMovementService)
    ├── Support/
    │   └── Validation/    # MX validators (RFC, CURP, CLABE, CP)
    ├── Http/
    └── Console/
        └── Commands/
```

## How extensions to core models work

For example, to add `mx_hire_date` to `employees_employees`:

```php
// database/migrations/2026_06_03_000000_add_mx_hire_date_to_employees.php
Schema::table('employees_employees', function (Blueprint $table) {
    $table->date('mx_hire_date')->nullable()->after('birthday');
});
```

Add `Webkul\Employee\Models\Employee` to an Observer in this plugin to wire up behavior. Upstream is never touched.

## Branch strategy

- `master`: clean mirror of upstream `aureuserp/aureuserp:master`
- `xolocodigo-base` (this branch): `master` + this plugin = the reusable base for any Mexican petfood ERP implementation
- `feat/winners-*`: client-specific configuration / data layered on top of `xolocodigo-base` for the Winners Mark deployment

See `docs/superpowers/specs/2026-06-03-petfood-roadmap.md` (gitignored) for the implementation roadmap.
