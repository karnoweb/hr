# فهرست خطاها

همهٔ خطاهای اختصاصی از `Karnoweb\Hr\Exceptions\HrException` ارث می‌برند مگر `InvalidArgumentException` استاندارد PHP که برای ورودی نامعتبر استفاده می‌شود.

| کلاس | معنی کلی |
|------|-----------|
| `HrException` | پایه دامنه |
| `EmployeeAlreadyExistsException` | هویت تکراری |
| `InvalidEmployeeLifecycleException` | گذار وضعیت کارمند / تسویه وام اجباری |
| `InvalidOrganizationStructureException` | درخت دپارتمان نامعتبر |
| `DuplicateActiveRecordException` | دو رکورد جاری (قرارداد، سمت، حقوق، شیفت) |
| `InsufficientLeaveBalanceException` | مانده مرخصی کافی نیست |
| `PayrollPeriodExistsException` | دوره تکراری |
| `PayrollPeriodLockedException` | دوره در وضعیت غیرقابل ویرایش/تأیید |
| `DocumentLockedException` | سند دیگر draft نیست |
| `UnauthorizedApprovalException` | actor ≠ assigned_to |
| `UnresolvableWorkflowException` | workflow لازم پیدا نشد |
| `UnresolvableApproverException` | approver به user id نرسید |

جزئیات هر حوزه در همان صفحهٔ usage آمده است.

## الگوی catch

```php
use Karnoweb\Hr\Exceptions\HrException;
use InvalidArgumentException;

try {
    Hr::payroll()->calculate($period);
} catch (HrException $e) {
    // خطای دامنه — به کاربر کسب‌وکار نشان دهید
} catch (InvalidArgumentException $e) {
    // ورودی یا پیش‌شرط (نبود نرخ، سقف، overlap، …)
}
```
