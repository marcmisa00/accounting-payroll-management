<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Ports the attendance-based payroll calculation loop from the legacy
 * editpayroll.php.
 *
 * STATUS — staged port, pass 1 of 4:
 *   1. Base pay + overtime (before/after 8 hrs, OB/idle deductions)  <-- THIS PASS
 *   2. Night differential (including manual ND overrides)            <-- not yet ported
 *   3. Holiday pay (regular + special non-working, worked + not-worked) <-- not yet ported
 *   4. Leave types (VL/SL/BL/BLP/SPL/PTL + tenure-based BL eligibility)  <-- not yet ported
 *
 * Until passes 2-4 land:
 *   - $ndhrs / $ndrate always compute to 0 (no night differential pay yet).
 *   - Holiday detection is not run at all — regholiday/spholiday and every
 *     holiday-specific multiplier stay 0, even on a day whose stored
 *     `status` already contains an "rh"/"snwh" flag, and a holiday actually
 *     WORKED will be paid as a normal day for now (under-paid until pass 3).
 *   - The remarks-based leave cascade (VL/SL/BL/BLP/SPL/PTL tracking and
 *     BL tenure-eligibility zeroing) is not run. A leave day whose `status`
 *     contains the "leave" token still gets its full daily rate via the
 *     $leave>0 shortcut in the legacy code (ported below), so ordinary
 *     leave days are usually already paid correctly — but BL-ineligible
 *     employees are NOT yet zeroed out, and the paidVLhrs/paidSLhrs/etc.
 *     reporting totals stay 0 until pass 4.
 *
 * Two pieces of the legacy file were dropped entirely because they were
 * dead code (computed but never read anywhere downstream):
 *   - The top-level movement_tracker/dlsTop/adjustedEffectivity block
 *   - $hasDlsChange, and the standalone isNightShift($dls) function
 * A number of unused intermediate hour-difference variables
 * (eogybefore/eogyafter/difference_eo/difference_ott/diff_gylu/diff_gyam/
 * totalstart/totalam/shiftdiff/wfhhrs/wfhnotgy/sevendif/onedif) were
 * dropped for the same reason. Flag if any of those turn out to matter.
 */
class PayrollCalculationService
{
    private const HRIS_CONNECTION = 'hris';

