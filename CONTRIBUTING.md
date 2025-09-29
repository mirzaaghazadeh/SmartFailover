# Contributing to Smart Failover

Thank you for your interest in contributing to Smart Failover! This guide will help you understand our development workflow and contribution process.

## Semantic Release & Conventional Commits

This project uses [semantic-release](https://semantic-release.gitbook.io/) for automated versioning and release management. This means that **commit messages must follow the [Conventional Commits](https://www.conventionalcommits.org/) specification**.

### Commit Message Format

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

### Commit Types

- **feat**: A new feature (triggers a **minor** version bump)
- **fix**: A bug fix (triggers a **patch** version bump)
- **perf**: A performance improvement (triggers a **patch** version bump)
- **refactor**: Code refactoring (triggers a **patch** version bump)
- **docs**: Documentation changes (no version bump)
- **style**: Code style changes (no version bump)
- **test**: Adding or updating tests (no version bump)
- **chore**: Maintenance tasks (no version bump)
- **ci**: CI/CD changes (no version bump)
- **build**: Build system changes (no version bump)
- **revert**: Reverting a previous commit (triggers a **patch** version bump)

### Breaking Changes

To trigger a **major** version bump, add `BREAKING CHANGE:` in the commit footer or add `!` after the type:

```
feat!: remove deprecated failover method

BREAKING CHANGE: The old failover() method has been removed. Use smartFailover() instead.
```

### Examples

```bash
# Feature addition (minor version bump)
feat: add Redis failover support

# Bug fix (patch version bump)  
fix: resolve connection timeout issue in database failover

# Performance improvement (patch version bump)
perf: optimize cache failover switching time

# Documentation update (no version bump)
docs: update installation instructions

# Breaking change (major version bump)
feat!: redesign failover configuration API

BREAKING CHANGE: Configuration format has changed from array to object structure.
```

## Automated Releases

When you push commits to the `main` branch, our GitHub Actions workflow will:

1. **Analyze commits** since the last release using conventional commit format
2. **Determine the next version** based on the types of changes
3. **Generate release notes** automatically from commit messages
4. **Create a GitHub release** with detailed changelog
5. **Update CHANGELOG.md** with the new version information

### Release Notes Generation

The automated release notes will be organized by change type:

- 🚀 **Features** - New functionality
- 🐛 **Bug Fixes** - Bug fixes and patches  
- ⚡ **Performance Improvements** - Performance enhancements
- ♻️ **Code Refactoring** - Code improvements
- 📚 **Documentation** - Documentation updates
- ⏪ **Reverts** - Reverted changes

## Development Workflow

1. **Fork** the repository
2. **Create a feature branch** from `main`
3. **Make your changes** following our coding standards
4. **Write tests** for new functionality
5. **Commit your changes** using conventional commit format
6. **Push** to your fork
7. **Create a Pull Request** to the `main` branch

## Testing

Before submitting a pull request, make sure all tests pass:

```bash
composer test
```

## Code Quality

We use several tools to maintain code quality:

- **PHP CS Fixer** for code formatting
- **PHPStan** for static analysis  
- **Psalm** for additional static analysis
- **PHPUnit** for testing

Run quality checks:

```bash
composer cs-fix
composer analyse
```

## Questions?

If you have questions about the contribution process or semantic versioning, feel free to:

- Open an issue for discussion
- Check existing issues and pull requests
- Review the [semantic-release documentation](https://semantic-release.gitbook.io/)

Thank you for contributing! 🚀