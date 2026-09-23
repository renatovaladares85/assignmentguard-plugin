# Políticas de integração

## Conceito

Provider externo responde “qual política este plugin produzirá?”; ele não executa a política.

## Behaviors 2.7.8

Fonte:
`PluginBehaviorsConfig::getInstance()` / configuração equivalente validada no código oficial.

Campo:
`single_tech_mode`.

### Valores

#### 0 — No

Múltiplos responsáveis não são reduzidos pelo mecanismo de unicidade estudado.

Resultado:
`ALLOW_MULTIPLE`.

Assignment Guard:
NO-OP.

#### 1 — Single user and single group

`PluginBehaviorsGroup_Ticket::afterAdd()` remove outros grupos responsáveis quando um novo grupo é adicionado.

Para uma operação exclusivamente de grupo, sem outra ambiguidade:
`REPLACE`.

Assignment Guard:
pode normalizar `[A] + B -> [B]`.

#### 2 — Single user or group

Ao adicionar novo grupo, além de remover outros grupos, o Behaviors remove usuários responsáveis.

A v1 não normaliza usuários.

Resultado:
`COUPLED_ACTORS`.

Assignment Guard:
NO-OP.

### Falhas

Classe ausente, configuração não legível ou valor inesperado:
`UNKNOWN` => NO-OP.

## Escalade 2.9.18–2.9.22

Configuração principal:
`remove_group`.

### remove_group = 0

Escalade preserva grupos existentes.

Resultado:
`ALLOW_MULTIPLE`.

NO-OP.

### remove_group = 1

Escalade removeria grupos anteriores após a nova atribuição.

Resultado base:
`REPLACE`.

Antes de permitir atuação, verificar efeitos acoplados.

### Bloqueios de v1

Tratar como `COUPLED_ACTORS`/`UNKNOWN` e NO-OP quando a operação puder produzir estado final diferente em campos não normalizados, incluindo:

- `remove_tech = 1`;
- `remove_requester = 1`;
- status após escalada gerenciado pelo Escalade;
- técnico alterado na mesma operação com autoatribuição de grupo por técnico ativa;
- categoria alterada na mesma operação com autoatribuição de grupo por categoria ativa;
- qualquer configuração da versão exata que altere atores relevantes no mesmo fluxo.

### Não bloquear apenas por efeitos históricos

Recursos como histórico visual/task de escalada devem continuar pertencendo ao Escalade. O Guard não os reproduz.

## Combinação

Exemplo seguro:

- Behaviors mode 1 => `REPLACE`
- Escalade remove_group 1, sem acoplamentos => `REPLACE`
- resultado do resolver => `REPLACE`

Exemplo conflito:

- Behaviors mode 0 => `ALLOW_MULTIPLE`
- Escalade remove_group 1 => `REPLACE`
- resultado => `CONFLICT`
- NO-OP

## Autorização

Se o plugin estiver ativo, mas a integração do Guard estiver desabilitada:
- não ler/aplicar política para normalização;
- não fazer fallback standalone;
- NO-OP com `NOT_ACTED_INTEGRATION_DISABLED`.
