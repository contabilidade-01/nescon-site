import { defineCollection } from "astro:content";
import { glob } from "astro/loaders";
import { z } from "astro/zod";

const faq = z.array(z.object({ pergunta: z.string(), resposta: z.string() })).default([]);

const blog = defineCollection({
  loader: glob({ pattern: "**/*.md", base: "./src/content/blog" }),
  schema: z.object({
    titulo: z.string(),
    descricao: z.string().max(170),
    resumo: z.string(),
    publicado: z.coerce.date(),
    atualizado: z.coerce.date().optional(),
    categoria: z.enum(["Abertura de empresa", "MEI", "Simples Nacional", "Gestão", "Impostos"]),
    servico: z.string().optional(),
    faq,
    rascunho: z.boolean().default(false),
  }),
});

const servicos = defineCollection({
  loader: glob({ pattern: "**/*.md", base: "./src/content/servicos" }),
  schema: z.object({
    titulo: z.string(),
    tituloSeo: z.string(),
    descricao: z.string().max(170),
    chamada: z.string(),
    subtitulo: z.string(),
    ordem: z.number().default(0),
    precoAPartirDe: z.string().optional(),
    beneficios: z.array(z.object({ titulo: z.string(), texto: z.string() })),
    passos: z.array(z.object({ titulo: z.string(), texto: z.string() })),
    faq,
  }),
});

const cidades = defineCollection({
  loader: glob({ pattern: "**/*.md", base: "./src/content/cidades" }),
  schema: z.object({
    cidade: z.string(),
    uf: z.string().length(2),
    descricao: z.string().max(170),
    presencial: z.boolean().default(false),
    bairros: z.array(z.string()).default([]),
    faq,
    rascunho: z.boolean().default(false),
  }),
});

export const collections = { blog, servicos, cidades };
