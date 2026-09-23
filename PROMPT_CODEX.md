# PROMPT MESTRE — Assignment Guard / GLPI 10

Você é o engenheiro responsável por implementar o plugin GLPI **Assignment Guard**.

Leia primeiro `AGENTS.md` e todos os arquivos em `docs/`. Eles fazem parte obrigatória desta tarefa.

## Objetivo

Entregar o MVP funcional, testado e publicável tecnicamente do Assignment Guard para GLPI `10.0.20` a `10.0.26`, mantendo o GLPI e plugins de terceiros intactos.

O problema central é evitar que regras do Ticket, especialmente regras de SLA baseadas no grupo responsável, sejam avaliadas com um estado transitório de grupos que será posteriormente alterado por Behaviors/Escalade.

A solução é preventiva: normalizar o **input em memória** no `pre_item_update`, antes de `Ticket::prepareInputForUpdate()` e do `RuleTicketCollection`.

## Fatos já confirmados

Nos GLPI `10.0.20`, `10.0.21`, `10.0.22`, `10.0.23`, `10.0.24`, `10.0.25` e `10.0.26`:

1. `CommonDBTM::update()` chama `Plugin::doHook(Hooks::PRE_ITEM_UPDATE, $this)` antes de `$this->prepareInputForUpdate($this->input)`.
2. `Ticket::prepareInputForUpdate()` executa `transformActorsInput()` antes do processamento das regras do Ticket.
3. O processamento usa `RuleTicketCollection` depois do ponto de intervenção.
4. Portanto, `pre_item_update` é um ponto válido para alterar o input antes das regras nesta faixa suportada.
5. `Plugin::doHook()` percorre plugins na ordem existente em `$PLUGIN_HOOKS`; não existe neste projeto uma garantia de prioridade que possa ser presumida.

Não reabra esses fatos sem evidência de que o código presente no repositório diverge da tag oficial.

## Compatibilidade

### GLPI
- mínimo: `10.0.20`
- máximo: `10.0.26`
- metadata: `min = 10.0.20`, `max = 10.0.27` (máximo exclusivo)

### PHP
O plugin deve herdar a compatibilidade do GLPI e ser sintaticamente compatível com PHP 7.4.

Não use:
- `match`;
- enums;
- attributes;
- property promotion;
- union/intersection types;
- `readonly`;
- nullsafe operator;
- `str_contains`, `str_starts_with`, `str_ends_with`;
- qualquer sintaxe ou API que eleve o requisito acima do GLPI suportado.

### Banco
Não imponha requisito próprio mais restritivo. Não crie tabela na v1. Não use SQL direto para atores.

### Integrações oficialmente suportadas
- Behaviors `2.7.8`.
- Escalade `2.9.18` a `2.9.22`.

Versões fora dessas faixas devem ser detectadas, registradas como não suportadas e provocar NO-OP no escopo sobreposto.

## Política standalone

Se **Behaviors e Escalade não estiverem ativos**, o Assignment Guard é a fonte de política de grupos:

- persistido: exatamente `[A]`;
- alteração: exatamente um novo grupo `[B]`;
- sem múltiplos novos grupos;
- sem estado inicial multigrupo;
- sem interpretação desconhecida.

Resultado esperado para o processamento nativo:

`A -> B`

O Assignment Guard deve representar a remoção de A e a permanência de B no input que o GLPI processará.

Não persistir nada diretamente.

## Integrações

### Behaviors 2.7.8

Ler a configuração efetiva sem executar lógica corretiva do Behaviors.

Campo confirmado: `single_tech_mode`.

Semântica para v1:

- `0` = política não substitutiva / múltiplos possíveis => `ALLOW_MULTIPLE` => NO-OP.
- `1` = `Single user and single group`; o `Group_Ticket::afterAdd()` remove outros grupos, sem remover usuários nesse ramo => `REPLACE`, candidato seguro no cenário simples.
- `2` = `Single user or group`; ao adicionar grupo, o Behaviors também remove usuários atribuídos => `COUPLED_ACTORS` => NO-OP na v1.

Se classe/configuração não puder ser lida com segurança => `UNKNOWN` => NO-OP.

### Escalade 2.9.18–2.9.22

Campo principal confirmado: `remove_group`.

- `remove_group = 0` => `ALLOW_MULTIPLE` => NO-OP.
- `remove_group = 1` => `REPLACE`, mas somente se não houver efeitos acoplados fora do escopo.

