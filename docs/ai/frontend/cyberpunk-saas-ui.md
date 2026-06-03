# Cyberpunk Modern SaaS Frontend Rebuild

Use this file only when the task explicitly asks to rebuild, redesign, or create a Cyberpunk-inspired modern SaaS frontend.

## Core Objectives

- Rebuild the frontend as a modern Cyberpunk-inspired SaaS UI.
- Keep the interface clean, usable, and professional. Do not turn it into a game UI.
- Consume Laravel REST API or JSON API through a separated API/service layer.
- Optimize for performance, scalability, maintainability, and long-term design consistency.
- Build component-driven architecture using atomic design principles where practical.

## Required Architecture

Target standalone structure:

```text
src/
  api/
    axiosClient.ts
    auth.api.ts
    user.api.ts
  components/
    ui/
      Button/
      Input/
      Modal/
      Card/
      Table/
      Loader/
    layout/
      Sidebar/
      Topbar/
      Layout/
  features/
    auth/
      components/
      hooks/
      pages/
      services/
    dashboard/
    users/
    settings/
  hooks/
    useAuth.ts
    useFetch.ts
  utils/
  constants/
  styles/
  pages/
    Login.tsx
    Dashboard.tsx
```

For this Laravel monorepo, adapt the same structure under:

```text
apps/laravel/resources/js/spa/
```

## Separation Rules

- UI components must not contain business logic.
- API calls must be isolated in `api/` or feature `services/`.
- Business logic belongs in `features/*/hooks` or `features/*/services`.
- Pages only compose layouts and feature components.
- Existing UI must not be merely restyled when architecture is poor. Refactor into the new architecture.
- Split large components into smaller components before applying visual redesign.
- Remove duplication instead of creating parallel Cyberpunk versions of existing components.

## Cyberpunk SaaS Design Direction

Base theme:

- dark base: `#0a0a0f`, `#050510`
- neon cyan: `#00f5ff`
- neon purple: `#a855f7`
- neon pink: `#ff2bd6`
- glassmorphism surfaces with blur and transparency
- subtle glow only, never aggressive

Inspiration:

- Vercel dashboard
- Linear.app
- Tesla UI
- Cyberpunk 2077 HUD, minimal and professional

Typography:

- Inter or system font
- clear hierarchy
- no decorative fonts
- no viewport-scaled type

## Design Constraints

- Cyberpunk styling must remain enterprise-readable.
- Use dark surfaces with high contrast text.
- Neon accents are for actions, focus, active navigation, status highlights, and subtle borders.
- Do not flood the UI with neon.
- Avoid decorative blobs, random gradients, or game-like panels.
- All spacing, radius, color, shadow, and glow values must come from design tokens.
- Use [design-tokens.md](design-tokens.md) as the production token source when building Cyberpunk modern SaaS UI.

Suggested token groups:

```text
colors:
  bg-base
  bg-deep
  surface-glass
  surface-solid
  text-primary
  text-muted
  border-subtle
  accent-cyan
  accent-purple
  accent-pink
  danger
  success

spacing:
  4, 8, 12, 16, 24, 32

radius:
  sm, md, lg

effects:
  glow-cyan-soft
  glow-purple-soft
  glass-blur
```

## Component Design Rules

Every component must be:

- reusable
- props-driven
- fully typed when TypeScript is used
- free of API calls
- free of duplicated UI logic
- accessible by default
- responsive by default

Use [ui-kit.md](ui-kit.md) as the base UI component contract for Cyberpunk SaaS primitives.

Required UI primitives:

Button:

- variants: `primary`, `secondary`, `ghost`, `danger`
- sizes: `sm`, `md`, `lg`
- states: `loading`, `disabled`, `focus-visible`

Input:

- label support
- error state
- helper text
- controlled component contract

Card:

- variants: `glass`, `solid`
- optional glow border
- flexible children
- no nested decorative card stacks

Modal:

- overlay click close
- Escape key close
- focus management
- animation support

Table:

- pagination
- sorting
- loading skeleton
- empty state
- responsive strategy

Loader:

- skeleton variants for cards, tables, and detail panels
- avoid layout shift

## Data Flow Rules

Laravel returns JSON only. Frontend must never depend on backend HTML rendering.

Required flow:

```text
Component -> Hook -> Feature Service -> API Client -> Laravel API
```

API client rules:

- use a single Axios instance if the project standard uses Axios
- base URL comes from env/config
- auth token or Sanctum behavior handled centrally
- global error normalization
- request cancellation where needed
- no raw transport calls in components

Authentication:

- JWT or Sanctum are supported.
- HttpOnly cookie is preferred when possible.
- Do not store secrets in React state.
- Token handling must be centralized.

## State Management

- Server state uses React Query or SWR.
- Local state is only for UI state.
- Avoid Redux unless workflow complexity clearly requires it.
- Do not duplicate server state into global client stores.
- Do not store derived state manually when it can be computed.

## UX Requirements

- Loading skeleton for all async data.
- Empty states for all lists.
- Error states with retry where possible.
- Smooth transitions around 200-300ms.
- Mobile-first responsive design.
- Sidebar collapsible or drawer-based on mobile.
- Keyboard navigation works for menus, modals, buttons, and forms.
- Focus states are visible and consistent with the neon accent system.

## Performance Rules

- Lazy load pages.
- Split code by route.
- Avoid unnecessary re-renders.
- Memoize heavy components only when needed.
- Debounce search and filter API calls.
- Avoid large global imports.
- Optimize images and media.
- Avoid heavy logic in render.

## Output Order When Generating Code

When generating frontend code for this system:

1. Provide folder structure.
2. Create design tokens and global styles.
3. Create reusable UI primitives.
4. Create layout components.
5. Create API client and services.
6. Create feature modules.
7. Compose pages.
8. Add loading, empty, error, and responsive states.
9. Add focused tests or verification notes.

Do not dump everything into one file.

## Rebuild Rule For Existing UI

If existing UI exists:

- Do not only restyle it.
- Inspect existing components and flows first.
- Split large components.
- Remove duplication.
- Move API calls out of components.
- Move business logic into hooks/services.
- Replace inconsistent styles with token-based components.
- Preserve working behavior while changing presentation and architecture.

## Final Quality Bar

The finished frontend must be:

- scalable like enterprise SaaS
- clean like Linear or Vercel UI
- modular like a design system
- easy to extend for future features
- visually Cyberpunk but professional
- fully integrated with Laravel API contracts
