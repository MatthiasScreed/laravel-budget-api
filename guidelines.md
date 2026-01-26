# Budget API - Project Guidelines

## Overview
This is a Laravel 12 API-only application for budget management and financial gamification. It integrates with Bridge API for banking data and features a robust gamification system (levels, XP, achievements, streaks).

## Architecture & Patterns

### 1. Controllers & Routing
- **Location**: `app/Http/Controllers/Api/`
- **Routing**: Routes are defined in `routes/api.php` and use the `api` middleware.
- **Responses**: Controllers return `JsonResponse`. While Eloquent Resources are recommended, several existing controllers return direct JSON arrays for speed. Maintain consistency with the specific controller's style.
- **Authentication**: Uses Laravel Sanctum (`auth:sanctum` middleware).

### 2. Models & Database
- **Eloquent First**: Use Eloquent models and relationships (`HasMany`, `BelongsTo`, `HasManyThrough`).
- **Scopes**: Extensive use of local scopes in models (e.g., `Transaction::scopeIncome()`, `Transaction::scopeCompleted()`) to keep queries readable.
- **Accessors/Mutators**: Use the `Attribute` class or traditional `get...Attribute` methods for computed properties (e.g., `formatted_amount`, `is_bridge_transaction`).
- **Casts**: Defined in the `casts()` method on models.

### 3. Business Logic (Services)
- **Location**: `app/Services/`
- **Pattern**: Move complex logic out of controllers into dedicated service classes (e.g., `GamingService`, `BankIntegrationService`, `TransactionCategorizationService`).
- **Injection**: Inject services into controller constructors.

### 4. Validation (Form Requests)
- **Location**: `app/Http/Requests/`
- **Convention**: Always use Form Request classes for validation.
- **Content**: Include `rules()`, `messages()`, and `attributes()` (for French translations) in Form Requests.
- **Sanitization**: Use `prepareForValidation()` for data normalization (e.g., `strtolower` on emails).

### 5. Gamification (Gaming Layer)
- **System**: Users earn XP and level up based on activities.
- **Entities**: `UserLevel`, `Achievement`, `Streak`, `Challenge`.
- **Logic**: Centralized in `GamingService`. Any financial action (transaction creation, goal contribution) should consider triggering gaming rewards.

### 6. Banking Integration (Bridge)
- **Service**: `BankIntegrationService` and `BankWebhookService`.
- **Version**: Currently using Bridge API v3 (2025-01-15).
- **Commands**: Use `php artisan bridge:test-config` to verify connection settings.

## Coding Standards

### PHP
- **Version**: 8.2+
- **Types**: Always use strict typing, parameter type hints, and return type declarations.
- **Constructor**: Use PHP 8 constructor property promotion.

### Laravel
- **Naming**: Use camelCase for variables/methods, StudyCaps for classes/models, and snake_case for database columns/attributes.
- **Formatting**: Run `vendor/bin/pint --dirty` before committing.

### Testing
- **Framework**: Pest PHP.
- **Commands**: `php artisan test`.
- **Expectation**: Every new feature or bug fix must have a corresponding Pest test.

## Language & Localisation
- **Code**: All code (classes, variables, methods, comments) should be in English.
- **User-Facing**: Error messages and validation attributes in Form Requests are currently in French (`messages()` and `attributes()`). Maintain this for consistency unless instructed otherwise.
