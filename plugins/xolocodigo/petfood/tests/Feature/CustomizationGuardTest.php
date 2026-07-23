<?php

use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use XoloCodigo\PetFood\Quality\Filament\Pages\QualityLocations;
use XoloCodigo\PetFood\Recipes\Filament\Pages\BomVersions;
use XoloCodigo\PetFood\Traceability\Filament\Pages\LotTraceability;

/*
 * Guards for the "chrome / config" customizations that an upstream sync can
 * silently override without a merge conflict or a failing logic test — the
 * exact gap the 2026-07-22 nav-icon regression fell through. Each assertion
 * checks the OBSERVABLE outcome through the real resolution path, not that an
 * ingredient merely exists. See [[styling-no-build-deploy]] / [[project-upstream-sync]].
 */

it('groups the three petfood pages under "Calidad e Inocuidad"', function () {
    foreach ([QualityLocations::class, BomVersions::class, LotTraceability::class] as $page) {
        $group = (new ReflectionProperty($page, 'navigationGroup'))->getValue();

        expect($group)->toBe('Calidad e Inocuidad');
    }
});

it('keeps our nav-group order with Calidad e Inocuidad right after Inventario', function () {
    $icons = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->map(fn ($group) => $group->getIcon())
        ->values();

    $inventory = $icons->search('petfood-inventario');
    $quality = $icons->search('petfood-calidad');
    $invoice = $icons->search('petfood-facturas');

    expect($quality)->toBe($inventory + 1)     // Calidad immediately after Inventario
        ->and($quality)->toBeLessThan($invoice); // and before Facturas
});

it('applies the Mexican d/m/Y date format to every Filament date input', function () {
    expect(DatePicker::make('date')->getDisplayFormat())->toBe('d/m/Y')
        ->and(DateTimePicker::make('datetime')->getDisplayFormat())->toBe('d/m/Y H:i');
});

it('keeps the Mexican Spanish term overrides (Celular, not the peninsular Móvil)', function () {
    // Canary for the bundled `es` overrides (accepted webkul exception): a sync
    // that reverts them to peninsular Spanish flips this file back to "Móvil".
    $terms = require base_path('plugins/webkul/partners/resources/lang/es/filament/resources/address.php');

    expect($terms['form']['mobile'])->toBe('Celular');
});
