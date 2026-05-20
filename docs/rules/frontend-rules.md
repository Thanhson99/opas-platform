# Frontend Rules

## Purpose

Use these rules whenever a task touches `resources/js/spa`, `resources/scss`, UI copy, layout behavior, or interaction states.

## Rule Scope

- Treat frontend rules in two layers:
  - global rules that apply to every SPA surface
  - site-specific rules that apply only to a given shell or product area such as workspace or admin
- Add new guidance to the narrowest layer that owns the decision.
- Do not put admin-only visual behavior into global rules if workspace screens should not inherit it.
- Do not put one-page exceptions into shell-wide rules unless at least one more page is expected to reuse the pattern.

## Global Vs Site-Specific Boundaries

- Global frontend rules should cover:
  - typography baseline
  - button, input, dropdown, modal, icon, and validation states
  - accessibility requirements
  - security and sanitization expectations
  - responsive behavior expectations
  - shared spacing, hover, focus, disabled, and empty-state rules
- Workspace-specific rules should cover:
  - the main user shell
  - sidebar-driven navigation
  - account menu behavior
  - dashboard and day-to-day tool screens
  - shared footer presentation inside the workspace content area
- Admin-specific rules should cover:
  - the admin console shell
  - higher-density management screens
  - admin navigation groups and submenus
  - configuration and audit-oriented forms
  - admin footer placement inside the admin content region instead of spanning the full shell unless the layout intentionally calls for it

## Design-System Baseline

- Frontend work must extend the visual language already present in OPAS instead of inventing a disconnected mini-theme.
- Reuse shared tokens such as `--opas-text`, `--opas-text-strong`, `--opas-text-soft`, `--opas-line`, `--opas-card`, and `--opas-gradient` before adding new raw colors.
- New colors should harmonize with the current OPAS palette:
  - cool blue for primary structure and interaction
  - warm gold or peach for accent highlights
  - soft green only for success or verified states
  - muted gray-blue for disabled or secondary text
- Prefer subtle gradients and layered surfaces over flat white blocks when the surrounding page already uses atmospheric gradients.
- New UI should feel like it belongs to the same product family as sidebar, cards, and admin surfaces already in the repo.

## Visual Hierarchy Rules

- Every page should make the primary action and primary information obvious within the first viewport.
- Use clear hierarchy:
  - page title for the main task
  - section title for grouped features
  - hint or helper text only where the user actually needs it
- Avoid equal visual weight across all blocks. Main actions, active cards, and warnings should stand out.
- Do not let helper text visually compete with headings or data values.
- Empty states, disabled states, and secondary controls should be quieter than active content.

## Layout Rules

- Layouts should be built from clear sections with intentional spacing between them.
- Use consistent container spacing and card padding across a page.
- Avoid stretched rows where one small button causes a large card to feel visually empty or awkward.
- For mixed-content rows, separate:
  - information on the left
  - actions on the right
  - collapse to a stacked layout on smaller screens
- Prefer `flex` for horizontal alignment and `grid` for repeated cards or form fields when each has a clear role.
- On mobile widths, stack sections predictably and avoid requiring horizontal scrolling.
- Do not add a sidebar item for a page if the user can reach that page more naturally from the current context, such as an account menu.
- Shared shell furniture such as header, sidebar, content gutter, and footer should follow a consistent placement rule inside each shell:
  - workspace shell content furniture belongs inside the main content column
  - admin shell furniture belongs inside the admin content region unless the design intentionally needs a full-shell treatment

## Color And Surface Rules

- Pages should not feel "white-on-white" unless the feature deliberately requires an ultra-neutral presentation.
- If a page already has a brand gradient or highlighted shell, buttons, cards, dropdowns, and headers should carry subtle color echoes from that palette.
- Keep contrast strong enough for readability. Decorative color should never reduce text clarity.
- Use accent color intentionally:
  - primary action buttons
  - active navigation
  - hover states
  - highlighted headers or subpanels
- Do not use red or orange accents for normal neutral actions unless the action has removal or warning meaning.

## Buttons And Action Rules

- Buttons must have clearly distinct visual states:
  - default
  - hover
  - focus-visible
  - disabled
  - destructive when applicable
- Primary buttons should visibly lead the eye.
- Ghost or secondary buttons should still feel interactive, not unfinished.
- Disabled buttons must look intentionally unavailable:
  - dimmer text
  - muted border/background
  - no misleading hover effect
- Small contextual actions such as "Unlink" should not stretch an entire layout awkwardly. Size them to the action.
- Use destructive styling only for genuinely destructive actions such as delete or unlink.

## Input And Form Rules

- Inputs must visibly communicate state:
  - default
  - hover if used
  - focus
  - invalid
  - disabled
  - read-only if visually distinct
- Disabled inputs should not look editable. Use muted fill, muted border, and not-allowed cursor.
- Required fields and validation errors must be discoverable without reading the code.
- Place helper text directly under the relevant field rather than in a distant paragraph.
- Do not expose inputs that imply editability when the backend does not allow the change.
- Sanitize and validate user input at the backend boundary even if frontend also constrains it.
- Frontend should treat all user-entered text as untrusted. Never inject raw HTML into the DOM from user-controlled fields.

## Dropdown And Overlay Rules

- Dropdowns should have:
  - a clear surface
  - visible hover states
  - enough contrast from the page behind them
  - spacing that makes each action easy to scan
