# Changelog

All notable changes to `laravel-smart-failover` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of Laravel SmartFailover package
- Database failover with automatic connection switching
- Cache failover with Redis, Memcached, and array driver support
- Queue failover with SQS, Redis, and database driver support
- Mail failover with dynamic mailer switching
- Storage failover with S3, local, and other disk support
- Comprehensive health check system with HTTP endpoints
- CLI commands for health monitoring and failover testing
- Slack, Telegram, and email notifications for service failures
- Graceful degradation support for all services
- Automatic retry with exponential backoff
- Performance metrics and response time tracking
- Laravel Nova and Filament dashboard integration support
- Fluent API for easy configuration and usage
- Comprehensive logging and error tracking
- Service provider with automatic registration
- Publishable configuration file
- Health check routes with JSON responses
- Background health monitoring
- Connection pooling for improved performance

### Features
- **SmartFailover Core Class**: Fluent API for configuring multiple service failovers
- **DatabaseFailoverManager**: Handles database connection failover and health checks
- **CacheFailoverManager**: Manages cache store failover with multiple drivers
- **QueueFailoverManager**: Provides queue connection failover with retry logic
- **MailFailoverManager**: Dynamic mail driver swapping with health monitoring
- **StorageFailoverManager**: Storage disk failover with file synchronization
- **HealthCheckManager**: Comprehensive health monitoring for all services
- **NotificationManager**: Multi-channel notifications for service alerts
- **HealthController**: HTTP endpoints for external monitoring systems
- **Console Commands**: CLI tools for health checks and failover testing

### Configuration
- Publishable configuration file with environment variable support
- Configurable retry attempts and delays
- Health check intervals and caching
- Notification throttling and channel configuration
- Service-specific failover settings

### Documentation
- Comprehensive README with installation and usage examples
- API documentation for all classes and methods
- Configuration examples for different scenarios
- Integration guides for Laravel Nova and Filament
- Performance optimization recommendations
- Security best practices

## [1.0.0] - TBD

### Added
- Initial stable release
- Production-ready failover system
- Complete test suite
- Performance optimizations
- Security hardening

---

## Contributing

When contributing to this changelog:

1. Add new entries under the "Unreleased" section
2. Use the following categories: Added, Changed, Deprecated, Removed, Fixed, Security
3. Include relevant details and breaking changes
4. Move entries to a new version section when releasing

## Version Guidelines

- **Major version** (X.0.0): Breaking changes, major new features
- **Minor version** (0.X.0): New features, backwards compatible
- **Patch version** (0.0.X): Bug fixes, backwards compatible