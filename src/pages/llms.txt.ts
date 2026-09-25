import type { APIRoute } from "astro";
import { getCollection } from "astro:content";
import { escritorio, enderecoCompleto } from "../config/site";

/** Resumo em Markdown para assistentes de IA (padrão llms.txt). */
export const GET: APIRoute = async () => {
  const u = (p: string) => new URL(p, escritorio.url).href;
  const servicos = (await getCollection("servicos")).sort((a, b) => a.data.ordem - b.data.ordem);
  const cidades = await getCollection("cidades", (c) => !c.data.rascunho);
  const artigos = await getCollection("blog", (p) => !p.data.rascunho);
  const r = escritorio.responsavel;

  const linhas = [
    `# ${escritorio.nome}`,
    "",
    `> ${escritorio.descricao}`,
    "",
    `- Responsável técnico: ${r.nome}, ${r.cargo} (${r.crc})`,
    `- Endereço: ${enderecoCompleto()}`,
    `- Atendimento: presencial em ${escritorio.cidadesAtendidas.join(", ")} e online para todo o Brasil`,
    `- Horário: ${escritorio.horario.diasUteis}, ${escritorio.horario.abre}–${escritorio.horario.fecha}`,
    `- Contato: WhatsApp +${escritorio.whatsapp} · ${escritorio.email}`,
    "",
    "## Serviços",
    ...servicos.map((s) => `- [${s.data.titulo}](${u(`/${s.id}`)}): ${s.data.descricao}`),
    ...(cidades.length ? ["", "## Cidades", ...cidades.map((c) => `- [Contabilidade em ${c.data.cidade}](${u(`/contabilidade-em-${c.id}`)})`)] : []),
    "",
    "## Guias",
    ...artigos.map((a) => `- [${a.data.titulo}](${u(`/blog/${a.id}`)}): ${a.data.resumo}`),
    "",
  ];
  return new Response(linhas.join("\n"), { headers: { "Content-Type": "text/plain; charset=utf-8" } });
};
