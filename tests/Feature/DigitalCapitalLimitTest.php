<?php

use App\Models\CapitalLedgerEntry;
use App\Models\StoreProfile;
use App\Models\User;
use App\Services\CapitalLedgerService;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

function digitalCapitalProfile(float $balance = 3100000): StoreProfile
{
    return StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => $balance,
    ]);
}

it('menolak pengaturan saldo digital di atas modal tanpa mengubah profil atau ledger', function () {
    $this->actingAs(User::factory()->create());
    $profile = digitalCapitalProfile();
    $account = app(CapitalLedgerService::class)->ensureAccounts()->firstWhere('slug', 'saldo-digital');
    $entryCount = CapitalLedgerEntry::count();

    $this->from('/settings/store')->post('/settings/store', [
        'store_name' => 'Nama yang tidak boleh tersimpan',
        'address' => $profile->address,
        'phone' => $profile->phone,
        'saldo_digital' => 18258210,
    ])->assertSessionHasErrors('saldo_digital');

    expect((float) $profile->fresh()->saldo_digital)->toBe(3100000.0)
        ->and($profile->fresh()->store_name)->toBe('Era Digital')
        ->and((float) $account->fresh()->current_balance)->toBe(3100000.0)
        ->and((float) $account->fresh()->contributed_capital)->toBe(3100000.0)
        ->and(CapitalLedgerEntry::count())->toBe($entryCount);
});

it('mengizinkan penyesuaian saldo sampai batas modal disetor', function (float $balance) {
    $this->actingAs(User::factory()->create());
    $profile = digitalCapitalProfile();
    $service = app(CapitalLedgerService::class);
    $account = $service->ensureAccounts()->firstWhere('slug', 'saldo-digital');
    $service->syncDigitalBalance(3100000, 1000000, '2026-11-01');
    $profile->update(['saldo_digital' => 1000000]);

    $this->post('/settings/store', [
        'store_name' => $profile->store_name,
        'address' => $profile->address,
        'phone' => $profile->phone,
        'saldo_digital' => $balance,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect((float) $profile->fresh()->saldo_digital)->toBe($balance)
        ->and((float) $account->fresh()->current_balance)->toBe($balance)
        ->and((float) $account->fresh()->contributed_capital)->toBe(3100000.0);
})->with([0.0, 2500000.0, 3100000.0]);

it('memvalidasi batas modal di service termasuk penyesuaian tanpa selisih', function (float $oldBalance) {
    digitalCapitalProfile();
    $service = app(CapitalLedgerService::class);
    $account = $service->ensureAccounts()->firstWhere('slug', 'saldo-digital');
    $entryCount = CapitalLedgerEntry::count();

    expect(fn () => $service->syncDigitalBalance($oldBalance, 18258210, '2026-11-01'))
        ->toThrow(ValidationException::class);

    expect((float) $account->fresh()->current_balance)->toBe(3100000.0)
        ->and(CapitalLedgerEntry::count())->toBe($entryCount);
})->with([3100000.0, 18258210.0]);

it('mengkoreksi kelebihan saldo lama sekali dengan histori penyesuaian tanpa menaikkan modal', function () {
    $this->actingAs(User::factory()->create());
    $profile = digitalCapitalProfile();
    $account = app(CapitalLedgerService::class)->ensureAccounts()->firstWhere('slug', 'saldo-digital');
    $legacyEntry = $account->entries()->create([
        'entry_date' => '2026-11-01',
        'entry_type' => 'adjustment',
        'direction' => 'credit',
        'amount' => 15158210,
        'description' => 'Penyesuaian saldo digital toko',
        'reference_type' => 'system',
    ]);
    $account->update(['current_balance' => 18258210]);
    $profile->update(['saldo_digital' => 18258210]);
    $entryCount = CapitalLedgerEntry::count();

    $this->get('/capital')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Capital/Index')
        ->where('digitalSummary.current_balance', fn ($value) => (float) $value === 3100000.0)
        ->where('digitalSummary.contributed_capital', fn ($value) => (float) $value === 3100000.0));
    $this->get('/pos')->assertOk();
    $this->get('/capital')->assertOk();

    $correction = CapitalLedgerEntry::latest('id')->firstOrFail();
    expect((float) $account->fresh()->current_balance)->toBe(3100000.0)
        ->and((float) $account->fresh()->contributed_capital)->toBe(3100000.0)
        ->and((float) $profile->fresh()->saldo_digital)->toBe(3100000.0)
        ->and(CapitalLedgerEntry::count())->toBe($entryCount + 1)
        ->and($legacyEntry->fresh())->not->toBeNull()
        ->and($correction->entry_type)->toBe('adjustment')
        ->and($correction->direction)->toBe('debit')
        ->and((float) $correction->amount)->toBe(15158210.0)
        ->and((float) $account->entries()->get()->sum('signed_amount'))->toBe(3100000.0);
});

it('membatasi saldo profil lama sebelum POS menyinkronkannya ke ledger', function () {
    $this->actingAs(User::factory()->create());
    $profile = digitalCapitalProfile();
    $account = app(CapitalLedgerService::class)->ensureAccounts()->firstWhere('slug', 'saldo-digital');
    $profile->update(['saldo_digital' => 18258210]);

    $this->get('/pos')->assertOk();

    expect((float) $profile->fresh()->saldo_digital)->toBe(3100000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(3100000.0)
        ->and((float) $account->fresh()->contributed_capital)->toBe(3100000.0);
});

it('menolak saldo positif tanpa modal disetor', function () {
    $this->actingAs(User::factory()->create());
    $profile = digitalCapitalProfile(0);

    $this->from('/settings/store')->post('/settings/store', [
        'store_name' => $profile->store_name,
        'address' => $profile->address,
        'phone' => $profile->phone,
        'saldo_digital' => 1,
    ])->assertSessionHasErrors('saldo_digital');

    expect((float) $profile->fresh()->saldo_digital)->toBe(0.0);
});

it('menaikkan batas saldo hanya setelah tambahan modal dicatat', function () {
    $this->actingAs(User::factory()->create());
    $profile = digitalCapitalProfile();
    $account = app(CapitalLedgerService::class)->ensureAccounts()->firstWhere('slug', 'saldo-digital');

    $this->post('/capital/entries', [
        'capital_account_id' => $account->id,
        'direction' => 'credit',
        'amount' => 500000,
        'entry_date' => '2026-11-01',
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/settings/store', [
        'store_name' => $profile->store_name,
        'address' => $profile->address,
        'phone' => $profile->phone,
        'saldo_digital' => 3600000,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect((float) $account->fresh()->contributed_capital)->toBe(3600000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(3600000.0)
        ->and((float) $profile->fresh()->saldo_digital)->toBe(3600000.0);
});
