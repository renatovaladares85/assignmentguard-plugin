# Assignment Guard — Codex Handoff

Este pacote é o ponto de partida para desenvolvimento do plugin **Assignment Guard** no Codex.

## Como usar

1. Copie `AGENTS.md`, `PROMPT_CODEX.md` e a pasta `docs/` para a raiz do repositório do plugin.
2. Se o repositório já possuir `AGENTS.md`, mescle as instruções sem apagar regras existentes mais restritivas.
3. No Codex, use como tarefa inicial:

   > Leia `AGENTS.md`, `PROMPT_CODEX.md` e todos os arquivos em `docs/`. Execute o plano do `PROMPT_CODEX.md` em fases, respeitando os gates, e implemente o MVP do Assignment Guard.

4. O Codex deve interromper apenas se encontrar:
   - contradição entre a especificação e o código oficial da versão suportada;
   - impossibilidade de garantir comportamento fail-safe;
   - ausência de informação essencial que não possa ser obtida do repositório ou das fontes oficiais;
   - decisão de publicação que exija escolha do proprietário, como licença/autoria.

## Estado do refinamento

O escopo funcional e técnico do MVP está fechado. Os itens de publicação em `docs/OPEN_ITEMS.md` não impedem desenvolvimento e testes, mas impedem uma release pública final enquanto não forem definidos.

## Arquivos principais

- `PROMPT_CODEX.md`: prompt mestre de execução.
- `AGENTS.md`: regras persistentes para agentes/Codex.
- `docs/SPECIFICATION.md`: especificação funcional consolidada.
- `docs/ARCHITECTURE.md`: arquitetura e invariantes.
- `docs/COMPATIBILITY_MATRIX.md`: versões suportadas.
- `docs/INTEGRATION_POLICIES.md`: Behaviors e Escalade.
- `docs/TEST_MATRIX.md`: casos obrigatórios.
- `docs/IMPLEMENTATION_PLAN.md`: fases e gates.
- `docs/ACCEPTANCE_CRITERIA.md`: definição de pronto.
- `docs/SOURCE_MANIFEST.md`: fontes oficiais prioritárias.
- `docs/RELEASE_CHECKLIST.md`: preparação para publicação.
- `docs/OPEN_ITEMS.md`: somente pendências que exigem decisão do proprietário.
