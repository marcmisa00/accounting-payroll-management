<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    private function holidays()
    {
        return DB::connection('hris')->table('holidays');
    }

    public function index()
    {
        return view('holidays.holiday');
    }

    public function events(Request $request)
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end'   => ['required', 'date', 'after:start'],
        ]);

        $holidays = $this->holidays()
            ->where('date', '>=', $request->start)
            ->where('date', '<', $request->end)
            ->orderBy('date')
            ->get();

        return response()->json(
            $holidays->map(fn ($holiday) => [
                'id'    => (string) $holiday->id,
                'title' => $holiday->description,
                'start' => $holiday->date,
                'allDay' => true,
                'backgroundColor' => match ($holiday->type) {
                    'rh'    => '#dc3545',
                    'snwh'  => '#0d6efd',
                    'close' => '#6f42c1',
                    default => '#64748b',
                },
                'borderColor' => 'transparent',
                'extendedProps' => [
                    'type' => $holiday->type,
                    'location' => $holiday->location,
                    'description' => $holiday->description,
                ],
            ])
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateHoliday($request);

        $id = $this->holidays()->insertGetId([
            'date' => $data['date'],
            'type' => $data['type'],
            'location' => $data['location'],
            'description' => $data['description'],
        ]);

        return response()->json([
            'message' => 'Holiday added successfully.',
            'id' => $id,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validateHoliday($request);

        $updated = $this->holidays()
            ->where('id', $id)
            ->update([
                'date' => $data['date'],
                'type' => $data['type'],
                'location' => $data['location'],
                'description' => $data['description'],
            ]);

        if (!$updated && !$this->holidays()->where('id', $id)->exists()) {
            return response()->json([
                'message' => 'Holiday not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Holiday updated successfully.',
        ]);
    }

    public function destroy(int $id)
    {
        $holiday = $this->holidays()->where('id', $id)->first();

        if (!$holiday) {
            return response()->json([
                'message' => 'Holiday not found.',
            ], 404);
        }

        // Payroll uses historical holiday records.
        // Prevent accidental deletion of holidays from previous years.
        if (substr($holiday->date, 0, 4) < now()->format('Y')) {
            return response()->json([
                'message' => 'Previous-year holidays cannot be deleted because payroll may depend on them.',
            ], 422);
        }

        $this->holidays()->where('id', $id)->delete();

        return response()->json([
            'message' => 'Holiday deleted successfully.',
        ]);
    }

    private function validateHoliday(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(['rh', 'snwh', 'close'])],
            'location' => [
                'required',
                Rule::in([
                    'allbranch',
                    'Davao',
                    'Iloilo',
                    'Kidapawan',
                    'Panabo',
                    'Digos',
                    'Koronadal',
                ]),
            ],
            'description' => ['required', 'string', 'max:255'],
        ]);
    }
}
