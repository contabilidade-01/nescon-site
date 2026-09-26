import { getStore } from "@netlify/blobs";
import type { Lead } from "../../src/config/qualificacao";

/** Lead como fica guardado: inclui o token secreto que autoriza a pessoa a continuar a conversa. */
export type LeadArmazenado = Lead & { token: string };

export interface ArmazemLeads {
  obter(id: string): Promise<LeadArmazenado | null>;
  salvar(lead: LeadArmazenado): Promise<void>;
  listar(): Promise<LeadArmazenado[]>;
}

function armazemNetlify(): ArmazemLeads {
  const store = getStore({ name: "leads", consistency: "strong" });
  return {
    obter: (id) => store.get(id, { type: "json" }) as Promise<LeadArmazenado | null>,
    salvar: async (lead) => {
      await store.setJSON(lead.id, lead);
    },
    listar: async () => {
      const { blobs } = await store.list();
      const leads = await Promise.all(blobs.map((b) => store.get(b.key, { type: "json" }) as Promise<LeadArmazenado | null>));
      return leads.filter((l): l is LeadArmazenado => l !== null);
    },
  };
}

let armazem: ArmazemLeads | undefined;

/** Nos testes, `definirArmazem` troca o Netlify Blobs por um armazenamento em memória. */
export const definirArmazem = (a: ArmazemLeads) => {
  armazem = a;
};

export const leads = () => (armazem ??= armazemNetlify());

/** Remove o token antes de devolver o lead para o navegador ou o painel. */
export const publico = ({ token: _token, ...lead }: LeadArmazenado): Lead => lead;

export const json = (dados: unknown, status = 200) =>
  new Response(JSON.stringify(dados), {
    status,
    headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" },
  });

export function tokensIguais(a: string, b: string) {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}
