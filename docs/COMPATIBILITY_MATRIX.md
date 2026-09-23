# Matriz de compatibilidade

## GLPI

| Versão | Suporte Assignment Guard v1 |
|---|---|
| 10.0.20 | Sim |
| 10.0.21 | Sim |
| 10.0.22 | Sim |
| 10.0.23 | Sim |
| 10.0.24 | Sim |
| 10.0.25 | Sim |
| 10.0.26 | Sim |
| < 10.0.20 | Não |
| >= 10.0.27 | Não homologado / bloquear ativação pela metadata |

No `plugin_version_assignmentguard()`:
- min `10.0.20`;
- max `10.0.27`, exclusivo.

## PHP

A compatibilidade do plugin deve acompanhar a compatibilidade aceita pelo GLPI suportado.

Requisito de desenvolvimento:
- código sintaticamente compatível com PHP 7.4;
- não declarar requisito mínimo mais alto;
- não introduzir limite máximo próprio mais restritivo que o GLPI.

As tags GLPI analisadas definem `GLPI_MIN_PHP = 7.4.0` e limite superior do core exclusivo em `8.6.0`.

O `composer.json` do plugin deve seguir o padrão GLPI 10, normalmente `php >=7.4`, deixando o core do GLPI controlar o teto do runtime.

## Banco

Não criar requisito próprio.

As versões GLPI analisadas aceitam:
- MariaDB >= 10.2;
- MySQL >= 5.7.

Como a v1 não possui tabela própria e não deve emitir SQL para atores, não há justificativa para estreitar essa faixa.

## Behaviors

| Versão | Suporte |
|---|---|
| 2.7.8 | Suportada |
| 2.7.4–2.7.7 | Não homologadas na v1 |
| 3.x | Não; linha GLPI 11 |
| outras | Detectar e NO-OP |

## Escalade

Repositório correto: `pluginsGLPI/escalade`.

| Versão | Suporte |
|---|---|
| 2.9.18 | Sim |
| 2.9.19 | Sim |
| 2.9.20 | Sim |
| 2.9.21 | Sim |
| 2.9.22 | Sim |
| 2.9.0–2.9.17 | Não homologadas na v1 |
| 2.10.x | Não; linha GLPI 11 |

`ddurieux/glpi_escalation` é código legado para GLPI 9.5 e não integra a matriz da v1.

## Regra de versão não suportada

Plugin conhecido ativo + versão fora da matriz => não aplicar standalone no mesmo escopo.

Decisão:
`NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED`.
