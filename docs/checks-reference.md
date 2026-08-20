# Checks Reference Guide

This document lists all active checks in the `laravel-integrity` pipeline.

| Key | Name | Severity | Fixable | Expensive | Description |
|---|---|---|---|---|---|
| `strict-types` | Strict Types Declaration | `Low` | Yes | No | Enforces `declare(strict_types=1);` is present as the first statement in PHP files. |
| `root-facade` | Root Namespace Facades | `Low` | Yes | No | Replaces root facade references (e.g. `\DB::`) with clean imports. |
| `direct-env` | Direct Env Calls | `Medium` | No | No | Prohibits direct calls to `env()` outside config files. |
| `unused-import` | Unused Import Statements | `Low` | Yes | No | Identifies and removes unused `use` declarations. |
| `missing-named-route` | Missing Named Routes | `High` | No | No | Verifies that calls to `route('name')` map to valid routes. |
| `missing-view-path` | Missing View Paths | `High` | No | No | Verifies that calls to `view('name')`, `@include('name')`, and `@extends('name')` exist on disk. |
| `orphaned-component` | Orphaned Components | `Medium` | No | No | Verifies that custom Blade tags (e.g. `<x-alert />`) resolve to components or views. |
| `livewire-manifest` | Livewire Manifest Check | `Critical` | No | No | Verifies that `<livewire:name />` and `@livewire('name')` map to registered Livewire aliases. |
| `wire-action-method` | Livewire Action Methods | `Critical` | No | No | Asserts that methods bound via `wire:click="..."` exist and are public. |
| `wire-visibility` | Livewire Property Visibility | `High` | No | No | Asserts that fields bound via `wire:model="..."` exist and are public on the component class. |
| `missing-env-variable` | Missing Env Variables | `Medium` | No | No | Scans for `env("KEY")` calls and ensures they exist in `.env.example`. |
| `route-serialization` | Route Serialization | `High` | No | Yes | Flags Closure routes that prevent route caching (`route:cache`). |
| `dangling-controller` | Dangling Controller Actions | `Critical` | No | Yes | Asserts that routes map to valid controllers and public methods. |
| `blade-compile` | Blade Compile Check | `High` | No | Yes | Sandboxes Blade views to catch syntax and compilation errors. |
| `blade-component-strict-type` | Blade Component Strict Types | `Medium` | No | Yes | Statically verifies that required `@props` are passed to Blade components. |
| `seeder-exists` | Seeder Class Existence | `High` | No | No | Verifies that classes invoked in `$this->call([...])` inside database seeders exist. |
| `pending-migration` | Pending Migrations | `High` | No | Yes | Flags migrations that exist on disk but have not been executed. |
| `migration-syntax` | Migration Syntax Check | `Critical` | No | No | Parses migrations to assert they compile cleanly. |
| `model-table-mapping` | Model Table Mapping | `High` | No | Yes | Verifies that Eloquent models correspond to existing database tables. |
| `database-index` | Database Index Hygiene | `Medium` | No | Yes | Detects missing indexes on foreign key columns (e.g., `_id`) in the database schema. |
| `dead-provider` | Dead Service Providers | `Critical` | No | No | Flags service provider configurations mapping to missing classes. |
| `policy-mapping` | Policy Method Mapping | `Medium` | No | Yes | Verifies that authorization abilities map to public policy methods. |
| `event-listener-mapping`| Event Listener Mappings | `High` | No | Yes | Asserts that event listener callbacks exist and are public. |
| `n-plus-one` | Static N+1 Query Detection | `High` | No | Yes | Detects Eloquent queries or lazy-loaded property accesses inside loops (N+1 vulnerability). |
| `unreferenced-private-method`| Dead Code Elimination (DCE)| `Low` | No | No | Detects private methods that are never called locally within the same class (`$this->method()`). |
| `model-mass-assignment` | Model Mass Assignment Security | `High` | No | No | Detects models with `$guarded = []` that are high risk for mass assignment vulnerabilities. |