    /**
     * @return array{rows: array, totals: array}
     */
    public function calculate(
        string $idno,
        int $periodId,
        string $periodStart,
        string $periodEnd,
        string $company,
        string $salaryType,
        int $workdays,
        bool $blockOtBefore,
        bool $blEligible
    ): array {
        $conn = DB::connection(self::HRIS_CONNECTION);

        $employeeDetails = $conn->table('employee_details')->where('idno', $idno)->first();
        $employeePayroll = $conn->table('employee_payroll')->where('idno', $idno)->first();

        if (! $employeeDetails) {
            return ['rows' => [], 'totals' => $this->emptyTotals()];
        }

        $employeeWorkArea = $employeeDetails->work_area ?? '';
        $location          = $employeeDetails->location ?? '';
        $salary            = $employeePayroll->salary ?? 0;
        $previousSalary    = $employeePayroll->previous_salary ?? 0;
        $effectivityRaw    = $employeePayroll->effective_date ?? null;

        $attendanceStart = date('Y-m-d', strtotime($periodStart . ' -1 day'));

        $attendanceRows = $conn->table('attendance')
            ->where('idno', $idno)
            ->whereBetween('logindate', [$attendanceStart, $periodEnd])
            ->orderBy('logindate')
            ->orderByDesc('addedtime')
            ->get();

        $rows = [];
        $t = $this->emptyTotals(); // running totals accumulator

        foreach ($attendanceRows as $attendance) {
            $logindate      = $attendance->logindate;
            $attendanceDls  = $attendance->attendance_dls ?? 0;

            $payrollDate = $attendanceDls == 1
                ? date('Y-m-d', strtotime($logindate . ' +1 day'))
                : $logindate;

            if ($payrollDate < $periodStart || $payrollDate > $periodEnd) {
                continue;
            }

            $dayOfWeek = date('l', strtotime($logindate));

            [$startshift, $endshift, $dls] = $this->resolveShiftForDate(
                $conn, $idno, $logindate, $attendance->shiftchange ?? null, $dayOfWeek, $employeeDetails
            );

            [$loginam, $logoutam, $loginpm, $logoutpm] = $this->resolveDisplayTimes(
                $conn, $idno, $logindate, $attendance
            );

            $isNightShift = ($attendanceDls == 1);

            $loginam_ts  = ($loginam !== '0') ? strtotime($loginam) : 0;
            $logoutam_ts = ($logoutam !== '0') ? strtotime($logoutam) : 0;
            $startshift_ts = strtotime($startshift);

            if ($loginam_ts > 0) {
                if ($isNightShift) {
                    if ($startshift === '23:00:00') {
                        if ((int) date('H', $loginam_ts) < 12) {
                            $loginam_ts += 86400;
                        }
                    } elseif ($startshift === '00:00:00') {
                        if ((int) date('H', $loginam_ts) >= 18) {
                            $loginam_ts -= 86400;
                        }
                    }
                }

                if (in_array($location, ['WFH', 'Hybrid'], true)) {
                    if ($loginam_ts < $startshift_ts) {
                        $loginam_ts = $startshift_ts;
                    }
                } else {
                    $allowedEarly = $startshift_ts - 600;
                    if ($loginam_ts < $allowedEarly) {
                        $loginam_ts = $allowedEarly;
                    }
                }
            }

            $loginam  = $loginam_ts ? date('H:i:s', $loginam_ts) : '0';
            $logoutam = $logoutam_ts ? date('H:i:s', $logoutam_ts) : '0';

            $rowDls = $attendanceDls;
            $displayLogindate = date('Y-m-d', strtotime($logindate) + ($rowDls ? 86400 : 0));

            $idle       = $attendance->idle ?? null;
            $otremarks  = $attendance->otremarks;
            $ottime     = $attendance->ottime ?? 0;
            $status     = $attendance->status;
            $remarks    = $attendance->remarks;

            $ab_count  = substr_count($status, 'ab');
            $pto_count = substr_count($remarks, 'PTO');
            $mtl_count = substr_count($remarks, 'MTL');
            $mdl_count = substr_count($remarks, 'MDL');
            $ltl_count = substr_count($remarks, 'LTL');
            $suspended_count = substr_count($remarks, 'SUS');

            $t['_ab_total']  = ($t['_ab_total']  ?? 0) + $ab_count;
            $t['_pto_total'] = ($t['_pto_total'] ?? 0) + $pto_count;
            $t['_mtl_total'] = ($t['_mtl_total'] ?? 0) + $mtl_count;
            $t['_mdl_total'] = ($t['_mdl_total'] ?? 0) + $mdl_count;
            $t['_ltl_total'] = ($t['_ltl_total'] ?? 0) + $ltl_count;
            $t['_suspended_total'] = ($t['_suspended_total'] ?? 0) + $suspended_count;

            $lunchouttime = '06:30:00';
            $lunchintime  = '07:30:00';
            $halftime     = '12:00:00';

            $toMin = fn ($v) => ($v === '0' || $v === null) ? 0 : floor(strtotime($v) / 60) * 60;

            $time1am   = $toMin($loginam);
            $time2am   = $toMin($logoutam);
            $time1pm   = $toMin($loginpm);
            $time2pm   = $toMin($logoutpm);
            $timeotpm  = ($ottime === '0' || !$ottime) ? 0 : $toMin($ottime);
            $timestart = $toMin($startshift);
            $timeend   = strtotime($endshift);
            $timeout   = strtotime($lunchouttime);
            $timein    = strtotime($lunchintime);
            $halfhrs   = strtotime($halftime);

            $difference_am   = round(abs($time2am - $time1am) / 3600, 2);
            $difference_pm   = round(abs($time2pm - $time1pm) / 3600, 2);
            $difference_amot = round(abs($timeout - $time1am) / 3600, 2);
            $difference_pmot = round(abs($timeend - $timein) / 3600, 2);
            $obbreak = round(abs($time2am - $time1pm) / 3600, 2);
            $halfday = round(abs($time1am - $halfhrs) / 3600, 2);

            if ($time2pm < $timestart) {
                $time2pm += 86400;
            }
            if ($time2pm < $time1am) {
                $time2pm += 86400;
            }
            if ($timeend < $time1am) {
                $timeend += 86400;
            }

            $idleHours = (!empty($idle) && $idle !== '00:00:00')
                ? (strtotime($idle) - strtotime('00:00:00')) / 3600
                : 0;

            if ($timeotpm < $time1am) {
                $timeotpm += 86400;
            }

            $checkTotal = round(abs($timeend - $time1am) / 3600, 2) - 1;

            if ($otremarks === 'OT') {
                $totalwo = ($time2pm > $timeotpm)
                    ? round(abs($timeotpm - $time1am) / 3600, 2) - 1
                    : round(abs($time2pm - $time1am) / 3600, 2) - 1;
            } elseif ($time2am == 0 && $time1pm == 0) {
                $totalwo = round(abs($time2pm - $time1am) / 3600, 2);
            } elseif ($time2pm < $timeend && $logoutpm != 0) {
                $totalwo = round(abs($time2pm - $time1am) / 3600, 2) - 1;
            } else {
                $totalwo = round(abs($timeend - $time1am) / 3600, 2) - 1;
            }

            if ($salaryType === 'Fixed') {
                $totalwo = 8;
            }
            if (is_null($status) && is_null($remarks)) {
                $totalwo = 8;
            }
            if ($otremarks === 'OT') {
                $logoutpm = min($logoutpm, $ottime);
            }

            if (in_array($location, ['WFH', 'Hybrid'], true)) {
                if (is_null($status) && is_null($remarks)) {
                    $totalwo = 8;
                } elseif ($time2am === 0 && $time1pm === 0) {
                    $totalwo = round(abs($time2pm - $time1am) / 3600, 2);
                } elseif ($time2pm < $timeend && $logoutpm != 0) {
                    $totalwo = round(abs($time2pm - $time1am) / 3600, 2) - 1;
                } else {
                    $totalwo = round(abs($timeend - $time1am) / 3600, 2) - 1;
                }

                if (in_array($remarks, ['P/EO', 'P/EEO', 'EEO-IO', 'EEO-PO', 'EEO-PcP', 'P-TD', 'EEO-NC', 'EEO-GS'], true)) {
                    $totalwo = ($time2am === 0 && $time1pm === 0)
                        ? round(abs($time2pm - $timestart) / 3600, 2)
                        : round(abs($time2pm - $timestart) / 3600, 2) - 1;
                }

                if ($otremarks === 'OT') {
                    $totalwo = ($time2pm > $timeotpm)
                        ? round(abs($timeotpm - $time1am) / 3600, 2) - 1
                        : round(abs($time2pm - $time1am) / 3600, 2) - 1;
                }
            }

            // Status-token flags (parsed once; work/rh/snwh/leave are also
            // needed by the ND/holiday/leave passes still to come).
            $nd = $work = $rh = $snwh = $leave = $ot = $pot = $ab = $sus = 0;
            foreach (explode('/', (string) $status) as $token) {
                match ($token) {
                    'nd'   => $nd++,
                    'work' => $work++,
                    'rh'   => $rh++,
                    'snwh' => $snwh++,
                    'leave'=> $leave++,
                    'ot'   => $ot++,
                    'pt'   => $pot++,
                    'ab'   => $ab++,
                    'sus'  => $sus++,
                    default => null,
                };
            }

            $ndhrs = 0;    // pass 2
            $ndrate = 0;   // pass 2
            $spholiday = $spholidayot = $regholiday = $regholidayot = 0;      // pass 3
            $reghoursnwamount = 0;                                            // pass 3
            $isRegularHoliday = false;                                        // pass 3 will compute this for real

            $effectivity = $effectivityRaw
                ? ($dls == 1 ? date('Y-m-d', strtotime($effectivityRaw . ' -1 day')) : $effectivityRaw)
                : 'N/A';

            $empsalary = ($effectivity !== 'N/A' && $effectivity > $logindate) ? $previousSalary : $salary;
            $missingsal = $empsalary;

            if ($otremarks === 'OT') {
                $empsalary = ($checkTotal > 7.98) ? (($empsalary / 8) * 8) : (($empsalary / 8) * $checkTotal);
            }

            if ($leave > 0) {
                // Legacy shortcut: a "leave" status day is paid the full
                // daily rate as-is (unless OT-adjusted above). The
                // remarks-specific VL/SL/BL/etc. tracking + BL-eligibility
                // zeroing is pass 4.
                $ndhrs = 0;
                $ndrate = 0;
            } else {
                $empsalary = ($totalwo > 7.98) ? (($missingsal / 8) * 8) : (($missingsal / 8) * $totalwo);
            }

            $overtime = 0;
            $overtimebefore = 0;
            $regdaysot = 0;
            $obsubsal = 0;

            if ($work > 0 && $location === 'OS') {
                if ($startshift === '00:00:00' || $startshift === '23:00:00') {
                    $gy_am_hours = $this->hoursWithMidnight($time1am, $timeout);
                    $totalhrs = $gy_am_hours + $difference_pmot;
                } else {
                    $totalhrs = $difference_amot + $difference_pmot;
                }

                if ($totalwo > 8) {
                    $overtimebefore = $totalhrs - 8;
                    if ($overtimebefore < 0.0167) {
                        $overtimebefore = 0;
                    } elseif ($overtimebefore > 0.17) {
                        $overtimebefore = 0.17;
                    }
                    if ($blockOtBefore) {
                        $overtimebefore = 0;
                    }
                }

                $obDeduct = ($obbreak > 1) ? $obbreak - 1 : 0;
                $usedFromOT_OB = min($overtimebefore, $obDeduct);
                $overtime = $overtimebefore - $usedFromOT_OB;
                $remainingOb = $obDeduct - $usedFromOT_OB;

                $obsubsal = ($missingsal / 8) * $remainingOb;
                $totalwo -= $remainingOb;

                $regdaysot = (($missingsal / 8) * 1.25) * $overtime;
            } else {
                $obDeduct = ($obbreak > 1) ? $obbreak - 1 : 0;
                $totalwo -= $obDeduct;
                if ($totalwo < 0) {
                    $totalwo = 0;
                }
                $obsubsal = ($missingsal / 8) * $obDeduct;
            }

            $reghrs = 8;

            if ($nd > 0 && $pot == 0 && $otremarks === 'OT') {
                $overtime = $totalwo - $reghrs;
            }

            if (in_array($remarks, ['P/EO', 'P/EEO', 'EEO-IO', 'EEO-PO', 'EEO-PcP', 'P-TD', 'EEO-NC', 'EEO-GS'], true)) {
                $totalhrs = $totalwo;
            }

            $isEOEEO = ($work > 0 && in_array($remarks, ['P/EO', 'P/P/EO', 'P/EEO', 'EO', 'EEO', 'EEO-NC', 'EEO-PO', 'EEO-PcP', 'EEO-IO', 'EEO-GS'], true));

            if ($isEOEEO) {
                $overtime = 0;
                $regdaysot = 0;
                $overtimebefore = 0;

                if ($time2pm < $time1am) {
                    $time2pm += 86400;
                }

                if ($startshift === '00:00:00' || $startshift === '23:00:00') {
                    $totalhrs = $this->hoursWithMidnight($time1am, $time2pm);
                }

                $work = $totalhrs ?? 0;
            }

            if (in_array($salaryType, ['Daily', 'Fixed'], true)
                && in_array($remarks, ['EEO', 'EO', 'EEO-IO', 'EEO-PO'], true)) {
                $totalwo = 0;
                $reghrs = 0;
                $ndhrs = 0;
            }

            if ($nd > 0 && $otremarks === 'OT' && $work) {
                $overtime = $totalwo - $reghrs;
            }

            if ($work > 0 && $otremarks === 'OT') {
                if ($startshift === '00:00:00' || $startshift === '23:00:00') {
                    $gy_am_hours = $this->hoursWithMidnight($time1am, $timeout);
                    $totalhrs = $gy_am_hours + $difference_pmot;
                } else {
                    $totalhrs = $difference_amot + $difference_pmot;
                }

                $overtime = 0;
                $overtimebefore = 0;

                if ($totalhrs > 8) {
                    $overtimebefore = $totalhrs - 8;
                    if ($overtimebefore < 0.0167) {
                        $overtimebefore = 0;
                    } elseif ($overtimebefore > 0.17) {
                        $overtimebefore = 0.17;
                    }
                    if ($blockOtBefore) {
                        $overtimebefore = 0;
                    }
                }

                $overtimeafter = ($totalwo - $overtimebefore) - 8;
                $overtime = ($totalwo <= 8) ? 0 : ($overtimeafter + $overtimebefore);
            }

            if ($idleHours > 0) {
                if ($overtime > 0) {
                    $totalwo = round($totalwo - $idleHours, 2);
                    if ($overtime >= $idleHours) {
                        $overtime -= $idleHours;
                        $idleHours = 0;
                    } else {
                        $idleHours -= $overtime;
                        $overtime = 0;
                    }
                }
                if ($idleHours > 0) {
                    $totalwo = round($totalwo - $idleHours, 2);
                }

                $empsalary = ($totalwo > 7.98) ? (($missingsal / 8) * 8) : (($missingsal / 8) * $totalwo);
            }

            // Manual OT override (attendance_ot_override), same as legacy.
            $otOverride = $conn->table('attendance_ot_override')->where('attendance_id', $attendance->id)->value('ot_adjustment');
            $ot_minutes = $otOverride ?? 0;
            $final_ot = round($overtime + ($ot_minutes / 60), 2);
            $overtime = $final_ot;

            $regdaysot = (($missingsal / 8) * 1.25) * $overtime;

            // Simple unpaid-absence remarks: zero everything for the day.
            if (in_array($remarks, ['CI', 'CI-A', 'Code A', 'CI-C', 'CI-NC', 'CI-B', 'CI-IO', 'CI-PO', 'AA', '-'], true)) {
                $empsalary = 0; $totalwo = 0; $reghrs = 0; $ndhrs = 0;
            }
            if ($remarks === 'RD') {
                $regdaysot = 0;
                $overtime = 0;
                if ($totalwo > 7.98) {
                    $empsalary = ($empsalary / 8) * 8;
                }
            }
            if (in_array($remarks, ['PTO', 'OC', 'SUS', 'LTL', 'MDL', 'CI', 'AWOL', 'MTL', 'AWOL2', 'RES'], true)) {
                $empsalary = 0; $totalwo = 0; $reghrs = 0; $ndhrs = 0;
            }

            if ($totalwo > 8) {
                $reghrs = 8;
            } else {
                $reghrs = $totalwo;
            }

            // Manual ND override lookup is skipped in this pass — ND stays
            // at 0 until pass 2 — but keep the display placeholder shape.
            $final_nd = $ndhrs;
            $is_manual_nd = false;

            $totalpay = $this->computeDailyTotalPay(
                $salaryType, $empsalary, $regdaysot, $ndrate, $spholiday, $spholidayot,
                $regholiday, $regholidayot, $reghoursnwamount, $obsubsal, $salary,
                $ab_count, $pto_count, $mtl_count, $mdl_count, $ltl_count, $suspended_count,
                $ab
            );

            // Row coloring, same precedence order as legacy.
            $style = '';
            if ($effectivity !== 'N/A' && $displayLogindate >= $periodStart && $displayLogindate <= $periodEnd && $displayLogindate < $effectivity) {
                $style = 'color: #FF0000;';
            }
            if ($otremarks === 'OT') {
                $style = 'color: #8F00FF;';
            }
            if ($rh > 0) { // rh_auto/rh_auto_nextday come from holiday detection, pass 3
                $style = 'color: orange;';
            }
            if ($snwh > 0) { // snwh_auto comes from holiday detection, pass 3
                $style = 'color: #f50a50;';
            }

            $showTimes = ! (($sus > 0 && $remarks === 'SUS') || $leave > 0 || $ab > 0);

            $rows[] = [
                'date'         => date('m/d/Y', strtotime($displayLogindate)),
                'show_times'   => $showTimes,
                'loginam'      => $loginam === '0' ? null : date('h:i A', strtotime($loginam)),
                'logoutam'     => $logoutam === '0' ? null : date('h:i A', strtotime($logoutam)),
                'loginpm'      => $loginpm === '0' ? null : date('h:i A', strtotime($loginpm)),
                'logoutpm'     => $logoutpm === '0' ? null : date('h:i A', strtotime($logoutpm)),
                'totalwo'      => $totalwo,
                'reghrs'       => $reghrs,
                'ot'           => number_format($final_ot, 2),
                'ot_attendance_id' => $attendance->id,
                'ot_minutes'   => $ot_minutes,
                'nd'           => $final_nd,
                'nd_is_manual' => $is_manual_nd,
                'ratday'       => number_format($empsalary, 2),
                'regdaysot'    => number_format($regdaysot, 2),
                'ndrate'       => number_format($ndrate, 2),
                'spholiday'    => number_format($spholiday, 2),
                'spholidayot'  => number_format($spholidayot, 2),
                'regholidayot' => number_format($regholidayot, 2),
                'regholiday'   => number_format($regholiday, 2),
                'totalpay'     => number_format($totalpay, 2),
                'style'        => $style,
                'attendance_id'=> $attendance->id,
            ];

            if (($nd > 0 || $work > 0) && $rh == 0 && $snwh == 0 && $leave == 0) {
                $t['regular_hours'] += $reghrs;
            }
            $t['totalhours']      += $totalwo;
            $t['regularhours']    += $reghrs;
            $t['totalovertime']   += $overtime;
            $t['totalregdaysot']  += $regdaysot;
            $t['totalbasesalary'] += $empsalary;

            if ($effectivity !== 'N/A' && $effectivity > $logindate) {
                $t['reghours_prev']         += $reghrs;
                $t['reghoursot_prev']       += $overtime;
                $t['reghoursotamount_prev'] += $regdaysot;
                $t['totalbasesalary_prev']  += $empsalary;
                $t['totalpay_prev']         += $totalpay;
            }

            if ($salaryType === 'Rated') {
                $t['grandtotal'] += $totalpay;
            } elseif ($salaryType === 'Fixed') {
                $totalDeductions = $t['_ab_total'] + $t['_pto_total'] + $t['_mtl_total'] + $t['_mdl_total'] + $t['_ltl_total'] + $t['_suspended_total'];
                if ($workdays <= 10) {
                    $t['grandtotal'] = ($salary * 10) - ($salary * $totalDeductions);
                } else {
                    $workingDaysPresent = $workdays - $totalDeductions;
                    $t['grandtotal'] = ($workingDaysPresent >= 10)
                        ? $salary * 10
                        : ($salary * 10) - ($salary * (10 - $workingDaysPresent));
                }
            } elseif ($salaryType === 'Daily') {
                $totalDeductions = $t['_ab_total'] + $t['_pto_total'] + $t['_mtl_total'] + $t['_mdl_total'] + $t['_ltl_total'] + $t['_suspended_total'];
                $workingDaysPresent = $workdays - $totalDeductions;
                $t['grandtotal'] = $salary * $workingDaysPresent;
            }
        }

        if ($attendanceRows->isEmpty()) {
            if ($salaryType === 'Fixed') {
                $t['grandtotal'] = $workdays <= 10 ? $salary * 10 : $salary * 10;
            } elseif ($salaryType === 'Daily') {
                $t['grandtotal'] = $salary * $workdays;
            }
        }

        $t['totalpay'] = $t['grandtotal'];

        unset($t['_ab_total'], $t['_pto_total'], $t['_mtl_total'], $t['_mdl_total'], $t['_ltl_total'], $t['_suspended_total']);

        return ['rows' => $rows, 'totals' => $t];
    }

