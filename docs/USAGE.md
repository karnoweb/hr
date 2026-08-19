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
// ثبت درخواست مرخصی
$request = Hr::leave()->request($employee, [
    'type' => 'annual',
    'start_date' => '1403/06/01',
    'end_date' => '1403/06/03',
    'days' => 3,
    'reason' => 'سفر خانوادگی',
]);

// مانده مرخصی کارمند برای سال و نوع
$balance = Hr::leave()->balance($employee, 1403, 'annual');
```

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

```php
use Karnoweb\Hr\Models\LeaveRequest;
use Karnoweb\Hr\Enums\LeaveRequestStatus;

$request = LeaveRequest::create([
    'employee_id' => $employee->id,
    'type' => 'annual',
    'start_date' => '1403/06/01',
    'end_date' => '1403/06/03',
    'days' => 3,
    'reason' => 'سفر خانوادگی',
    'status' => LeaveRequestStatus::Pending,
]);
```

### مانده مرخصی (LeaveBalance)

برای هر کارمند و هر سال و هر نوع مرخصی یک رکورد `LeaveBalance` دارید (entitled, used, carried, remaining). می‌توانید قبل از ثبت درخواست، مانده را چک کنید:

```php
$balance = $employee->leaveBalances()
    ->where('year', 1403)
    ->where('type', 'annual')
    ->first();

if ($balance && $balance->remaining_days >= 3) {
    // ثبت درخواست مجاز
}
```

### درخواست مأموریت

```php
use Karnoweb\Hr\Models\MissionRequest;

MissionRequest::create([
    'employee_id' => $employee->id,
    'start_date' => now(),
    'end_date' => now()->addDays(2),
    'destination' => 'تهران',
    'purpose' => 'جلسه با مشتری',
    'days' => 2,
    'status' => \Karnoweb\Hr\Enums\LeaveRequestStatus::Pending,
]);
```

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

```php
use Karnoweb\Hr\Models\OvertimeRecord;
use Karnoweb\Hr\Enums\OvertimeType;

OvertimeRecord::create([
    'employee_id' => $employee->id,
    'date' => today(),
    'calculated_minutes' => 60,
    'approved_minutes' => 60,
    'type' => OvertimeType::Regular,
    'status' => 'approved',
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
Hr::documents()->approve($approval, $comment); // تأیید مرحله
Hr::documents()->reject($approval, $comment); // رد سند
```

پس از ارسال، بر اساس Workflow تعریف‌شده برای آن `document_type`، رکوردهای **DocumentApproval** برای هر مرحله ایجاد می‌شود. وقتی همه مراحل تأیید شوند، وضعیت سند به `approved` و در صورت تنظیم، پس از `lock_delay_hours` به `locked` تغییر می‌کند. برای کنترل دستی از مدل‌های `Workflow`, `WorkflowStep`, `DocumentApproval` و `DocumentHistory` استفاده کنید.

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

یک **SalaryStructure** مجموعه‌ای از SalaryItemها با مقادیر پیش‌فرض است. برای هر کارمند یک یا چند **EmployeeSalary** با `effective_date` و `end_date` ثبت می‌کنید و در صورت نیاز **EmployeeSalaryItem** برای مقادیر خاص هر آیتم.

```php
use Karnoweb\Hr\Models\EmployeeSalary;
use Karnoweb\Hr\Models\SalaryStructure;

$structure = SalaryStructure::where('code', 'DEFAULT')->first();

EmployeeSalary::create([
    'employee_id' => $employee->id,
    'salary_structure_id' => $structure->id,
    'base_salary' => 50_000_000,
    'effective_date' => now(),
    'is_current' => true,
]);
```

### دوره حقوق (PayrollPeriod) و سطر حقوق (PayrollRecord)

برای هر ماه یک **PayrollPeriod** و برای هر کارمند یک **PayrollRecord** (با فیلدهای کارکرد، درآمدها، کسورات، خالص و قابل پرداخت) دارید. محاسبه واقعی حقوق (بیمه، مالیات، وام و …) را در لایه سرویس/ماشین‌حساب خود انجام دهید و خروجی را در همین مدل‌ها ذخیره کنید.

---

## وام

### ثبت وام

```php
use Karnoweb\Hr\Models\Loan;
use Karnoweb\Hr\Enums\LoanStatus;

$loan = Loan::create([
    'employee_id' => $employee->id,
    'loan_number' => 'LN-1403-001',
    'type' => 'general',
    'amount' => 50_000_000,
    'installments' => 12,
    'installment_amount' => 4_500_000,
    'remaining_amount' => 50_000_000,
    'remaining_installments' => 12,
    'start_date' => now(),
    'status' => LoanStatus::Active,
]);
```

اقساط در **LoanPayment** با لینک به **PayrollRecord** (در صورت کسر از حقوق) قابل ثبت است. قوانین حداکثر مبلغ، تعداد اقساط و فاصله بین وام‌ها از `config('hr.loan')` خوانده می‌شوند.

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
