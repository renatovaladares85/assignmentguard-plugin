# Registro de decisões de projeto

## D-001 — Solução preventiva
O Guard atua no input antes das regras. Não corrige depois.

## D-002 — Sem core patch
Nenhum arquivo do GLPI é alterado.

## D-003 — Sem patch de terceiros
Behaviors e Escalade permanecem intactos.

## D-004 — Sem SLA próprio
O SLA continua sendo calculado exclusivamente pelo GLPI.

## D-005 — Sem reprocessamento artificial
Não chamar `RuleTicketCollection` nem realizar segundo `Ticket::update()`.

## D-006 — Sem escrita em atores
Não fazer SQL/CRUD corretivo em `Group_Ticket` ou tabelas de atores.

## D-007 — Fail-safe
Ambiguidade => NO-OP.

## D-008 — Fail-open
Erro do Guard não deve bloquear a atualização nativa.

## D-009 — Standalone
Sem Behaviors/Escalade ativos, um único grupo antigo + um único grupo novo inequívoco é substituição.

## D-010 — Integração explícita
Plugin externo ativo só é usado como fonte de política se administrador habilitar sua integração.

## D-011 — Plugin conhecido ativo sem integração
Não usar fallback standalone.

## D-012 — Logging obrigatório
Sempre registrar atuação ou não atuação.

## D-013 — Sem tabelas próprias na v1
Configuração usa `Config` do GLPI.

## D-014 — Compatibilidade GLPI
10.0.20 a 10.0.26.

## D-015 — Compatibilidade Behaviors
2.7.8.

## D-016 — Compatibilidade Escalade
2.9.18 a 2.9.22.

## D-017 — Técnicos fora da normalização v1
Efeitos acoplados a usuários/técnicos bloqueiam atuação quando não for possível manter estado coerente.

## D-018 — pre_item_update confirmado
Validado no código oficial das sete versões suportadas como anterior a `prepareInputForUpdate()` e às regras do Ticket.
