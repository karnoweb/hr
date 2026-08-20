# راهنمای استفاده از پکیج Karnoweb HR

این سند نحوه استفاده از پکیج HR در پروژه لاراول را شرح می‌دهد. **توصیه می‌شود عملیات رایج را با فاساد `Hr` انجام دهید** و در صورت نیاز به کنترل دقیق‌تر از مدل‌ها و Enumها استفاده کنید.

---

## فهرست

1. [استفاده با فاساد (پیشنهادی)](#استفاده-با-فاساد-پیشنهادی)
2. [نصب و راه‌اندازی](#نصب-و-راه‌اندازی)
3. [اتصال User به Employee](#اتصال-user-به-employee)
4. [ساختار سازمانی (دپارتمان و سمت)](#ساختار-سازمانی)
5. [استخدام و قرارداد](#استخدام-و-قرارداد)
6. [مرخصی و مأموریت](#مرخصی-و-مأموریت)
7. [حضور و غیاب و اضافه‌کار](#حضور-و-غیاب-و-اضافه‌کار)
8. [اسناد و گردش کار (Workflow)](#اسناد-و-گردش-کار)
9. [حقوق و حقوق ساعتی](#حقوق-و-حقوق-ساعتی)
10. [وام](#وام)
11. [فاساد و تنظیمات](#فاساد-و-تنظیمات)

---

## استفاده با فاساد (پیشنهادی)

در پروژه نهایی برای سادگی فقط یک `use` کافی است:

```php
use Karnoweb\Hr\Facades\Hr;
```

### کارمند

```php
// ایجاد کارمند برای یک User (کد کارمند در صورت خالی بودن از config خودکار تولید می‌شود)
$employee = Hr::employees()->createForUser($user, [
    'branch_id' => 1,
    'hire_date' => now(),
    'national_id' => '0123456789',
    'insurance_number' => '1234567890',
]);

// پیدا کردن کارمند از روی User
$employee = Hr::employees()->findByUser($user);

// انتساب سمت به کارمند
Hr::employees()->assignPosition($employee, $departmentId, $positionId, $effectiveDate, [
    'is_primary' => true,
    'hr_document_id' => $documentId,  // اختیاری
]);

// چرخه حیات
Hr::employees()->suspend($employee);                    // تعلیق موقت — قرارداد/سمت/حقوق بسته نمی‌شود
Hr::employees()->terminate($employee, now());           // خاتمه + بستن قرارداد/سمت/حقوق جاری و لغو درخواست‌های pending
Hr::employees()->reactivate($employee);                 // استخدام مجدد — همان employee_code حفظ می‌شود

// فقط تولید کد کارمند (مثلاً برای نمایش قبل از ذخیره)
$code = Hr::employees()->generateEmployeeCode($branchId);
```

### قرارداد

```php
use Karnoweb\Hr\Enums\ContractType;

// استخدام — ایجاد اولین قرارداد فعال
$contract = Hr::contracts()->hire($employee, [
    'contract_number' => 'C-1403-001',
    'type' => ContractType::Permanent,
    'start_date' => '2026-01-01',
    'hr_document_id' => $documentId,  // اختیاری — اعتبارسنجی app-level
]);

// تمدید — بستن قرارداد فعلی و باز کردن قرارداد جدید (اتمیک)
$newContract = Hr::contracts()->renew($employee, [
    'contract_number' => 'C-1404-001',
    'start_date' => '2027-01-01',
]);

Hr::contracts()->extend($employee, '2028-12-31');       // تمدید end_date قرارداد فعال
Hr::contracts()->terminate($employee, now());           // خاتمه قرارداد فعال
```

هر کارمند **حداکثر یک قرارداد فعال** دارد (`active_key` در DB). شماره قرارداد (`contract_number`) در کل سیستم یکتا است.

### حضور و شیفت

```php
// انتساب شیفت ثابت یا الگوی چرخشی
Hr::shiftAssignments()->assignShift($employee, $shiftId, $effectiveDate);
Hr::shiftAssignments()->assignPattern($employee, $patternId, $effectiveDate, $patternStartDate);

// ورود / خروج — دیرکرد و تعجیل از روی شیفت resolve‌شده محاسبه می‌شود
Hr::attendance()->clockIn($employee);
Hr::attendance()->clockOut($employee);

// رکورد placeholder برای تعطیل/آخر هفته (بدون clock)
Hr::attendance()->ensureDayRecord($employee, $date);

// اصلاح دستی با audit trail در raw_data
Hr::attendance()->adjust($employee, $date, ['late_minutes' => 0], $userId);
```

**سیاست شیفت شبانه:** فیلد `date` همیشه برابر تاریخ تقویمی **clock-in** است؛ خروج بعد از نیمه‌شب روی همان ردیف ثبت می‌شود.

---

## Security model

این پکیج یک **دامنه HR** است، نه لایهٔ کنترل دسترسی اپلیکیشن. به‌صورت پیش‌فرض:

- متدهای `EmployeeService` (و سایر سرویس‌ها) **بررسی نمی‌کنند** که caller مجاز به ساخت/خواندن/تغییر کارمند است یا نه.
- فیلتر خودکار بر اساس `branch_id` اعمال نمی‌شود؛ هر کوییری پیش‌فرض می‌تواند کارمندان همهٔ شعب را برگرداند مگر اینکه اپ شما scope/policy بگذارد.
- تنها استثنای intentional داخل پکیج، چک مالکیت approve/reject سند در فازهای بعدی است (وقتی پیاده شود).

**مسئولیت integrator:** قبل از افشای این API به کاربران نهایی، authorization و branch-scoping را در controller/policy/middleware خودتان اعمال کنید. جزئیات یافته‌های ممیزی در `HR-AUDIT-08-SECURITY-TESTING.md` آمده است.

برای اعتبارسنجی اختیاری کد ملی ایران (بدون اجبار در سرویس):

```php
use Karnoweb\Hr\Support\IranianNationalId;

IranianNationalId::isValid('0123456789'); // true/false

$request->validate([
    'national_id' => ['required', new IranianNationalId],
]);
```

---

### مرخصی

```php
// ثبت درخواست — روزها به‌صورت پیش‌فرض از WorkingDayCalculator محاسبه می‌شوند
$request = Hr::leave()->request($employee, [
    'type' => 'annual',
    'start_date' => '2026-06-01',
    'end_date' => '2026-06-05',
    'reason' => 'سفر خانوادگی',
], ['create_document' => true]);  // اختیاری: HrDocument مرتبط

Hr::leave()->approve($request);
Hr::leave()->reject($request, 'دلیل رد');
Hr::leave()->cancel($request);

// مانده مرخصی
$balance = Hr::leave()->balance($employee, 2026, 'annual');
```

### مأموریت

```php
$mission = Hr::missions()->request($employee, [
    'start_date' => '2026-07-01',
    'end_date' => '2026-07-03',
    'destination' => 'اصفهان',
    'purpose' => 'آموزش',
], ['create_document' => true]);

Hr::missions()->approve($mission);
```

### اضافه‌کار

با `clockOut`، دقایق بعد از پایان شیفت در ستون‌های `overtime_*` حضور ذخیره می‌شوند و در صورت عبور از `min_minutes`، رکورد `OvertimeRecord` ساخته می‌شود.

```php
// ورود/خروج — sync خودکار overtime (پیش‌فرض)
Hr::attendance()->clockOut($employee, $at, ['hr_document_id' => $preApprovalDocId]);

Hr::overtime()->approve($record, $approvedByUserId);
Hr::overtime()->reject($record, 'دلیل رد');

// تجمیع برای حقوق (فاز ۸)
$totals = Hr::overtime()->approvedMinutesForPeriod($employee, $payrollPeriod);
// ['regular' => 120, 'night' => 45, 'holiday' => 0]
```

`config('hr.overtime')`: `min_minutes` (آستانه ثبت)، `monthly_cap` (دقیقه — رد approve اگر از سقف ماه بگذرد)، `requires_pre_approval` (نیاز به `HrDocument` از نوع `overtime_approval` تأییدشده با `effective_date <= تاریخ کار`)، `night_start` / `night_end` (پنجره شب).

نرخ ضریب (`OvertimeType::rate()`) از `config('hr.overtime.rates')` خوانده می‌شود.

### اسناد و گردش کار

```php
use Karnoweb\Hr\Enums\DocumentType;

// ایجاد سند (مثلاً مرخصی)
$doc = Hr::documents()->create(DocumentType::Leave, $employee, [
    'leave_request_id' => $request->id,
    'days' => 3,
], [
    'created_by' => auth()->id(),
    'branch_id' => $employee->branch_id,
]);

// ارسال برای تأیید (وضعیت به pending و رکوردهای DocumentApproval ساخته می‌شوند)
Hr::documents()->submit($doc);

// تأیید یا رد یک مرحله
Hr::documents()->approve($approval, 'تأیید شد');
Hr::documents()->reject($approval, 'دلیل رد');
```

### تنظیمات

```php
Hr::config('leave.types.annual.days_per_year');  // 26
Hr::config('overtime.rates.regular');             // 1.4
```

---

## نصب و راه‌اندازی

### نصب

```bash
composer require karnoweb/hr
```

### انتشار تنظیمات (اختیاری)

برای تغییر مقادیر پیش‌فرض، فایل config را در اپ منتشر کنید:

```bash
php artisan vendor:publish --tag=hr-config
```

فایل `config/hr.php` در پروژه شما ایجاد می‌شود و می‌توانید آن را ویرایش کنید.

### اجرای Migrationها

```bash
php artisan migrate
```

تمام جداول با پیشوند `hr_` (قابل تغییر از config) ساخته می‌شوند.

### متغیرهای محیطی (اختیاری)

در `.env` می‌توانید تعیین کنید:

```env
HR_USER_MODEL=App\Models\User
HR_BRANCH_MODEL=App\Models\Branch
HR_CALENDAR_TYPE=jalali
```

---

## اتصال User به Employee

هر کارمند در سیستم شما معمولاً یک **User** (ورود به پنل) و یک رکورد **Employee** (اطلاعات HR) دارد. رابطه از نوع **Polymorphic** است: یک Employee به هر مدلی (مثلاً User) وصل می‌شود.

### تعریف رابطه در مدل User

در مدل `User` (یا هر مدلی که نقش «قابل استخدام» دارد):

```php
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Karnoweb\Hr\Models\Employee;

class User extends Authenticatable
{
    public function employee(): MorphOne
    {
        return $this->morphOne(Employee::class, 'employable');
    }
}
```

### ایجاد کارمند برای یک User

```php
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Enums\EmployeeStatus;

$user = User::find(1);

$employee = $user->employee()->create([
    'branch_id' => 1,
    'employee_code' => '1403-0001',  // یا با سرویس خودکار تولید شود
    'hire_date' => now(),
    'status' => EmployeeStatus::Active,
    'national_id' => '0123456789',
    'insurance_number' => '1234567890',
    // ...
]);

// یا اگر employee از قبل وجود دارد:
$employee = $user->employee;
```

### دسترسی از Employee به User

```php
$employee = Employee::find(1);
$user = $employee->employable;  // مثلاً همان User
```

---

## ساختار سازمانی

### دپارتمان (درخت سلسله‌مراتبی)

```php
use Karnoweb\Hr\Models\Department;

// دپارتمان ریشه
$it = Department::create([
    'code' => 'IT',
    'name' => 'فناوری اطلاعات',
    'name_en' => 'IT',
    'parent_id' => null,
    'is_active' => true,
]);

// دپارتمان فرزند (path و level به‌صورت خودکار به‌روز می‌شود)
$dev = Department::create([
    'code' => 'DEV',
    'name' => 'توسعه',
    'parent_id' => $it->id,
    'is_active' => true,
]);

// دپارتمان‌های ریشه
$roots = Department::root()->active()->get();

// زیرمجموعه‌های یک دپارتمان
$children = $it->children;
```

### سمت (Position)

```php
use Karnoweb\Hr\Models\Position;

$position = Position::create([
    'code' => 'DEV-SENIOR',
    'name' => 'توسعه‌دهنده ارشد',
    'grade' => 5,
    'is_active' => true,
]);
```

### انتساب کارمند به دپارتمان و سمت (EmployeePosition)

برای ثبت «کارمند X از تاریخ Y در دپارتمان D با سمت P» از جدول تاریخچه‌ای `employee_positions` استفاده می‌کنید:

```php
use Karnoweb\Hr\Models\EmployeePosition;

EmployeePosition::create([
    'employee_id' => $employee->id,
    'department_id' => $dev->id,
    'position_id' => $position->id,
    'is_primary' => true,
    'effective_date' => now(),
    'end_date' => null,
]);

// دپارتمان و سمت فعلی کارمند
$employee->load('currentPosition.department', 'currentPosition.position');
$department = $employee->currentPosition->department;
$position = $employee->currentPosition->position;
```

---

## استخدام و قرارداد

از `Hr::contracts()` برای چرخه حیات قرارداد استفاده کنید (جزئیات در بخش [فاساد](#استفاده-با-فاساد-پیشنهادی) بالا). برای خواندن:

```php
$contract = $employee->currentContract;
```

---

## مرخصی و مأموریت

### انواع مرخصی (از config)

انواع پیش‌فرض: `annual`, `sick`, `unpaid`, `hourly`, `marriage`, `maternity`, `bereavement`. تنظیمات هر نوع در `config('hr.leave.types')` است.

### ثبت درخواست مرخصی

از `Hr::leave()->request()` / `approve()` / `reject()` / `cancel()` استفاده کنید (API کامل در بخش [فاساد](#استفاده-با-فاساد-پیشنهادی)). سرویس به‌صورت خودکار overlap، مانده، `fixed_days`، `once_per_employment` و سقف مرخصی ساعتی را اعتبارسنجی می‌کند.

```php
$balance = Hr::leave()->balance($employee, 2026, 'annual');
```

### درخواست مأموریت

از `Hr::missions()->request()` و متدهای lifecycle استفاده کنید. مأموریت همپوشان با مرخصی یا مأموریت دیگر رد می‌شود.

---

## حضور و غیاب و اضافه‌کار

### شیفت (Shift)

```php
use Karnoweb\Hr\Models\Shift;

$shift = Shift::create([
    'code' => 'MORNING',
    'name' => 'صبح',
    'start_time' => '08:00',
    'end_time' => '16:00',
    'break_start' => '12:00',
    'break_end' => '13:00',
    'work_minutes' => 420,
    'is_active' => true,
]);
```

### ثبت حضور

از `Hr::attendance()->clockIn()` / `clockOut()` استفاده کنید تا دقایق کار، تاخیر و تعجیل خودکار محاسبه شوند (API کامل در بخش [فاساد](#استفاده-با-فاساد-پیشنهادی)).

دستور `php artisan hr:auto-clock-out` وقتی `config('hr.attendance.auto_clock_out')` فعال باشد، روی scheduler ساعتی اجرا می‌شود.

```php
use Karnoweb\Hr\Support\WorkingDayCalculator;

$calculator = app(WorkingDayCalculator::class);
$workingDays = $calculator->count($start, $end, $branchId);
```

### رکورد اضافه‌کار (OvertimeRecord)

ترجیحاً از `Hr::overtime()` استفاده کنید؛ رکوردها معمولاً از `AttendanceService::clockOut()` sync می‌شوند:

```php
Hr::attendance()->clockOut($employee, '2026-03-02 17:30:00');
Hr::overtime()->approve($record);
```

برای ایجاد دستی (غیرمعمول):

```php
use Karnoweb\Hr\Models\OvertimeRecord;
use Karnoweb\Hr\Enums\OvertimeType;
use Karnoweb\Hr\Enums\OvertimeStatus;

OvertimeRecord::create([
    'employee_id' => $employee->id,
    'date' => today(),
    'calculated_minutes' => 60,
    'approved_minutes' => 60,
    'type' => OvertimeType::Regular,
    'status' => OvertimeStatus::Approved,
]);
```

نرخ‌های اضافه‌کار (عادی، تعطیل، شب) از `config('hr.overtime.rates')` خوانده می‌شوند.

---

## اسناد و گردش کار

اسناد HR (استخدام، ترک کار، تغییر سمت، تغییر حقوق، مرخصی، مأموریت، وام، تأیید اضافه‌کار و …) در جدول **HrDocument** با نوع (`type`) و وضعیت (`status`) نگهداری می‌شوند. برای انواعی که در `config('hr.documents.require_approval')` هستند، گردش کار (Workflow) تعریف می‌شود.

### ایجاد سند (مثلاً مرخصی)

```php
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\DocumentStatus;

$document = HrDocument::create([
    'employee_id' => $employee->id,
    'type' => DocumentType::Leave,
    'effective_date' => now(),
    'status' => DocumentStatus::Draft,
    'data' => [
        'leave_request_id' => $leaveRequest->id,
        'days' => 3,
    ],
    'created_by' => auth()->id(),
]);

// شماره سند به‌صورت خودکار تولید می‌شود (مثلاً LEA-2024-0001)
```

### ویرایش فقط در وضعیت Draft

```php
if ($document->canEdit()) {
    $document->ensureEditable();  // در غیر این صورت DocumentLockedException
    $document->update([...]);
}
```

### ارسال برای تأیید و Workflow

با فاساد می‌توانید مستقیماً ارسال، تأیید و رد کنید:

```php
Hr::documents()->submit($document);           // وضعیت به pending و رکوردهای DocumentApproval ساخته می‌شوند
Hr::documents()->approve($approval, $comment, actorId: $userId); // تأیید مرحله — actorId الزامی اگر auth ندارید
Hr::documents()->reject($approval, $comment, actorId: $userId);  // رد سند
Hr::documents()->cancel($pendingDocument, actorId: $userId, reason: '...');
Hr::documents()->resubmit($rejectedDocument); // سند ردشده → Draft جدید (metadata.resubmitted_from)
```

پس از ارسال، بر اساس Workflow تعریف‌شده برای آن `document_type`، رکوردهای **DocumentApproval** برای هر مرحله ایجاد می‌شود. وقتی همه مراحل تأیید شوند، وضعیت سند به `approved` و در صورت تنظیم، پس از `lock_delay_hours` به `locked` تغییر می‌کند.

**Workflow (فاز ۱۱):** `ApproverResolver` انواع `user` / `department_head` / `position` / `custom` را به user id تبدیل می‌کند. `Department.head_employee_id` برای department head لازم است. `execution_mode` روی Workflow می‌تواند `parallel` (پیش‌فرض) یا `sequential` باشد. شرط مرحله (`condition`) و `is_required`/`can_reject` رعایت می‌شوند. timeout با `php artisan hr:process-workflow-timeouts` (هر ۵ دقیقه در scheduler).

```bash
php artisan hr:process-workflow-timeouts
```

---

## حقوق و حقوق ساعتی

### آیتم حقوق (SalaryItem)

آیتم‌های درآمد و کسر (حق پایه، بن، اضافه‌کار، بیمه، مالیات و …) در **SalaryItem** تعریف می‌شوند:

```php
use Karnoweb\Hr\Models\SalaryItem;
use Karnoweb\Hr\Enums\SalaryItemType;
use Karnoweb\Hr\Enums\CalculationType;

SalaryItem::create([
    'code' => 'BASE',
    'name' => 'حق پایه',
    'type' => SalaryItemType::Earning,
    'calculation_type' => CalculationType::Fixed,
    'is_taxable' => true,
    'is_insurable' => true,
]);
```

### ساختار حقوق (SalaryStructure) و حقوق کارمند (EmployeeSalary)

یک **SalaryStructure** مجموعه‌ای از SalaryItemها با مقادیر پیش‌فرض است. برای تخصیص و تغییر حقوق از `Hr::salaries()` استفاده کنید:

```php
Hr::salaries()->assign($employee, [
    'base_salary' => 50_000_000,
    'salary_structure_id' => $structure->id,
    'effective_date' => '2026-01-01',
    'items' => [
        ['code' => 'HOUSING', 'value' => 3_000_000],
    ],
]);

Hr::salaries()->changeSalary($employee, [
    'base_salary' => 55_000_000,
    'effective_date' => '2026-04-01',
    'hr_document_id' => $salaryChangeDocumentId,
]);

$result = Hr::salaries()->calculate($employee->currentSalary);
// base_salary, items[], totals[taxable_amount, insurable_amount, ...]
```

هر کارمند **حداکثر یک حقوق جاری** دارد (`current_key` در DB). `CalculationType::Fixed` / `Percentage` / `Formula` در `SalaryCalculator` ارزیابی می‌شوند؛ `percentage_of` باید به `SalaryItem.code` موجود اشاره کند.

### دوره حقوق (PayrollPeriod) و سطر حقوق (PayrollRecord)

```php
$period = Hr::payroll()->openPeriod($branchId, 2026, 3);

Hr::payroll()->calculate($period);
Hr::payroll()->approve($period, approvedBy: $userId);
Hr::payroll()->markPaid($period);
```

`PayrollCalculator` حضور، مرخصی، اضافه‌کار تأییدشده، حقوق جاری، بیمه/مالات (از جداول نسخه‌دار `insurance_rates` / `tax_brackets`) و اقساط وام سررسیدشده را تجمیع می‌کند. **کسر وام** فقط در `approve()` روی `LoanPayment` ثبت می‌شود، نه در `calculate()`.

نرخ بیمه و پلکان مالیات از config فقط برای seed اولیه migration است — مقادیر NEEDS VERIFICATION هستند.

برای سال مالی جدید، نرخ‌ها را با JSON وارد کنید:

```bash
php artisan hr:import-rates /path/to/rates.json
php artisan hr:import-rates /path/to/rates.json --dry-run
php artisan hr:import-rates /path/to/rates.json --force
```

نمونه JSON:

```json
{
  "insurance": {
    "effective_date": "2027-03-21",
    "employee_rate": 7,
    "employer_rate": 20,
    "unemployment_rate": 3,
    "ceiling_multiplier": 7
  },
  "tax": {
    "fiscal_year": 1406,
    "effective_date": "2027-03-21",
    "annual_exemption": 672000000,
    "brackets": [
      {"up_to": 200000000, "rate": 10},
      {"up_to": null, "rate": 20}
    ]
  }
}
```

معافیت per-employee روی `Employee`: `insurance_exempt`, `tax_exempt`, `additional_tax_exemption`. معافیت وابستگان از `dependents_count` فقط وقتی `config('hr.tax.dependents_exemption.enabled')` فعال باشد (NEEDS VERIFICATION).

---

## وام

### درخواست و تأیید

```php
$loan = Hr::loans()->apply($employee, [
    'amount' => 50_000_000,
    'installments' => 12,
    'start_date' => '2026-03-01',
    'purpose' => 'ضروری',
]);

Hr::loans()->approve($loan);   // ساخت اقساط + فعال‌سازی
Hr::loans()->reject($loan, 'دلیل رد');

Hr::loans()->recordPayment($installment, paidDate: '2026-04-01');
Hr::loans()->settleEarly($loan, '2026-06-01');

$due = Hr::loans()->deductionsForPeriod($employee, $payrollPeriod);
```

قوانین از `config('hr.loan')`: `max_amount`, `max_installments`, `min_installments`, `max_active_loans` (در approve), `min_months_between_loans` (از `start_date` آخرین وام Active/Completed), `max_percentage_of_salary` (نسبت قسط به **base_salary** جاری).

شماره وام (`loan_number`) در صورت عدم ارسال خودکار تولید می‌شود (`LN-{year}-{seq}`).

### مدل‌ها (پیشرفته)

```php
use Karnoweb\Hr\Models\Loan;
use Karnoweb\Hr\Enums\LoanStatus;
```

اقساط در **LoanPayment** با لینک به **PayrollRecord** (در صورت کسر از حقوق) ثبت می‌شوند. ترجیحاً از `Hr::loans()` استفاده کنید تا سقف‌ها، schedule و sync مانده رعایت شود.

---

## امنیت، چند-شعبه‌ای، و مسئولیت یکپارچه‌سازی

پکیج HR یک **لایه دامنه** است، نه یک اپلیکیشن کامل. بیشتر متدهای سرویس **هیچ بررسی احراز هویت/مجوز سطح کاربر نهایی انجام نمی‌دهند** — این عمدی است و مسئولیت آن با اپ میزبان است (Controller، Policy، Middleware).

### چک‌لیست چند-شعبه‌ای (HR-149)

مدل‌های زیر ستون `branch_id` دارند (یا از طریق `employee.branch_id` به شعبه وابسته‌اند). **هیچ Global Scope پیش‌فرضی برای فیلتر شعبه وجود ندارد** — قبل از expose کردن API به کاربر نهایی، اپ میزبان باید یکی از این‌ها را اعمال کند:

| مدل / حوزه | ستون شعبه | اقدام لازم در اپ میزبان |
|---|---|---|
| `Employee` | `branch_id` | Policy/Scope روی CRUD و گزارش‌ها |
| `Department`, `Position`, `Shift` | `branch_id` | محدود کردن لیست/ویرایش به شعبه جاری |
| `HrDocument`, `Workflow` | `branch_id` | submit/approve فقط برای اسناد همان شعبه |
| `PayrollPeriod` | `branch_id` | باز کردن/محاسبه/تأیید دوره فقط برای شعبه مجاز |
| `AttendanceRecord`, `LeaveRequest`, `MissionRequest` | از طریق `employee_id` | Scope از روی employee.branch_id |
| `Loan`, `Contract`, `EmployeeSalary` | از طریق `employee_id` | Scope از روی employee.branch_id |

Helperهایی مثل `scopeForBranch()` روی بعضی مدل‌ها وجود دارد، اما **opt-in** هستند — خودکار اعمال نمی‌شوند.

### مجوزهای داخل پکیج

| متد | رفتار |
|---|---|
| `DocumentService::approve()` / `reject()` | **در پکیج:** `actorId` باید با `assigned_to` برابر باشد؛ در غیر این صورت `UnauthorizedApprovalException` |
| `DocumentService::create()` | **جزئی:** اعتبارسنجی `branch_id` کارمند در برابر options |
| سایر سرویس‌ها (`employees`, `leave`, `loans`, `payroll`, …) | **خارج از پکیج:** فراخوان‌کننده باید Policy/Middleware خودش را اعمال کند |

جزئیات audit: `HR-AUDIT-08-SECURITY-TESTING.md`. تست‌های زنده: `tests/Security/ServiceAuthorizationMatrixTest.php`.

### محدودیت نرخ درخواست‌ها (HR-158)

پکیج **rate limit** برای ایجاد درخواست مرخصی، وام، یا مأموریت پیاده نمی‌کند. اگر نگران spam/abuse هستید، در اپ میزبان از `RateLimiter`/`throttle` لاراول (یا WAF) روی routeهای مربوط استفاده کنید.

---

## فاساد و تنظیمات

### فاساد Hr

فاساد `Hr` نقطه ورود اصلی پکیج است: هم برای **تنظیمات** و هم برای **عملیات رایج** (کارمند، مرخصی، اسناد).

```php
use Karnoweb\Hr\Facades\Hr;

// تنظیمات
Hr::config('leave.types.annual.days_per_year');  // 26
Hr::config('overtime.rates.regular');            // 1.4

// زیر-سرویس‌ها برای عملیات
Hr::employees()->createForUser($user, [...]);
Hr::leave()->request($employee, [...]);
Hr::overtime()->approve($record, $approvedBy);
Hr::salaries()->assign($employee, ['base_salary' => 50_000_000]);
Hr::loans()->apply($employee, ['amount' => 10_000_000, 'installments' => 10]);
Hr::payroll()->openPeriod(1, 2026, 3);
Hr::documents()->create(DocumentType::Leave, $employee, [...]);
Hr::documents()->submit($doc);
Hr::documents()->approve($approval, $comment);
```

### Enums و ترجمه

همه Enumها متد `label()` دارند که از فایل‌های ترجمه پکیج (`resources/lang/{en|fa}/enums.php`) مقدار می‌گیرند. برای تغییر متن‌ها می‌توانید در اپ خود فایل‌های lang با namespace `hr` منتشر یا override کنید.

### پیشوند جداول

پیش‌فرض پیشوند جداول `hr_` است و از `config('hr.tables.prefix')` خوانده می‌شود. در migrationها و مدل BaseModel همین مقدار استفاده می‌شود.

---

برای جزئیات بیشتر به کد مدل‌ها و Enumها در namespace `Karnoweb\Hr` مراجعه کنید.
