<?php

namespace App\Services;

use DateTime;
use Illuminate\Support\Facades\DB;

/**
 * Leave credit rules ported as-is from the legacy HRIS accounting pages
 * (accounting/leavecredits.php and accounting/fetch_leave_dates.php).
 * Read-only: nothing here writes to the database.
 */
class LeaveCreditService
{
    private const HRIS_CONNECTION = 'hris';

    public const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    // Leave types and the attendance remarks that count as using them
    public const TYPES = [
        'VL'  => ['label' => 'Vacation Leave', 'remarks' => ['VL'], 'period_based' => true, 'exclude_first_date' => true],
        'SL'  => ['label' => 'Sick Leave', 'remarks' => ['SL-A', 'SL-B', 'SL', 'SL-C', 'SL-IO', 'SL-NC', 'SL-PO', 'SL-GS', 'SL-PcP', 'SL-TI', 'SL-TP'], 'period_based' => true, 'exclude_first_date' => false],
        'PTO' => ['label' => 'Personal Time Off', 'remarks' => ['PTO'], 'year_based' => true],
        'SPL' => ['label' => 'Solo Parent Leave', 'remarks' => ['SPL'], 'period_based' => true, 'exclude_first_date' => false],
        'BLP' => ['label' => 'Birthday Leave', 'remarks' => ['BLP'], 'year_based' => true],
    ];

    /**
     * Eligibility date, length of service and current leave period.
     * Hired on or before 2020-07-31 with a regularization date → counted from regularization.
     */
    public function serviceData(?string $dateHired, ?string $dateRegular, DateTime $today): array
    {
        $result = [
            'eligibility' => null,
            'years'       => 0,
            'months'      => 0,
            'days'        => 0,
            'periodfrom'  => null,
            'periodto'    => null,
        ];

        if (!$this->isDate($dateHired)) {
            return $result;
        }

        $hireDate = new DateTime($dateHired);
        $start = $this->isDate($dateRegular) && $hireDate <= new DateTime('2020-07-31')
            ? new DateTime($dateRegular)
            : new DateTime($dateHired);

        $endDate = new DateTime($today->format('Y-m-d'));
        $current = clone $start;
        $years = 0;
        $months = 0;

        while ((clone $current)->modify('+1 year') <= $endDate) {
            $current->modify('+1 year');
            $years++;
        }

        while ((clone $current)->modify('+1 month') <= $endDate) {
            $current->modify('+1 month');
            $months++;
        }

        $periodFrom = clone $start;
        if ($years > 0) {
            $periodFrom->modify("+{$years} years");
        }
        $periodTo = (clone $periodFrom)->modify('+1 year');

        return [
            'eligibility' => $start,
            'years'       => $years,
            'months'      => $months,
            'days'        => $current->diff($endDate)->days,
            'periodfrom'  => $periodFrom,
            'periodto'    => $periodTo,
        ];
    }

    public function vacationLeave(int $years, ?string $dateRegular): float
    {
        $drhire = $this->isDate($dateRegular) ? new DateTime($dateRegular) : null;

        return match (true) {
            $years <= 0 => 0,
            $years <= 2 => 5,
            $years <= 4 => 8,
            $years <= 6 => 12,
            $years <= 8 => 15,
            $drhire && $drhire >= new DateTime('2017-01-01') => 20,
            default     => $years == 9 ? 25 : 30,
        };
    }

    public function sickLeave(int $years): float
    {
        return match (true) {
            $years < 1  => 0,
            $years <= 5 => 5,
            default     => 7,
        };
    }

    public function pto(?string $status, ?string $dateRegular, DateTime $today): float
    {
        if ($status !== 'REGULAR' || !$this->isDate($dateRegular)) {
            return 0;
        }

        $drhire = new DateTime($dateRegular);
        if ((int) $today->format('Y') !== (int) $drhire->format('Y')) {
            return 5;
        }

        return match ((int) $drhire->format('n')) {
            1       => 5,
            2, 3    => 4,
            4, 5, 6 => 3,
            7, 8, 9 => 2,
            default => 1,
        };
    }

    public function birthdayLeave(int $years): float
    {
        return $years >= 1 ? 1 : 0;
    }

    public function earlyOutCredits(): float
    {
        return 2;
    }

