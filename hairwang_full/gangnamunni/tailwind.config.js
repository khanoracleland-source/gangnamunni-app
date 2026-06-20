/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './**/*.php',
    './assets/js/**/*.js',
  ],
  theme: {
    extend: {
      spacing: {
        '42': '10.5rem',
      },
      colors: {
        primary: '#FF6B9D',
        secondary: '#FFB6C1',
      }
    }
  },
  plugins: [],
}
