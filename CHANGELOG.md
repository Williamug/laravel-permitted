# Changelog

All notable changes to `laravel-permitted` will be documented in this file.

## 1.0.0 - 2026-02-17

### Added
- Initial release
- Role-based access control (RBAC)
- Multi-tenancy support (single database & multi-database)
- Single-tenant support (zero configuration)
- Hierarchical module/sub-module system
- Super admin role with bypass capabilities
- Wildcard permissions support
- Middleware for route protection
- Blade directives for view permissions
- Permission caching for performance
- Comprehensive documentation with 7 real-world examples
- Production-tested and extracted from real SaaS applications

### Features
- `HasRoles` trait for User models
- `HasPermissions` trait for User models
- Role model with automatic tenant scoping
- Permission model
- Module and SubModule models
- RoleMiddleware for route protection
- PermissionMiddleware for route protection
- RoleOrPermissionMiddleware for flexible authorization
- TenantScope and SubTenantScope for automatic data isolation
- PermittedServiceProvider with Blade directive registration
- PermissionPro Facade
- Flexible configuration
- Automatic tenant resolution
