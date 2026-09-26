/**
 * Roteiro da qualificação em forma de conversa.
 * Usado pelo widget (navegador) e pelas funções da Netlify (servidor) — edite aqui as perguntas.
 */
import escritorio from "../data/escritorio.json";

export type Opcao = { valor: string; rotulo: string };

export type Pergunta = {
  id: string;
  /** Rótulo curto usado nos resumos, e-mails e no painel. */
  rotulo: string;
  texto: string;
  /** Explicação rápida exibida junto da pergunta. */
  ajuda?: string;
  tipo: "opcoes" | "texto";
  opcoes?: Opcao[];
  formato?: "nome" | "email" | "whatsapp";
  placeholder?: string;
  opcional?: boolean;
  max?: number;
};

export type Trilha = { id: string; rotulo: string; intro: string; perguntas: Pergunta[] };

export type Respostas = Record<string, string>;

export type StatusEquipe = "novo" | "contatado" | "cliente" | "descartado";

export type Lead = {
  id: string;
  status: "em_andamento" | "completo";
  statusEquipe: StatusEquipe;
  criadoEm: string;
  atualizadoEm: string;
  respostas: Respostas;
  pagina?: string;
  classificacao?: Classificacao;
  recomendacao?: string;
  concluidoEm?: string;
  abandonoNotificadoEm?: string;
};

export type Classificacao = "quente" | "morno" | "frio";

const urgencia: Opcao[] = [
  { valor: "agora", rotulo: "O quanto antes" },
  { valor: "30-dias", rotulo: "Nos próximos 30 dias" },
  { valor: "3-meses", rotulo: "Em até 3 meses" },
  { valor: "pesquisando", rotulo: "Só pesquisando por enquanto" },
];

const regime: Pergunta = {
  id: "regime",
  rotulo: "Regime atual",
  texto: "Qual é o regime tributário da empresa hoje?",
  ajuda: "Se não souber, tudo bem: conferimos pelo CNPJ.",
  tipo: "opcoes",
  opcoes: [
    { valor: "mei", rotulo: "MEI" },
    { valor: "simples", rotulo: "Simples Nacional" },
    { valor: "presumido", rotulo: "Lucro Presumido" },
    { valor: "real", rotulo: "Lucro Real" },
    { valor: "nao-sei", rotulo: "Não sei" },
  ],
};

const cidade: Pergunta = {
  id: "cidade",
  rotulo: "Cidade",
  texto: "Em qual cidade fica (ou vai ficar) a empresa?",
  ajuda: `Atendemos presencialmente em ${escritorio.cidadesAtendidas.join(", ")} e online em todo o Brasil.`,
  tipo: "texto",
  placeholder: "Cidade - UF",
  max: 80,
};

export const CONTATO: Pergunta[] = [
  {
    id: "nome",
    rotulo: "Nome",
    texto: "Para começar, qual é o seu nome?",
    tipo: "texto",
    formato: "nome",
    placeholder: "Seu nome",
    max: 80,
  },
  {
    id: "email",
    rotulo: "E-mail",
    texto: "Qual é o seu melhor e-mail?",
    ajuda: "Enviamos por lá o resumo do seu atendimento.",
    tipo: "texto",
    formato: "email",
    placeholder: "voce@email.com",
    max: 120,
  },
  {
    id: "whatsapp",
    rotulo: "WhatsApp",
    texto: "E o seu WhatsApp com DDD?",
    ajuda: "É por ele que um contador da equipe vai falar com você. Ao continuar, você autoriza nosso contato sobre esta solicitação.",
    tipo: "texto",
    formato: "whatsapp",
    placeholder: "(11) 91234-5678",
    max: 20,
  },
];

