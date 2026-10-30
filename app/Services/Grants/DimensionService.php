<?php

namespace App\Services\Grants;

use App\Models\CostCentre;
use App\Models\Grant;
use App\Models\GrantDea;
use App\Models\MeProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The coding block every line can carry — SOF (grant_id), DEA (dea_id),
 * project (project_id, an M&E project) and cost centre (cost_centre_id) — and
 * the rules for it: each must be the company's own; a DEA belongs to the SOF
 * on the same line (and so needs one); and a line can only be charged to a
 * SOF that is active and whose dates cover the transaction date.
 */
class DimensionService
{
    public const KEYS = ['grant_id', 'dea_id', 'project_id', 'cost_centre_id'];

    /** The four ids of a line, null where not given. */
    public static function of(array $line): array
    {
        $dimensions = [];
        foreach (self::KEYS as $key) {
            $dimensions[$key] = isset($line[$key]) && $line[$key] !== '' ? (int) $line[$key] : null;
        }

        return $dimensions;
    }

    /**
     * Validates the coding on each of $lines (keyed as given, so errors read
     * "lines.2.grant_id") for a transaction dated $date. $prefix is the
     * request key the lines sit under ('lines', 'items'), or '' for a single
     * coded document such as a supplier bill.
     */
    public function validate(int $companyId, array $lines, string $date, string $prefix = 'lines'): void
    {
        $coded = collect($lines)->map(fn ($line) => self::of($line));
        $ids = fn (string $key) => $coded->pluck($key)->filter()->unique()->values()->all();

        $grants = Grant::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $ids('grant_id'))->get()->keyBy('id');
        $deas = GrantDea::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $ids('dea_id'))->get()->keyBy('id');
        $projects = MeProject::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $ids('project_id'))->pluck('id')->flip();
        $costCentres = CostCentre::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $ids('cost_centre_id'))->pluck('id')->flip();

        $errors = [];
        foreach ($coded as $index => $dimensions) {
            $field = fn (string $key) => $prefix === '' ? $key : "{$prefix}.{$index}.{$key}";

            if ($dimensions['grant_id']) {
                $grant = $grants->get($dimensions['grant_id']);
                if (! $grant) {
                    $errors[$field('grant_id')][] = 'The selected SOF does not exist.';
                } elseif ($grant->status !== 'active') {
                    $errors[$field('grant_id')][] = "SOF {$grant->code} is closed and can't be charged.";
                } elseif ($date < $grant->start_date->toDateString() || $date > $grant->end_date->toDateString()) {
                    $errors[$field('grant_id')][] = "SOF {$grant->code} runs {$grant->start_date->toDateString()} to {$grant->end_date->toDateString()}; {$date} is outside it.";
                }
            }

            if ($dimensions['dea_id']) {
                $dea = $deas->get($dimensions['dea_id']);
                if (! $dea) {
                    $errors[$field('dea_id')][] = 'The selected DEA does not exist.';
                } elseif ($dea->grant_id !== $dimensions['grant_id']) {
                    $errors[$field('dea_id')][] = "DEA {$dea->code} belongs to a different SOF; choose its SOF on the same line.";
                }
            }

            if ($dimensions['project_id'] && ! $projects->has($dimensions['project_id'])) {
                $errors[$field('project_id')][] = 'The selected project does not exist.';
            }

            if ($dimensions['cost_centre_id'] && ! $costCentres->has($dimensions['cost_centre_id'])) {
                $errors[$field('cost_centre_id')][] = 'The selected cost centre does not exist.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Report filters: limits a query over journal_entry_lines (or another
     * table carrying the coding block) to lines coded to every dimension given.
     * Journal lines also carry the department of the product or service they
     * came from, filterable the same way.
     */
    public static function filter(Builder|\Illuminate\Database\Query\Builder $query, array $dimensions, string $table = 'journal_entry_lines'): Builder|\Illuminate\Database\Query\Builder
    {
        foreach ([...self::KEYS, 'department_id'] as $key) {
            if (! empty($dimensions[$key])) {
                $query->where("{$table}.{$key}", (int) $dimensions[$key]);
            }
        }

        return $query;
    }

    /** Validation rules for the dimension filters on a report request. */
    public static function filterRules(): array
    {
        return [
            'grant_id' => ['nullable', 'integer'],
            'dea_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'cost_centre_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ];
    }

    /** Validation rules for the coding block on lines under $prefix ('lines.*', 'items.*', or '' for the document itself). */
    public static function lineRules(string $prefix = ''): array
    {
        $key = fn (string $name) => $prefix === '' ? $name : "{$prefix}.{$name}";

        return [
            $key('grant_id') => ['nullable', 'integer'],
            $key('dea_id') => ['nullable', 'integer'],
            $key('project_id') => ['nullable', 'integer'],
            $key('cost_centre_id') => ['nullable', 'integer'],
        ];
    }
}
