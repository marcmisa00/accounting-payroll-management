<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Master lists of addon and deduction names (HRIS `addons` / `deductions`).
 * Names only: amounts are entered per employee or per period in payroll edit.
 */
class PayrollItemController extends Controller
{
    private const HRIS_CONNECTION = 'hris';

    private const TYPES = [
        'addons' => [
            'table'        => 'addons',
            'column'       => 'addons',
            'employee'     => 'employee_addons',
            'foreign_key'  => 'addon_id',
            'period'       => 'payroll_addons',
            'label'        => 'Addon',
        ],
        'deductions' => [
            'table'        => 'deductions',
            'column'       => 'deduction',
            'employee'     => 'employee_deductions',
            'foreign_key'  => 'deduction_id',
            'period'       => 'payroll_deductions',
            'label'        => 'Deduction',
        ],
    ];

    public function index(): View
    {
        $conn = DB::connection(self::HRIS_CONNECTION);
        $lists = [];

        foreach (self::TYPES as $type => $t) {
            $items = $conn->table($t['table'])
                ->select('id', $t['column'] . ' as name')
                ->orderBy($t['column'])
                ->get();

            $employeeCounts = $conn->table($t['employee'])
                ->select($t['foreign_key'] . ' as item_id', DB::raw('COUNT(DISTINCT idno) as n'))
                ->groupBy($t['foreign_key'])
                ->pluck('n', 'item_id');

            // Period rows store the name as text, so they are matched by name
            $periodCounts = $items->isEmpty() ? collect() : $conn->table($t['period'])
                ->whereIn('description', $items->pluck('name')->filter())
                ->select('description', DB::raw('COUNT(*) as n'))
                ->groupBy('description')
                ->pluck('n', 'description')
                ->mapWithKeys(fn ($n, $name) => [mb_strtolower(trim($name)) => $n]);

            $lists[$type] = $items->map(function ($item) use ($employeeCounts, $periodCounts) {
                $item->employees = (int) ($employeeCounts[$item->id] ?? 0);
                $item->period_rows = (int) ($periodCounts[mb_strtolower(trim((string) $item->name))] ?? 0);

                return $item;
            });
        }

        return view('payroll.items', [
            'lists' => $lists,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $t = self::TYPES[$type];
        $name = $this->validatedName($request, $type);
        $conn = DB::connection(self::HRIS_CONNECTION);

        if ($this->nameExists($conn, $t, $name)) {
            return $this->back($type, 'error', "\"{$name}\" is already in the {$t['label']} list.");
        }

        // amount is unused by payroll; 0 keeps the legacy HRIS list page working
        $conn->table($t['table'])->insert([
            $t['column'] => $name,
            'amount'     => 0,
        ]);

        return $this->back($type, 'success', "{$t['label']} \"{$name}\" added.");
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $t = self::TYPES[$type];
        $name = $this->validatedName($request, $type);
        $conn = DB::connection(self::HRIS_CONNECTION);

        $current = $conn->table($t['table'])->where('id', $id)->value($t['column']);

        if ($current === null) {
            return $this->back($type, 'error', "That {$t['label']} no longer exists.");
        }

        // Saved payroll keeps the old name as text, so only unused items can be renamed
        if ($this->employeeCount($conn, $t, $id) > 0 || $this->periodCount($conn, $t, $current) > 0) {
            return $this->back($type, 'error', "\"{$current}\" is already used in payroll, so it can't be renamed. Add a new {$t['label']} instead.");
        }

        if ($this->nameExists($conn, $t, $name, $id)) {
            return $this->back($type, 'error', "\"{$name}\" is already in the {$t['label']} list.");
        }

        $conn->table($t['table'])->where('id', $id)->update([$t['column'] => $name]);

        return $this->back($type, 'success', "Renamed to \"{$name}\".");
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $t = self::TYPES[$type];
        $conn = DB::connection(self::HRIS_CONNECTION);

        $result = $conn->transaction(function () use ($conn, $t, $id) {
            $current = $conn->table($t['table'])->where('id', $id)->lockForUpdate()->value($t['column']);

            if ($current === null) {
                return ['error', "That {$t['label']} no longer exists."];
            }

            $employees = $this->employeeCount($conn, $t, $id);

            if ($employees > 0) {
                return ['error', "\"{$current}\" is used by {$employees} " . ($employees === 1 ? 'employee' : 'employees') . '. Remove it from them first.'];
            }

            $conn->table($t['table'])->where('id', $id)->delete();

            return ['success', "{$t['label']} \"{$current}\" deleted."];
        });

        return $this->back($type, ...$result);
    }

    private function validatedName(Request $request, string $type): string
    {
        $request->merge(['name' => preg_replace('/\s+/', ' ', trim((string) $request->input('name')))]);

        $data = $request->validateWithBag($type, [
            'name' => ['required', 'string', 'max:100'],
        ]);

        return $data['name'];
    }

    private function nameExists($conn, array $t, string $name, ?int $exceptId = null): bool
    {
        return $conn->table($t['table'])
            ->whereRaw('LOWER(TRIM(`' . $t['column'] . '`)) = ?', [mb_strtolower($name)])
            ->when($exceptId, fn ($q) => $q->where('id', '<>', $exceptId))
            ->exists();
    }

    private function employeeCount($conn, array $t, int $id): int
    {
        return (int) $conn->table($t['employee'])->where($t['foreign_key'], $id)->distinct()->count('idno');
    }

    private function periodCount($conn, array $t, string $name): int
    {
        return $conn->table($t['period'])->where('description', $name)->count();
    }

    private function back(string $type, string $status, string $message): RedirectResponse
    {
        return redirect()
            ->route('payroll.items.index')
            ->with('items_flash', ['type' => $type, 'status' => $status, 'message' => $message]);
    }
}
