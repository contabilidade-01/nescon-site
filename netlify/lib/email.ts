import { Resend } from "resend";
import escritorio from "../../src/data/escritorio.json";
import { formatarWhatsapp, primeiroNome, proximaPergunta, resumo, trilhaPorId, type Respostas } from "../../src/config/qualificacao";
import type { LeadArmazenado } from "./leads";

type Email = { para: string; assunto: string; html: string; responderPara?: string };
export type Enviador = (email: Email) => Promise<boolean>;

const enviarPorResend: Enviador = async ({ para, assunto, html, responderPara }) => {
  const chave = process.env.RESEND_API_KEY;
  if (!chave) {
    console.warn(`E-mail não enviado (RESEND_API_KEY ausente): ${assunto}`);
    return false;
  }
  const { error } = await new Resend(chave).emails.send({
    from: process.env.EMAIL_REMETENTE || `${escritorio.nome} <${escritorio.email}>`,
    to: para,
    subject: assunto,
    html,
    ...(responderPara ? { replyTo: responderPara } : {}),
  });
  if (error) console.error("Falha ao enviar e-mail:", error.message);
  return !error;
};

let enviador: Enviador = enviarPorResend;
export const definirEnviador = (e: Enviador) => {
  enviador = e;
};
export const enviarEmail: Enviador = (email) => enviador(email);

export const emailEquipe = () => process.env.EMAIL_EQUIPE || escritorio.email;
const siteUrl = () => process.env.URL || escritorio.url;

const esc = (s: string) => s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);

const botao = (href: string, texto: string, cor = "#15803d") =>
  `<a href="${esc(href)}" style="display:inline-block;background:${cor};color:#fff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:999px">${esc(texto)}</a>`;

const moldura = (conteudo: string) => `<!doctype html><html lang="pt-BR"><body style="margin:0;background:#faf8f4;font-family:Arial,Helvetica,sans-serif;color:#1e293b">
<div style="max-width:560px;margin:0 auto;padding:24px">
<p style="font-weight:700;color:#10284e;font-size:18px;margin:0 0 16px">${esc(escritorio.nome)}</p>
<div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;line-height:1.55">${conteudo}</div>
<p style="font-size:12px;color:#64748b;margin-top:16px">${esc(escritorio.nome)} · ${esc(escritorio.responsavel.crc)} · ${esc(escritorio.telefone)}</p>
</div></body></html>`;

const tabela = (r: Respostas) =>
  `<table style="width:100%;border-collapse:collapse;font-size:14px">${resumo(r)
    .map((l) => `<tr><td style="padding:6px 8px 6px 0;color:#64748b;vertical-align:top;white-space:nowrap">${esc(l.rotulo)}</td><td style="padding:6px 0">${esc(l.valor)}</td></tr>`)
    .join("")}</table>`;

const linkWhatsappDoLead = (lead: LeadArmazenado) => {
  const r = lead.respostas;
  const msg = `Olá, ${primeiroNome(r.nome)}! Aqui é da ${escritorio.nome}. Recebemos seu contato pelo site sobre "${trilhaPorId(r.trilha)?.rotulo.toLowerCase() ?? "seu atendimento"}".`;
  return `https://wa.me/${r.whatsapp}?text=${encodeURIComponent(msg)}`;
};

export const linkContinuar = (lead: LeadArmazenado) => `${siteUrl()}/?continuar=${lead.id}.${lead.token}`;

const selo = { quente: "🔥 Quente", morno: "🌤️ Morno", frio: "❄️ Frio" } as const;

export function emailEquipeConcluido(lead: LeadArmazenado): Email {
  const r = lead.respostas;
  const assunto = `[${selo[lead.classificacao ?? "frio"]}] Novo lead: ${r.nome} — ${trilhaPorId(r.trilha)?.rotulo ?? "Contato"}`;
  return {
    para: emailEquipe(),
    assunto,
    responderPara: r.email,
    html: moldura(`<p style="margin-top:0"><strong>${esc(r.nome)}</strong> concluiu a qualificação no site.</p>
${lead.recomendacao ? `<p style="background:#f2f5fa;border-radius:10px;padding:10px 12px;font-size:14px">${esc(lead.recomendacao)}</p>` : ""}
${tabela(r)}
<p style="margin:24px 0 8px">${botao(linkWhatsappDoLead(lead), `Chamar ${primeiroNome(r.nome)} no WhatsApp`)}</p>
<p style="font-size:13px"><a href="${esc(`${siteUrl()}/painel`)}">Ver todos os leads no painel</a></p>`),
  };
}

export function emailPessoaConcluido(lead: LeadArmazenado, prazo: string): Email {
  const r = lead.respostas;
  return {
    para: r.email,
    assunto: `Recebemos seu contato, ${primeiroNome(r.nome)}!`,
    responderPara: emailEquipe(),
    html: moldura(`<p style="margin-top:0">Olá, ${esc(primeiroNome(r.nome))}!</p>
<p>Recebemos suas respostas. Um contador da nossa equipe vai te chamar no WhatsApp <strong>${esc(formatarWhatsapp(r.whatsapp))}</strong> ${esc(prazo)}.</p>
${lead.recomendacao ? `<p>${esc(lead.recomendacao)}</p>` : ""}
<p style="font-size:14px;color:#64748b">Resumo do que você nos contou:</p>
${tabela(r)}
<p style="margin-top:24px">Se preferir, fale com a gente agora:</p>
<p>${botao(`https://wa.me/${escritorio.whatsapp}`, "Chamar no WhatsApp")}</p>`),
  };
}

export function emailPessoaAbandono(lead: LeadArmazenado): Email {
  const r = lead.respostas;
  return {
    para: r.email,
    assunto: `${primeiroNome(r.nome)}, faltou pouco para concluir seu atendimento`,
    responderPara: emailEquipe(),
    html: moldura(`<p style="margin-top:0">Olá, ${esc(primeiroNome(r.nome))}!</p>
<p>Vimos que você começou a conversar com a gente no site, mas não terminou. Suas respostas estão salvas: é só continuar de onde parou, leva menos de 1 minuto.</p>
<p style="margin:24px 0">${botao(linkContinuar(lead), "Continuar de onde parei")}</p>
<p>Prefere falar direto com um contador? Responda este e-mail ou chame a gente no <a href="https://wa.me/${escritorio.whatsapp}">WhatsApp</a>.</p>`),
  };
}

export function emailEquipeAbandono(lead: LeadArmazenado): Email {
  const r = lead.respostas;
  const parou = proximaPergunta(r);
  return {
    para: emailEquipe(),
    assunto: `[Abandono] ${r.nome} parou em: ${parou?.rotulo ?? "fim"}`,
    responderPara: r.email,
    html: moldura(`<p style="margin-top:0"><strong>${esc(r.nome)}</strong> deixou o contato mas não concluiu a conversa no site. Já enviamos um e-mail automático com o link para continuar.</p>
<p>Parou na pergunta: <strong>${esc(parou?.texto ?? "—")}</strong></p>
${tabela(r)}
<p style="margin:24px 0 8px">${botao(linkWhatsappDoLead(lead), `Chamar ${primeiroNome(r.nome)} no WhatsApp`)}</p>`),
  };
}