export const TRILHAS: Trilha[] = [
  {
    id: "abrir-empresa",
    rotulo: "Quero abrir uma empresa",
    intro: "Ótimo! Com 7 respostas rápidas já conseguimos indicar o formato ideal (MEI, ME ou EPP) para o seu negócio.",
    perguntas: [
      {
        id: "faturamento",
        rotulo: "Faturamento anual previsto",
        texto: "Quanto você pretende faturar por ano?",
        ajuda: "Isso define o porte: até R$ 81 mil pode ser MEI; até R$ 360 mil é Microempresa (ME); até R$ 4,8 milhões, Empresa de Pequeno Porte (EPP).",
        tipo: "opcoes",
        opcoes: [
          { valor: "ate-81k", rotulo: "Até R$ 81 mil (± R$ 6,7 mil/mês)" },
          { valor: "ate-360k", rotulo: "De R$ 81 mil a R$ 360 mil" },
          { valor: "ate-4-8mi", rotulo: "De R$ 360 mil a R$ 4,8 milhões" },
          { valor: "acima", rotulo: "Acima de R$ 4,8 milhões" },
          { valor: "nao-sei", rotulo: "Ainda não sei" },
        ],
      },
      {
        id: "tipoAtividade",
        rotulo: "Tipo de atividade",
        texto: "Qual é o tipo de atividade?",
        ajuda: "Serviço, comércio e indústria têm impostos e licenças diferentes.",
        tipo: "opcoes",
        opcoes: [
          { valor: "servico", rotulo: "Serviço" },
          { valor: "comercio", rotulo: "Comércio" },
          { valor: "industria", rotulo: "Indústria" },
          { valor: "misto", rotulo: "Serviço e comércio" },
        ],
      },
      {
        id: "atividade",
        rotulo: "Atividade",
        texto: "Em poucas palavras, o que a empresa vai fazer?",
        tipo: "texto",
        placeholder: "Ex.: loja de roupas online, consultoria de TI…",
        max: 200,
      },
      {
        id: "socios",
        rotulo: "Sócios",
        texto: "Vai ter sócios?",
        ajuda: "MEI não pode ter sócio. Sozinho, pode ser MEI ou SLU; com sócios, abrimos uma LTDA.",
        tipo: "opcoes",
        opcoes: [
          { valor: "nao", rotulo: "Não, só eu" },
          { valor: "sim", rotulo: "Sim, terei sócios" },
        ],
      },
      {
        id: "funcionarios",
        rotulo: "Funcionários no 1º ano",
        texto: "Pretende contratar funcionários no primeiro ano?",
        ajuda: "O MEI pode ter no máximo 1 funcionário.",
        tipo: "opcoes",
        opcoes: [
          { valor: "nenhum", rotulo: "Nenhum" },
          { valor: "1", rotulo: "1 funcionário" },
          { valor: "2-5", rotulo: "2 a 5" },
          { valor: "6+", rotulo: "6 ou mais" },
        ],
      },
      { id: "prazo", rotulo: "Prazo", texto: "Quando pretende abrir?", tipo: "opcoes", opcoes: urgencia },
      cidade,
    ],
  },
  {
    id: "mei-crescendo",
    rotulo: "Sou MEI e estou crescendo",
    intro: "Crescer é ótimo! Vamos ver se é hora de passar para Microempresa, antes que o limite vire multa.",
    perguntas: [
      {
        id: "faturamentoAno",
        rotulo: "Faturamento no ano",
        texto: "Quanto o seu MEI já faturou este ano?",
        ajuda: "O limite é R$ 81 mil/ano (proporcional se abriu no meio do ano). Passar até 20% gera imposto sobre o excesso; acima de 20%, o desenquadramento volta a janeiro.",
        tipo: "opcoes",
        opcoes: [
          { valor: "abaixo-60k", rotulo: "Menos de R$ 60 mil" },
          { valor: "60-81k", rotulo: "Entre R$ 60 mil e R$ 81 mil" },
          { valor: "81-97k", rotulo: "Já passei, até R$ 97,2 mil" },
          { valor: "acima-97k", rotulo: "Passei de R$ 97,2 mil" },
          { valor: "nao-sei", rotulo: "Não sei ao certo" },
        ],
      },
      {
        id: "motivo",
        rotulo: "Motivo",
        texto: "O que está te levando a pensar em sair do MEI?",
        tipo: "opcoes",
        opcoes: [
          { valor: "faturamento", rotulo: "Faturamento perto do limite" },
          { valor: "funcionarios", rotulo: "Preciso contratar mais gente" },
          { valor: "socio", rotulo: "Vou ter um sócio" },
          { valor: "atividade", rotulo: "Minha atividade não é permitida no MEI" },
          { valor: "outro", rotulo: "Outro motivo" },
        ],
      },
      { id: "atividade", rotulo: "Atividade", texto: "Qual é a sua atividade?", tipo: "texto", placeholder: "Ex.: salão de beleza, desenvolvedor…", max: 200 },
      { id: "prazo", rotulo: "Prazo", texto: "Para quando você precisa resolver isso?", tipo: "opcoes", opcoes: urgencia },
      cidade,
    ],
  },
  {
    id: "trocar-contador",
    rotulo: "Quero trocar de contador",
    intro: "Perfeito. São 6 perguntas rápidas para prepararmos uma proposta sob medida. A transição com o escritório atual fica com a gente.",
    perguntas: [
      regime,
      {
        id: "faturamentoMes",
        rotulo: "Faturamento mensal",
        texto: "Qual o faturamento médio por mês?",
        tipo: "opcoes",
        opcoes: [
          { valor: "ate-30k", rotulo: "Até R$ 30 mil" },
          { valor: "30-100k", rotulo: "De R$ 30 mil a R$ 100 mil" },
          { valor: "100-400k", rotulo: "De R$ 100 mil a R$ 400 mil" },
          { valor: "acima-400k", rotulo: "Acima de R$ 400 mil" },
        ],
      },
      {
        id: "funcionarios",
        rotulo: "Funcionários",
        texto: "Quantos funcionários a empresa tem?",
        tipo: "opcoes",
        opcoes: [
          { valor: "nenhum", rotulo: "Nenhum" },
          { valor: "1-5", rotulo: "1 a 5" },
          { valor: "6-20", rotulo: "6 a 20" },
          { valor: "20+", rotulo: "Mais de 20" },
        ],
      },
      {
        id: "notas",
        rotulo: "Notas fiscais/mês",
        texto: "Quantas notas fiscais emite e recebe por mês?",
        ajuda: "Ajuda a dimensionar o trabalho e montar uma proposta justa.",
        tipo: "opcoes",
        opcoes: [
          { valor: "ate-20", rotulo: "Até 20" },
          { valor: "21-100", rotulo: "21 a 100" },
          { valor: "101-500", rotulo: "101 a 500" },
          { valor: "500+", rotulo: "Mais de 500" },
        ],
      },
      {
        id: "motivo",
        rotulo: "Motivo da troca",
        texto: "Qual é o principal motivo da troca?",
        tipo: "opcoes",
        opcoes: [
          { valor: "atendimento", rotulo: "Atendimento demorado" },
          { valor: "imposto", rotulo: "Acho que pago imposto demais" },
          { valor: "erros", rotulo: "Erros, multas ou atrasos" },
          { valor: "preco", rotulo: "Preço" },
          { valor: "outro", rotulo: "Outro motivo" },
        ],
      },
      {
        id: "urgencia",
        rotulo: "Urgência",
        texto: "Qual é a urgência?",
        ajuda: "A troca costuma levar cerca de 30 dias, com uma data de corte para nenhuma obrigação ficar descoberta.",
        tipo: "opcoes",
        opcoes: [
          { valor: "agora", rotulo: "Quero trocar já" },
          { valor: "30-dias", rotulo: "No próximo mês" },
          { valor: "pesquisando", rotulo: "Estou avaliando" },
        ],
      },
    ],
  },
  {
    id: "regularizar",
    rotulo: "Tenho empresa e preciso de ajuda",
    intro: "Certo! Três perguntas e já direcionamos para o contador certo.",
    perguntas: [
      regime,
      {
        id: "situacao",
        rotulo: "Situação",
        texto: "Como está a situação da empresa?",
        tipo: "opcoes",
        opcoes: [
          { valor: "em-dia", rotulo: "Em dia, quero um contador" },
          { valor: "pendencias", rotulo: "Tenho pendências ou débitos" },
          { valor: "encerrar", rotulo: "Está parada / quero encerrar" },
          { valor: "nao-sei", rotulo: "Não sei" },
        ],
      },
      { id: "necessidade", rotulo: "Necessidade", texto: "Conte rapidamente do que você precisa.", tipo: "texto", placeholder: "Ex.: tenho DAS atrasados desde 2024…", max: 400 },
      { id: "urgencia", rotulo: "Urgência", texto: "Para quando precisa resolver?", tipo: "opcoes", opcoes: urgencia },
    ],
  },
  {
    id: "outro",
    rotulo: "Outro assunto",
    intro: "Sem problema.",
    perguntas: [{ id: "mensagem", rotulo: "Mensagem", texto: "Como podemos ajudar?", tipo: "texto", placeholder: "Escreva sua dúvida", max: 600 }],
  },
];

