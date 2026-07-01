import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{html,js,svelte,ts}'],
  theme: {
    extend: {},
  },
  plugins: [daisyui],
  daisyui: {
    themes: [
      {
        'samurai-blue': {
          primary: '#1E3A8A',
          'primary-content': '#FFFFFF',
          secondary: '#2563EB',
          'secondary-content': '#FFFFFF',
          accent: '#38BDF8',
          'accent-content': '#0F172A',
          neutral: '#0F172A',
          'neutral-content': '#F8FAFC',
          'base-100': '#FFFFFF',
          'base-200': '#F8FAFC',
          'base-300': '#DBEAFE',
          'base-content': '#0F172A',
          info: '#0EA5E9',
          'info-content': '#FFFFFF',
          success: '#16A34A',
          'success-content': '#FFFFFF',
          warning: '#F59E0B',
          'warning-content': '#111827',
          error: '#DC2626',
          'error-content': '#FFFFFF',
        },
      },
    ],
    darkTheme: false,
    logs: false,
  },
};
