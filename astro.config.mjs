// @ts-check
import { defineConfig } from "astro/config";
import sitemap from "@astrojs/sitemap";
import tailwindcss from "@tailwindcss/vite";
import escritorio from "./src/data/escritorio.json" with { type: "json" };

export default defineConfig({
  site: escritorio.url,
  trailingSlash: "never",
  build: { format: "file" },
  integrations: [sitemap({ filter: (page) => !page.includes("/admin") })],
  vite: { plugins: [tailwindcss()] },
});