export const ESCOLHA_TRILHA: Pergunta = {
  id: "trilha",
  rotulo: "Assunto",
  texto: "Prazer! Como podemos ajudar?",
  tipo: "opcoes",
  opcoes: TRILHAS.map((t) => ({ valor: t.id, rotulo: t.rotulo })),
};

export const trilhaPorId = (id?: string) => TRILHAS.find((t) => t.id === id);

/** Sequência completa de perguntas para as respostas atuais. */
export function roteiro(respostas: Respostas): Pergunta[] {
  return [...CONTATO, ESCOLHA_TRILHA, ...(trilhaPorId(respostas.trilha)?.perguntas ?? [])];
}

export const proximaPergunta = (respostas: Respostas) => roteiro(respostas).find((p) => !(p.id in respostas));

export const contatoCompleto = (r: Respostas) => CONTATO.every((p) => Boolean(r[p.id]));

export const rotuloResposta = (p: Pergunta, valor: string) => p.opcoes?.find((o) => o.valor === valor)?.rotulo ?? valor;

export function normalizarWhatsapp(valor: string) {
  let d = valor.replace(/\D/g, "");
  if (d.length === 10 || d.length === 11) d = `55${d}`;
  return /^55\d{10,11}$/.test(d) ? d : null;
}

export function formatarWhatsapp(d: string) {
  const m = d.match(/^55(\d{2})(\d{4,5})(\d{4})$/);
  return m ? `(${m[1]}) ${m[2]}-${m[3]}` : d;
}

