# Layout System

## Concept

Layout is not part of the page. Layout is an injected wrapper selected by route metadata or layout registry.

## Supported Layouts

- `DefaultLayout`
- `AuthLayout`
- `DashboardLayout`
- `MinimalLayout`
- `CustomLayout` for plugin or special surfaces

## Layout Structure

```text
[Header optional]
[Sidebar optional]
[Main Content]
[Footer optional]
```

## Hard Rules

- Pages must never import Header, Footer, or Sidebar directly.
- Header, Footer, and Sidebar must be independent components.
- Layouts must be interchangeable without touching page code.
- Layouts must accept `children`.
- Layouts must not fetch page business data.
- Layouts may read only shell-level app state such as auth user, theme, locale, permissions, or navigation config.
- Sidebar items come from navigation config, not hardcoded page JSX.

## Layout Registry Concept

```tsx
export type LayoutKey = 'default' | 'auth' | 'dashboard' | 'minimal';

const layouts: Record<LayoutKey, LayoutComponent> = {
  default: DefaultLayout,
  auth: AuthLayout,
  dashboard: DashboardLayout,
  minimal: MinimalLayout,
};

export function resolveLayout(layout: LayoutKey): LayoutComponent {
  return layouts[layout];
}
```
