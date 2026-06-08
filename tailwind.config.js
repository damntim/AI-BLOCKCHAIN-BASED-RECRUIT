/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./**/*.php', './js/**/*.js'],
  theme: {
    extend: {
      fontFamily: {
        display: ['Syne', 'sans-serif'],
        body:    ['Plus Jakarta Sans', 'sans-serif'],
        mono:    ['ui-monospace', 'Cascadia Code', 'Consolas', 'monospace'],
      },
      colors: {
        primary:       '#1E5FA8',
        'primary-dark':'#154680',
        'primary-light':'#EBF3FC',
        'primary-mid': '#C5D8EE',
        surface:       '#FFFFFF',
        'surface-soft':'#F7FAFD',
        'surface-muted':'#EEF3F8',
        'text-primary':'#1A2332',
        'text-secondary':'#4A6380',
        'text-muted':  '#8FAABF',
        success:       '#1A7A4A',
        'success-bg':  '#E8F5EE',
        warning:       '#B05C00',
        'warning-bg':  '#FFF3E6',
        danger:        '#C0392B',
        'danger-bg':   '#FDECEA',
        info:          '#1E5FA8',
        'info-bg':     '#EBF3FC',
        'border-color':'#C5D8EE',
        'dark-navy':   '#1A2332',
      },
    },
  },
  plugins: [],
};