- Menu items should look interactive on hover and keyboard focus.
- Dropdown content should be minimal and task-relevant. Do not duplicate navigation paths without a product reason.
- Keep overlays visually attached to their trigger so the interaction feels coherent.

## Icon Rules

- Icons should support meaning, not replace text without context.
- Use icons consistently for the same concept across pages.
- Icon color should inherit from the surrounding action or state unless a brand icon intentionally uses its own palette.
- Empty states may use a neutral icon chip instead of falling back to plain numbers when the rest of the UI uses icon-based affordances.

## Typography And Copy Rules

- UI copy should be short, direct, and user-centered.
- Avoid technical implementation wording in end-user text unless the audience is explicitly technical.
- For account/profile screens, say what the user can actually do:
  - "view email"
  - "change display name"
  - "change password"
- Helper copy should explain intent or consequence, not restate the label.
- If a line sounds like internal implementation detail, rewrite it in user language.

## Interaction And Workflow Rules

- The UI should match the real workflow:
  - if an item is not editable, show it as locked or disabled
  - if an action is unavailable, explain why
  - if a section has no available data or actions, either hide it or show a clean empty state
- Do not render optional sections when they have no meaningful content unless the empty state itself helps the user.
- Keep the path to common account tasks obvious:
  - viewing profile
  - changing display name
  - changing password
  - managing linked providers
- Use the smallest navigation surface that fits the task. For example, account settings usually belong in an account menu rather than the main workspace navigation.
- If multiple routes lead to the same screen, keep only the most natural primary entrypoint visible in persistent navigation and remove redundant clutter.

## Accessibility Rules

- Ensure keyboard access for menus, buttons, links, and form controls.
- Hover-only affordances are not enough. Focus-visible states must also be clear.
- Contrast must remain readable for labels, helper text, and disabled-but-readable fields.
- Use semantic elements:
  - `button` for actions
  - `a`/`Link` for navigation
  - `label` tied to form controls
- Do not communicate important state through color alone.

## Security And Safety Rules

- Validate and sanitize external input on the backend even for small profile forms.
- Do not trust SPA-only validation for security-sensitive fields.
- Avoid `dangerouslySetInnerHTML` unless there is a reviewed, sanitized source and a strong product reason.
- Never show secret values, tokens, or internal diagnostics in the SPA.
- When displaying user-provided strings, render them as plain text and keep resource contracts safe.

## Frontend Architecture Rules

- Shared API access belongs in shared API helpers or feature contexts, not scattered ad hoc across unrelated components.
- Keep page components focused on page orchestration and extract repeated UI blocks when they become reusable.
- Prefer feature-local helpers over duplicating formatting or provider logic across multiple screens.
- SCSS should mirror feature intent. Reuse existing component classes when possible before adding more one-off variants.
- Shared shell components such as `Header`, `Sidebar`, and `Footer` should accept explicit variants or props when admin and workspace need different presentation. Do not rely on accidental placement differences alone.
- Shared frontend contracts should be documented close to the owning code with JSDoc or local `@typedef` shapes instead of being left implicit across multiple files.
- Context hooks, config registries, shared payload builders, and shared render helpers should expose a readable documentation contract before they are reused broadly.

## Workspace Shell Rules

- Workspace screens should feel lighter, more exploratory, and more atmospheric than admin screens.
- Workspace footer should align with the main content column and should not visually disappear into the left sidebar gap.
- Workspace shell should keep the footer pushed to the bottom edge of the visible content column when the page content is short, while still letting the footer scroll naturally after long content.
- User account actions should prefer the account menu over persistent workspace navigation when the action is personal rather than task-oriented.
- Workspace pages should favor approachable helper copy and softer card density.

## Admin Shell Rules

- Admin screens should keep stronger information density and faster scanability than workspace screens.
- Admin footer should sit inside the admin content region so it aligns with the managed surface rather than reading like a detached global strip.
- Admin content should own the footer through a column layout so the footer settles at the bottom of the content region when a management page is short.
- Admin shell navigation may expose deeper submenu structures when needed for configuration-heavy features.
- Admin visual accents may be slightly stronger for status, configuration readiness, and management state, but should still stay within the OPAS palette.

## Local Frontend Workflow Rules

- During local frontend work, prefer the Vite dev server over repeated `build` runs.
- The default local loop should be:
  - start `npm run dev:fresh` when you need a clean dev session
  - keep that process running while editing JS, JSX, and SCSS
  - restart it only after changing Vite config itself
- Dev assets should be served with no-store cache headers so browser refreshes pick up edits without requiring manual cache clearing.
- If a frontend change appears stale, first verify the dev server is running from the same `apps/laravel` directory before reaching for a production build.
- Do not tell users to rely on hard refresh as the normal workflow when the repo can be configured to refresh assets correctly in local development.

## Review Checklist

- The screen matches the OPAS visual language.
- Header, buttons, inputs, dropdowns, and cards carry clear state styling.
- Disabled and read-only fields look non-editable.
- Hover and focus states are obvious.
- Optional sections are hidden when empty unless an explicit empty state helps the user.
- Layout remains balanced on desktop and mobile.
- User copy describes real user actions.
- Backend validation and sanitization exist for user-entered fields.
- No raw user HTML is rendered.
- Primary actions are visually obvious and secondary actions stay secondary.
