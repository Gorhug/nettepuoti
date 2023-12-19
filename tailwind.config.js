module.exports = {
  content: ['./app/**/Presenters/**/*.{latte,js,ts,php,svg}',],
  plugins: [require("@tailwindcss/typography"), require('daisyui')],
  daisyui: {
    themes: [
      {
        gorhug: {
          ...require("daisyui/src/theming/themes")["business"],
          "primary": "#ff8000",
          "secondary": "#800080",
          "accent": "#8000ff",
          "neutral": "#0f0a09",
          "base-100": "#270510",
          "info": "#00d6f9",
          "success": "#008040",
          "warning": "#ffff00",
          "error": "#ff0000",
        },
      },
    ],
  },
};
