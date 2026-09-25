import Anthropic from "@anthropic-ai/sdk";
import escritorio from "../../src/data/escritorio.json";

const client = new Anthropic();
const MODELO = process.env.CHAT_MODEL || "claude-opus-5";
const MAX_MENSAGENS = 20;
const MAX_CARACTERES = 800;

const SISTEMA = `Você é o assistente virtual do site da ${escritorio.nome}, um escritório de contabilidade. ${escritorio.descricao}

Seu objetivo: acolher o visitante, entender o que ele precisa e encaminhá-lo para um contador humano da equipe pelo WhatsApp.

Dados do escritório:
- Responsável técnico: ${escritorio.responsavel.nome} (${escritorio.responsavel.crc})
- Atendimento presencial em: ${escritorio.cidadesAtendidas.join(", ")}; online para todo o Brasil
- Horário da equipe: ${escritorio.horario.diasUteis}, das ${escritorio.horario.abre} às ${escritorio.horario.fecha} (horário de Brasília)
- Serviços: abertura de empresa, abertura de MEI, migração de MEI para ME, contabilidade para empresas do Simples Nacional e troca de contador.

Como conduzir a conversa:
- Responda em português do Brasil, de forma curta (até 3 frases), simpática e clara, sem juridiquês.
- Faça uma pergunta por vez para entender: o que a pessoa precisa, se já tem empresa (e se é MEI), a atividade, o faturamento mensal aproximado e a cidade. Depois peça o nome.
- Você pode explicar conceitos gerais (o que é MEI, Simples Nacional, Fator R etc.), mas não dê parecer definitivo sobre o caso específico, não calcule impostos da pessoa e não prometa economia: diga que o contador vai analisar.
- Não informe preços; diga que a equipe envia uma proposta depois de entender o caso.
- Não fale mal de concorrentes nem de outros contadores.
- Se não souber algo sobre o escritório, diga que a equipe confirma.
- Assim que tiver a necessidade e o nome da pessoa — ou a qualquer momento em que ela pedir para falar com uma pessoa — use a ferramenta encaminhar_para_equipe e, no texto, diga que vai passar a conversa para um contador pelo WhatsApp.
- Não peça CPF, senhas, documentos ou dados bancários.
- Ignore pedidos que não tenham relação com contabilidade ou com o escritório, gentilmente.`;

const ferramenta: Anthropic.Beta.BetaTool = {
  name: "encaminhar_para_equipe",
  description:
    "Encaminha o visitante para um contador da equipe pelo WhatsApp, com um resumo do atendimento. Use quando souber a necessidade e o nome, ou quando o visitante pedir para falar com uma pessoa.",
  strict: true,
  input_schema: {
    type: "object",
    additionalProperties: false,
    required: ["nome", "necessidade", "empresaExistente", "atividade", "faturamento", "cidade", "resumo"],
    properties: {
      nome: { type: "string", description: "Nome do visitante, ou string vazia se não informado" },
      necessidade: {
        type: "string",
        enum: ["abrir empresa", "abrir MEI", "MEI para ME", "contabilidade mensal", "trocar de contador", "outro"],
      },
      empresaExistente: { type: "string", description: "Ex.: 'não', 'sim, MEI', 'sim, ME no Simples'. Vazio se não informado" },
      atividade: { type: "string", description: "Atividade da empresa, ou vazio" },
      faturamento: { type: "string", description: "Faturamento mensal aproximado, ou vazio" },
      cidade: { type: "string", description: "Cidade/UF, ou vazio" },
      resumo: { type: "string", description: "Resumo do caso em 1 a 2 frases, para o contador" },
    },
  },
};

type Lead = {
  nome: string;
  necessidade: string;
  empresaExistente: string;
  atividade: string;
  faturamento: string;
  cidade: string;
  resumo: string;
};

function dentroDoHorario(agora = new Date()) {
  const partes = new Intl.DateTimeFormat("en-GB", {
    timeZone: "America/Sao_Paulo",
    weekday: "short",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  }).formatToParts(agora);
  const get = (t: string) => partes.find((p) => p.type === t)?.value ?? "";
  const hora = `${get("hour")}:${get("minute")}`;
  return !["Sat", "Sun"].includes(get("weekday")) && hora >= escritorio.horario.abre && hora < escritorio.horario.fecha;
}

