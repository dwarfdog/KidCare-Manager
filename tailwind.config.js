/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.js",
    "./templates/**/*.html.twig",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}

// tailwind -i assets/styles/app.css -o assets/styles/app.tailwind.css -w
// tailwind -i assets/styles/app.css -o assets/styles/app.tailwind.css -m