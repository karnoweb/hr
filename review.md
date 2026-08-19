بله. مخزن `karnoweb/hr` را مستقیم از GitHub بررسی کردم و فقط به README اکتفا نکردم؛ ساختار package، migrations، models، enums، services، facade/provider، configuration، workflow و وضعیت تست/CI را بررسی کردم.

### نتیجه اولیه

**این پکیج از نظر مدل دامنه و اسکیمای اولیه خوب طراحی شده، اما در وضعیت فعلی یک HR Domain کامل و Production-Ready نیست.**

من فعلاً آن را حدود **5.5/10 از نظر آمادگی به‌عنوان هسته HR برای ERP** ارزیابی می‌کنم.

نکته مهم این است که مشکل اصلی package، «بد بودن کد» نیست؛ مشکل اصلی **فاصله زیاد بین مدل/دیتابیس طراحی‌شده و business logic واقعاً پیاده‌سازی‌شده** است.

---

## چیزهایی که خوب انجام داده‌ای

ساختار domain نسبتاً گسترده است:

* Employee
* Department / Position
* Contract
* Employee Position History
* Shift / Shift Pattern
* Attendance
* Overtime
* Leave
* Mission
* Leave Balance
* HR Documents
* Document Attachments
* Workflow / Workflow Steps
* Document Approvals / History
* Salary Items / Structures
* Employee Salary
* Loans / Loan Payments
* Payroll Period / Payroll Record

در مجموع **27 migration** و حدود **30 مدل domain** وجود دارد.

این برای طراحی اولیه یک ERP HR foundation کاملاً قابل قبول است.

همچنین:

* PHP 8.3+
* Laravel 13
* enum-based statuses
* casts
* relationships
* soft deletes
* configurable table prefix
* Jalali calendar
* configurable employee code
* workflow abstraction

در معماری اولیه انتخاب‌های خوبی هستند. `composer.json` هم package را به Laravel 13 و PHP 8.3 متصل کرده است.

---

# اما چند مشکل جدی پیدا کردم

## 🔴 1. مهم‌ترین مشکل: Business Logic بسیار ناقص است

در حال حاضر فقط سه service واقعی داری:

* `EmployeeService`
* `LeaveService`
* `DocumentService`

ولی دیتابیس برای این domainها ساخته شده:

```text
Attendance
Overtime
Leave
Mission
Salary
Payroll
Insurance
Tax
Loan
Workflow
Contracts
Positions
Shifts
...
```

اما برای بخش بزرگی از آنها **Application/Domain Service وجود ندارد**.

مثلاً Payroll model داری:

```text
PayrollPeriod
PayrollRecord
```

ولی engine محاسبه payroll نداری.

یعنی فعلاً بیشتر شبیه:

> **HR Data Model Package**

است تا:

> **HR Management Domain Package**

---

# 🔴 2. Document Number Generation از نظر Concurrency مشکل دارد

این قسمت:

```php
return DB::transaction(function () {
    ...
    $sequence = static::where(...)
        ->lockForUpdate()
        ->count() + 1;
});
```

در `creating` event اجرا می‌شود.

مشکل اینجاست که transaction داخلی قبل از اینکه رکورد اصلی document واقعاً insert شود تمام می‌شود.

در concurrency:

```text
Request A -> count = 10
Request B -> count = 10

A -> DOC-2026-0011
B -> DOC-2026-0011
```

و اگر unique index نداشته باشی، collision داری.

حتی اگر unique index داشته باشی، یکی از requestها fail می‌شود.

### راه درست

Sequence باید خودش یک resource transactional باشد:

```text
document_sequences
------------------
type
year
branch_id
last_number
```

و:

```php
DB::transaction(function () {
    $sequence = DocumentSequence::query()
        ->where(...)
        ->lockForUpdate()
        ->firstOrCreate(...);

    $sequence->increment('last_number');

    return $sequence->last_number;
});
```

---

# 🔴 3. Employee Code هم Race Condition دارد

در:

`EmployeeService::generateEmployeeCode()`

این کار انجام می‌شود:

```php
$lastCode = $query
    ->orderByDesc('employee_code')
    ->value('employee_code');

$sequence = $lastSeq + 1;
```

این هم در concurrent requests safe نیست.

دو employee همزمان می‌توانند:

```text
2026-0001
2026-0001
```

بگیرند.

`employee_code` unique است، بنابراین احتمالاً یکی fail می‌شود.

برای ERP این روش مناسب نیست.

---

# 🔴 4. Workflow ناقص است

این قسمت برای من یکی از مهم‌ترین موارد است.

`Workflow` دارای:

```text
conditions
priority
branch_id
```

است.

و `WorkflowStep` هم:

```text
approver_type
approver_id
condition
is_required
can_reject
timeout_hours
timeout_action
```

دارد.

اما `DocumentService` عملاً فقط:

