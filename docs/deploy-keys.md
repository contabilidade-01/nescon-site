# Chaves SSH de deploy autorizadas

Registro das chaves **públicas** usadas para dar acesso somente-leitura de
`git clone`/`git pull` a este repositório privado a partir de serviços de
deploy (ex.: EasyPanel na VPS Hostinger).

> ⚠️ Guardar a chave aqui é só um registro/documentação. Quem realmente
> autoriza o acesso é o GitHub: a mesma chave também precisa estar
> cadastrada em **Settings → Deploy keys** do repositório
> (`github.com/contabilidade-01/nescon-site/settings/keys`), com "Allow
> write access" desmarcado. Sem esse cadastro no GitHub, ter a chave
> listada aqui não dá acesso a nada.

| Chave pública | Origem | Adicionada em |
|---|---|---|
| `ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIE1vK6adEpkfVqq2d/RhvmR/Acz1kci0yJesacrUEFr/ root@0323227ad728` | EasyPanel (container de deploy na VPS Hostinger) | 2026-09-28 |

Chaves são **públicas** por definição — não há problema em versioná-las no
git. A chave **privada** correspondente nunca deve ser copiada para fora
do container do EasyPanel.