Trate como acoplamento inseguro na v1 e faça NO-OP quando aplicável:
- `remove_tech = 1`;
- `remove_requester = 1`;
- Escalade configurado para alterar status após escalada (`ticket_last_status` não gerenciado pelo core);
- mesma atualização altera técnico e `use_assign_user_group_modification` pode interferir;
- mesma atualização altera categoria e `reassign_group_from_cat` pode interferir;
- qualquer outra opção confirmada no código da versão exata que mude atores/campos relevantes após a escalada.

`show_history`, histórico visual e criação de task, por si só, não são razão para reimplementar comportamento; apenas preserve o fluxo do Escalade.

Nunca chame métodos do Escalade para executar escalada. O provider apenas lê política.

### Mais de uma integração ativa

- ambas devem estar habilitadas pelo administrador no Assignment Guard;
- ambas devem estar em versão suportada;
- ambas devem produzir política compatível;
- qualquer conflito ou `UNKNOWN` => NO-OP;
- o Assignment Guard nunca decide “qual plugin vence”.

### Plugin conhecido ativo sem integração habilitada

Behaviors/Escalade ativo + integração desabilitada => NO-OP no escopo de grupo.

Não usar fallback standalone nessa situação.

## Configuração administrativa

Criar uma página de configuração seguindo padrões GLPI 10.

Armazenar configuração própria via `Config` do GLPI, contexto do plugin, sem tabela própria.

Defaults recomendados:

- `standalone_group_replacement = true`;
- `integration_behaviors_enabled = false`;
- `integration_escalade_enabled = false`;
- `diagnostic_logging = false`.

A tela deve mostrar para Behaviors e Escalade:
- instalado;
- ativo;
- versão;
- versão suportada;
- integração habilitada no Assignment Guard;
- política detectada, somente leitura;
- estado: OK / AVISO / BLOQUEADO.

O administrador não informa manualmente se o plugin externo está instalado/ativo.

O checkbox significa: “autorizar o Assignment Guard a consultar este plugin como fonte de política”.

## Logging obrigatório

Toda atualização de Ticket que passe pelo hook deve gerar decisão.

Arquivo esperado:
`files/_log/assignmentguard.log`

Formato recomendado: JSON Lines.

Campos mínimos:
- timestamp;
- plugin_version;
- glpi_version;
- ticket_id;
- acted;
- decision;
- policy_source.

Quando houver alteração de grupos, incluir IDs:
- existing_groups;
- input_groups;
- added_groups;
- removed_groups;
- normalized_groups, se aplicável.

Nunca registrar nomes, conteúdo do chamado, emails ou dados desnecessários.

Códigos estáveis mínimos:

- `ACTED_GROUP_REPLACEMENT`
- `NOT_ACTED_NO_GROUP_CHANGE`
- `NOT_ACTED_NO_EXISTING_GROUP`
- `NOT_ACTED_ALREADY_REPLACED`
- `NOT_ACTED_MULTIPLE_EXISTING_GROUPS`
- `NOT_ACTED_MULTIPLE_NEW_GROUPS`
- `NOT_ACTED_NO_NEW_GROUP`
- `NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT`
- `NOT_ACTED_POLICY_ALLOWS_MULTIPLE`
- `NOT_ACTED_POLICY_CONFLICT`
- `NOT_ACTED_INTEGRATION_DISABLED`
- `NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED`
- `NOT_ACTED_INTEGRATION_POLICY_UNKNOWN`
- `NOT_ACTED_COUPLED_ACTORS`
- `NOT_ACTED_UNSUPPORTED_CONTEXT`
- `ERROR_INTERNAL`

## Tratamento de erro

No hook:

1. copie/snapshot o input original;
2. analise sem mutar;
3. resolva a política;
4. construa um novo input separado;
5. valide pós-condições;
6. aplique a alteração de uma vez.

Se ocorrer `Throwable`:
- restaure exatamente o input original;
- registre `ERROR_INTERNAL`;
- não relance a exceção por padrão;
- permita que o GLPI continue com o comportamento nativo.

## Parser de atores

Não presuma que `_actors` é a única forma de entrada.

Inspecione o código exato do GLPI 10 suportado e implemente apenas formatos comprovados.

O parser deve produzir conceitualmente:

- grupos persistidos;
- grupos explicitamente adicionados;
- grupos explicitamente removidos;
- grupo novo inequívoco;
- indicador de formato reconhecido.

Entrada parcial/desconhecida => NO-OP.

Não altere técnicos, solicitantes, observadores ou fornecedores.

## Arquitetura mínima

Evite framework interno excessivo. Uma separação aceitável é:

