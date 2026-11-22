<?php

use Livewire\Livewire;
use Webkul\Product\Models\Product;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Company;
use XoloCodigo\PetFood\Recipes\Filament\Pages\BomVersions;

require_once __DIR__.'/../../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

it('shows what changed from the previous recipe version and who changed it', function () {
    $bom = PetFoodScenario::billOfMaterial();
    $corn = Product::factory()->create(['name' => 'Maíz']);
    $salt = Product::factory()->create(['name' => 'Sal']);
    $premix = Product::factory()->create(['name' => 'Premezcla']);
    $cornLine = PetFoodScenario::bomLine($bom, $corn, 500);
    $saltLine = PetFoodScenario::bomLine($bom, $salt, 3);

    PetFoodScenario::newRequest();
    $user = User::factory()->create(['name' => 'Luis Formulación', 'default_company_id' => $bom->company_id]);
    $user->allowedCompanies()->syncWithoutDetaching(array_filter([$bom->company_id]));
    $this->actingAs($user);
    $cornLine->update(['quantity' => 520]);
    $saltLine->delete();
    PetFoodScenario::bomLine($bom, $premix, 2.5);

    [$latest, $initial] = Livewire::test(BomVersions::class)->get('versions');

    expect($latest['changes'])->toEqualCanonicalizing([
        ['kind' => 'added', 'text' => 'Premezcla 2.5 '.$premix->uom->name],
        ['kind' => 'removed', 'text' => 'Sal 3 '.$salt->uom->name],
        ['kind' => 'changed', 'text' => "Maíz: 500 {$corn->uom->name} → 520 {$corn->uom->name}"],
    ])
        ->and($latest['by'])->toBe('Luis Formulación')
        ->and($initial['version'])->toBe(1)
        ->and($initial['changes'])->toBe([]);
});

it('does not show recipe versions of a company the user cannot access', function () {
    $bom = PetFoodScenario::billOfMaterial();
    PetFoodScenario::bomLine($bom, Product::factory()->create(), 1);
    $bom->forceFill(['company_id' => Company::factory()->create()->id])->saveQuietly();

    $this->actingAs(User::factory()->create());

    expect(Livewire::test(BomVersions::class)->get('versions'))->toBe([]);
});
