# Git Portfolio

**Git Portfolio** é uma extensão para phpBB que permite exibir projetos e repositórios de forma organizada dentro do fórum.

A proposta da extensão é ser mais flexível do que uma solução focada apenas em GitHub, permitindo trabalhar com diferentes fontes e também com projetos cadastrados manualmente.

## Principais destaques

- Suporte a **GitHub**
- Suporte a **GitLab**
- Suporte a **instâncias self-hosted**
- Suporte a **repositórios customizados/manuais**
- Página pública de portfólio
- Página individual para cada repositório
- Bloco opcional no índice do fórum
- ACP organizado por seções
- Filtros, paginação e opções visuais
- Destaque e ordenação de projetos
- Vinculação de projeto com fórum, discussão ou suporte

## Diferença em relação ao GitHub Portfolio

Enquanto o **GitHub Portfolio** é focado no ecossistema GitHub, o **Git Portfolio** foi criado com uma abordagem mais aberta.

O objetivo é funcionar como uma vitrine de projetos Git dentro do phpBB, independentemente da plataforma usada. Isso permite reunir no mesmo espaço:

- projetos do GitHub
- projetos do GitLab
- projetos em instâncias próprias
- projetos cadastrados manualmente no ACP

## Recursos atuais

### Providers

- GitHub
- GitLab
- Custom / Manual

### Frontend

- Página pública de portfólio
- Página individual de repositório
- Filtros por provider e linguagem
- Busca
- Modo grade/lista
- Paginação
- Bloco opcional no índice do fórum

### ACP

- Configurações separadas por seção
- Área específica para GitHub
- Área específica para GitLab
- Área específica para repositórios customizados
- Área de exibição
- Área de ferramentas
- Refresh de cache
- Preview dos providers
- Upload de imagem para projetos customizados
- Controle de visibilidade e destaque

## Estrutura geral

O projeto foi organizado para trabalhar com uma arquitetura baseada em providers, facilitando expansão futura e evitando ficar preso a uma única plataforma.

Exemplos de providers:

- GitHub
- GitLab
- Custom

## Instalação

1. Envie a pasta da extensão para:

```text
ext/mundophpbb/gitportfolio