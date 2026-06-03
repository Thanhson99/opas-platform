# Cyberpunk SaaS UI Kit

Use this file when creating reusable React + TailwindCSS UI primitives. Components in this kit must contain no business logic and no API calls.

## Structure

```text
components/ui/
  Button/
    Button.tsx
    index.ts
  Input/
    Input.tsx
    index.ts
  Card/
    Card.tsx
    index.ts
  Table/
    Table.tsx
    index.ts
  Badge/
    Badge.tsx
    index.ts
  Modal/
    Modal.tsx
    index.ts
  Loader/
    Loader.tsx
    index.ts
  index.ts
```

## UI Kit Rules

- Components are fully reusable.
- Components are props-driven.
- Components are fully typed.
- Components do not call APIs.
- Components do not contain business logic.
- Components do not duplicate UI logic.
- Components use design tokens from `design-tokens.md`.
- Components support accessible focus and keyboard behavior where relevant.

## Button

Contract:

- variants: `primary`, `secondary`, `ghost`, `danger`
- sizes: `sm`, `md`, `lg`
- states: `loading`, `disabled`, `focus-visible`

Example:

```tsx
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import clsx from 'clsx';

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
type ButtonSize = 'sm' | 'md' | 'lg';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  children: ReactNode;
};

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  className,
  children,
  disabled,
  ...props
}: ButtonProps) {
  return (
    <button
      type={props.type ?? 'button'}
      disabled={disabled || loading}
      className={clsx(
        'relative inline-flex items-center justify-center rounded-xl font-medium transition-all duration-200',
        'focus:outline-none focus:ring-2 focus:ring-neon-cyan/40',
        {
          'px-3 py-1 text-sm': size === 'sm',
          'px-4 py-2 text-sm': size === 'md',
          'px-6 py-3 text-base': size === 'lg',
          'bg-neon-cyan text-black shadow-glow hover:shadow-glowStrong': variant === 'primary',
          'bg-bg-secondary border border-border-strong text-text-primary hover:bg-white/5':
            variant === 'secondary',
          'text-text-primary hover:bg-white/5': variant === 'ghost',
          'bg-red-500/10 text-red-400 hover:bg-red-500/20': variant === 'danger',
          'opacity-50 cursor-not-allowed': disabled || loading,
        },
        className,
      )}
      {...props}
    >
      {loading ? 'Loading...' : children}
    </button>
  );
}
```

## Input

Contract:

- label support
- error state
- helper text
- controlled component support

Example:

```tsx
import type { InputHTMLAttributes } from 'react';
import clsx from 'clsx';

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  error?: string;
  helperText?: string;
};

export function Input({ label, error, helperText, className, id, ...props }: InputProps) {
  const inputId = id ?? props.name;

  return (
    <div className="flex flex-col gap-1">
      {label ? (
        <label htmlFor={inputId} className="text-sm text-text-secondary">
          {label}
        </label>
      ) : null}

      <input
        id={inputId}
        className={clsx(
          'w-full rounded-xl px-4 py-2 bg-bg-secondary border transition-all',
          'text-text-primary placeholder:text-text-muted',
          'focus:outline-none focus:ring-2 focus:ring-neon-cyan/40',
          error ? 'border-red-500/50' : 'border-border hover:border-neon-cyan/30',
          className,
        )}
        aria-invalid={error ? 'true' : undefined}
        {...props}
      />

      {error ? <span className="text-xs text-red-400">{error}</span> : null}
      {!error && helperText ? <span className="text-xs text-text-muted">{helperText}</span> : null}
    </div>
  );
}
```

## Card

Contract:

- variants: `glass`, `solid`
- optional glow border
- flexible children

Example:

```tsx
import type { ReactNode } from 'react';
import clsx from 'clsx';

type CardVariant = 'glass' | 'solid';

type CardProps = {
  children: ReactNode;
  className?: string;
  glow?: boolean;
  variant?: CardVariant;
};

export function Card({ children, className, glow = false, variant = 'glass' }: CardProps) {
  return (
    <div
      className={clsx(
        'rounded-2xl p-4 border transition-all duration-200',
        variant === 'glass' && 'bg-bg-secondary/60 backdrop-blur-md border-border shadow-card',
        variant === 'solid' && 'bg-bg-secondary border-border-strong shadow-card',
        glow && 'shadow-glow hover:shadow-glowStrong',
        className,
      )}
    >
      {children}
    </div>
  );
}
```

## Badge

Contract:

- variants: `success`, `warning`, `error`, `info`
- status display only
- no business-rule mapping inside the primitive

Example:

```tsx
import type { ReactNode } from 'react';
import clsx from 'clsx';

type BadgeVariant = 'success' | 'warning' | 'error' | 'info';

type BadgeProps = {
  children: ReactNode;
  variant?: BadgeVariant;
};

export function Badge({ children, variant = 'info' }: BadgeProps) {
  return (
    <span
      className={clsx('text-xs px-2 py-1 rounded-full border', {
        'bg-green-500/10 text-green-400 border-green-500/20': variant === 'success',
        'bg-yellow-500/10 text-yellow-400 border-yellow-500/20': variant === 'warning',
        'bg-red-500/10 text-red-400 border-red-500/20': variant === 'error',
        'bg-blue-500/10 text-blue-400 border-blue-500/20': variant === 'info',
      })}
    >
      {children}
    </span>
  );
}
```

