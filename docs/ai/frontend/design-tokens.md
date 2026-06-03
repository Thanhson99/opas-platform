# Cyberpunk Modern SaaS Design Tokens

Use this file when implementing TailwindCSS or CSS token layers for the Cyberpunk modern SaaS frontend.

## Tailwind Config Tokens

Recommended `tailwind.config.js` extension:

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        bg: {
          primary: '#0a0a0f',
          secondary: '#0f0f1a',
          tertiary: '#151528',
        },
        text: {
          primary: '#e5e7eb',
          secondary: '#9ca3af',
          muted: '#6b7280',
        },
        neon: {
          cyan: '#00f5ff',
          purple: '#a855f7',
          pink: '#ff2bd6',
          blue: '#3b82f6',
          green: '#00ff85',
        },
        border: {
          DEFAULT: 'rgba(255,255,255,0.08)',
          strong: 'rgba(255,255,255,0.15)',
          neon: 'rgba(0,245,255,0.35)',
        },
        success: '#00ff85',
        warning: '#ffb020',
        error: '#ff4d6d',
        info: '#38bdf8',
      },
      boxShadow: {
        glow: '0 0 20px rgba(0, 245, 255, 0.25)',
        glowStrong: '0 0 40px rgba(168, 85, 247, 0.35)',
        card: '0 8px 30px rgba(0, 0, 0, 0.4)',
      },
      backgroundImage: {
        'cyber-gradient':
          'radial-gradient(circle at 20% 20%, rgba(168,85,247,0.25), transparent 40%), radial-gradient(circle at 80% 0%, rgba(0,245,255,0.18), transparent 50%), radial-gradient(circle at 50% 100%, rgba(255,43,214,0.12), transparent 50%)',
      },
      borderRadius: {
        xl: '1rem',
        '2xl': '1.25rem',
        '3xl': '1.75rem',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
      },
      spacing: {
        18: '4.5rem',
        22: '5.5rem',
        26: '6.5rem',
      },
      backdropBlur: {
        xs: '2px',
      },
      animation: {
        glow: 'glow 2s ease-in-out infinite alternate',
        float: 'float 6s ease-in-out infinite',
      },
      keyframes: {
        glow: {
          '0%': { boxShadow: '0 0 10px rgba(0,245,255,0.2)' },
          '100%': { boxShadow: '0 0 25px rgba(168,85,247,0.35)' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0px)' },
          '50%': { transform: 'translateY(-6px)' },
        },
      },
    },
  },
  plugins: [],
};
```

For this Laravel monorepo, adjust `content` to match the actual Vite/Laravel paths, for example:

```js
content: [
  './resources/views/**/*.blade.php',
  './resources/js/**/*.{js,jsx,ts,tsx}',
]
```

## CSS Variables Token Layer

Recommended `styles/tokens.css`:

```css
:root {
  --bg-primary: #0a0a0f;
  --bg-secondary: #0f0f1a;
  --bg-tertiary: #151528;

  --text-primary: #e5e7eb;
  --text-secondary: #9ca3af;
  --text-muted: #6b7280;

  --neon-cyan: #00f5ff;
  --neon-purple: #a855f7;
  --neon-pink: #ff2bd6;

  --border: rgba(255, 255, 255, 0.08);
  --border-strong: rgba(255, 255, 255, 0.15);

  --shadow-glow: 0 0 20px rgba(0, 245, 255, 0.25);
}
```

## Design Principles

Dark-first:

- Do not use white page backgrounds.
- All UI is based on dark surfaces.

Glass hierarchy:

- level 1: page background `#0a0a0f`
- level 2: card `#0f0f1a` plus blur
- level 3: modal `#151528` plus subtle glow

Neon usage:

- Neon is only for active states, hover states, CTA, focus rings, and subtle emphasis.
- Do not use neon as the default text or surface style.

Typography:

- H1: 32-40px bold
- H2: 24-28px
- body: 14-16px
- caption: 12px muted
- use Inter or system font only

## Reusable UI Patterns

Button:

- primary: `bg-neon-cyan text-black shadow-glow`
- secondary: `bg-bg-secondary border border-border-strong`
- ghost: `hover:bg-white/5 text-text-primary`
- danger: `bg-red-500/10 text-red-400`

Card:

- `bg-bg-secondary/60`
- `backdrop-blur`
- `border border-border`
- `rounded-2xl`
- `shadow-card`

Hover:

- `hover:shadow-glow`
- `hover:border-neon-cyan/40`
- `transition-all duration-200`

## Layout Tokens

- sidebar expanded width: `260px`
- sidebar collapsed width: `72px`
- topbar height: `64px`
- content max width: `1280px`
- grid system: 12 columns

## Responsive Rules

- mobile `< 768px`: sidebar collapses or becomes drawer
- tablet `< 1024px`: compact layout
- desktop: full layout system

## Token Enforcement

- Do not introduce arbitrary hex colors in components.
- Do not use inline styles for tokenized values.
- Do not create random margin or padding values.
- Add new tokens only when they are reusable across the system.
