import type { Config } from "@netlify/functions";
import { randomBytes, randomUUID } from "node:crypto";
import { classificar, prazoRetorno, proximaPergunta, recomendar, validarRespostas } from "../../src/config/qualificacao";
import { emailEquipeConcluido, emailPessoaConcluido, enviarEmail } from "../lib/email";
import { json, leads, tokensIguais, type LeadArmazenado } from "../lib/leads";

const TAMANHO_MAXIMO = 10_000;

async function autorizado(id: unknown, token: unknown) {
  if (typeof id !== "string" || typeof token !== "string" || !id || !token) return null;
  const lead = await leads().obter(id);
  return lead && tokensIguais(lead.token, token) ? lead : null;
}

/** Devolve as respostas salvas para a pessoa continuar a conversa (link do e-mail de abandono). */
async function retomar(url: URL) {
  const lead = await autorizado(url.searchParams.get("id"), url.searchParams.get("token"));
  if (!lead) return json({ erro: "Conversa não encontrada" }, 404);
  return json({ id: lead.id, token: lead.token, status: lead.status, respostas: lead.respostas });
}

/** Cria ou atualiza o lead a cada resposta; ao concluir, avisa a equipe e a pessoa (uma única vez). */
async function salvar(req: Request) {
  const texto = await req.text();
  if (texto.length > TAMANHO_MAXIMO) return json({ erro: "Dados grandes demais" }, 413);
  let corpo: Record<string, unknown>;
  try {
    corpo = JSON.parse(texto);
  } catch {
    return json({ erro: "JSON inválido" }, 400);
  }
  if (!corpo || typeof corpo !== "object") return json({ erro: "JSON inválido" }, 400);

  // Campo invisível: só robôs de spam preenchem
  if (corpo.site) return json({ id: randomUUID(), token: "", status: "em_andamento" });

  const validacao = validarRespostas(corpo.respostas);
  if ("erro" in validacao) return json({ erro: validacao.erro }, 400);
  const { respostas } = validacao;

  const agora = new Date().toISOString();
  let lead: LeadArmazenado;
  if (corpo.id) {
    const existente = await autorizado(corpo.id, corpo.token);
    if (!existente) return json({ erro: "Não autorizado" }, 403);
    lead = { ...existente, respostas, atualizadoEm: agora };
  } else {
    lead = {
      id: randomUUID(),
      token: randomBytes(24).toString("base64url"),
      status: "em_andamento",
      statusEquipe: "novo",
      criadoEm: agora,
      atualizadoEm: agora,
      respostas,
      pagina: typeof corpo.pagina === "string" ? corpo.pagina.slice(0, 200) : undefined,
    };
  }

  const concluiuAgora = corpo.concluido === true && lead.status !== "completo" && !proximaPergunta(respostas);
  if (lead.status === "completo" || concluiuAgora) {
    lead.classificacao = classificar(respostas);
    lead.recomendacao = recomendar(respostas);
  }
  if (concluiuAgora) {
    lead.status = "completo";
    lead.concluidoEm = agora;
  }
  await leads().salvar(lead);

  if (concluiuAgora) {
    await Promise.allSettled([enviarEmail(emailEquipeConcluido(lead)), enviarEmail(emailPessoaConcluido(lead, prazoRetorno()))]);
  }
  return json({ id: lead.id, token: lead.token, status: lead.status });
}

export default async (req: Request) => {
  if (req.method === "GET") return retomar(new URL(req.url));
  if (req.method === "POST") return salvar(req);
  return json({ erro: "Método não permitido" }, 405);
};

export const config: Config = { path: "/api/lead" };