/** Valida e normaliza uma resposta. Retorna o valor limpo ou uma mensagem de erro. */
export function validarResposta(p: Pergunta, bruto: unknown): { valor: string } | { erro: string } {
  const valor = typeof bruto === "string" ? bruto.trim() : "";
  if (!valor) return p.opcional ? { valor: "" } : { erro: "Pode responder para continuarmos?" };
  if (p.tipo === "opcoes") return p.opcoes?.some((o) => o.valor === valor) ? { valor } : { erro: "Escolha uma das opções." };
  if (p.max && valor.length > p.max) return { erro: `Use no máximo ${p.max} caracteres.` };
  if (p.formato === "nome" && valor.length < 2) return { erro: "Digite seu nome." };
  if (p.formato === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor)) return { erro: "Esse e-mail parece incompleto. Confere pra mim?" };
  if (p.formato === "whatsapp") {
    const n = normalizarWhatsapp(valor);
    return n ? { valor: n } : { erro: "Digite o número com DDD, ex.: (11) 91234-5678." };
  }
  return { valor };
}

/** Valida um conjunto de respostas recebido do navegador, descartando o que não pertence ao roteiro. */
export function validarRespostas(bruto: unknown): { respostas: Respostas } | { erro: string } {
  if (!bruto || typeof bruto !== "object") return { erro: "Respostas inválidas" };
  const entrada = bruto as Record<string, unknown>;
  const respostas: Respostas = {};
  for (const p of roteiro({ trilha: typeof entrada.trilha === "string" ? entrada.trilha : "" })) {
    if (!(p.id in entrada)) continue;
    const r = validarResposta(p, entrada[p.id]);
    if ("erro" in r) return { erro: `${p.rotulo}: ${r.erro}` };
    respostas[p.id] = r.valor;
  }
  if (!contatoCompleto(respostas)) return { erro: "Contato incompleto" };
  return { respostas };
}

