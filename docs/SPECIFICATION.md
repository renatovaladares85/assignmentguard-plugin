# Especificação funcional consolidada — Assignment Guard v1

## Objetivo

Normalizar preventivamente alterações de grupo responsável de Tickets antes das regras nativas do GLPI, para que o estado considerado pelo `RuleTicketCollection` seja coerente com a atribuição final pretendida.

## Contrato principal

O Assignment Guard pode alterar o input em memória somente quando:

1. existe mudança de grupo;
2. existe política efetiva conhecida;
3. todas as integrações relevantes são suportadas/autorizadas;
4. existe exatamente uma interpretação segura do estado final.

Se qualquer condição falhar: NO-OP.

## Standalone

Sem Behaviors e Escalade ativos:

- Ticket existente;
- exatamente um grupo responsável persistido A;
- exatamente um grupo novo B;
- B diferente de A;
- nenhum segundo grupo novo;
- estado inicial não multigrupo;
- formato de input reconhecido.

O Guard prepara a operação para que o estado final processado seja B.

## Integrado

Behaviors/Escalade podem atuar como **fontes declarativas de política**.

O Guard não executa lógica desses plugins.

Integração ativa significa permissão para consultar sua configuração.

## Fora de escopo v1

- criação de Ticket;
- técnicos;
- solicitantes;
- observadores;
- fornecedores;
- escolha entre vários novos grupos;
- recuperação automática de Ticket já multigrupo;
- cálculo/recalculo de SLA;
- segunda gravação;
- alteração de core;
- alteração de plugin externo;
- tabelas próprias.

## Requisito de auditoria

Toda atualização de Ticket que passe pelo hook gera registro de decisão, inclusive quando o Guard não atua.

## Rollback

Desativar o plugin deve devolver o ambiente ao comportamento anterior.

O Guard não mantém estado persistente de normalização.
