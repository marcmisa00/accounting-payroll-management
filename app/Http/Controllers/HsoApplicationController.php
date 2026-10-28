<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesHrisApplications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * HSO arrangement (work from home / hybrid) requests from hris.wfh_application,
 * grouped company → department and filtered by effective date. Accounting can
 * view an application's details and acknowledge it; nothing else is written.
 */
class HsoApplicationController extends Controller
{
    use HandlesHrisApplications;

    private const TABLE = 'wfh_application';

    // Agreement uploads as the HRIS portal names them, e.g. "MAYLOS,J_STRATHSOAGREEMENT.pdf"
    private const AGREEMENT_PATTERN = '/^[A-Za-z0-9 _.,()\'ñÑ-]+\.(pdf|jpe?g|png)$/u';

    public function index(Request $request): View
    {
        $range = $this->dateRange($request, self::TABLE, 'date_effective');
        $companies = $this->companies();

        $rows = $range['rangeFrom'] > $range['rangeTo'] ? collect() : $this->hris()->table('wfh_application as a')
            ->join('employee_details as ed', 'ed.idno', '=', 'a.idno')
            ->join('employee_profile as ep', 'ep.idno', '=', 'a.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $companies->pluck('companycode'))
            ->whereBetween('a.date_effective', [$range['rangeFrom'], $range['rangeTo']])
            ->select(
                'a.id', 'a.idno', 'a.date_effective', 'a.end_date', 'a.reasons', 'a.reason_type', 'a.schedule',
                'a.hso_type', 'a.datetime', 'a.application_status', 'a.acknowledged',
                'a.it_acknowledged_by', 'a.hr_acknowledged_by', 'a.acc_acknowledged_by',
                'ep.lastname', 'ep.firstname',
                'ed.company', 'ed.department as department_id', 'd.department as department_name'
            )
            ->orderByDesc('a.date_effective')
            ->orderByDesc('a.id')
            ->get();

        $tabs = $this->tabs($companies, $rows, fn ($r) => [
            'id'       => (string) $r->idno,
            'name'     => trim($r->lastname . ', ' . $r->firstname, ', ') ?: (string) $r->idno,
            'start'    => $this->date($r->date_effective),
            'end'      => $this->date($r->end_date),
            'type'     => $this->hsoType($r->hso_type),
            'reason'   => $r->reasons,
            'schedule' => $r->schedule,
        ] + $this->applicationState($r));

        return view('applications.hso', $range + [
            'tabs'  => $tabs,
            'total' => $rows->count(),
        ]);
    }

    /**
     * Details for the popup (the legacy get_wfh_details.php), as JSON.
     */
    public function show(int $id): JsonResponse
    {
        $a = $this->hris()->table('wfh_application as a')
            ->join('employee_details as ed', 'ed.idno', '=', 'a.idno')
            ->join('employee_profile as ep', 'ep.idno', '=', 'a.idno')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department')
            ->leftJoin('jobtitle as jt', 'jt.id', '=', 'ed.designation')
            ->where('a.id', $id)
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $this->companies()->pluck('companycode'))
            ->first([
                'a.id', 'a.idno', 'a.hso_address', 'a.schedule', 'a.hso_type', 'a.contactnum', 'a.work_call',
                'a.conn_internet', 'a.type_internet', 'a.speed_internet', 'a.backup_internet', 'a.backup_isp',
                'a.backup_speed', 'a.reasons', 'a.reason_type', 'a.date_effective', 'a.end_date', 'a.supporting_document',
                'ep.firstname', 'ep.lastname', 'd.department', 'jt.jobtitle',
            ]);
        abort_if(!$a, 404);

        // Backup ISP and speed only apply to a wired backup, as on the legacy popup
        $wired = $a->backup_internet === 'Wired connection';

        return response()->json([
            'id'         => (int) $a->id,
            'name'       => trim($a->firstname . ' ' . $a->lastname),
            'idno'       => (string) $a->idno,
            'department' => $a->department,
            'jobtitle'   => $a->jobtitle,
            'contact'    => $a->contactnum,
            'type'       => $this->hsoType($a->hso_type),
            'address'    => $a->hso_address,
            'start'      => $this->date($a->date_effective),
            'end'        => $this->date($a->end_date),
            'schedule'   => $a->schedule,
            'workCall'   => $a->work_call,
            'connection' => $a->conn_internet,
            'isp'        => $a->type_internet,
            'speed'      => $a->speed_internet,
            'backup'     => $a->backup_internet,
            'backupIsp'  => $wired ? $a->backup_isp : null,
            'backupSpeed' => $wired ? $a->backup_speed : null,
            'reasonType' => $a->reason_type,
            'reason'     => $a->reasons,
            'agreement'  => $this->agreementName($a->supporting_document)
                ? ['name' => $a->supporting_document, 'url' => route('hso.agreement', $a->id)]
                : null,
        ]);
    }

    /**
     * The signed agreement, read from the HRIS upload folder on this server
     * (config app.hso_agreements_path) instead of linking to the HRIS website.
     */
    public function agreement(int $id): BinaryFileResponse
    {
        $doc = $this->hris()->table('wfh_application as a')
            ->join('employee_details as ed', 'ed.idno', '=', 'a.idno')
            ->where('a.id', $id)
            ->where('ed.status', 'not like', '%RESIGNED%')
            ->whereIn('ed.company', $this->companies()->pluck('companycode'))
            ->value('a.supporting_document');

        $name = $this->agreementName($doc);
        abort_if(!$name, 404);

        $dir = realpath((string) config('app.hso_agreements_path'));
        $path = $dir ? realpath($dir . DIRECTORY_SEPARATOR . $name) : false;

        // Missing, outside the folder, or not readable by the web server user
        abort_if(!$path || dirname($path) !== $dir || !is_readable($path), 404, 'The agreement file is not available on this server.');

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . addcslashes($name, '"\\') . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function acknowledgeApplication(int $id): JsonResponse
    {
        return $this->acknowledge(self::TABLE, $id);
    }

    private function agreementName(?string $doc): ?string
    {
        $doc = trim((string) $doc);

        return $doc !== '' && basename($doc) === $doc && preg_match(self::AGREEMENT_PATTERN, $doc) ? $doc : null;
    }

    // Legacy labels: 1 = Hybrid, 0 = Full HSO
    private function hsoType($type): ?string
    {
        return match ((string) $type) {
            '1'     => 'Hybrid',
            '0'     => 'Full HSO',
            default => null,
        };
    }
}
