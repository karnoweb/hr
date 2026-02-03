<?php

namespace Karnoweb\Hr\Enums;

enum DocumentType: string
{
    case Hire = 'hire';
    case Termination = 'termination';
    case Rehire = 'rehire';
    case ContractStart = 'contract_start';
    case ContractEnd = 'contract_end';
    case ContractExtend = 'contract_extend';
    case PositionChange = 'position_change';
    case SalaryChange = 'salary_change';
    case Leave = 'leave';
    case Mission = 'mission';
    case OvertimeApproval = 'overtime_approval';
    case Warning = 'warning';
    case Reward = 'reward';
    case Loan = 'loan';
    case Payroll = 'payroll';

    public function label(): string
    {
        return match ($this) {
            self::Hire => __('hr::enums.document_type.hire'),
            self::Termination => __('hr::enums.document_type.termination'),
            self::Rehire => __('hr::enums.document_type.rehire'),
            self::ContractStart => __('hr::enums.document_type.contract_start'),
            self::ContractEnd => __('hr::enums.document_type.contract_end'),
            self::ContractExtend => __('hr::enums.document_type.contract_extend'),
            self::PositionChange => __('hr::enums.document_type.position_change'),
            self::SalaryChange => __('hr::enums.document_type.salary_change'),
            self::Leave => __('hr::enums.document_type.leave'),
            self::Mission => __('hr::enums.document_type.mission'),
            self::OvertimeApproval => __('hr::enums.document_type.overtime_approval'),
            self::Warning => __('hr::enums.document_type.warning'),
            self::Reward => __('hr::enums.document_type.reward'),
            self::Loan => __('hr::enums.document_type.loan'),
            self::Payroll => __('hr::enums.document_type.payroll'),
        };
    }

    public function requiresApproval(): bool
    {
        return in_array($this->value, config('hr.documents.require_approval', []));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
