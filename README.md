# Rider X Framework

PHP 8.1+ mini framework. Zero external dependencies.

---

## Dotenv

```php
Dotenv::load(__DIR__ . '/.env');

$_ENV['APP_ENV'];   // string
$_ENV['APP_DEBUG']; // bool
$_ENV['DB_PORT'];   // int
```

Existing `$_ENV` keys are never overwritten.

---

## Router

### Apache — .htaccess

```apacheconf
Options -Indexes

RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L,QSA]
```

### Nginx

```nginx
server {
    root /var/www/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Basic routes

```php
$router = new Router();

$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->put('/users/{id}', [UserController::class, 'update']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);

// Closure
$router->get('/', function (array $params) {
    echo 'Hello';
});

$router->dispatch();

if ($error = $router->hasError()) {
    http_response_code($error); // 404 or 405
}
```

Route parameters arrive as `$params['id']` inside the handler.

### Groups

```php
$router->group('/admin');
$router->get('/dashboard', [AdminController::class, 'dashboard']); // → /admin/dashboard
$router->get('/users', [AdminController::class, 'users']);         // → /admin/users

$router->resetGroup();
$router->get('/', [HomeController::class, 'index']); // → /
```

### Middleware

```php
class AuthMiddleware extends AbstractMiddleware
{
    public function run(): bool
    {
        if (!isset($_SESSION['user'])) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        return true;
    }
}
```

```php
$router->middleware(AuthMiddleware::class, AnotherMiddleware::class);
$router->get('/profile', [UserController::class, 'profile']);

$router->resetMiddleware();
```

Returning `false` from `run()` stops the chain — the route handler is not executed.  
`AbstractMiddleware` provides `json(array, status)` and `redirect(url, status)` helpers.

### Route classes

```php
class SiteRoutes extends AbstractRoute
{
    public function run(): void
    {
        $this->get('/', [HomeController::class, 'index']);
        $this->post('/contact', [HomeController::class, 'contact']);
    }
}

$router->addRouters(new SiteRoutes());
```

Groups and middleware are available inside `AbstractRoute` via `$this->group()`, `$this->middleware()`, `$this->resetGroup()`, `$this->resetMiddleware()`.

### Tracker

Auto-registers all public methods of a controller as routes.

```php
$router->tracker(HttpMethod::GET, 'posts', PostController::class);
// GET /posts/index   → PostController::index()
// GET /posts/show    → PostController::show()
// GET /posts/create  → PostController::create()
```

Magic methods and inherited methods are ignored.

---

## ORM

### Model setup

```php
class Product extends Model
{
    protected string $table      = 'products';
    protected string $primaryKey = 'id';
    protected bool   $timestamps = true;
    protected array  $fillable   = ['code', 'name', 'price'];
}
```

`timestamps` adds `created_at` / `updated_at` automatically on `create()` and `update()`.  
`fillable` is a whitelist — columns outside it are discarded on `create()` and `update()`.

### Read

```php
$db = new Product();

$db->findById(1);
$db->findAll();
$db->findBy('name', 'Notebook');
$db->first('email', 'a@a.com');
$db->random();
$db->count();
```

### Write

```php
$db->create(['code' => 'ABC', 'name' => 'Notebook', 'price' => 3500]);

$product = $db->findById(1);
$product->name = 'Updated';
$db->update($product);

$db->delete(1);
$db->truncate();
$db->deleteOlderThan(60, 'created_at'); // returns int (rows deleted)

$db->upsert(['id' => 1, 'name' => 'Notebook', 'price' => 3500]); // insert or update
```

`upsert()` uses `INSERT ... ON DUPLICATE KEY UPDATE`. The primary key must be present in the array. On conflict, all fields are updated except the primary key and `created_at`. Requires a `PRIMARY KEY` or `UNIQUE` constraint on the target column.

### Raw

```php
$db->query('SELECT * FROM products WHERE price > ?', 100);
$db->query('SELECT * FROM products WHERE active = ? AND role = ?', [1, 'admin']);

