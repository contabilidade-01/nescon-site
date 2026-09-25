import escritorio from "../data/escritorio.json";

export { escritorio };

export const siteName = escritorio.nome;

export const nav = [
  { href: "/abrir-empresa", label: "Abrir empresa" },
  { href: "/abrir-mei", label: "MEI" },
  { href: "/contabilidade-simples-nacional", label: "Simples Nacional" },
  { href: "/trocar-de-contador", label: "Trocar de contador" },
  { href: "/blog", label: "Conteúdos" },
  { href: "/sobre", label: "Sobre" },
];

export function whatsappLink(mensagem = `Olá! Vim pelo site da ${escritorio.nome} e gostaria de falar com um contador.`) {
  return `https://wa.me/${escritorio.whatsapp}?text=${encodeURIComponent(mensagem)}`;
}

export function enderecoCompleto() {
  const e = escritorio.endereco;
  return `${e.rua}, ${e.bairro}, ${e.cidade} - ${e.uf}, ${e.cep}`;
}

/** Dados estruturados do escritório (schema.org), reutilizados na home e nas páginas de cidade. */
export function schemaEscritorio(extra: Record<string, unknown> = {}) {
  const e = escritorio;
  const sameAs = Object.values(e.redes).filter(Boolean);
  return {
    "@context": "https://schema.org",
    "@type": "AccountingService",
    "@id": `${e.url}/#escritorio`,
    name: e.nome,
    description: e.descricao,
    url: e.url,
    telephone: e.telefone,
    email: e.email,
    foundingDate: String(e.fundacao),
    image: `${e.url}/og-default.png`,
    logo: `${e.url}/favicon.svg`,
    address: {
      "@type": "PostalAddress",
      streetAddress: e.endereco.rua,
      addressLocality: e.endereco.cidade,
      addressRegion: e.endereco.uf,
      postalCode: e.endereco.cep,
      addressCountry: "BR",
    },
    ...(e.geo.lat ? { geo: { "@type": "GeoCoordinates", latitude: e.geo.lat, longitude: e.geo.lng } } : {}),
    areaServed: [...e.cidadesAtendidas.map((c) => ({ "@type": "City", name: c })), { "@type": "Country", name: "Brasil" }],
    openingHoursSpecification: {
      "@type": "OpeningHoursSpecification",
      dayOfWeek: ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
      opens: e.horario.abre,
      closes: e.horario.fecha,
    },
    employee: {
      "@type": "Person",
      name: e.responsavel.nome,
      jobTitle: e.responsavel.cargo,
      identifier: e.responsavel.crc,
    },
    ...(sameAs.length ? { sameAs } : {}),
    ...extra,
  };
}

export function schemaFaq(faq: { pergunta: string; resposta: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: faq.map((f) => ({
      "@type": "Question",
      name: f.pergunta,
      acceptedAnswer: { "@type": "Answer", text: f.resposta },
    })),
  };
}

export function schemaBreadcrumb(itens: { nome: string; url: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: itens.map((it, i) => ({
      "@type": "ListItem",
      position: i + 1,
      name: it.nome,
      item: new URL(it.url, escritorio.url).href,
    })),
  };
}
