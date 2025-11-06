<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Constants\ProjectConstants;
use Illuminate\Http\JsonResponse;

class ProjectConstantsController extends Controller
{
    /**
     * Get all project-related constants
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $constants = [
                'projectStatuses' => $this->getProjectStatuses(),
                'requestTypes' => $this->getRequestTypes(),
                'ticketStatuses' => $this->getTicketStatuses(),
            ];

            return response()->json([
                'success' => true,
                'data' => $constants,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch project constants',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get project statuses with colors
     *
     * @return array
     */
    private function getProjectStatuses(): array
    {
        $statusMap = ProjectConstants::getProjectStatusMap();
        $colorMap = $this->getStatusColorMap();

        $statuses = [];
        foreach ($statusMap as $value => $label) {
            $statuses[] = [
                'value' => $value,
                'label' => $label,
                'color' => $colorMap[$value] ?? 'default',
            ];
        }

        return $statuses;
    }

    /**
     * Get request types
     *
     * @return array
     */
    private function getRequestTypes(): array
    {
        $typeMap = ProjectConstants::getRequestTypeLabels();

        $types = [];
        foreach ($typeMap as $value => $label) {
            $types[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $types;
    }

    /**
     * Get ticket statuses
     *
     * @return array
     */
    private function getTicketStatuses(): array
    {
        // You can expand this based on your needs
        $statusMap = [
            ProjectConstants::STATUS_NEW => 'New',
            ProjectConstants::STATUS_TRIAGED => 'Triaged',
            ProjectConstants::STATUS_APPROVED => 'Approved',
            ProjectConstants::STATUS_IN_PROGRESS => 'In Progress',
            ProjectConstants::STATUS_RESOLVED => 'Resolved',
            ProjectConstants::STATUS_CLOSED => 'Closed',
            ProjectConstants::STATUS_REJECTED => 'Rejected',
            ProjectConstants::STATUS_ON_HOLD => 'On Hold',
            ProjectConstants::STATUS_RETURNED => 'Returned',
        ];

        $statuses = [];
        foreach ($statusMap as $value => $label) {
            $statuses[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $statuses;
    }

    /**
     * Map status IDs to Ant Design colors
     *
     * @return array
     */
    private function getStatusColorMap(): array
    {
        return [
            ProjectConstants::PROJ_STATUS_PLANNING => 'gold',
            ProjectConstants::PROJ_STATUS_TRIAGED => 'cyan',
            ProjectConstants::PROJ_STATUS_IN_PROGRESS => 'processing',
            ProjectConstants::PROJ_STATUS_ON_HOLD => 'orange',
            ProjectConstants::PROJ_STATUS_DEPLOYED => 'geekblue',
            ProjectConstants::PROJ_STATUS_CANCELLED => 'red',
            ProjectConstants::PROJ_STATUS_INACTIVE => 'default',
        ];
    }
}
