import type { Config } from "@netlify/functions";
import type { StatusEquipe } from "../../src/config/qualificacao";
import { json, leads, publico, tokensIguais } from "../lib/leads";

const STATUS: StatusEquipe[] = ["novo", "contatado", "cliente", "descartado"];

export default async (req: Request) => {
  const senha = process.env.PAINEL_SENHA;
  if (!senha) return json({ erro: "Painel desativado: configure PAINEL_SENHA na Netlify" }, 503);
  const enviada = req.headers.get("authorization")?.replace(/^Bearer\s+/i, "") ?? "";
  if (!tokensIguais(enviada, senha)) return json({ erro: "Senha incorreta" }, 401);

  if (req.method === "GET") {
    const lista = (await leads().listar()).map(publico).sort((a, b) => b.criadoEm.localeCompare(a.criadoEm));
    return json({ leads: lista });
  }

  if (req.method === "POST") {
    const corpo = (await req.json().catch(() => null)) as { id?: unknown; statusEquipe?: unknown } | null;
    if (typeof corpo?.id !== "string" || !STATUS.includes(corpo.statusEquipe as StatusEquipe)) return json({ erro: "Dados inválidos" }, 400);
    const lead = await leads().obter(corpo.id);
    if (!lead) return json({ erro: "Lead não encontrado" }, 404);
    await leads().salvar({ ...lead, statusEquipe: corpo.statusEquipe as StatusEquipe });
    return json({ ok: true });
  }

  return json({ erro: "Método não permitido" }, 405);
};

export const config: Config = { path: "/api/painel/leads" };
