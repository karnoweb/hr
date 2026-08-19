<?php

namespace Karnoweb\Hr\Tests\Unit;

use Karnoweb\Hr\Exceptions\DocumentLockedException;
use Karnoweb\Hr\Exceptions\DuplicateActiveRecordException;
use Karnoweb\Hr\Exceptions\EmployeeAlreadyExistsException;
use Karnoweb\Hr\Exceptions\HrException;
use Karnoweb\Hr\Exceptions\InsufficientLeaveBalanceException;
use Karnoweb\Hr\Exceptions\InvalidEmployeeLifecycleException;
use Karnoweb\Hr\Exceptions\InvalidOrganizationStructureException;
use Karnoweb\Hr\Exceptions\PayrollPeriodLockedException;
use Karnoweb\Hr\Exceptions\UnauthorizedApprovalException;
use Karnoweb\Hr\Exceptions\UnresolvableApproverException;
use Karnoweb\Hr\Exceptions\UnresolvableWorkflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HrExceptionHierarchyTest extends TestCase
{
    #[DataProvider('domainExceptions')]
    public function test_domain_exceptions_extend_hr_exception(string $class): void
    {
        $exception = new $class('test');

        $this->assertInstanceOf(HrException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertSame('test', $exception->getMessage());
    }

    public static function domainExceptions(): array
    {
        return [
            [DocumentLockedException::class],
            [DuplicateActiveRecordException::class],
            [UnresolvableApproverException::class],
            [InsufficientLeaveBalanceException::class],
            [PayrollPeriodLockedException::class],
            [UnauthorizedApprovalException::class],
            [EmployeeAlreadyExistsException::class],
            [UnresolvableWorkflowException::class],
            [InvalidEmployeeLifecycleException::class],
            [InvalidOrganizationStructureException::class],
        ];
    }
}
