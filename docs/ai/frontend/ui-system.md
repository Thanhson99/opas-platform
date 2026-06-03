# UI System

## Design Style

- Modern enterprise SaaS style.
- Minimal, clean, calm, and easy to scan.
- Consistent spacing, typography, surfaces, and interaction states.
- No random decorative gradients, blobs, or unrelated dashboard visuals.

## Tokens

- Spacing: `4, 8, 12, 16, 24, 32, 40, 48`.
- Radius: `sm, md, lg`.
- Colors: theme-based only.
- No hardcoded colors that break dark mode.

## Button Rules

- Required variants: primary, secondary, ghost, danger, link.
- Every button must have default, hover, focus-visible, disabled, and async loading states when needed.

## Form Rules

- Every input has an associated label.
- Validation messages appear near the field.
- Disabled fields look disabled.
- Read-only values do not look editable.
- Do not expose frontend fields for backend-unsupported changes.

## Responsive Rules

- Mobile-first.
- No horizontal page scroll.
- Use flexbox and CSS grid.
- Use `minmax(0, 1fr)` in grids.
- Add `min-width: 0` to flex/grid children that contain text.
- Long URLs, tokens, IDs, and emails use `overflow-wrap: anywhere`.
- Test mobile width at 360px.

## Accessibility Rules

- Use semantic HTML first.
- Dialogs manage focus and close with Escape.
- Menus are keyboard navigable.
- Focus-visible states are visible.
- Do not communicate important state through color only.
- Maintain WCAG AA contrast.
- Icon-only buttons need accessible labels.

## Performance Rules

- Lazy load route pages.
- Split bundles by route and heavy feature.
- Avoid heavy computation inside render.
- Use stable keys; do not use array index keys for mutable lists.
- Paginate or virtualize large lists.
- Optimize images.