$db->execute('UPDATE products SET active = 0 WHERE id = ?', [5]);
```

### Transaction

```php
$db->transaction(function (Product $db) {
    $db->create(['code' => 'A', 'name' => 'A', 'price' => 10]);
    $db->delete(5);
});
```

Rolls back automatically on any exception.

### Export

```php
$export = new ExportDatabase(__DIR__ . '/backup.sql');
$export->setExclude(['logs', 'sessions']);
$export->export();
```

### Truncate multiple tables

```php
$truncate = new TruncateTables();
$truncate->truncateAll(['users', 'migrations']);
$truncate->addTable('logs');
$truncate->truncate();
```

---

## Migrations

```bash
php migrate.php                           # run pending migrations
php migrate.php rollback                  # undo last batch
php migrate.php status                    # list ran / pending
php migrate.php make create_users_table   # generate migration file
php migrate.php make:seeder UserSeeder    # generate seeder file
php migrate.php seed                      # run all seeders
php migrate.php seed UserSeeder           # run specific seeder
```

### Migration

```php
class CreateUsersTable extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            CREATE TABLE users (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                name       VARCHAR(100) NOT NULL,
                email      VARCHAR(150) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS users");
    }
}
```

Migration files live in `database/migrations/` and are named with a timestamp prefix (`2025_01_01_120000_create_users_table.php`) for ordering. The `migrations` table is created automatically on first run.

Each `php migrate.php` call groups all pending migrations into a **batch**. `rollback` undoes the entire last batch at once.

### Seeder

```php
class UserSeeder extends AbstractSeeder
{
    public function run(): void
    {
        $this->insert('users', [
            ['name' => 'Admin',  'email' => 'admin@site.com'],
            ['name' => 'Editor', 'email' => 'editor@site.com'],
        ]);
    }
}
```

Seeder files live in `database/seeders/`. `insert()` uses prepared statements — all rows in a single query.

---

## Upload

```php
$uploader = new UploadFiles('/var/app/storage/uploads');
$path = $uploader->upload($_FILES['photo'], AllowedType::Image);
```

Custom size limit (default 5 MB):

```php
$uploader = new UploadFiles('/var/app/storage/uploads', 10); // 10 MB
```

### Allowed types

| Case | Accepted MIMEs | Extensions |
|---|---|---|
| `AllowedType::Image` | image/jpeg, image/png, image/gif, image/webp | jpg, png, gif, webp |
| `AllowedType::Zip` | application/zip, application/x-zip-compressed | zip |
| `AllowedType::Pdf` | application/pdf | pdf |
| `AllowedType::Video` | video/mp4, video/mpeg, video/quicktime, video/x-msvideo | mp4, mpeg, mov, avi |
| `AllowedType::Document` | application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document | doc, docx |

MIME is detected via `finfo` — `$_FILES['type']` is never trusted. The stored filename is a random 32-char hex string; the original name is discarded. The storage directory must be outside `public/`.

---

## SSL

### Symmetric encryption (AES-256-GCM)

```php
$encrypted = SSL::encode('sensitive data', $key);
$plain     = SSL::decode($encrypted, $key);
```

`$key` is hashed with SHA-256 internally — any string length is accepted. Output is base64-encoded and carries the IV and authentication tag.  
`decode()` throws `SslException` if the data is tampered or the key is wrong.

### RSA key pair generation

```php
SSL::generateKeyPair('/var/app/storage/keys', 'public', 'secret');
// → /var/app/storage/keys/public.pem
// → /var/app/storage/keys/secret.pem  (chmod 0600)
```

```php
SSL::generateKeyPair('/var/app/storage/keys', 'public', 'secret', 4096); // custom bits
```

The private key is saved with `0600` permissions. The directory must exist before calling.

---

## Cache

### Drivers

```php
$cache = new ArrayCache();                          // in-memory, request lifetime
$cache = new FileCache('/var/app/storage/cache');   // persistent on disk
```

Both implement `CacheInterface` (PSR-16 compatible) and are interchangeable.

### Basic operations

```php
$cache->set('key', $value);           // no expiry
$cache->set('key', $value, 300);      // expires in 300 seconds
$cache->set('key', $value, new DateInterval('PT5M')); // DateInterval

$cache->get('key');                   // returns null if missing or expired
$cache->get('key', 'default');        // custom default

$cache->has('key');                   // bool — also evicts expired entries
$cache->delete('key');
$cache->clear();
```

### Multiple keys

```php
$cache->setMultiple(['a' => 1, 'b' => 2], 60);

