# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [[v0.3.0](https://github.com/The-FireHub-Project/Container/compare/v0.2.0...v0.3.0)] – 2026-10-22

### Added
* Disposable service lifecycle management by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/3
* Automatic disposal of singleton and scoped services by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/3
* Container and scope shutdown support by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/3
* Disposable resource tracking with identity deduplication and reverse disposal order by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/3
* Active scope tracking to prevent premature container shutdown by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/3
* Shared container lifecycle state management by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/3

### Changed
* Centralized container freezing and closure state management in `ContainerState`.
* Enforced container lifecycle restrictions across dependency resolution operations.
* Improved scope cleanup and resource release during shutdown.

### Fixed
* Prevented dependency resolution after container closure, including cached singleton instances.
* Ensured container shutdown remains idempotent.
* Ensured disposable resources are released even when individual disposal operations fail.
* Prevented container shutdown while active scopes remain registered.


## [[v0.2.0](https://github.com/The-FireHub-Project/Container/compare/v0.1.0...v0.2.0)] – 2026-10-21

### Added
* Contextual bindings by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/2
* Union i intersection dependency resolution by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/2
* Tagged services by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/2
* Tagged constructor injection by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/2
* Collection injection by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/2
* Circular dependency detection by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/2
* Alias deduplication by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/2by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/2

## [v0.1.0] – 2026-10-21

### Added
* Service registration i resolution by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/1
* Singleton, scoped, transient lifetimes by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/1
* Constructor autowiring by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/1
* Parameter binding by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/1
* Service aliases i factories by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/1
* Scope management by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/1
* Container freezing by @dieselxxx in https://github.com/The-FireHub-Project/Container/pull/1