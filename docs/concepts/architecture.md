# معماری پکیج

پکیج یک **دامنه HR** است، نه اپلیکیشن کامل. کنترلر HTTP، Filament، Policy مجوز و Global Scope شعبه داخل پکیج نیستند.

## لایه‌ها

| لایه | مسیر | نقش |
|------|------|------|
| Service | `src/Services/` | Use-case: تراکنش، lock، lifecycle |
| Calculator | `src/Calculators/` | محاسبه خالص از ورودی از قبل بارگذاری‌شده |
| Support | `src/Support/` | ابزار مشترک (`SequenceGenerator`, allocatorها) |
| Event | `src/Events/` | واقعیت برای مصرف‌کننده خارجی (حسابداری) |
| Exception | `src/Exceptions/` | خطای دامنه قابل catch |
| Model | `src/Models/` | persistence و رابطه — نه workflow چندمرحله‌ای |
| Enum | `src/Enums/` | واژگان ثابت |

قواعد:

1. اپ میزبان `Hr::…()` را صدا می‌زند؛ برای موجودیت‌های حاکم (حقوق، سند، وام، دوره حقوق) `Model::create()` خام نزنید.
2. یکپارچگی بین‌پکیجی فقط با **event + سند** است، نه dependency به حسابداری.
3. شماره‌گذاری کسب‌وکار فقط از `SequenceGenerator` است، نه `max()+1`.
4. مجوز بیشتر با اپ میزبان است؛ تنها استثنای داخل پکیج برابری `actorId` با `assigned_to` در approve/reject سند است.

## الگوی «حداکثر یک رکورد جاری»

برای حقوق جاری، سمت جاری، قرارداد فعال و انتساب شیفت جاری از ستون nullable `current_key` / `active_key` با ایندکس یکتا استفاده می‌شود.

- ردیف جاری: `current_key = employee_id`
- ردیف تاریخی: `current_key = NULL`

چند `NULL` در ایندکس یکتا مجاز است (MySQL/SQLite/Postgres). تعویض جاری و جدید باید داخل یک تراکنش با `lockForUpdate()` باشد.

## بیرون از scope پکیج

HTTP، UI، tenant isolation، rate limit. این‌ها مسئولیت اپ میزبان‌اند.

**آینده:** لایه `Rules/` جدا برای قانون‌های پیچیده — فعلاً قانون‌ها داخل Service / Support هستند.
