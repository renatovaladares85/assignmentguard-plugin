# Arquitetura — Assignment Guard v1

## Fluxo

```text
Ticket persistido
      +
input recebido
      +
política efetiva
      |
      v
pre_item_update
      |
      v
Assignment Guard
  - snapshot
  - parse
  - policy resolve
  - decision
  - normalize or no-op
  - log
      |
      v
Ticket::prepareInputForUpdate()
      |
      v
transformActorsInput()
      |
      v
RuleTicketCollection
      |
      v
persistência/atores/histórico/notificações/hooks nativos
```

## Ponto de intervenção confirmado

Em GLPI 10.0.20–10.0.26, `PRE_ITEM_UPDATE` ocorre antes de `prepareInputForUpdate()` e, no Ticket, antes das regras.

## Componentes sugeridos

Use nomes coerentes com a base real do repositório; conceitualmente:

- `AssignmentGuardHookHandler`
- `ActorInputParser`
- `AssignmentDelta`
- `PolicyResolver`
- `PolicyProviderInterface`
- `StandalonePolicyProvider`
- `BehaviorsPolicyProvider`
- `EscaladePolicyProvider`
- `GroupInputNormalizer`
- `AssignmentDecision`
- `DecisionLogger`
- `PluginConfig`
- `CompatibilityService`

Evite criar abstrações sem uso real.

## Invariantes

- análise não muta input;
- normalização é construída separadamente;
- aplicação é atômica do ponto de vista do array `$item->input`;
- erro restaura snapshot;
- nenhuma persistência de atores pelo Guard;
- nenhum recálculo de regra/SLA;
- nenhum hook de terceiro é chamado diretamente;
- nenhuma dependência de prioridade de plugin.

## Policy values

Valores conceituais mínimos:

- `REPLACE`
- `ALLOW_MULTIPLE`
- `UNKNOWN`
- `COUPLED_ACTORS`

`CONFLICT` é resultado do resolver ao combinar providers.

## Hook order

`Plugin::doHook()` percorre a coleção registrada. Não há prioridade de hook assumida neste projeto.

Logo:
- não reordenar terceiros;
- não depender de nome alfabético;
- testar coexistência no ambiente;
- decidir apenas com o input visível no momento do hook;
- input que já tenha sido transformado por outro plugin deve ser revalidado pelo parser;
- se o resultado seguro não for demonstrável, NO-OP.