```php
foreach ($workflow->steps as $step) {
    DocumentApproval::create(...)
}
```

می‌کند.

یعنی:

* `condition` واقعاً evaluate نمی‌شود.
* `timeout_action` اجرا نمی‌شود.
* `approver_type` resolve نمی‌شود.
* required/optional بودن step در progression لحاظ نمی‌شود.
* sequence واقعی approval تعریف نشده.
* parallel vs sequential مشخص نیست.
* escalation وجود ندارد.

در واقع workflow فعلی بیشتر:

> Approval Assignment

است تا Workflow Engine.

---

# 🔴 5. یک bug منطقی در Submit Document

اگر workflow پیدا نشود:

```php
$document->update([
    'status' => DocumentStatus::Pending
]);
```

ولی approval ایجاد نمی‌شود.

بعد:

```php
advanceDocumentStatusIfComplete()
```

هم فراخوانی نمی‌شود.

پس document می‌تواند برای همیشه:

```text
Pending
```

بماند.

در حالی که config صراحتاً دارد:

```php
'skip_on_no_approver' => false,
```

این قسمت باید policy مشخص داشته باشد:

```text
requires approval?
    ↓
workflow exists?
    ├── yes → create approvals
    ├── no + skip allowed → auto approve
    └── no + skip disabled → reject/block submission
```

---

# 🔴 6. `LeaveService` تقریباً فقط CRUD است

فعلاً:

```php
request()
balance()
```

داری.

ولی config تو business rules زیادی دارد:

```text
carry_over
carry_over_max
requires_document
requires_approval
paid
fixed_days
once_per_employment
max_hours_per_month
```

اما `LeaveService::request()` هیچ‌کدام را enforce نمی‌کند.

مثلاً می‌توانم درخواست کنم:

```php
[
    'type' => 'annual',
    'days' => 500,
]
```

و service فعلی صرفاً آن را create می‌کند.

هیچ:

* balance validation
* overlap detection
* holiday calculation
* working-day calculation
* leave policy
* carry-over
* hourly limit
* duplicate marriage leave
* maternity rule

اینجا وجود ندارد.

---

# 🔴 7. Payroll در حد Schema است

`PayrollRecord` فیلدهای زیادی دارد:

```text
base_salary
gross_earnings
total_deductions
insurance_employee
insurance_employer
insurance_unemployment
taxable_income
tax
net_salary
loan_deduction
payable
...
```

ولی calculation engine نداریم.

در نتیجه اینها فعلاً فقط **stored values** هستند.

برای ERP واقعی باید pipeline مشخص داشته باشیم:

```text
Attendance
   ↓
Leave
   ↓
Overtime
   ↓
Salary Structure
   ↓
Allowances
   ↓
Deductions
   ↓
Insurance
   ↓
Tax
   ↓
Loans
   ↓
Payroll Calculation
   ↓
Payroll Record
   ↓
Accounting
```

این مهم‌ترین بخش HR است که هنوز وجود ندارد.

---

# 🔴 8. Employee Salary invariant وجود ندارد

`EmployeeSalary`:

```php
'is_current' => true
```

دارد.

ولی migration فقط:

```php
index(['employee_id', 'is_current'])
```

دارد و unique constraint ندارد.

بنابراین ممکن است:

```text
Employee #10
 ├── Salary A → is_current = true
 ├── Salary B → is_current = true
 └── Salary C → is_current = true
```

و:

```php
$employee->currentSalary()
```

از نظر domain دیگر invariant معتبری ندارد.

باید این invariant در database هم enforce شود.

---

# 🔴 9. `Hr` Facade از نظر architecture جای بحث دارد

فعلاً:

```php
$this->employeeService = new EmployeeService;
```

انجام می‌شود.

برای package ساده قابل قبول است، ولی برای architecture که می‌خواهیم در ERPهای مختلف استفاده کنیم، بهتر است services از container resolve شوند:

```php
app(EmployeeService::class)
```

یا binding مشخص:

```php
$this->app->scoped(...)
```

این موضوع مخصوصاً وقتی service dependencies اضافه شوند مهم می‌شود.

---

# 🟠 10. تست واقعی عملاً نداریم

Composer برای tests تنظیم شده:

```json
"autoload-dev": {
    "psr-4": {
        "Karnoweb\\Hr\\Tests\\": "tests/"
    }
}
```

و PHPUnit/Testbench هم dependency هستند.

اما در repository فعلی test suite قابل مشاهده‌ای وجود ندارد و جستجوی `TestCase` هم چیزی برنگرداند.

همچنین `.github` workflow هم پیدا نشد.

یعنی فعلاً:

```text
No meaningful automated regression safety net
```

داریم.

برای package‌ای که قرار است Core ERP باشد این **P0/P1 architectural gap** است.

---

# 🟠 11. README از implementation جلوتر است

README package را اینطور معرفی می‌کند:

