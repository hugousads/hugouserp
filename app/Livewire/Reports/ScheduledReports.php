<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\ReportTemplate;
use App\Services\ScheduledReportService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ScheduledReports extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public bool $showModal = false;

    public ?int $templateId = null;

    public string $scheduleName = '';

    public string $frequency = 'daily';

    public string $dayOfWeek = '1';

    public string $dayOfMonth = '1';

    public string $timeOfDay = '08:00';

    public string $recipientEmails = '';

    public string $format = 'pdf';

    public bool $isActive = true;

    public array $filters = [];

    protected $listeners = ['refreshComponent' => '$refresh'];

    protected ScheduledReportService $reportService;

    public function boot(ScheduledReportService $reportService): void
    {
        $this->reportService = $reportService;
    }

    public function mount(): void
    {
        // Authorization check - must have reports.manage permission
        $user = auth()->user();
        if (! $user || ! $user->can('reports.manage')) {
            abort(403, __('Unauthorized access to scheduled reports'));
        }
    }

    public function render()
    {
        $templates = ReportTemplate::active()->orderBy('name')->get();

        $schedules = DB::table('report_schedules')
            ->leftJoin('report_templates', 'report_schedules.report_template_id', '=', 'report_templates.id')
            ->leftJoin('users', 'report_schedules.created_by', '=', 'users.id')
            ->select([
                'report_schedules.*',
                'report_templates.name as template_name',
                'users.name as created_by_name',
            ])
            ->orderByDesc('report_schedules.created_at')
            ->paginate(15);

        return view('livewire.reports.scheduled-reports', [
            'templates' => $templates,
            'schedules' => $schedules,
        ]);
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->templateId = null;
        $this->scheduleName = '';
        $this->frequency = 'daily';
        $this->dayOfWeek = '1';
        $this->dayOfMonth = '1';
        $this->timeOfDay = '08:00';
        $this->recipientEmails = '';
        $this->format = 'pdf';
        $this->isActive = true;
        $this->filters = [];
    }

    public function edit(int $id): void
    {
        $schedule = DB::table('report_schedules')->find($id);
        if ($schedule) {
            $this->editingId = $id;
            $this->templateId = $schedule->report_template_id;
            $this->scheduleName = $schedule->name;
            $this->frequency = $schedule->frequency;
            $this->dayOfWeek = (string) ($schedule->day_of_week ?? '1');
            $this->dayOfMonth = (string) ($schedule->day_of_month ?? '1');
            $this->timeOfDay = $schedule->time_of_day ?? '08:00';
            $this->recipientEmails = $schedule->recipient_emails ?? '';
            $this->format = $schedule->format ?? 'pdf';
            $this->isActive = (bool) $schedule->is_active;
            $this->filters = json_decode($schedule->filters ?? '[]', true);
            $this->showModal = true;
        }
    }

    public function save(): void
    {
        $this->validate([
            'templateId' => 'required|exists:report_templates,id',
            'scheduleName' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly',
            'timeOfDay' => 'required|date_format:H:i',
            'recipientEmails' => 'required|string',
            'format' => 'required|in:pdf,excel,csv',
        ]);

        $data = [
            'report_template_id' => $this->templateId,
            'name' => $this->scheduleName,
            'frequency' => $this->frequency,
            'day_of_week' => $this->frequency === 'weekly' ? (int) $this->dayOfWeek : null,
            'day_of_month' => in_array($this->frequency, ['monthly', 'quarterly']) ? (int) $this->dayOfMonth : null,
            'time_of_day' => $this->timeOfDay,
            'recipient_emails' => $this->recipientEmails,
            'format' => $this->format,
            'is_active' => $this->isActive,
            'filters' => json_encode($this->filters),
            'updated_at' => now(),
        ];

        if ($this->editingId) {
            DB::table('report_schedules')
                ->where('id', $this->editingId)
                ->update($data);
            $this->dispatch('notify', type: 'success', message: __('Schedule updated successfully'));
        } else {
            $data['created_at'] = now();
            $data['created_by'] = auth()->id();
            $data['next_run_at'] = $this->calculateNextRun();
            DB::table('report_schedules')->insert($data);
            $this->dispatch('notify', type: 'success', message: __('Schedule created successfully'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        DB::table('report_schedules')->where('id', $id)->delete();
        $this->dispatch('notify', type: 'success', message: __('Schedule deleted successfully'));
    }

    public function toggleActive(int $id): void
    {
        $schedule = DB::table('report_schedules')->find($id);
        if ($schedule) {
            DB::table('report_schedules')
                ->where('id', $id)
                ->update(['is_active' => ! $schedule->is_active, 'updated_at' => now()]);
        }
    }

    public function runNow(int $id): void
    {
        $result = $this->reportService->runNow($id);

        if ($result['success']) {
            $sentCount = count($result['sent_to'] ?? []);
            $this->dispatch('notify', type: 'success', message: __('Report generated and sent to :count recipient(s)', ['count' => $sentCount]));
        } else {
            $this->dispatch('notify', type: 'error', message: $result['error'] ?? __('Failed to generate report'));
        }
    }

    protected function calculateNextRun(): string
    {
        $now = now();
        $time = explode(':', $this->timeOfDay);
        $hour = (int) $time[0];
        $minute = (int) ($time[1] ?? 0);

        switch ($this->frequency) {
            case 'daily':
                $next = $now->copy()->setTime($hour, $minute);
                if ($next->lte($now)) {
                    $next->addDay();
                }
                break;
            case 'weekly':
                $next = $now->copy()->next((int) $this->dayOfWeek)->setTime($hour, $minute);
                break;
            case 'monthly':
                $next = $now->copy()->day((int) $this->dayOfMonth)->setTime($hour, $minute);
                if ($next->lte($now)) {
                    $next->addMonth();
                }
                break;
            case 'quarterly':
                $next = $now->copy()->day((int) $this->dayOfMonth)->setTime($hour, $minute);
                $quarter = ceil($now->month / 3);
                $next->month(($quarter * 3) + 1);
                if ($next->lte($now)) {
                    $next->addMonths(3);
                }
                break;
            default:
                $next = $now->addDay();
        }

        return $next->toDateTimeString();
    }
}
