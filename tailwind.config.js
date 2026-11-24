/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./app/Modules/**/*.php', './public/assets/js/**/*.js'],
  theme: {
    extend: {
      fontFamily: {
        display: ['"Space Grotesk"', '"PingFang SC"', '"Noto Sans SC"', 'sans-serif'],
        sans: ['"Space Grotesk"', '"PingFang SC"', '"Noto Sans SC"', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'SFMono-Regular', 'monospace'],
      },
      colors: {
        ink: {
          950: '#04070d',
          900: '#070d17',
          850: '#0a1220',
          800: '#0d1726',
          750: '#112033',
          700: '#16283d',
          600: '#20374f',
        },
        signal: {
          DEFAULT: '#20e3bd',
          soft: '#7ff5df',
          dim: '#0d9f88',
        },
        electric: '#5b8dff',
        plasma: '#a68cff',
        hazard: '#ffb454',
        danger: '#ff6b81',
      },
      boxShadow: {
        glow: '0 0 0 1px rgba(32,227,189,.15), 0 0 36px rgba(32,227,189,.10)',
        'glow-blue': '0 0 0 1px rgba(91,141,255,.18), 0 0 42px rgba(91,141,255,.12)',
        panel: '0 24px 80px rgba(0,0,0,.35)',
      },
      backgroundImage: {
        grid: 'linear-gradient(rgba(148,163,184,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(148,163,184,.045) 1px, transparent 1px)',
        radialGlow: 'radial-gradient(circle at 50% 0%, rgba(32,227,189,.10), transparent 45%)',
      },
      keyframes: {
        pulseSoft: {
          '0%, 100%': { opacity: '1', transform: 'scale(1)' },
          '50%': { opacity: '.35', transform: 'scale(.8)' },
        },
        scanline: {
          '0%': { transform: 'translateY(-100%)' },
          '100%': { transform: 'translateY(100%)' },
        },
        floaty: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-6px)' },
        },
        ringPulse: {
          '0%': { transform: 'scale(.7)', opacity: '.8' },
          '80%, 100%': { transform: 'scale(1.9)', opacity: '0' },
        },
        shimmer: {
          '0%': { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
        marquee: {
          '0%': { transform: 'translateX(0)' },
          '100%': { transform: 'translateX(-50%)' },
        },
      },
      animation: {
        'pulse-soft': 'pulseSoft 2.4s ease-in-out infinite',
        scanline: 'scanline 4.5s linear infinite',
        floaty: 'floaty 5s ease-in-out infinite',
        'ring-pulse': 'ringPulse 2.6s ease-out infinite',
        shimmer: 'shimmer 5s linear infinite',
        marquee: 'marquee 32s linear infinite',
      },
    },
  },
  plugins: [],
};