    /**
     * Priority order, same as legacy: an approved shift-change on the
     * attendance record itself, then the employee's per-day shift
     * schedule, then movement_tracker's effective-dated shift ranges,
     * falling back to the employee's default shift.
     */
    private function resolveShiftForDate($conn, string $idno, string $logindate, ?string $shiftChangeRaw, string $dayOfWeek, object $employeeDetails): array
    {
        $startshift = $employeeDetails->startshift;
        $endshift   = $employeeDetails->endshift;
        $dls        = $employeeDetails->dls;

        $movement = $conn->table('movement_tracker')
            ->where('idno', $idno)
            ->where('effectivitydate', '<=', $logindate)
            ->orderByDesc('effectivitydate')
            ->first();

        if ($movement) {
            $shiftEffectivity = $movement->effectivitydate;
            if ($dls == 1) {
                $shiftEffectivity = date('Y-m-d', strtotime($shiftEffectivity . ' -1 day'));
            }

            if ($logindate < $shiftEffectivity) {
                $shiftStr = $movement->shiftfrom;
                $dls = $movement->dls;
            } else {
                $shiftStr = $movement->shiftto;
                $dls = $employeeDetails->dls;
            }

            if (! empty($shiftStr)) {
                $parts = preg_split('/\s*-\s*/', $shiftStr);
                if (count($parts) === 2) {
                    $startshift = date('H:i:s', strtotime(trim($parts[0])));
                    $endshift   = date('H:i:s', strtotime(trim($parts[1])));
                }
            }
        }

        if (! empty($shiftChangeRaw)) {
            $shiftchange = preg_replace('/\s+/', '', trim($shiftChangeRaw));
            if (str_contains($shiftchange, '-')) {
                [$s, $e] = explode('-', $shiftchange, 2);
                $startshift = date('H:i:s', strtotime($s));
                $endshift   = date('H:i:s', strtotime($e));
            }
        } else {
            $dayShift = $conn->table('employee_shift_schedule')
                ->where('idno', $idno)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            if ($dayShift) {
                $startshift = $dayShift->startshift;
                $endshift   = $dayShift->endshift;
            }
        }

        return [$startshift, $endshift, $dls];
    }

