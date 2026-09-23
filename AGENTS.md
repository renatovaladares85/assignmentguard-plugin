# AGENTS.md — Assignment Guard

## Missão

Desenvolver e manter o plugin GLPI **Assignment Guard** como uma camada preventiva de compatibilidade para alterações de grupo responsável em Tickets.

O plugin deve preparar o input antes do processamento nativo de regras do GLPI, sem substituir o motor de regras, sem recalcular SLA e sem corrigir o Ticket posteriormente.

## Fonte de verdade

Use esta ordem:

1. instruções explícitas do proprietário;
2. `PROMPT_CODEX.md` e `docs/`;
3. código oficial da versão exata do GLPI;
4. código oficial da versão exata do plugin integrado;
5. documentação oficial do GLPI;
6. documentação oficial de plugins;
7. DeepWiki apenas para localizar fluxos; confirme no código oficial.

Quando houver conflito, não escolha silenciosamente. Registre como:
- **Confirmado**
- **Hipótese**
- **Decisão de projeto**
- **Questão em aberto**

## Compatibilidade obrigatória

- GLPI: `10.0.20` até `10.0.26`, inclusive.
- `requirements.glpi.min`: `10.0.20`.
- `requirements.glpi.max`: `10.0.27` porque o máximo do GLPI é exclusivo.
- PHP: não criar requisito mais restritivo que o GLPI suportado; código deve ser sintaticamente compatível com PHP 7.4.
- Banco: não criar requisito mais restritivo que o GLPI suportado.
- Behaviors suportado na v1: `2.7.8`.
- Escalade suportado na v1: `2.9.18` até `2.9.22`, inclusive.
- Escalade `2.10.x`: fora do escopo, linha GLPI 11.
- `ddurieux/glpi_escalation`: legado, não suportado.

## Regras duras

Nunca:

- alterar core do GLPI;
- alterar Behaviors, Escalade ou qualquer plugin de terceiros;
- executar SQL direto em tabelas de atores para corrigir atribuição;
- chamar `RuleTicketCollection` para reproduzir/reexecutar regras;
- recalcular SLA;
- executar segunda atualização de `Ticket` para provocar regras;
- usar `Group_Ticket::delete()` como correção posterior do Assignment Guard;
- persistir antecipadamente a normalização;
- escolher arbitrariamente entre múltiplos grupos novos;
- depender de ordem de hooks sem comprovação;
- usar sintaxe exclusiva de PHP > 7.4;
- criar tabela própria na v1.

## Princípio de segurança

- Fail-safe para intervenção: dúvida, conflito, entrada desconhecida ou integração não suportada => **NO-OP**.
- Fail-open para o GLPI: erro interno do Assignment Guard => restaurar input original, registrar erro e permitir que o GLPI continue.
- Construir input normalizado separadamente e aplicar de uma vez após validação.
- Preservar todos os atores e campos fora do escopo tratado.

## Escopo v1

- `Ticket` existente.
- Atualização.
- Grupo responsável.
- Exatamente um grupo persistido anterior.
- Exatamente um novo grupo inequívoco.
- Sem ambiguidade de atores.
- Técnicos, solicitantes, observadores e fornecedores não são normalizados na v1.

## Logging

Toda passagem do hook monitorado por um Ticket deve gerar uma linha de decisão em `files/_log/assignmentguard.log`, mesmo quando não houver atuação.

Preferir JSON Lines e `Toolbox::logInFile('assignmentguard', ..., true)` ou mecanismo equivalente que preserve o requisito de escrita.

Não registrar conteúdo, nomes, emails ou dados pessoais desnecessários.

## Desenvolvimento

- Seguir o padrão do `pluginsGLPI/empty` na branch `10.0/bugfixes`, não o skeleton de GLPI 11.
- Preferir `/src` com PSR-4 nativo do GLPI 10 (`GlpiPlugin\Assignmentguard`).
- UI deve usar funções de tradução GLPI.
- Configuração própria deve usar `Config`/contexto do GLPI, sem tabela própria.
- Não inventar licença, autor ou homepage.
- Rodar lint, coding standards, análise estática e testes após cada fase relevante.
- Mudanças devem ser pequenas, revisáveis e justificadas.
