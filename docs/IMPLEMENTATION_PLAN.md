# Plano de implementação

## P0 — Inventário
Saída: mapa do repositório, diferenças para skeleton GLPI 10, riscos locais.

Não editar terceiros.

## P1 — Plugin mínimo
Criar metadata, setup/hook, config storage, logger, hook NO-OP.

Critério:
instalar/ativar/desativar/desinstalar sem efeitos no Ticket.

## P2 — Domínio mínimo
Criar decisão, parser e delta sem mutação.

Critério:
testes unitários da matriz standalone.

## P3 — Normalizador standalone
Transformar somente input reconhecido.

Critério:
A->B em memória; nenhum CRUD de ator.

## P4 — Tela de configuração
Mostrar estado das integrações e toggles.

Critério:
config sem tabela própria.

## P5 — Provider Behaviors
Suporte exclusivo 2.7.8.

Critério:
modes 0/1/2 classificados corretamente.

## P6 — Provider Escalade
Suporte 2.9.18–2.9.22.

Critério:
remove_group e bloqueios acoplados classificados.

## P7 — Resolver conjunto
Conflito => NO-OP.

## P8 — Teste funcional SLA
Provar B + SLA B em uma gravação.

## P9 — CI/matriz
GLPI 10.0.20–10.0.26.

## P10 — Release readiness
README, CHANGELOG, plugin.xml/metadata quando licença/autoria estiverem decididas.

## Política de continuação

Codex pode avançar automaticamente entre fases quando o gate anterior passa.

Parar se:
- a fonte oficial contradisser uma decisão;
- um parser exigir heurística;
- um provider exigir executar código corretivo externo;
- o resultado depender de hook order não controlável;
- um teste de regressão demonstrar alteração fora do escopo.
