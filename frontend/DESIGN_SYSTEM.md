# Global Design System

## Principles

The interface uses one semantic token system, one component vocabulary, and Heroicons Outline only. Feature pages compose shared primitives and must not introduce their own visual language.

## Theme and color

`tokens.css` owns typography, spacing, radii, shadows, breakpoints, and focus treatment. `themes.css` owns semantic colors. Components consume names such as `--color-primary`, `--color-surface`, `--color-text`, `--color-border`, and status tokens—never palette values.

Modes: `light`, `dark`. Accents: `blue`, `green`, `orange`. The settings store applies `data-mode`, `data-accent`, and `data-density` to `<html>` and persists choices locally. Accessible foreground tokens accompany colored backgrounds.

## Typography and spacing

Inter is preferred with a system fallback. The scale is `xs`, `sm`, `md`, `lg`, `xl`, and `page`; weights are 400–700. Spacing uses the 4px-based `--space-1` through `--space-10` scale. Radii are `sm`, `md`, `lg`, and `xl`; shadows are `sm` and `md`.

## Components

- Buttons: `AppButton` variants primary, secondary, outline, ghost, danger; `IconButton`; `ButtonGroup`.
- Forms: `AppInput`, `AppTextarea`, `AppSelect`, checkbox, radio, switch, date wrapper, `FormField`, `FormError`. All forward normal input props for React Hook Form.
- Tables: `DataTable` is the required table primitive. It provides search, sorting, pagination, loading/empty states, responsive overflow, and injected permission-aware actions. Density is global.
- Overlays: `AppModal`, `ConfirmDialog`, `AppDrawer`, `AppDropdown`, `AppTooltip`. Dialogs require labels, Escape/close behavior when extended, and visible focus.
- Feedback: `AppBadge`, `AppAlert`, toast adapter, spinner, skeleton, `EmptyState`, error state.
- Layout: `PageHeader`, `PageContainer`, `SectionCard`, `StatCard`, `FilterBar`, tabs, breadcrumbs.

## Icon system

All icons are `@heroicons/react/24/outline` entries registered in `src/design-system/icons/iconRegistry.ts`. `AppIcon` accepts only registry keys and standardizes sizes (`xs` 14, `sm` 16, `md` 20, `lg` 24, `xl` 32) and stroke width. Changing a registry mapping changes every consumer.

## Responsive and accessibility

Breakpoints: 640, 768, 1024, 1280px. Forms collapse to one column below tablet width; tables scroll horizontally; overlays fit within the viewport; the dashboard sidebar becomes a drawer on mobile. Every control needs a programmatic label, errors use `role=alert`, focus is visible, color is not the only status signal, and icon-only controls require an accessible label.
