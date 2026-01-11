# حل مشكلة Infinite Recursion في BranchScope

## المشكلة الأساسية

تحدث مشكلة الـ **Infinite Recursion** (التكرار اللانهائي) عندما يحاول النظام تنفيذ التسلسل التالي:

```
1. Auth::check() / Auth::user()
   ↓
2. EloquentUserProvider يحاول استرجاع User من قاعدة البيانات
   ↓
3. عند تحميل علاقة user->branches، يتم query على BranchAdmin
   ↓
4. BranchAdmin يرث من BaseModel
   ↓
5. BaseModel يستخدم HasBranch trait
   ↓
6. HasBranch يضيف BranchScope كـ Global Scope
   ↓
7. BranchScope يستدعي auth()->user() لتحديد الفرع الحالي
   ↓
8. عودة إلى الخطوة 1 ← **Infinite Loop!** ⚠️
```

## لماذا استخدام Auth داخل Global Scope خطير؟

### الأسباب الرئيسية:

1. **Circular Dependency (الاعتماد الدائري)**
   - Global Scope يعتمد على Auth
   - Auth يعتمد على Query Builder
   - Query Builder يطبق Global Scope
   - النتيجة: حلقة لا نهائية

2. **Stack Overflow**
   - كل استدعاء يضيف stack frame جديد
   - عند الوصول لحد معين، يحدث crash
   - الخطأ: `Maximum call stack size exceeded`

3. **تأثير على الأداء**
   - حتى لو لم يحدث crash، يحصل تباطؤ كبير
   - استهلاك عالي للذاكرة
   - استعلامات قاعدة بيانات متكررة

4. **صعوبة التشخيص**
   - الخطأ قد يظهر فقط في حالات معينة
   - صعوبة تتبع المشكلة في stacktrace

## الحل المطبق

### 1. BranchContextManager Service

أنشأنا service متخصص لإدارة سياق الفروع بشكل آمن:

```php
<?php

namespace App\Services;

class BranchContextManager
{
    // منع التكرار خلال عملية المصادقة
    protected static bool $resolvingAuth = false;
    
    // تخزين مؤقت للمستخدم الحالي
    protected static ?object $cachedUser = null;
    
    // تخزين مؤقت لمعرفات الفروع
    protected static ?array $cachedBranchIds = null;
    
    /**
     * الحصول على المستخدم الحالي بشكل آمن
     */
    public static function getCurrentUser(): ?object
    {
        // منع التكرار - إذا كنا نحل auth، نرجع القيمة المخزنة
        if (self::$resolvingAuth) {
            return self::$cachedUser;
        }
        
        // ... باقي المنطق
    }
}
```

#### الميزات الرئيسية:

- **Circuit Breaker Pattern**: يوقف التكرار عند اكتشافه
- **Request-level Caching**: يخزن البيانات خلال Request واحد فقط
- **Safe Fallbacks**: يعطي قيم افتراضية آمنة عند حدوث خطأ

### 2. تحديث BranchScope

```php
public function apply(Builder $builder, Model $model): void
{
    // منع التكرار اللانهائي أثناء المصادقة
    if (BranchContextManager::isResolvingAuth()) {
        return;
    }
    
    // استبعاد Models التي لا يجب تطبيق Scope عليها
    if ($this->shouldExcludeModel($model)) {
        return;
    }
    
    // استخدام BranchContextManager بدلاً من auth() مباشرة
    $user = BranchContextManager::getCurrentUser();
    
    // ... باقي المنطق
}

/**
 * Models التي يجب استبعادها من BranchScope
 */
protected function shouldExcludeModel(Model $model): bool
{
    $excludedModels = [
        \App\Models\User::class,
        \App\Models\Branch::class,
        \App\Models\BranchAdmin::class,
        \App\Models\Module::class,
        \App\Models\Permission::class,
        \App\Models\Role::class,
    ];
    
    foreach ($excludedModels as $excludedModel) {
        if ($model instanceof $excludedModel) {
            return true;
        }
    }
    
    return false;
}
```

### 3. تحديث BranchAdmin Model

```php
class BranchAdmin extends BaseModel
{
    /**
     * Boot the model.
     * إزالة BranchScope لمنع التكرار اللانهائي
     */
    protected static function booted(): void
    {
        parent::booted();
        
        // هذا Model يجب أن يكون متاح دائماً بدون branch filtering
        // لأنه يستخدم لتحديد صلاحيات المستخدم
    }
}
```

### 4. Middleware للتنظيف بعد Request

```php
class ClearBranchContext
{
    public function terminate(Request $request, Response $response): void
    {
        // تنظيف الـ cache بعد انتهاء Request
        BranchContextManager::clearCache();
    }
}
```

## Best Practices لتطبيقات Multi-Tenant

### 1. فصل منطق Auth عن Global Scopes

❌ **خطأ:**
```php
// داخل Global Scope
public function apply(Builder $builder, Model $model): void
{
    $user = auth()->user(); // خطر!
    if ($user) {
        $builder->where('branch_id', $user->branch_id);
    }
}
```

✅ **صحيح:**
```php
// استخدام Context Manager
public function apply(Builder $builder, Model $model): void
{
    $user = BranchContextManager::getCurrentUser();
    if ($user) {
        $branchIds = BranchContextManager::getAccessibleBranchIds();
        $builder->whereIn('branch_id', $branchIds);
    }
}
```

### 2. استبعاد Models الحساسة من Scoping

```php
// Models التي يجب أن تكون متاحة دائماً
$excludedModels = [
    User::class,          // للمصادقة
    Branch::class,        // البيانات الأساسية
    BranchAdmin::class,   // الصلاحيات
    Permission::class,    // الأذونات
    Role::class,          // الأدوار
];
```

