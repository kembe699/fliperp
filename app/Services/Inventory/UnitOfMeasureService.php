<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class UnitOfMeasureService
{
    /**
     * A general-purpose starter set covering count, weight, volume, length, and time —
     * broad enough to be useful across most kinds of businesses without being exhaustive.
     * [name, abbreviation].
     */
    protected const DEFAULTS = [
        ['Piece', 'Pcs'], ['Each', 'Ea'], ['Dozen', 'Dz'], ['Pair', 'Pr'], ['Set', 'Set'],
        ['Box', 'Box'], ['Carton', 'Ctn'], ['Case', 'Case'], ['Pack', 'Pack'], ['Pallet', 'Plt'],
        ['Bundle', 'Bndl'], ['Roll', 'Roll'], ['Sheet', 'Sht'], ['Ream', 'Ream'],
        ['Kilogram', 'Kg'], ['Gram', 'g'], ['Milligram', 'mg'], ['Tonne', 'T'], ['Pound', 'lb'], ['Ounce', 'oz'],
        ['Liter', 'L'], ['Milliliter', 'mL'], ['Gallon', 'Gal'], ['Bottle', 'Btl'], ['Sachet', 'Sch'], ['Drum', 'Drm'],
        ['Meter', 'm'], ['Centimeter', 'cm'], ['Millimeter', 'mm'], ['Inch', 'in'], ['Foot', 'ft'], ['Yard', 'yd'],
        ['Hour', 'Hr'], ['Day', 'Day'], ['Week', 'Wk'], ['Month', 'Mo'], ['Year', 'Yr'], ['Unit', 'Unit'],
    ];

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return UnitOfMeasure::query()->latest()->paginate($perPage);
    }

    /**
     * Inserts every default unit not already present for this company (matched
     * case-insensitively by name), leaving existing rows — including any the user has
     * since renamed or customized — untouched. Safe to call repeatedly.
     */
    public function seedDefaults(): Collection
    {
        $existingNames = UnitOfMeasure::query()->pluck('name')->map(fn (string $name) => strtolower($name))->all();

        foreach (self::DEFAULTS as [$name, $abbreviation]) {
            if (in_array(strtolower($name), $existingNames, true)) {
                continue;
            }

            UnitOfMeasure::create(['name' => $name, 'abbreviation' => $abbreviation]);
        }

        return UnitOfMeasure::query()->orderBy('name')->get();
    }

    public function create(array $data): UnitOfMeasure
    {
        return UnitOfMeasure::create($data);
    }

    public function update(UnitOfMeasure $unitOfMeasure, array $data): UnitOfMeasure
    {
        $unitOfMeasure->update($data);

        return $unitOfMeasure;
    }

    public function delete(UnitOfMeasure $unitOfMeasure): void
    {
        if (Product::where('unit_of_measure_id', $unitOfMeasure->id)->exists()) {
            throw ValidationException::withMessages([
                'unit_of_measure' => ['Cannot delete a unit of measure that is still used by products.'],
            ]);
        }

        $unitOfMeasure->delete();
    }
}
