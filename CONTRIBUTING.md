# Contributing to SubSphere

Thank you for your interest in contributing to SubSphere! This document provides guidelines and information for contributors.

## 🤝 Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Laravel 10.0 or higher
- MySQL 8.0+ or PostgreSQL 13+
- Git

### Development Setup

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:

   ```bash
   git clone https://github.com/aahmedessam30/sub-sphere.git
   cd sub-sphere
   ```

3. **Install dependencies**:

   ```bash
   composer install
   ```

4. **Set up testing environment**:

   ```bash
   cp phpunit.xml.dist phpunit.xml
   ```

5. **Run tests** to ensure everything works:
   ```bash
   composer test
   ```

## 🔧 Development Workflow

### Branching Strategy

- **main** - Stable release branch
- **develop** - Development branch (if used)
- **feature/feature-name** - New features
- **fix/bug-description** - Bug fixes
- **docs/documentation-update** - Documentation updates

### Making Changes

1. **Create a feature branch** from `main`:

   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** following our coding standards

3. **Write or update tests** for your changes

4. **Update documentation** if needed

5. **Commit with clear messages**:

   ```bash
   git commit -m "feat: add subscription pausing functionality"
   ```

6. **Push to your fork**:

   ```bash
   git push origin feature/your-feature-name
   ```

7. **Create a Pull Request** on GitHub

## 📋 Coding Standards

### PHP Standards

- Follow **PSR-12** coding standards
- Use **strict types** (`declare(strict_types=1);`)
- Write **comprehensive PHPDoc** comments
- Use **meaningful variable and method names**
- Keep methods **small and focused** (max 40 lines)
- Follow **SOLID principles**

### Code Quality

- **Test Coverage**: Maintain at least 80% code coverage
- **Static Analysis**: Use PHPStan or Psalm
- **Code Style**: Run PHP-CS-Fixer before committing
- **No Dead Code**: Remove commented-out code

### Example Code Structure

```php
<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Services;

use AhmedEssam\SubSphere\Contracts\ServiceContract;
use AhmedEssam\SubSphere\Exceptions\ServiceException;

/**
 * Service description with clear responsibility
 */
final class ExampleService implements ServiceContract
{
    /**
     * Method description explaining what it does
     *
     * @param string $parameter Clear parameter description
     * @return array<string, mixed> Clear return description
     * @throws ServiceException When validation fails
     */
    public function performAction(string $parameter): array
    {
        // Implementation with clear, readable code
    }
}
```

## 🧪 Testing Guidelines

### Test Structure

- **Unit Tests**: Test individual methods and classes
- **Integration Tests**: Test component interactions
- **Feature Tests**: Test complete workflows

### Test Naming

```php
/** @test */
public function it_can_create_subscription_with_trial_period(): void
{
    // Arrange
    $plan = Plan::factory()->create();

    // Act
    $subscription = $this->subscriptionService->createSubscription($plan, 14);

    // Assert
    $this->assertInstanceOf(Subscription::class, $subscription);
    $this->assertEquals(14, $subscription->trial_days);
}
```

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific test file
./vendor/bin/phpunit tests/Unit/Services/SubscriptionServiceTest.php

# Run with filter
./vendor/bin/phpunit --filter "test_can_create_subscription"
```

## 📖 Documentation

### Code Documentation

- **PHPDoc blocks** for all classes and public methods
- **Inline comments** for complex logic
- **README updates** for new features
- **Changelog entries** for all changes

### Translation

- **All user-facing strings** must be translatable
- **Add keys** to `resources/lang/en/subscription.php`
- **Provide Arabic translations** in `resources/lang/ar/subscription.php`
- **Use descriptive keys**: `subscription.errors.invalid_plan` not `error1`

## 🚀 Pull Request Guidelines

### Before Submitting

- [ ] **Tests pass** locally
- [ ] **Code follows** PSR-12 standards
- [ ] **Documentation** is updated
- [ ] **Translations** are provided
- [ ] **Changelog** is updated
- [ ] **No merge conflicts** with main

### PR Description Template

```markdown
## Description

Brief description of the changes

## Type of Change

- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing

- [ ] Tests added/updated
- [ ] All tests pass
- [ ] Manual testing completed

## Checklist

- [ ] Code follows project standards
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] Translations provided
```

### Review Process

1. **Automated checks** must pass
2. **Maintainer review** required
3. **Address feedback** promptly
4. **Squash commits** if requested
5. **Merge** when approved

## 🐛 Bug Reports

### Before Reporting

1. **Search existing issues** for duplicates
2. **Update to latest version** if possible
3. **Reproduce the bug** consistently
4. **Gather relevant information**

### Bug Report Template

```markdown
**Bug Description**
Clear description of the bug

**To Reproduce**
Steps to reproduce the behavior:

1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

**Expected Behavior**
What you expected to happen

**Environment**

- OS: [e.g. Windows 10]
- PHP Version: [e.g. 8.1]
- Laravel Version: [e.g. 10.0]
- Package Version: [e.g. 1.0.0]

**Additional Context**
Any other context about the problem
```

## 💡 Feature Requests

### Before Requesting

1. **Check existing issues** for similar requests
2. **Consider the scope** - Does it fit the package goals?
3. **Think about implementation** - Is it feasible?

### Feature Request Template

```markdown
**Feature Description**
Clear description of the feature

**Problem Statement**
What problem does this solve?

**Proposed Solution**
How should this work?

**Alternatives Considered**
What other approaches did you consider?

**Additional Context**
Any other context or screenshots
```

## 🔐 Security

### Reporting Security Issues

**DO NOT** create public issues for security vulnerabilities.

Instead, email security reports to: [security@example.com](mailto:security@example.com)

Include:

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

### Security Best Practices

- **Validate all inputs** thoroughly
- **Use parameterized queries** (already handled by Eloquent)
- **Sanitize user data** before storage/display
- **Follow Laravel security practices**
- **Keep dependencies updated**

## 🏆 Recognition

### Contributors

All contributors are recognized in:

- **README.md** contributors section
- **CHANGELOG.md** for significant contributions
- **GitHub** contributor graph

### Contribution Types

We recognize all types of contributions:

- **Code** contributions
- **Documentation** improvements
- **Bug reports** and testing
- **Translation** work
- **Community support**

## 📞 Getting Help

### Communication Channels

- **GitHub Issues** - Bug reports and feature requests
- **GitHub Discussions** - General questions and ideas
- **Email** - security@example.com for security issues

### Code of Conduct

This project follows the **Contributor Covenant** Code of Conduct. By participating, you are expected to uphold this code.

### Response Times

- **Bug reports**: Within 48 hours
- **Feature requests**: Within 1 week
- **Pull requests**: Within 1 week
- **Security issues**: Within 24 hours

## 📚 Additional Resources

### Laravel Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Testing](https://laravel.com/docs/testing)
- [Laravel Packages](https://laravel.com/docs/packages)

### Development Tools

- [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer)
- [PHPStan](https://phpstan.org/)
- [PHPUnit](https://phpunit.de/)

### Package Development

- [Laravel Package Development](https://laravel.com/docs/packages)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Packagist](https://packagist.org/)

---

Thank you for contributing to SubSphere! 🚀

Your contributions help make subscription management better for everyone.
