<?php

namespace App\ValueObjects;

use App\Constants\TicketConstants;

/**
 * Value Object for Workflow Path Configuration
 * 
 * Represents the approval workflow requirements for different ticket types.
 * Immutable object that encapsulates workflow rules.
 */
class WorkflowPath
{
    public function __construct(
        public readonly bool $requiresAssessment,
        public readonly bool $requiresDHApproval,
        public readonly bool $requiresODApproval,
        public readonly bool $canDirectAssign,
        public readonly string $workflowType
    ) {}

    /**
     * Factory method to create WorkflowPath based on request type
     */
    public static function forRequestType(string $requestType): self
    {
        return match ($requestType) {
            TicketConstants::REQUEST_NEW_SYSTEM,
            TicketConstants::REQUEST_MODIFICATION,
            TicketConstants::REQUEST_ENHANCEMENT => new self(
                requiresAssessment: true,
                requiresDHApproval: true,
                requiresODApproval: true,
                canDirectAssign: false,
                workflowType: 'FULL_APPROVAL'
            ),

            TicketConstants::REQUEST_ADJUSTMENT => new self(
                requiresAssessment: true,
                requiresDHApproval: true,
                requiresODApproval: false,
                canDirectAssign: false,
                workflowType: 'DH_APPROVAL_ONLY'
            ),

            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN => new self(
                requiresAssessment: false,
                requiresDHApproval: false,
                requiresODApproval: false,
                canDirectAssign: true,
                workflowType: 'DIRECT_ASSIGN'
            ),

            default => new self(
                requiresAssessment: true,
                requiresDHApproval: true,
                requiresODApproval: true,
                canDirectAssign: false,
                workflowType: 'FULL_APPROVAL'
            ),
        };
    }

    /**
     * Check if this workflow requires any approvals
     */
    public function requiresApprovals(): bool
    {
        return $this->requiresDHApproval || $this->requiresODApproval;
    }

    /**
     * Check if this is a full approval workflow
     */
    public function isFullApproval(): bool
    {
        return $this->workflowType === 'FULL_APPROVAL';
    }

    /**
     * Check if this is a direct assignment workflow
     */
    public function isDirectAssign(): bool
    {
        return $this->workflowType === 'DIRECT_ASSIGN';
    }

    /**
     * Get a human-readable description of the workflow
     */
    public function getDescription(): string
    {
        return match ($this->workflowType) {
            'FULL_APPROVAL' => 'Requires assessment, Department Head approval, and Operations Director approval',
            'DH_APPROVAL_ONLY' => 'Requires assessment and Department Head approval only',
            'DIRECT_ASSIGN' => 'Can be directly assigned by programmer without approvals',
            default => 'Unknown workflow type',
        };
    }

    /**
     * Get the required approval steps in order
     */
    public function getRequiredSteps(): array
    {
        $steps = [];

        if ($this->requiresAssessment) {
            $steps[] = 'Assessment by Programmer';
        }

        if ($this->requiresDHApproval) {
            $steps[] = 'Department Head Approval';
        }

        if ($this->requiresODApproval) {
            $steps[] = 'Operations Director Approval';
        }

        if ($this->canDirectAssign) {
            $steps[] = 'Direct Assignment';
        } else {
            $steps[] = 'Assignment by MIS Supervisor';
        }

        return $steps;
    }

    /**
     * Convert to array (for API responses)
     */
    public function toArray(): array
    {
        return [
            'requires_assessment' => $this->requiresAssessment,
            'requires_dh_approval' => $this->requiresDHApproval,
            'requires_od_approval' => $this->requiresODApproval,
            'can_direct_assign' => $this->canDirectAssign,
            'workflow_type' => $this->workflowType,
            'description' => $this->getDescription(),
            'required_steps' => $this->getRequiredSteps(),
        ];
    }
}