    /**
     * Applies an approved missed-log-application override on top of the
     * raw attendance record, same precedence as legacy (approved missed
     * log wins over the raw punch, "0" stands for "no time recorded").
     */
    private function resolveDisplayTimes($conn, string $idno, string $logindate, object $attendance): array
    {
        $missed = $conn->table('missed_log_application')
            ->where('idno', $idno)
            ->where('datemissed', $logindate)
            ->where(function ($q) {
                $q->where('applic_status', 'like', 'Approved%')->orWhere('applic_status', 'like', '*Approved%');
            })
            ->get()
            ->mapWithKeys(fn ($m) => [strtolower($m->incident) => $m->mttime]);

        $raw = fn ($field) => ($attendance->{$field} && $attendance->{$field} !== '0') ? date('h:i A', strtotime($attendance->{$field})) : '0';

        $loginam  = $missed->has('in')        ? date('h:i A', strtotime($missed['in']))        : $raw('loginam');
        $logoutam = $missed->has('lunch out') ? date('h:i A', strtotime($missed['lunch out']))  : $raw('logoutam');
        $loginpm  = $missed->has('lunch in')  ? date('h:i A', strtotime($missed['lunch in']))   : $raw('loginpm');
        $logoutpm = $missed->has('out')       ? date('h:i A', strtotime($missed['out']))        : $raw('logoutpm');

        return [
            $loginam === '0' ? '0' : date('H:i:s', strtotime($loginam)),
            $logoutam === '0' ? '0' : date('H:i:s', strtotime($logoutam)),
            $loginpm === '0' ? '0' : date('H:i:s', strtotime($loginpm)),
            $logoutpm === '0' ? '0' : date('H:i:s', strtotime($logoutpm)),
        ];
    }

