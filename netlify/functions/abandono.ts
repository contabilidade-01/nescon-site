import type { Config } from "@netlify/functions";
import { emailEquipeAbandono, emailPessoaAbandono, enviarEmail } from "../lib/email";
import { leads } from "../lib/leads";

/** Tempo sem responder para considerar a conversa abandonada. */
const INATIVIDADE_MIN = 30;
/** Conversas mais antigas que isso não recebem mais o lembrete. */
const JANELA_DIAS = 3;

/** Robô de abandono: roda a cada 10 minutos e dispara o lembrete uma única vez por lead. */
export async function verificarAbandonos(agora = new Date()) {
  const limiteInatividade = agora.getTime() - INATIVIDADE_MIN * 60_000;
  const limiteJanela = agora.getTime() - JANELA_DIAS * 86_400_000;
  const pendentes = (await leads().listar()).filter((l) => {
    const t = Date.parse(l.atualizadoEm);
    return l.status === "em_andamento" && !l.abandonoNotificadoEm && t <= limiteInatividade && t >= limiteJanela;
  });

  for (const lead of pendentes) {
    await Promise.allSettled([enviarEmail(emailPessoaAbandono(lead)), enviarEmail(emailEquipeAbandono(lead))]);
    // Relê o lead para não sobrescrever uma resposta que chegou durante o envio
    const atual = (await leads().obter(lead.id)) ?? lead;
    await leads().salvar({ ...atual, abandonoNotificadoEm: agora.toISOString() });
  }
  return pendentes.length;
}

export default async () => {
  const total = await verificarAbandonos();
  console.log(`Robô de abandono: ${total} lembrete(s) enviado(s)`);
};

export const config: Config = { schedule: "*/10 * * * *" };