    /**
     * Credits / used / remaining for every leave, from the stored leave_credits
     * row; the legacy fallback formula is used only when a credit is empty (NULL).
     */
    public function balances(object $emp, array $service, DateTime $today): array
    {
        $years = $service['years'];
        $fallback = [
            'vl'  => fn () => $this->vacationLeave($years, $emp->dateofregular),
            'sl'  => fn () => $this->sickLeave($years),
            'pto' => fn () => $this->pto($emp->status, $emp->dateofregular, $today),
            'spl' => fn () => 0,
            'blp' => fn () => $this->birthdayLeave($years),
        ];
        $columns = [
            'vl'  => ['vacationleave', 'vlused'],
            'sl'  => ['sickleave', 'slused'],
            'pto' => ['pto', 'ptoused'],
            'spl' => ['spl', 'spl_used'],
            'blp' => ['bdayleave', 'blp_used'],
        ];
        foreach (self::MONTHS as $m) {
            $columns[$m] = ["{$m}_earlyout", "{$m}_eo_used"];
            $fallback[$m] = fn () => $this->earlyOutCredits();
        }

        $out = [];
        foreach ($columns as $key => [$creditCol, $usedCol]) {
            $credits = isset($emp->$creditCol) ? (float) $emp->$creditCol : $fallback[$key]();
            $used = isset($emp->$usedCol) ? (float) $emp->$usedCol : 0;
            $out[$key] = ['credits' => $credits, 'used' => $used, 'remaining' => $credits - $used];
        }

        return $out;
    }

    /**
     * Attendance dates counted as used for one leave type (VL, SL, PTO, SPL, BLP, JAN_EO … DEC_EO).
     */
    public function usedDates(string $idno, string $type, DateTime $today): array
    {
        $type = strtoupper(trim($type));
        $conn = DB::connection(self::HRIS_CONNECTION);
        $year = (int) $today->format('Y');

        if (preg_match('/^([A-Z]{3})_EO$/', $type, $m) && in_array(strtolower($m[1]), self::MONTHS, true)) {
            $month = array_search(strtolower($m[1]), self::MONTHS, true) + 1;
            $title = date('F', mktime(0, 0, 0, $month, 1)) . ' Early Out';
            $period = date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year;

            // EO, P/EO and anything ending in "/EO" (but not "/EEO")
            $query = $conn->table('attendance')
                ->where(fn ($q) => $q->whereIn('remarks', ['EO', 'P/EO'])->orWhere('remarks', 'like', '%/EO'))
                ->whereYear('logindate', $year)
                ->whereMonth('logindate', $month);
        } elseif (isset(self::TYPES[$type])) {
            $config = self::TYPES[$type];
            $title = $config['label'];
            $query = $conn->table('attendance')->whereIn('remarks', $config['remarks']);

            if (!empty($config['period_based'])) {
                $lc = $conn->table('leave_credits')->where('idno', $idno)->first(['periodfrom', 'periodto']);
                if (!$lc) {
                    return ['title' => $title, 'period' => null, 'dates' => [], 'message' => 'No leave period found'];
                }
                if (!$this->isDate($lc->periodfrom) || !$this->isDate($lc->periodto)) {
                    return ['title' => $title, 'period' => null, 'dates' => [], 'message' => 'No valid leave period found'];
                }

                $from = $config['exclude_first_date'] ? date('Y-m-d', strtotime($lc->periodfrom . ' +1 day')) : $lc->periodfrom;
                $query->whereBetween('logindate', [$from, $lc->periodto]);
                $period = date('M d, Y', strtotime($lc->periodfrom)) . ' - ' . date('M d, Y', strtotime($lc->periodto));
            } else {
                $query->whereYear('logindate', $year);
                $period = 'Year ' . $year;
            }
        } else {
            return ['title' => null, 'period' => null, 'dates' => [], 'message' => 'Invalid type'];
        }

        $dates = $query->where('idno', $idno)
            ->orderByDesc('logindate')
            ->get(['logindate', 'remarks'])
            ->map(fn ($r) => ['date' => date('M d, Y', strtotime($r->logindate)), 'remarks' => $r->remarks])
            ->all();

        return ['title' => $title, 'period' => $period, 'dates' => $dates, 'message' => null];
    }

    private function isDate(?string $date): bool
    {
        return !empty($date) && $date !== '0000-00-00';
    }
}