    private function computeDailyTotalPay(
        string $salaryType, float $empsalary, float $regdaysot, float $ndrate, float $spholiday,
        float $spholidayot, float $regholiday, float $regholidayot, float $reghoursnwamount,
        float $obsubsal, float $salary, int $ab, int $pto, int $mtl, int $mdl, int $ltl, int $sus, int $abFlag
    ): float {
        if ($salaryType === 'Fixed') {
            $totalDeductionsCount = $ab + $pto + $mtl + $mdl + $ltl + $sus;
            return $empsalary - ($empsalary * $totalDeductionsCount) + $reghoursnwamount;
        }

        if ($salaryType === 'Daily') {
            $totalDeductionsCount = $ab + $pto + $mtl + $mdl + $ltl + $sus;
            return ($empsalary + $ndrate) - ($empsalary * $totalDeductionsCount) + $reghoursnwamount;
        }

        // Rated (default)
        $totalpay = $empsalary + $regdaysot + $ndrate + $spholiday + $spholidayot + $regholiday + $regholidayot + $reghoursnwamount;
        return $totalpay - $obsubsal;
    }

    private function hoursWithMidnight($in, $out): float
    {
        if ($in == 0 || $out == 0) {
            return 0;
        }
        if ($out <= $in) {
            $out += 86400;
        }

        return round(($out - $in) / 3600, 2);
    }