/** Sugestão de porte para quem vai abrir empresa ou está saindo do MEI. */
export function recomendar(r: Respostas): string | undefined {
  if (r.trilha === "abrir-empresa") {
    if (r.faturamento === "ate-81k" && r.socios === "nao" && ["nenhum", "1"].includes(r.funcionarios ?? "")) {
      return "Pelo que você contou, seu negócio pode se encaixar no MEI, se a atividade estiver na lista permitida. O contador vai confirmar isso com você.";
    }
    if (r.faturamento === "ate-81k" || r.faturamento === "ate-360k") {
      const porQue = r.socios === "sim" ? "por ter sócios" : r.faturamento === "ate-360k" ? "pelo faturamento previsto" : "pelo número de funcionários";
      return `Seu perfil indica Microempresa (ME), ${porQue}, provavelmente no Simples Nacional. Vamos simular os impostos antes de abrir.`;
    }
    if (r.faturamento === "ate-4-8mi") return "Seu perfil indica Empresa de Pequeno Porte (EPP). Vale comparar Simples Nacional e Lucro Presumido antes de abrir.";
    if (r.faturamento === "acima") return "Acima de R$ 4,8 milhões a empresa fica fora do Simples. Vamos comparar Lucro Presumido e Lucro Real para você.";
    return "Sem problema não saber o faturamento ainda: o contador vai te ajudar a estimar e escolher o formato certo.";
  }
  if (r.trilha === "mei-crescendo") {
    if (r.faturamentoAno === "acima-97k") return "Atenção: acima de 20% do limite, o desenquadramento volta a janeiro. Quanto antes regularizar, menor o impacto.";
    if (r.faturamentoAno === "81-97k") return "Você passou do limite, mas dentro dos 20%: dá para migrar para ME a partir de janeiro, pagando imposto só sobre o excesso.";
    return "É um bom momento para planejar a migração para ME com calma, sem pressa nem multa.";
  }
  return undefined;
}

/** Temperatura do lead, para a equipe priorizar os contatos. */
export function classificar(r: Respostas): Classificacao {
  let pontos = 0;
  const prazo = r.prazo ?? r.urgencia;
  if (prazo === "agora") pontos += 3;
  else if (prazo === "30-dias") pontos += 2;
  else if (prazo === "3-meses") pontos += 1;
  if (["ate-360k", "ate-4-8mi", "acima"].includes(r.faturamento ?? "")) pontos += 1;
  if (["30-100k", "100-400k", "acima-400k"].includes(r.faturamentoMes ?? "")) pontos += 2;
  if (["81-97k", "acima-97k"].includes(r.faturamentoAno ?? "")) pontos += 2;
  if (r.situacao === "pendencias") pontos += 1;
  if (r.trilha === "trocar-contador") pontos += 1;
  return pontos >= 4 ? "quente" : pontos >= 2 ? "morno" : "frio";
}

/** Linhas "Pergunta: resposta" na ordem do roteiro. */
export function resumo(r: Respostas): { rotulo: string; valor: string }[] {
  return roteiro(r)
    .filter((p) => r[p.id])
    .map((p) => ({ rotulo: p.rotulo, valor: p.formato === "whatsapp" ? formatarWhatsapp(r[p.id]) : rotuloResposta(p, r[p.id]) }));
}

export function dentroDoHorario(agora = new Date()) {
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

export const prazoRetorno = (agora = new Date()) =>
  dentroDoHorario(agora) ? "ainda hoje, em horário comercial" : `no próximo horário de atendimento (${escritorio.horario.diasUteis.toLowerCase()}, a partir das ${escritorio.horario.abre})`;

export const primeiroNome = (nome = "") => nome.trim().split(/\s+/)[0] ?? "";