> comprehensive HR management package ... payroll, leave, attendance, workflow, and document management

از نظر schema درست است، ولی از نظر implementation نه کاملاً.

به بیان دقیق‌تر:

```text
Schema coverage       ██████████  ~90%
Domain model coverage ████████░░  ~75%
Application logic     ████░░░░░░  ~40%
Business rules        ███░░░░░░░  ~30%
Testing               █░░░░░░░░░  ~10%
ERP readiness         ████░░░░░░  ~40%
```

این البته برآورد من از کد فعلی است، نه metric رسمی.

---

# 🟢 یک نکته مثبت مهم

ساختار package از نظر **Domain vocabulary** واقعاً ارزشمند است.

برای پروژه ERP شما، این مدل:

```text
Employee
Contract
Position
Salary
Attendance
Leave
Loan
Payroll
Document
Workflow
```

boundary مناسبی ایجاد کرده و من **پیشنهاد نمی‌کنم package را از صفر بازنویسی کنیم.**

برعکس:

### پیشنهاد من

همین package را حفظ کنیم و آن را به شکل incremental تکمیل کنیم.

---

# معماری پیشنهادی برای نسخه Production

من package را به این لایه‌ها تقسیم می‌کنم:

```text
Karnoweb\Hr
│
├── Domain
│   ├── Employee
│   ├── Organization
│   ├── Contract
│   ├── Attendance
│   ├── Leave
│   ├── Overtime
│   ├── Salary
│   ├── Payroll
│   ├── Loan
│   └── Workflow
│
├── Actions
│   ├── Employee
│   ├── Contract
│   ├── Leave
│   ├── Attendance
│   ├── Payroll
│   └── ...
│
├── Services
│
├── Rules
│
├── Calculators
│   ├── PayrollCalculator
│   ├── TaxCalculator
│   ├── InsuranceCalculator
│   ├── OvertimeCalculator
│   └── LeaveCalculator
│
├── Policies
│
├── Models
│
├── Enums
│
└── Exceptions
```

و مهم‌تر از همه:

```text
HR
 │
 ├── Attendance
 ├── Leave
 ├── Payroll
 └── Loans
       │
       ↓
   Accounting
```

یعنی همان معماری modular که برای Accounting در پروژه اصلی داریم.

---

# اولویت‌بندی من

### P0 — قبل از استفاده در ERP

1. Test infrastructure
2. Employee code concurrency
3. Document number concurrency
4. Workflow state machine
5. Leave validation
6. Payroll calculation engine
7. Salary invariants
8. Transaction boundaries
9. Authorization/approver resolution
10. Accounting integration boundary

### P1

11. Attendance engine
12. Overtime calculation
13. Contract lifecycle
14. Loan lifecycle
15. Holiday/working-day engine
16. Jalali date abstraction
17. document numbering abstraction
18. event architecture

### P2

19. Notifications
20. escalation
21. scheduled jobs
22. reporting queries
23. audit improvements
24. caching
25. performance indexes

---

## یک نکته بسیار مهم برای پروژه خودت

با توجه به معماری‌ای که برای ERP کارنووب داریم، من **این package را به عنوان HR UI package نمی‌بینم**.

بهتر است تبدیل شود به:

> **HR Domain/Core Package**

و پروژه اصلی فقط orchestration + UI داشته باشد.

مثلاً:

```text
karnoweb/hr
    ↓
Domain + Models + Actions + Rules + Calculators
    ↓
SHOP / HOME SERVICE / LMS
    ↓
Admin UI / Livewire
```

این دقیقاً با مسیری که برای `karnoweb/laravel-accounting` در پیش گرفتیم سازگارتر است.

---

### جمع‌بندی

**بله، می‌توانم کامل بررسی‌اش کنم و بررسی اولیه نشان داد ارزش ادامه دادن دارد؛ بازنویسی لازم نیست.**

اما در وضعیت فعلی من **v13.0.0 را Production-ready اعلام نمی‌کنم**. آخرین commit فقط migration/compatibility را برای Laravel 13 انجام داده و tag فعلی `v13.0.0` است.

اگر بخواهیم درست جلو برویم، پیشنهاد من این است که مرحله بعدی را به شکل یک **Full HR Domain Audit** انجام دهیم؛ یعنی تک‌تک این بخش‌ها را با معیار ERP بررسی کنیم:

```text
Organization
Employee
Contract
Position
Attendance
Shift
Leave
Mission
Overtime
Salary
Loan
Payroll
Insurance
Tax
Documents
Workflow
Accounting Integration
Multi-branch
Concurrency
Authorization
Testing
```

و در نهایت یک **Gap Analysis دقیق + اولویت P0/P1/P2 + معماری نهایی + prompt اجرایی Cursor** بسازیم، بدون اینکه فعلاً هیچ کدی را تغییر دهیم. این به نظرم بهترین قدم بعدی است.