    /**
     * Every field the legacy page's hidden inputs (and payroll_details
     * columns) expect. Fields owned by passes 2-4 (ND/holiday/leave) stay
     * at 0 until those are ported.
     */
    public function emptyTotals(): array
    {
        return [
            'totalhours'              => 0,
            'regularhours'            => 0,
            'totalovertime'           => 0,
            'totalndhrs'              => 0,
            'totalbasesalary'         => 0,
            'totalregdaysot'          => 0,
            'totalndrate'             => 0,
            'totalspholiday'          => 0,
            'totalspholidayot'        => 0,
            'totalregholidayot'       => 0,
            'totalregholiday'         => 0,
            'grandtotal'              => 0,
            'regular_hours'           => 0,
            'totalhoursnotworked'     => 0,
            'hoursnotworkedamount'    => 0,
            'regholidayhrs'           => 0,
            'spholidayhrs'            => 0,
            'regholidaywork1'         => 0,
            'regholidayworkamount1'   => 0,
            'regholidaywork2'         => 0,
            'regholidayworkamount2'   => 0,
            'regholidayothrs'         => 0,
            'regholidayotamount'      => 0,
            'spholidayhours1'         => 0,
            'spholidayamount1'        => 0,
            'spholidayhours2'         => 0,
            'spholidayamount2'        => 0,
            'spholidayothrs'          => 0,
            'spholidayotamount'       => 0,
            'ndhrs'                   => 0,
            'ndamount'                => 0,
            'paidSLhrs'               => 0,
            'paidSLamount'            => 0,
            'paidVLhrs'               => 0,
            'paidVLamount'            => 0,
            'paidptlhrs'              => 0,
            'paidptlamount'           => 0,
            'paidsplhrs'              => 0,
            'paidsplamount'           => 0,
            'paidBLhrs'               => 0,
            'paidBLamount'            => 0,
            'bdayleavehrs'            => 0,
            'bdayleaveamount'         => 0,
            'doubleholidaypay'        => 0,
            'totalpay'                => 0,
            'reghours_prev'           => 0,
            'reghoursot_prev'         => 0,
            'reghoursotamount_prev'   => 0,
            'regholidayhrs_prev'      => 0,
            'regholidayamount_prev'   => 0,
            'regholidayothrs_prev'    => 0,
            'regholidayotamount_prev' => 0,
            'spholidayhrs_prev'       => 0,
            'spholidayamount_prev'    => 0,
            'spholidayothrs_prev'     => 0,
            'spholidayotamount_prev'  => 0,
            'ndhrs_prev'              => 0,
            'ndamount_prev'           => 0,
            'totalbasesalary_prev'    => 0,
            'totalpay_prev'           => 0,
            'paidVLhrs_prev'          => 0,
            'paidVLamount_prev'       => 0,
            'bdayleavehrs_prev'       => 0,
            'bdayleaveamount_prev'    => 0,
        ];
    }
}