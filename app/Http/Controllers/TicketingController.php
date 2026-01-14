<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Constants\TicketConstants;
use App\Services\TicketService;
use App\Services\NotificationService;

class TicketingController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private NotificationService $notificationService
    ) {}
    public function showTicketForm(): Response
    {
        $empData = session('emp_data');

        $requestTypes = $this->ticketService->getRequestTypes();
        $formData = $this->ticketService->getTicketFormData();

        return Inertia::render('Ticketing/Create', array_merge($formData, [
            'requestTypes' => $requestTypes,
            'userAccountType' => $this->getUserAccountType($empData),
        ]));
    }
    public function viewTicket($hash)
    {
        $decoded = base64_decode($hash);
        $parts = explode(':', $decoded);
        if (count($parts) < 2 || count($parts) > 3) abort(400, 'Invalid hash format');

        [$ticketId, $action] = $parts;
        $empData = session('emp_data');

        $data = $this->ticketService->viewTicketData($ticketId, $empData);
        if (!$data) abort(404, 'Ticket not found');

        $data['action'] = $action;
        $data['requestTypes'] = [
            TicketConstants::REQUEST_NEW_SYSTEM => 'New System',
            TicketConstants::REQUEST_MODIFICATION => 'Modification',
            TicketConstants::REQUEST_ENHANCEMENT => 'Enhancement',
            TicketConstants::REQUEST_ADJUSTMENT => 'Adjustment',
            TicketConstants::REQUEST_TESTING => 'Testing',
            TicketConstants::REQUEST_PARALLEL_RUN => 'Parallel Run',
        ];
        $data['statusTypes'] = [
            TicketConstants::STATUS_NEW => 'New',
            TicketConstants::STATUS_TRIAGED => 'Triaged',
            TicketConstants::STATUS_APPROVED => 'Approved',
            TicketConstants::STATUS_IN_PROGRESS => 'In Progress',
            TicketConstants::STATUS_RESOLVED => 'Resolved',
            TicketConstants::STATUS_CLOSED => 'Closed',
            TicketConstants::STATUS_REJECTED => 'Rejected',
            TicketConstants::STATUS_ON_HOLD => 'On Hold',
            TicketConstants::STATUS_RETURNED => 'Returned',
        ];
        // dd($data);
        return Inertia::render('Ticketing/ViewDetails', $data);
    }
    public function store(Request $request)
    {
        // dd(($request->all()));
        $validated = $request->validate([
            'request_type' => 'required|integer|in:1,2,3,4,5,6',
            'project' => 'required_unless:request_type,1|nullable|string|max:255',
            'project_name' => 'required_if:request_type,1|nullable|string|max:255',
            'parent_ticket' => 'nullable|string|exists:tickets,TICKET_ID',
            'testers' => 'nullable|array',
            'testers.*' => 'string|max:20',
            'target_date' => 'nullable|date_format:Y-m-d|after_or_equal:today', // Changed to date_format
            'details' => 'required|string|min:10',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf,doc,docx,ppt,pptx|max:10240',
        ]);

        $empData = session('emp_data');

        // Only pass the validated data and attachments array
        [$ticketId, $projId, $projectName, $testerId] = $this->ticketService->createTicketWithProject(
            $validated,
            $empData,
            $request->file('attachments') ?? []
        );

        // ========== SEND NOTIFICATIONS ==========
        try {
            Log::info('=== STARTING NOTIFICATIONS ===');
            // Add debugging
            Log::info('Notification sent', [
                'user_emp_id' => $empData['emp_id'],
                'channel' => 'users.' . $empData['emp_id'],
                'ticket_id' => $ticketId
            ]);
            $notificationService = new \App\Services\NotificationService();

            $result = $notificationService->notifyTicketCreated(
                $ticketId,
                $validated['request_type'],
                $empData['emp_name'],
                $validated['details'],
                $projectName,
                $testerId
            );

            Log::info('Notification result: ' . json_encode($result));
        } catch (\Exception $notifyException) {
            Log::error('NOTIFICATION ERROR: ' . $notifyException->getMessage());
            Log::error('Stack trace: ' . $notifyException->getTraceAsString());
        }
        return redirect()->route('tickets.view', base64_encode($ticketId . ':VIEW'))
            ->with('success', 'Ticket created successfully! Ticket ID: ' . $ticketId);
    }