foreach ($cache->getMultiple(['a', 'b'], 0) as $key => $value) {
    // $key => $value
}

$cache->deleteMultiple(['a', 'b']);
```

`getMultiple` and `setMultiple` return/accept generators — no intermediate array allocation.

### TTL

| Value | Behavior |
|---|---|
| `null` | No expiry |
| `int` | Seconds from now |
| `DateInterval` | Resolved via `DateTime::add()` |

`FileCache` stores entries as SHA-256-named files; expired entries are evicted lazily on read.

---

## Validation

```php
$v = Validator::make($_POST)
    ->field('name')->required()->string()->max(100)
    ->field('email')->required()->email()
    ->field('age')->required()->int()->min(18)->max(120)
    ->field('website')->nullable()->url()
    ->field('role')->required()->in(['admin', 'editor', 'user'])
    ->field('slug')->required()->regex('/^[a-z0-9-]+$/');

if (!$v->passes()) {
    $errors = $v->errors(); // ['field' => 'message', ...]
}

// Or throw on failure
$v->validate(); // throws ValidationException — message never carries user data
```

### Custom messages

```php
$v = Validator::make($_POST)
    ->field('email')
        ->required()->message('Email is required')
        ->email()->message('Enter a valid email')
    ->field('age')
        ->required()->message('Age is required')
        ->int()->message('Age must be a number')
        ->min(18)->message('You must be at least 18 years old');
```

`message()` only applies to the rule immediately before it. If that rule passed, `message()` is a no-op.

### Rules

| Rule | Accepts | Behavior |
|---|---|---|
| `required()` | — | Fails if `null` or `''` |
| `string()` | — | Fails if not a string |
| `int()` | — | Fails if not a valid integer representation |
| `float()` | — | Fails if not a valid float representation |
| `email()` | — | Validates via `filter_var` |
| `url()` | — | Validates via `filter_var` |
| `min(int\|float)` | limit | Numeric value ≥ limit · String length ≥ limit (mb-safe) |
| `max(int\|float)` | limit | Numeric value ≤ limit · String length ≤ limit (mb-safe) |
| `in(array)` | options | Strict type comparison |
| `regex(string)` | pattern | `preg_match` — pattern must include delimiters |
| `nullable()` | — | Skips all other rules if value is `null` or `''` |
| `cpf()` | — | Validates Brazilian CPF — accepts `000.000.000-00` or `00000000000` |

The first error per field wins — subsequent failing rules on the same field are silently ignored.

### Schema

Declarative shorthand validator. All fields are required by default. Returns only the declared fields. Throws `SchemaException` on failure.

```php
$data = Schema::object($_POST, [
    'name'     => 'string|min:2|max:100',
    'email'    => 'email|max:30',
    'age'      => 'int|min:18|max:120',
    'website'  => 'optional|url',
    'role'     => 'in:admin,editor,user',
    'slug'     => 'regex:/^[a-z0-9-]+$/',
    'document' => 'cpf',
]);

$data['name'];
$data['email'];
```

```php
try {
    $data = Schema::object($_POST, ['email' => 'email']);
} catch (SchemaException $e) {
    $e->errors(); // ['email' => 'The email field must be a valid email address']
}
```

Rules are pipe-separated (`|`); parameters use a colon (`min:6`, `in:a,b,c`).  
The first failing rule per field wins.  
Fields not declared in the schema are discarded from the return value.

### Schema rules

| Rule | Parameter | Behavior |
|---|---|---|
| `optional` | — | Field is not required — skips all other rules if value is `null` or `''` |
| `string` | — | Fails if not a string |
| `int` | — | Fails if not a valid integer representation |
| `float` | — | Fails if not a valid float representation |
| `email` | — | Validates via `filter_var` |
| `url` | — | Validates via `filter_var` |
| `min` | limit | Numeric value ≥ limit · String length ≥ limit (mb-safe) |
| `max` | limit | Numeric value ≤ limit · String length ≤ limit (mb-safe) |
| `in` | values | Comma-separated list — strict string comparison |
| `regex` | pattern | `preg_match` — pattern must include delimiters |
| `cpf` | — | Validates Brazilian CPF — accepts `000.000.000-00` or `00000000000` |