## Modal

Contract:

- controlled open/close state
- overlay click close
- Escape key close
- accessible dialog attributes
- animation support through className or motion wrapper
- no business logic

Example:

```tsx
import type { ReactNode } from 'react';
import { useEffect } from 'react';
import clsx from 'clsx';

type ModalProps = {
  open: boolean;
  title: string;
  children: ReactNode;
  className?: string;
  onClose: () => void;
};

export function Modal({ open, title, children, className, onClose }: ModalProps) {
  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [onClose, open]);

  if (!open) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
      role="presentation"
      onMouseDown={onClose}
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
        className={clsx(
          'w-full max-w-lg rounded-2xl border border-border bg-bg-tertiary/95 p-5 shadow-glow',
          className,
        )}
        onMouseDown={(event) => event.stopPropagation()}
      >
        <header className="mb-4 flex items-center justify-between gap-4">
          <h2 id="modal-title" className="text-lg font-semibold text-text-primary">
            {title}
          </h2>
          <button
            type="button"
            className="rounded-lg px-2 py-1 text-text-secondary hover:bg-white/5 hover:text-text-primary"
            aria-label="Close modal"
            onClick={onClose}
          >
            x
          </button>
        </header>
        {children}
      </section>
    </div>
  );
}
```

## Table

Contract:

- loading state
- empty state
- optional pagination/sorting added through typed callbacks
- stable row key required
- no `any`
- no array index keys for rows

Example:

```tsx
import type { ReactNode } from 'react';

type TableColumn<T extends Record<string, unknown>> = {
  key: keyof T | string;
  title: string;
  render?: (row: T) => ReactNode;
};

type TableProps<T extends Record<string, unknown>> = {
  data: T[];
  columns: TableColumn<T>[];
  rowKey: (row: T) => string | number;
  loading?: boolean;
  emptyText?: string;
};

function resolveCellValue<T extends Record<string, unknown>>(row: T, key: keyof T | string) {
  return key in row ? row[key as keyof T] : null;
}

export function Table<T extends Record<string, unknown>>({
  data,
  columns,
  rowKey,
  loading = false,
  emptyText = 'No data',
}: TableProps<T>) {
  return (
    <div className="w-full overflow-x-auto rounded-2xl border border-border bg-bg-secondary/40">
      <table className="w-full text-sm text-text-primary">
        <thead className="border-b border-border">
          <tr>
            {columns.map((column) => (
              <th key={String(column.key)} className="text-left p-3 text-text-secondary font-medium">
                {column.title}
              </th>
            ))}
          </tr>
        </thead>

        <tbody>
          {loading ? (
            <tr>
              <td className="p-4 text-text-muted" colSpan={columns.length}>
                Loading...
              </td>
            </tr>
          ) : data.length === 0 ? (
            <tr>
              <td className="p-4 text-text-muted" colSpan={columns.length}>
                {emptyText}
              </td>
            </tr>
          ) : (
            data.map((row) => (
              <tr key={rowKey(row)} className="border-b border-border hover:bg-white/5 transition">
                {columns.map((column) => (
                  <td key={String(column.key)} className="p-3">
                    {column.render ? column.render(row) : String(resolveCellValue(row, column.key) ?? '')}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
```

## Loader

Contract:

- pure visual component
- no timers or business state
- reusable in buttons, cards, tables, and panels

Example:

```tsx
export function Loader() {
  return (
    <div className="flex items-center gap-2 text-neon-cyan" role="status" aria-label="Loading">
      <div className="w-2 h-2 bg-neon-cyan rounded-full animate-bounce" />
      <div className="w-2 h-2 bg-neon-purple rounded-full animate-bounce delay-150" />
      <div className="w-2 h-2 bg-neon-pink rounded-full animate-bounce delay-300" />
    </div>
  );
}
```

## Index Export

```ts
export * from './Button';
export * from './Input';
export * from './Card';
export * from './Table';
export * from './Badge';
export * from './Modal';
export * from './Loader';
```

## Usage Example

```tsx
import { Badge, Button, Card, Input, Table } from '@/components/ui';

export function DashboardPanel() {
  return (
    <Card glow>
      <h2 className="text-neon-cyan">Dashboard</h2>
      <Input label="Email" placeholder="Enter email" />
      <Button variant="primary">Save</Button>
      <Badge variant="success">Online</Badge>
    </Card>
  );
}
```

## Implementation Notes

- Install or use an existing class merge helper such as `clsx`.
- Keep primitives free of feature-specific copy and domain mapping.
- Add pagination and sorting to Table through typed props/callbacks, not hidden stateful business logic.
- Prefer skeleton loaders for large async surfaces.
