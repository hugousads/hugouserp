# Contributing to HugousERP

Thank you for your interest in contributing to HugousERP! This document provides guidelines and instructions for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Commit Guidelines](#commit-guidelines)
- [Pull Request Process](#pull-request-process)
- [Testing](#testing)
- [Documentation](#documentation)

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment. We expect all contributors to:

- Be respectful and considerate
- Accept constructive criticism gracefully
- Focus on what's best for the project
- Show empathy towards other community members

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 18+ and NPM
- MySQL 8.0+ or PostgreSQL 13+ or SQLite 3.35+
- Git

### Setting Up Your Development Environment

1. **Fork the repository**
   ```bash
   # Fork on GitHub, then clone your fork
   git clone https://github.com/YOUR-USERNAME/hugouserp.git
   cd hugouserp
   ```

2. **Add upstream remote**
   ```bash
   git remote add upstream https://github.com/hugouseg/hugouserp.git
   ```

3. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

4. **Set up environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   touch database/database.sqlite
   php artisan migrate --seed
   ```

5. **Build assets**
   ```bash
   npm run dev
   ```

6. **Start development server**
   ```bash
   php artisan serve
   ```

## Development Workflow

### Branch Strategy

- `main` - Production-ready code
- `develop` - Integration branch for features
- `feature/*` - New features
- `bugfix/*` - Bug fixes
- `hotfix/*` - Production hotfixes

### Creating a Feature Branch

```bash
# Update your local main branch
git checkout main
git pull upstream main

# Create and checkout a new feature branch
git checkout -b feature/your-feature-name
```

### Keeping Your Branch Updated

```bash
# Regularly sync with upstream
git fetch upstream
git rebase upstream/main
```

## Coding Standards

### PHP Standards

We follow **PSR-12** coding standard with Laravel conventions:

1. **Type Declarations**
   - Always use strict types: `declare(strict_types=1);`
   - Type-hint all method parameters and return types
   - Use nullable types when appropriate: `?string`, `?int`

2. **Naming Conventions**
   - **Classes**: PascalCase (e.g., `UserService`)
   - **Methods**: camelCase (e.g., `getUserById`)
   - **Variables**: camelCase (e.g., `$userId`)
   - **Constants**: UPPER_SNAKE_CASE (e.g., `MAX_ATTEMPTS`)

3. **Code Style**
   ```php
   <?php

   declare(strict_types=1);

   namespace App\Services;

   use App\Models\User;
   use Illuminate\Support\Facades\Hash;

   class UserService
   {
       /**
        * Create a new user.
        *
        * @param  array<string, mixed>  $data
        */
       public function createUser(array $data): User
       {
           $user = new User([
               'name' => $data['name'],
               'email' => $data['email'],
               'password' => Hash::make($data['password']),
           ]);

           $user->save();

           return $user;
       }
   }
   ```

4. **Documentation**
   - Add PHPDoc blocks to all public methods
   - Include `@param`, `@return`, and `@throws` tags
   - Describe complex logic with inline comments

5. **Code Formatting**
   ```bash
   # Check code style
   ./vendor/bin/pint --test

   # Fix code style automatically
   ./vendor/bin/pint
   ```

### JavaScript Standards

- Use ES6+ syntax
- Follow Airbnb JavaScript Style Guide
- Use `const` and `let` (no `var`)
- Use arrow functions where appropriate
- Add JSDoc comments for complex functions

### Blade Templates

- Use `{{ }}` for output escaping (XSS protection)
- Use `{!! !!}` only for trusted HTML
- Keep logic in components/controllers, not views
- Use Blade components for reusability
- Follow consistent indentation (4 spaces)

## Commit Guidelines

### Commit Message Format

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, no logic change)
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```bash
feat(auth): add two-factor authentication support

Implement TOTP-based 2FA using Google Authenticator.
Users can now enable 2FA in their profile settings.

Closes #123

---

fix(inventory): prevent negative stock after sale

Added validation to ensure stock quantities remain non-negative
after processing sales transactions.

Fixes #456

---

docs(api): update authentication endpoint examples

Added more comprehensive examples for API authentication
including token refresh and revocation.
```

### Commit Best Practices

1. **Atomic Commits**: Each commit should represent a single logical change
2. **Clear Messages**: Write clear, concise commit messages
3. **Present Tense**: Use present tense ("add feature" not "added feature")
4. **Reference Issues**: Link related issues in commit messages

## Pull Request Process

### Before Submitting

1. **Update Your Branch**
   ```bash
   git fetch upstream
   git rebase upstream/main
   ```

2. **Run Tests**
   ```bash
   php artisan test
   ```

3. **Check Code Style**
   ```bash
   ./vendor/bin/pint --test
   ```

4. **Update Documentation**
   - Update README.md if adding new features
   - Add/update inline code comments
   - Update CHANGELOG.md

### Submitting a Pull Request

1. **Push Your Branch**
   ```bash
   git push origin feature/your-feature-name
   ```

2. **Create Pull Request**
   - Go to GitHub and create a new Pull Request
   - Use a clear, descriptive title
   - Fill out the PR template completely
   - Link related issues

3. **PR Description Template**
   ```markdown
   ## Description
   Brief description of changes

   ## Type of Change
   - [ ] Bug fix (non-breaking change fixing an issue)
   - [ ] New feature (non-breaking change adding functionality)
   - [ ] Breaking change (fix or feature causing existing functionality to change)
   - [ ] Documentation update

   ## Changes Made
   - Change 1
   - Change 2
   - Change 3

   ## Testing
   - [ ] Unit tests pass
   - [ ] Feature tests pass
   - [ ] Manual testing completed
   - [ ] Code style check passed

   ## Screenshots (if applicable)
   Add screenshots for UI changes

   ## Related Issues
   Closes #123
   Related to #456

   ## Checklist
   - [ ] Code follows project style guidelines
   - [ ] Self-review completed
   - [ ] Comments added for complex code
   - [ ] Documentation updated
   - [ ] No new warnings generated
   - [ ] Tests added/updated
   - [ ] All tests pass
   ```

### Code Review Process

1. **Review Timeline**
   - Initial review within 2-3 business days
   - Follow-up reviews within 1 business day

2. **Addressing Feedback**
   - Respond to all comments
   - Make requested changes
   - Push updates to the same branch
   - Request re-review when ready

3. **Approval Requirements**
   - At least one approval from maintainers
   - All tests passing
   - No merge conflicts
   - Code style checks passing

## Testing

### Writing Tests

1. **Unit Tests**
   ```php
   <?php

   namespace Tests\Unit\Services;

   use App\Services\UserService;
   use Tests\TestCase;

   class UserServiceTest extends TestCase
   {
       public function test_can_create_user(): void
       {
           $service = new UserService();
           $user = $service->createUser([
               'name' => 'John Doe',
               'email' => 'john@example.com',
               'password' => 'password123',
           ]);

           $this->assertNotNull($user->id);
           $this->assertEquals('John Doe', $user->name);
       }
   }
   ```

2. **Feature Tests**
   ```php
   <?php

   namespace Tests\Feature\Api;

   use App\Models\User;
   use Tests\TestCase;

   class ProductApiTest extends TestCase
   {
       public function test_can_list_products(): void
       {
           $user = User::factory()->create();
           
           $response = $this->actingAs($user, 'sanctum')
               ->getJson('/api/v1/products');

           $response->assertStatus(200)
               ->assertJsonStructure([
                   'success',
                   'data' => [
                       '*' => ['id', 'name', 'sku', 'price']
                   ]
               ]);
       }
   }
   ```

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/Api/ProductApiTest.php

# Run with coverage
php artisan test --coverage

# Run specific test method
php artisan test --filter test_can_list_products
```

### Test Coverage

- Aim for 80%+ test coverage
- All new features must include tests
- Bug fixes should include regression tests

## Documentation

### Code Documentation

1. **PHPDoc Blocks**
   ```php
   /**
    * Create a new product.
    *
    * This method creates a product with the given data and automatically
    * generates a unique SKU if not provided.
    *
    * @param  array{name: string, price: float, sku?: string}  $data  Product data
    * @param  int  $branchId  The branch ID to associate the product with
    * @return Product  The created product instance
    * @throws \InvalidArgumentException  If required fields are missing
    * @throws \Illuminate\Database\QueryException  If database operation fails
    */
   public function createProduct(array $data, int $branchId): Product
   {
       // Implementation
   }
   ```

2. **Inline Comments**
   ```php
   // Calculate discount based on customer loyalty tier
   // Tiers: Bronze (5%), Silver (10%), Gold (15%), Platinum (20%)
   $discount = match ($customer->loyalty_tier) {
       'bronze' => 0.05,
       'silver' => 0.10,
       'gold' => 0.15,
       'platinum' => 0.20,
       default => 0.00,
   };
   ```

### Updating Documentation

When making changes, update relevant documentation:

- **README.md**: Installation, configuration, usage
- **ARCHITECTURE.md**: System design and architecture
- **SECURITY.md**: Security policies and practices
- **CHANGELOG.md**: Version changes
- **API Documentation**: Endpoint changes

## Questions?

If you have questions:

1. Check existing documentation
2. Search closed issues
3. Ask in discussions
4. Open a new issue with the `question` label

## Recognition

Contributors are recognized in:
- CHANGELOG.md
- GitHub contributors page
- Project documentation

Thank you for contributing to HugousERP! 🚀
