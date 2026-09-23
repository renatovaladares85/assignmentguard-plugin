# Matriz obrigatória de testes

## A. Standalone

| Caso | Persistido | Input conceitual | Esperado |
|---|---|---|---|
| A1 | [A] | A+B | ACTED -> B |
| A2 | [] | B | NO-OP / no existing group |
| A3 | [A] | A | NO-OP / no change |
| A4 | [A] | A+B+C | NO-OP / multiple new |
| A5 | [A,B] | A+B+C | NO-OP / multiple existing |
| A6 | [A] | A removido + B explícito | NO-OP ou already normalized; não duplicar ação |
| A7 | [A] | formato desconhecido | NO-OP |
| A8 | [A] | update sem atores | NO-OP e log obrigatório |

## B. Behaviors 2.7.8

| single_tech_mode | Esperado |
|---|---|
| 0 | `ALLOW_MULTIPLE`, NO-OP |
| 1 | `REPLACE`, atuar no caso simples |
| 2 | `COUPLED_ACTORS`, NO-OP |
| inválido/indisponível | `UNKNOWN`, NO-OP |

Também testar:
- ativo + integração desabilitada => NO-OP;
- versão diferente de 2.7.8 => NO-OP.

## C. Escalade 2.9.18–2.9.22

Testar cada versão suportada para leitura/compatibilidade mínima.

Casos:
- `remove_group=0` => NO-OP;
- `remove_group=1`, sem acoplamentos => atuar no caso simples;
- `remove_group=1`, `remove_tech=1` => NO-OP;
- `remove_group=1`, `remove_requester=1` => NO-OP;
- status pós-escalada não gerenciado pelo core => NO-OP;
- técnico alterado + auto-grupo por técnico => NO-OP;
- categoria alterada + reassign group from category => NO-OP;
- integração desabilitada => NO-OP;
- versão fora da faixa => NO-OP.

## D. Duas integrações

- REPLACE + REPLACE seguro => atuar;
- REPLACE + ALLOW_MULTIPLE => conflito/NO-OP;
- REPLACE + UNKNOWN => NO-OP;
- uma integração habilitada e outra ativa/desabilitada => NO-OP;
- uma versão não suportada => NO-OP.

## E. Preservação

- mudança de técnico simultânea sem provider seguro => NO-OP;
- solicitante/observador/fornecedor não são removidos pelo Guard;
- campos comuns do Ticket permanecem inalterados;
- normalizador altera apenas chaves comprovadamente necessárias.

## F. Logging

Para todos os casos:
- exatamente uma decisão principal por execução do Guard;
- `acted` correto;
- `decision` estável;
- ticket_id presente quando disponível;
- sem conteúdo/nome/email;
- `ERROR_INTERNAL` em exceção simulada;
- logging mínimo ocorre mesmo com diagnóstico detalhado desligado.

## G. Fail-open

Simular exceção depois do snapshot:
- input restaurado byte/estrutura-equivalente;
- exceção não deve interromper atualização nativa;
- `ERROR_INTERNAL` registrado.

## H. Regressão SLA — caso determinante

Preparar regras nativas:

- grupo A => SLA A;
- grupo B => SLA B.

Fluxo:
1. Ticket já está com A e SLA A.
2. Operação adiciona B no cenário de substituição.
3. Uma única gravação.

Esperado:
- grupo final B;
- SLA final B;
- nenhuma segunda gravação pelo Guard;
- Guard não escreve SLA diretamente.

## I. Compatibilidade GLPI

Executar suíte em:
- 10.0.20
- 10.0.21
- 10.0.22
- 10.0.23
- 10.0.24
- 10.0.25
- 10.0.26

## J. PHP/DB

Antes da release:
- lint no PHP 7.4;
- pelo menos um teste funcional em MySQL suportado;
- pelo menos um teste funcional em MariaDB suportado;
- não declarar compatibilidade maior do que efetivamente suportada pelo GLPI.
