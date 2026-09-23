# Critérios de aceite — MVP

O MVP é aceito quando todos os itens abaixo forem verdadeiros.

## Funcional

- Ticket [A] + novo B, em política de substituição segura, termina em B.
- Regra nativa de SLA vê B na mesma gravação e produz SLA B.
- Nenhuma segunda gravação é disparada pelo Guard.
- Nenhum SLA é calculado/escrito pelo Guard.
- Ambiguidade não altera input.

## Integrações

- Behaviors 2.7.8 mode 1 é utilizável no caso simples.
- Behaviors mode 0 não é sobrescrito.
- Behaviors mode 2 não é normalizado na v1.
- Escalade 2.9.18–2.9.22 respeita `remove_group`.
- Efeitos acoplados configurados no Escalade bloqueiam normalização quando fora do escopo.
- Integração desabilitada/versão não suportada bloqueia atuação.
- Conflito entre providers bloqueia atuação.

## Segurança

- Nenhum core/terceiro alterado.
- Nenhum SQL em tabela de ator.
- Nenhum `RuleTicketCollection` chamado pelo Guard.
- Nenhum `Ticket::update()` corretivo pelo Guard.
- Nenhum `Group_Ticket::delete()` corretivo pelo Guard.
- Exceção restaura input e não bloqueia GLPI.

## Logging

- Toda execução gera decisão.
- Log usa IDs e dados técnicos mínimos.
- Sem conteúdo/nome/email.
- Códigos de decisão são estáveis.

## Compatibilidade

- Plugin metadata bloqueia GLPI fora de 10.0.20–10.0.26.
- Código passa lint PHP 7.4.
- Suíte passa na matriz GLPI definida.
- Sem requisito de DB mais restritivo.

## Operação

- Desativar o plugin restaura comportamento anterior.
- Nenhuma tabela própria.
- Desinstalação limpa somente configuração própria do plugin.
