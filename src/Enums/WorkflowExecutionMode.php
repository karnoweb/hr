<?php

namespace Karnoweb\Hr\Enums;

enum WorkflowExecutionMode: string
{
    case Parallel = 'parallel';
    case Sequential = 'sequential';

    public function label(): string
    {
        return match ($this) {
            self::Parallel => __('hr::enums.workflow_execution_mode.parallel'),
            self::Sequential => __('hr::enums.workflow_execution_mode.sequential'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