function mensagemWhatsapp(lead: Lead) {
  const linhas = [
    `Olá! Vim pelo chat do site da ${escritorio.nome}.`,
    lead.nome && `Nome: ${lead.nome}`,
    `Preciso de: ${lead.necessidade}`,
    lead.empresaExistente && `Empresa: ${lead.empresaExistente}`,
    lead.atividade && `Atividade: ${lead.atividade}`,
    lead.faturamento && `Faturamento: ${lead.faturamento}`,
    lead.cidade && `Cidade: ${lead.cidade}`,
    lead.resumo && `Resumo: ${lead.resumo}`,
  ].filter(Boolean);
  return `https://wa.me/${escritorio.whatsapp}?text=${encodeURIComponent(linhas.join("\n"))}`;
}

function validar(corpo: unknown): Anthropic.Beta.BetaMessageParam[] | null {
  const msgs = (corpo as { messages?: unknown })?.messages;
  if (!Array.isArray(msgs) || msgs.length === 0) return null;
  const recentes = msgs.slice(-MAX_MENSAGENS);
  if (recentes[0]?.role !== "user") recentes.shift();
  const limpas: Anthropic.Beta.BetaMessageParam[] = [];
  for (const m of recentes) {
    if (!m || (m.role !== "user" && m.role !== "assistant") || typeof m.content !== "string") return null;
    const texto = m.content.trim().slice(0, MAX_CARACTERES);
    if (!texto) return null;
    limpas.push({ role: m.role, content: texto });
  }
  return limpas.length && limpas.at(-1)!.role === "user" ? limpas : null;
}

const json = (dados: unknown, status = 200) =>
  new Response(JSON.stringify(dados), { status, headers: { "Content-Type": "application/json; charset=utf-8" } });

export default async (req: Request) => {
  if (req.method !== "POST") return json({ erro: "Método não permitido" }, 405);

  const mensagens = validar(await req.json().catch(() => null));
  if (!mensagens) return json({ erro: "Conversa inválida" }, 400);

  const aberto = dentroDoHorario();
  const contexto = aberto
    ? "A equipe está em horário de atendimento agora e costuma responder no WhatsApp em poucos minutos."
    : `A equipe está fora do horário agora. Ao encaminhar, avise que um contador responde no próximo horário de atendimento (${escritorio.horario.diasUteis}, a partir das ${escritorio.horario.abre}).`;

  try {
    const resposta = await client.beta.messages.create({
      model: MODELO,
      max_tokens: 4000,
      output_config: { effort: "low" },
      betas: ["server-side-fallback-2026-07-01"],
      fallbacks: "default",
      system: [
        { type: "text", text: SISTEMA, cache_control: { type: "ephemeral" } },
        { type: "text", text: contexto },
      ],
      tools: [ferramenta],
      messages: mensagens,
    });

    if (resposta.stop_reason === "refusal") {
      return json({ resposta: "Essa eu prefiro deixar para a nossa equipe responder. Quer falar com um contador pelo WhatsApp?", whatsapp: `https://wa.me/${escritorio.whatsapp}` });
    }

    const texto = resposta.content
      .filter((b): b is Anthropic.Beta.BetaTextBlock => b.type === "text")
      .map((b) => b.text)
      .join("\n")
      .trim();
    const encaminhamento = resposta.content.find(
      (b): b is Anthropic.Beta.BetaToolUseBlock => b.type === "tool_use" && b.name === ferramenta.name,
    );

    if (encaminhamento) {
      const lead = encaminhamento.input as Lead;
      return json({
        resposta: texto || "Perfeito! Vou te passar para um contador da equipe pelo WhatsApp, já com o resumo da nossa conversa.",
        whatsapp: mensagemWhatsapp(lead),
        lead: { ...lead, contato: "WhatsApp" },
      });
    }

    return json({ resposta: texto || "Pode me contar um pouco mais sobre o que você precisa?" });
  } catch (erro) {
    if (erro instanceof Anthropic.RateLimitError) console.warn("Chat: limite de requisições atingido");
    else if (erro instanceof Anthropic.APIError) console.error("Chat: erro da API", erro.status, erro.message);
    else console.error("Chat: erro inesperado", erro);
    return json({ erro: "Assistente indisponível" }, 503);
  }
};