public function getTicketsDataTable(Request $request)
{
    $empData = session('emp_data');
    if (!$empData) {
        return redirect()->route('login');
    }

    $userRoles = $this->getUserAccountType($empData);

    // Decode filters from the 'q' parameter (base64 JSON)
    $encodedFilters = $request->input('q', '');
    $filters = $this->decodeFilters($encodedFilters);

    // Set default values if not provided
    $filters = array_merge([
        'page' => 1,
        'pageSize' => 10,
        'search' => '',
        'sortField' => 'created_at',
        'sortOrder' => 'desc',
        'status' => 'all',
        'project' => '',
    ], $filters);

    $result = $this->ticketService->getTicketsDataTable($filters, $empData, $userRoles);

    return Inertia::render('Ticketing/Table', [
        'tickets' => $result['tickets'],
        'pagination' => $result['pagination'],
        'projects' => $result['projects'],
        'statusCounts' => $result['statusCounts'],
        'filters' => $result['filters'],
    ])->with('flash', ['message' => 'Tickets loaded successfully']);
}

    public function getTicketsCount(Request $request)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return response()->json(['count' => 0]);
        }

        $userRoles = $this->getUserAccountType($empData);

        // We’ll reuse the same filter logic, but keep it minimal
        $filters = [
            'page' => 1,
            'pageSize' => 1, // doesn’t matter for count
            'search' => trim($request->input('search', '')),
            'sortField' => 'created_at',
            'sortOrder' => 'desc',
            'status' => $request->input('status', 'all'),
            'project' => $request->input('project', ''),
        ];

        // Reuse the service (the same one used by getTicketsDataTable)
        $result = $this->ticketService->getTicketsDataTable($filters, $empData, $userRoles);

        return response()->json([
            'count' => $result['pagination']['total'] ?? 0,
        ]);
    }

    public function getAssignedTickets($empId)
    {
        try {
            $tickets = $this->ticketService->getAssignedTickets($empId);
            return response()->json($tickets, 200);
        } catch (\Exception $e) {
            Log::error("Error fetching assigned tickets: " . $e->getMessage());
            return response()->json(['error' => 'Failed to load assigned tickets'], 500);
        }
    }



    public function assignTicket(Request $request, $ticketId)
    {
        try {
            $empData = session('emp_data');
            if (!$empData) {
                return redirect()->route('login');
            }

            $validated = $request->validate([
                'assigned_to' => 'required|array',
                'assigned_to.*' => 'required|string|max:20',
                'remarks' => 'nullable|string|max:1000',
            ]);

            $result = $this->ticketService->assignTicket(
                $ticketId,
                $validated['assigned_to'],
                $empData,
                $validated['remarks'] ?? null
            );

            if (!$result['success']) {
                return back()->with('error', $result['message']);
            }

            $hash = base64_encode($ticketId . ':VIEW');
            return redirect()
                ->route('tickets.view', $hash)
                ->with('success', $result['message']);
        } catch (\Exception $e) {
            Log::error('Error assigning ticket: ' . $e->getMessage());
            return back()->with('error', 'Failed to assign ticket. Please try again.');
        }
    }
    /**
     * Handle ticket resolution (by assigned programmer)
     */
    public function resolveTicket(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'remarks' => 'required|string|min:10|max:1000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf,doc,docx,ppt,pptx|max:10240',
        ]);

        $empData = session('emp_data');
        if (!$empData) {
            return redirect()->route('login');
        }

        $result = $this->ticketService->resolveTicket(
            $ticketId,
            $empData,
            $validated['remarks'],
            $request->file('attachments') ?? []
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        $hash = base64_encode($ticketId . ':VIEW');
        return redirect()
            ->route('tickets.view', $hash)
            ->with('success', $result['message']);
    }

    public function closeTicket(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|min:1|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $empData = session('emp_data');
        if (!$empData) {
            return redirect()->route('login');
        }

        $result = $this->ticketService->closeTicket(
            $ticketId,
            $empData,
            $validated['remarks'] ?? null,
            $validated['rating'] ?? null
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        $hash = base64_encode($ticketId . ':VIEW');
        return redirect()
            ->route('tickets.view', $hash)
            ->with('success', $result['message']);
    }

    public function returnTicket(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'remarks' => 'required|string|min:10|max:1000',
        ]);

        $empData = session('emp_data');
        if (!$empData) {
            return redirect()->route('login');
        }

        $result = $this->ticketService->returnTicket(
            $ticketId,
            $empData,
            $validated['remarks']
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        $hash = base64_encode($ticketId . ':VIEW');
        return redirect()
            ->route('tickets.view', $hash)
            ->with('success', $result['message']);
    }


    public function resubmitTicket(Request $request, $ticketId)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return redirect()->route('login');
        }

        $result = $this->ticketService->resubmitTicket(
            $ticketId,
            $empData
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        $hash = base64_encode($ticketId . ':VIEW');
        return redirect()
            ->route('tickets.view', $hash)
            ->with('success', $result['message']);
    }


    /**
     * Submit test results (by tester)
     */
    public function submitTestResult(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'test_status' => 'required|in:PASSED,FAILED',
            'remarks' => 'required|string|min:10|max:1000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf,doc,docx,ppt,pptx|max:10240',
        ]);

        $empData = session('emp_data');
        if (!$empData) {
            return redirect()->route('login');
        }

        $result = $this->ticketService->submitTestResult(
            $ticketId,
            $empData,
            $validated,
            $request->file('attachments') ?? []
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], $result['status'] ?? 400);
        }

        return response()->json($result);
    }



    /**
     * Handle ticket assessment (moves to TRIAGED and logs assessment)
     */
    public function assessTicket(Request $request, $ticketId)
    {
        try {
            $empData = session('emp_data');
            if (!$empData) {
                return redirect()->route('login');
            }

            $validated = $request->validate([
                'remarks' => 'nullable|string|max:1000',
            ]);

            $result = $this->ticketService->assessTicket(
                $ticketId,
                $empData,
                $validated['remarks'] ?? null
            );

            if (!$result['success']) {
                return back()->with('error', $result['message']);
            }

            $hash = base64_encode($ticketId . ':VIEW');
            return redirect()
                ->route('tickets.view', $hash)
                ->with('success', $result['message']);
        } catch (\Exception $e) {
            Log::error('Error assessing ticket: ' . $e->getMessage());
            return back()->with('error', 'Failed to assess ticket. Please try again.');
        }
    }


    public function approveDH(Request $request, $ticketId)
    {
        try {
            $empData = session('emp_data');
            if (!$empData) {
                return redirect()->route('login');
            }

            $validated = $request->validate([
                'remarks' => 'nullable|string|max:1000',
            ]);

            $result = $this->ticketService->approveDH(
                $ticketId,
                $empData,
                $validated['remarks'] ?? null
            );

            if (!$result['success']) {
                return back()->with('error', $result['message']);
            }

            $hash = base64_encode($ticketId . ':VIEW');
            return redirect()
                ->route('tickets.view', $hash)
                ->with('success', $result['message']);
        } catch (\Exception $e) {
            Log::error('Error approving ticket (DH): ' . $e->getMessage());
            return back()->with('error', 'Failed to approve ticket. Please try again.');
        }
    }


    /**
     * Handle OD Approval - moves PROJECT to READY
     */
    public function approveOD(Request $request, $ticketId)
    {
        try {
            $empData = session('emp_data');
            if (!$empData) {
                return redirect()->route('login');
            }

            $validated = $request->validate([
                'remarks' => 'nullable|string|max:1000',
            ]);

            $result = $this->ticketService->approveOD(
                $ticketId,
                $empData,
                $validated['remarks'] ?? null
            );

            if (!$result['success']) {
                return back()->with('error', $result['message']);
            }

            $hash = base64_encode($ticketId . ':VIEW');
            return redirect()
                ->route('tickets.view', $hash)
                ->with('success', $result['message']);
        } catch (\Exception $e) {
            Log::error('Error approving ticket (OD): ' . $e->getMessage());
            return back()->with('error', 'Failed to approve ticket. Please try again.');
        }
    }

    private function isRequestorAccount($empData)
    {
        return !$this->isAssessedByProgrammer($empData) &&
            !$this->isDepartmentHead($empData) &&
            !$this->isODAccount($empData) &&
            !$this->isMISSupervisor($empData);
    }

    private function isAssessedByProgrammer($empData)
    {
        $dept = strtoupper($empData['emp_dept']);
        $job_Title = strtolower($empData['emp_jobtitle']);

        return $dept === 'MIS' &&
            (
                strpos($job_Title, 'programmer') !== false ||
                (strpos($job_Title, 'mis') !== false && strpos($job_Title, 'supervisor') !== false)
            );
    }

    private function isDepartmentHead($empData)
    {
        $userId = $empData['emp_id'];
        $hasApprovalRights = DB::connection('masterlist')->select("
            SELECT COUNT(*) as count FROM employee_masterlist 
            WHERE (APPROVER2 = '{$userId}' OR APPROVER3 = '{$userId}')
        ");

        return $hasApprovalRights[0]->count > 0;
    }

    private function isODAccount($empData)
    {
        return strtoupper($empData['emp_dept']) === 'OPERATIONS' ||
            strtoupper($empData['emp_jobtitle']) === 'OPERATIONS DIRECTOR';
    }

    private function isMISSupervisor($empData)
    {
        return strtoupper($empData['emp_dept']) === 'MIS' &&
            stripos($empData['emp_jobtitle'], 'supervisor') !== false;
    }

    private function getUserAccountType($empData)
    {
        $roles = [];

        if ($this->isMISSupervisor($empData)) {
            $roles[] = 'MIS_SUPERVISOR';
            $roles[] = 'PROGRAMMER';
        } elseif ($this->isAssessedByProgrammer($empData)) {
            $roles[] = 'PROGRAMMER';
        }

        if ($this->isODAccount($empData)) {
            $roles[] = 'OD';
        }

        if ($this->isDepartmentHead($empData)) {
            $roles[] = 'DEPARTMENT_HEAD';
        }

        if ($this->isRequestorAccount($empData)) {
            $roles[] = 'REQUESTOR';
        }

        return $roles ?: ['UNKNOWN'];
    }
     private function decodeFilters(string $encodedFilters): array
    {
        if (empty($encodedFilters)) {
            return [];
        }

        try {
            $decoded = base64_decode($encodedFilters, true);

            if ($decoded === false) {
                return [];
            }

            $filters = json_decode($decoded, true);

            return is_array($filters) ? $filters : [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
