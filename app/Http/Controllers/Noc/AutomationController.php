<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\AutomationJob;
use App\Services\Automation\AutomationJobService;
use App\Services\Automation\AutomationSchedulerService;
use App\Services\Automation\AutomationWorkerService;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    /**
     * Automation dashboard overview.
     */
    public function index()
    {
        $stats = AutomationJobService::getStats();
        $recentLogs = AutomationJobService::getRecentLogs(15);
        $jobs = AutomationJob::with(['creator', 'triggers'])
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        return view('noc.automation.index', compact('stats', 'recentLogs', 'jobs'));
    }

    /**
     * Jobs list with search and filters.
     */
    public function jobs(Request $request)
    {
        $jobs = AutomationJobService::list(
            type: $request->input('type'),
            status: $request->input('status'),
            search: $request->input('search'),
            limit: 25,
        );

        $types = AutomationJob::distinct()->pluck('type')->filter()->values();
        $statuses = ['idle', 'queued', 'running', 'completed', 'failed', 'cancelled'];

        return view('noc.automation.jobs', compact('jobs', 'types', 'statuses'));
    }

    /**
     * Create job form (GET) + store (POST).
     */
    public function create()
    {
        return view('noc.automation.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|string|max:100',
            'parameters' => 'nullable|string',
            'schedule_type' => 'required|in:manual,interval,daily,weekly,monthly,cron',
            'schedule_config' => 'nullable|string|max:255',
            'priority' => 'required|integer|min:1|max:10',
            'max_attempts' => 'required|integer|min:1|max:10',
            'timeout_seconds' => 'required|integer|min:10|max:3600',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['parameters'])) {
            $validated['parameters'] = json_decode($validated['parameters'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['parameters' => 'Invalid JSON parameters'])->withInput();
            }
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['created_by'] = $request->user()->id;

        $job = AutomationJobService::create($validated, $request->user()->id);

        return redirect()->route('noc.automation.index')
            ->with('success', "Job [{$job->name}] created successfully");
    }

    /**
     * Job detail.
     */
    public function show(int $id)
    {
        $job = AutomationJob::with(['creator', 'logs' => function ($q) {
            $q->latest('started_at')->limit(20);
        }, 'triggers'])->findOrFail($id);

        return view('noc.automation.show', compact('job'));
    }

    /**
     * Edit job.
     */
    public function edit(int $id)
    {
        $job = AutomationJob::findOrFail($id);

        return view('noc.automation.edit', compact('job'));
    }

    public function update(Request $request, int $id)
    {
        $job = AutomationJob::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|string|max:100',
            'parameters' => 'nullable|string',
            'schedule_type' => 'required|in:manual,interval,daily,weekly,monthly,cron',
            'schedule_config' => 'nullable|string|max:255',
            'priority' => 'required|integer|min:1|max:10',
            'max_attempts' => 'required|integer|min:1|max:10',
            'timeout_seconds' => 'required|integer|min:10|max:3600',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['parameters'])) {
            $validated['parameters'] = json_decode($validated['parameters'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['parameters' => 'Invalid JSON parameters'])->withInput();
            }
        }

        $validated['is_active'] = $request->boolean('is_active');

        AutomationJobService::update($job, $validated);

        return redirect()->route('noc.automation.show', $job->id)
            ->with('success', "Job [{$job->name}] updated successfully");
    }

    /**
     * Delete a job.
     */
    public function destroy(int $id)
    {
        $job = AutomationJob::findOrFail($id);
        $name = $job->name;
        AutomationJobService::delete($job);

        return redirect()->route('noc.automation.jobs')
            ->with('success', "Job [{$name}] deleted");
    }

    /**
     * Dispatch a job immediately.
     */
    public function dispatch(int $id)
    {
        $job = AutomationJob::findOrFail($id);
        AutomationJobService::dispatch($job, request()->user()->id);

        return back()->with('success', "Job [{$job->name}] dispatched");
    }

    /**
     * Cancel a running/queued job.
     */
    public function cancel(int $id)
    {
        $job = AutomationJob::findOrFail($id);
        AutomationJobService::cancel($job);

        return back()->with('success', "Job [{$job->name}] cancelled");
    }

    /**
     * Retry a failed job.
     */
    public function retry(int $id)
    {
        $job = AutomationJob::findOrFail($id);
        AutomationJobService::retry($job);

        return back()->with('success', "Job [{$job->name}] queued for retry");
    }

    /**
     * Reset a completed/failed job.
     */
    public function reset(int $id)
    {
        $job = AutomationJob::findOrFail($id);
        AutomationJobService::reset($job);

        return back()->with('success', "Job [{$job->name}] reset to idle");
    }

    /**
     * Manually trigger scheduler tick.
     */
    public function triggerScheduler()
    {
        $result = AutomationSchedulerService::tick();

        return back()->with('success', "Scheduler tick: {$result['dispatched']} dispatched");
    }

    /**
     * Manually trigger worker to process queue.
     */
    public function triggerWorker()
    {
        $result = AutomationWorkerService::processQueue();

        return back()->with('success', "Worker: {$result['processed']} processed, {$result['failed']} failed");
    }

    /**
     * Automation logs page.
     */
    public function logs(Request $request)
    {
        $logs = AutomationJobService::getRecentLogs(50);

        return view('noc.automation.logs', compact('logs'));
    }
}
