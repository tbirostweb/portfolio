const defaultTheme = require('tailwindcss/defaultTheme');

/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./src/**/*.{php,html,js}"],
  theme: {
    // En Tailwind v4, pour étendre le thème par défaut, on fusionne explicitement.
    // La clé 'extend' directement sous 'theme' comme dans la v3 n'est pas la méthode principale.
    colors: {
      ...defaultTheme.colors, // Inclut toutes les couleurs par défaut de Tailwind
      paper: '#E5E2D6',
      surface: '#FBFAF6',
      ink: '#231F20',
      accent: {
        DEFAULT: '#F0451E',
        d: '#CE3711',
      },
      gray: '#6E6A5F', // Ceci remplacera le gris par défaut de Tailwind
      line: {
        DEFAULT: '#CFCABC',
        '2': '#E9E5DB',
      },
      'd-bg': '#231F20',
      'd-text': '#F0EDE4',
      'd-dim': '#AEA99C',
      'd-line': '#3A3532',
    },
    fontFamily: {
      ...defaultTheme.fontFamily, // Inclut toutes les familles de polices par défaut de Tailwind
      fd: ['"IBM Plex Sans Condensed"', 'system-ui', 'sans-serif'],
      fb: ['"IBM Plex Sans"', 'system-ui', 'sans-serif'],
      fm: ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
    },
    borderRadius: {
      ...defaultTheme.borderRadius, // Inclut les rayons de bordure par défaut
      r: '10px',
    },
    maxWidth: {
      ...defaultTheme.maxWidth, // Inclut les largeurs maximales par défaut
      max: '1160px',
    }
  },
  plugins: [],
};