### 3. استخدام withoutGlobalScope عند الحاجة

```php
// عند تحميل علاقات قد تسبب recursion
$user->load(['branches' => function ($query) {
    $query->withoutGlobalScopes();
}]);
```

### 4. Caching على مستوى Request

```php
// تجنب استعلامات متكررة في نفس Request
protected static ?array $cachedBranchIds = null;

public static function getAccessibleBranchIds(): array
{
    if (self::$cachedBranchIds !== null) {
        return self::$cachedBranchIds;
    }
    
    // ... query database
    self::$cachedBranchIds = $branchIds;
    return $branchIds;
}
```

### 5. استخدام Middleware بدلاً من Scope للحالات المعقدة

❌ **تجنب:**
```php
// منطق معقد داخل Global Scope
public function apply(Builder $builder, Model $model): void
{
    // الكثير من الشروط والاستعلامات
    // يمكن أن يسبب مشاكل في الأداء
}
```

✅ **أفضل:**
```php
// Middleware يحدد branch_id في session/context
class SetBranchContext
{
    public function handle(Request $request, Closure $next)
    {
        $branchId = $request->header('X-Branch-ID');
        BranchContextManager::setBranchId($branchId);
        
        return $next($request);
    }
}

// Scope بسيط يقرأ من context
public function apply(Builder $builder, Model $model): void
{
    $branchId = BranchContextManager::getCurrentBranchId();
    if ($branchId) {
        $builder->where('branch_id', $branchId);
    }
}
```

## Refactoring قابل للتوسع

### بنية معمارية محسّنة:

```
App/
├── Services/
│   ├── BranchContextManager.php    ← إدارة مركزية للسياق
│   └── TenantManager.php           ← (اختياري) إدارة multi-tenancy
├── Models/
│   └── Scopes/
│       └── BranchScope.php         ← Scope بسيط ونظيف
├── Http/
│   └── Middleware/
│       ├── SetBranchContext.php    ← تعيين السياق
│       └── ClearBranchContext.php  ← التنظيف
└── Traits/
    └── HasBranch.php               ← وظائف مساعدة
```

### مثال على تطبيق متقدم:

```php
// Service متخصص لإدارة Branch Context
class BranchContextManager
{
    // 1. إدارة الحالة
    protected static array $context = [];
    
    // 2. منع التكرار
    protected static bool $resolvingAuth = false;
    
    // 3. Cache ذكي
    protected static array $cache = [];
    
    // 4. Event dispatching
    public static function setBranchContext(int $branchId): void
    {
        event(new BranchContextChanged($branchId));
        self::$context['branch_id'] = $branchId;
        self::clearCache();
    }
    
    // 5. Logging للتتبع
    public static function getCurrentUser(): ?object
    {
        if (self::$resolvingAuth) {
            Log::debug('Prevented recursion in BranchContextManager');
            return self::$cache['user'] ?? null;
        }
        
        // ...
    }
}
```

## اختبار الحل

### Test Case 1: منع Infinite Recursion

```php
public function test_no_infinite_recursion_when_checking_branch_admin(): void
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    
    BranchAdmin::create([
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'is_active' => true,
    ]);
    
    $this->actingAs($user);
    
    // هذا كان يسبب infinite recursion قبل الإصلاح
    $isBranchAdmin = $user->isBranchAdmin();
    
    $this->assertTrue($isBranchAdmin);
}
```

### Test Case 2: استبعاد Models من Scope

```php
public function test_branch_admin_model_is_excluded_from_scope(): void
{
    $branch1 = Branch::factory()->create();
    $branch2 = Branch::factory()->create();
    
    $user = User::factory()->create(['branch_id' => $branch1->id]);
    
    BranchAdmin::create(['user_id' => $user->id, 'branch_id' => $branch1->id]);
    BranchAdmin::create(['user_id' => $user->id, 'branch_id' => $branch2->id]);
    
    $this->actingAs($user);
    
    // BranchAdmin يجب أن لا يتأثر بـ BranchScope
    $adminRecords = BranchAdmin::where('user_id', $user->id)->get();
    
    $this->assertCount(2, $adminRecords);
}
```

## ملخص القواعد الذهبية

### ✅ افعل:

1. **استخدم Context Manager** لإدارة branch context
2. **استبعد Models الحساسة** من Global Scopes
3. **استخدم Caching** على مستوى Request
4. **اختبر السيناريوهات المعقدة** (علاقات متداخلة، etc.)
5. **استخدم Middleware** للمنطق المعقد
6. **وثّق القرارات المعمارية**

### ❌ لا تفعل:

1. **لا تستدعي auth() مباشرة** داخل Global Scope
2. **لا تضع منطق معقد** في Global Scope
3. **لا ترفع stack size** كحل للمشكلة
4. **لا تستخدم حلول hacky** (مثل try-catch فارغ)
5. **لا تتجاهل التحذيرات** من recursion
6. **لا تطبق Scope على كل Models** بشكل أعمى

## المراجع والموارد

- Laravel Documentation: [Global Scopes](https://laravel.com/docs/eloquent#global-scopes)
- Laravel Documentation: [Service Container](https://laravel.com/docs/container)
- Pattern: Circuit Breaker
- Pattern: Request-scoped Caching
- Multi-tenancy Best Practices

---

## النتيجة النهائية

بعد تطبيق هذا الحل:

- ✅ لا يوجد infinite recursion
- ✅ أداء محسّن (caching)
- ✅ كود نظيف وقابل للصيانة
- ✅ سهل التوسع والتطوير
- ✅ آمن ومختبر بشكل جيد

**الخلاصة**: الحل يفصل تماماً بين Auth و Global Scopes باستخدام Context Manager كطبقة وسيطة آمنة.