- Hook/orquestração;
- parser/delta de atores;
- resolver de política;
- provider standalone;
- provider Behaviors;
- provider Escalade;
- normalizador de input;
- logger de decisão;
- serviço de configuração/compatibilidade.

Use `/src` e namespace `GlpiPlugin\Assignmentguard` se o repositório ainda não possuir convenção diferente válida.

## Ordem de implementação

### Fase 0 — Inventário
- inspecione o repositório;
- identifique se já existe skeleton/código;
- preserve padrões válidos existentes;
- confirme que não há arquivos de core/terceiros dentro do escopo de edição;
- registre plano curto.

### Fase 1 — Skeleton + hook seguro
Baseie-se em `pluginsGLPI/empty`, branch `10.0/bugfixes`.

Crie/ajuste:
- `setup.php`;
- `hook.php`;
- `composer.json`;
- estrutura `/src`;
- `front/config.form.php` quando aplicável;
- PHPUnit/config equivalente do skeleton GLPI 10;
- phpstan/coding-standard compatíveis;
- README/CHANGELOG iniciais.

Nesta fase, o hook ainda pode ser NO-OP, mas o logging obrigatório deve funcionar.

Gate:
- plugin instala/ativa/desativa/desinstala;
- nenhum Ticket é alterado;
- log é emitido;
- PHP 7.4 lint passa.

### Fase 2 — Parser + standalone
Implemente parser conservador e normalização standalone.

Gate:
- `[A] + B` inequívoco => normalização;
- `[] + B`, `[A] + B+C`, `[A,B] + C`, input desconhecido => NO-OP;
- input original restaurado em erro;
- atores não tratados preservados.

### Fase 3 — Configuração + compatibilidade
Implemente detecção, versão, toggles e estados.

Gate:
- plugin externo ativo não autorizado => NO-OP;
- versão não suportada => NO-OP;
- nenhum plugin externo ativo => standalone.

### Fase 4 — Providers
Implemente Behaviors 2.7.8 e Escalade 2.9.18–2.9.22 conforme regras acima.

Não modifique nem faça monkey patch de terceiros.

### Fase 5 — Integração com regras/SLA
Crie teste funcional que reproduza:

- Ticket começa com grupo A e SLA A;
- regra de negócio associa SLA ao grupo;
- nova atribuição inequívoca para B;
- depois de uma única gravação: grupo B e SLA B.

Não calcule SLA no teste por código do Assignment Guard; valide o resultado produzido pelo GLPI.

### Fase 6 — Matriz de compatibilidade e CI
Valide GLPI:
- 10.0.20
- 10.0.21
- 10.0.22
- 10.0.23
- 10.0.24
- 10.0.25
- 10.0.26

Garanta sintaxe PHP 7.4.

Se CI completa em todas as combinações PHP/DB for impraticável, mantenha:
- matriz GLPI obrigatória;
- pelo menos um ambiente MySQL e um MariaDB antes de release;
- lint no PHP mínimo;
- documentação explícita do que foi realmente testado.

### Fase 7 — Documentação e release readiness
Atualize README, CHANGELOG, documentação de configuração, matriz suportada e checklist de release.

Não invente licença, autor ou homepage. Se ainda não definidos, marque release pública como bloqueada apenas por metadata.

## Testes obrigatórios

Leia `docs/TEST_MATRIX.md`. Não reduza a cobertura sem justificar.

Testes devem provar decisão e não apenas caminho de código.

O logger deve ser testável sem depender exclusivamente do filesystem real.

## Não fazer

Não:
- editar GLPI;
- editar Behaviors/Escalade;
- criar patch de terceiro;
- chamar `Ticket::update()` dentro do Guard para corrigir resultado;
- chamar `RuleTicketCollection`;
- remover grupo por `Group_Ticket::delete()` dentro do Guard;
- escrever diretamente em `glpi_groups_tickets`;
- escrever SLA;
- criar transação própria ao redor do GLPI;
- tentar corrigir todos os formatos de ator por heurística;
- expandir v1 para técnicos;
- suportar versões externas não homologadas por “parecerem iguais”.

## Formato do relatório final do Codex

Ao terminar, reporte:

1. resumo do que foi implementado;
2. arquivos principais alterados;
3. decisões confirmadas no código;
4. testes executados e resultados;
5. matriz de versões realmente testada;
6. logs/exemplos de decisão;
7. riscos ou gaps remanescentes;
8. itens de publicação pendentes;
9. qualquer diferença entre a especificação e o comportamento real encontrado.

Não declare compatibilidade com versão que não tenha sido explicitamente coberta pela matriz definida.
