import type { APIRoute } from "astro";
import { escritorio } from "../config/site";

// Buscadores e assistentes de IA são bem-vindos: queremos ser citados.
const robos = ["*", "Googlebot", "Bingbot", "GPTBot", "OAI-SearchBot", "ChatGPT-User", "ClaudeBot", "Claude-User", "Claude-SearchBot", "PerplexityBot", "Google-Extended", "Applebot-Extended"];

export const GET: APIRoute = () => {
  const regras = robos.map((r) => `User-agent: ${r}\nAllow: /\nDisallow: /admin/`).join("\n\n");
  return new Response(`${regras}\n\nSitemap: ${escritorio.url}/sitemap-index.xml\n`, {
    headers: { "Content-Type": "text/plain; charset=utf-8" },
  });
};
