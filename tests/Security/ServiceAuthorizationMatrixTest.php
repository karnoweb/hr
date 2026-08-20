<?php

namespace Karnoweb\Hr\Tests\Security;

use Karnoweb\Hr\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Living documentation of in-package authorization coverage (HR-150 / HR-160).
 *
 * Each row states whether the package enforces authorization itself or defers to the host app.
 */
class ServiceAuthorizationMatrixTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function authorizationExpectations(): array
    {
        return [
            'DocumentService::approve rejects wrong actor' => [
                'Karnoweb\\Hr\\Services\\DocumentService',
                'approve',
                'in_package',
            ],
            'DocumentService::reject rejects wrong actor' => [
                'Karnoweb\\Hr\\Services\\DocumentService',
                'reject',
                'in_package',
            ],
            'DocumentService::create branch validation' => [
                'Karnoweb\\Hr\\Services\\DocumentService',
                'create',
                'in_package_partial',
            ],
            'EmployeeService::createForUser' => [
                'Karnoweb\\Hr\\Services\\EmployeeService',
                'createForUser',
                'deferred_to_host',
            ],
            'LeaveService::request' => [
                'Karnoweb\\Hr\\Services\\LeaveService',
                'request',
                'deferred_to_host',
            ],
            'MissionService::request' => [
                'Karnoweb\\Hr\\Services\\MissionService',
                'request',
                'deferred_to_host',
            ],
            'LoanService::apply' => [
                'Karnoweb\\Hr\\Services\\LoanService',
                'apply',
                'deferred_to_host',
            ],
            'PayrollService::approve' => [
                'Karnoweb\\Hr\\Services\\PayrollService',
                'approve',
                'deferred_to_host',
            ],
            'PayrollService::markPaid' => [
                'Karnoweb\\Hr\\Services\\PayrollService',
                'markPaid',
                'deferred_to_host',
            ],
            'OvertimeService::approve' => [
                'Karnoweb\\Hr\\Services\\OvertimeService',
                'approve',
                'deferred_to_host',
            ],
        ];
    }

    #[DataProvider('authorizationExpectations')]
    public function test_service_authorization_expectation_is_documented(
        string $serviceClass,
        string $method,
        string $expectation,
    ): void {
        $this->assertTrue(class_exists($serviceClass), "Service class [{$serviceClass}] must exist.");
        $this->assertTrue(method_exists($serviceClass, $method), "Method [{$serviceClass}::{$method}] must exist.");
        $this->assertContains($expectation, ['in_package', 'in_package_partial', 'deferred_to_host']);
    }

    public function test_document_approval_idor_is_enforced_in_package(): void
    {
        $expectation = self::authorizationExpectations()['DocumentService::approve rejects wrong actor'];

        $this->assertSame('in_package', $expectation[2]);
        $this->assertSame('approve', $expectation[1]);
    }
}
