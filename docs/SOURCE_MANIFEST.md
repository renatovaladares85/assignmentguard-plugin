# Fontes oficiais

## Ordem de prioridade

1. código da instalação real, quando fornecido;
2. tag oficial exata;
3. documentação oficial;
4. DeepWiki apenas como navegação.

## GLPI

Repositório:
`https://github.com/glpi-project/glpi`

Tags suportadas:
- `10.0.20`
- `10.0.21`
- `10.0.22`
- `10.0.23`
- `10.0.24`
- `10.0.25`
- `10.0.26`

Arquivos relevantes:
- `src/CommonDBTM.php`
- `src/Ticket.php`
- `src/CommonITILObject.php`
- `src/Group_Ticket.php`
- `src/Plugin.php`
- `src/Config.php`
- `inc/define.php`
- `composer.json`
- `README.md`

Pontos já verificados:
- PRE_ITEM_UPDATE antes de prepareInputForUpdate;
- Ticket rules depois do hook;
- `Plugin::doHook()` percorre `$PLUGIN_HOOKS`;
- PHP mínimo do core 7.4;
- DB check MySQL 5.7 / MariaDB 10.2 nas extremidades da faixa.

## Skeleton de plugin GLPI 10

`https://github.com/pluginsGLPI/empty/tree/10.0/bugfixes`

Usar como referência para:
- `setup.php`
- `hook.php`
- `composer.json`
- phpunit
- phpstan
- coding standards
- plugin.xml
- estrutura `/src`

Não usar o `main` atual se ele estiver orientado a GLPI 11.

## Behaviors

Repositório:
`https://github.com/InfotelGLPI/behaviors`

Tag:
`2.7.8`

Arquivos:
- `setup.php`
- `inc/config.class.php`
- `inc/group_ticket.class.php`
- `inc/ticket.class.php`

Fatos:
- suporta GLPI >=10.0.5 e <11;
- usa `pre_item_update` para Ticket;
- usa `item_add` para Group_Ticket;
- `single_tech_mode` controla unicidade;
- afterAdd de Group_Ticket remove grupos antigos quando mode != 0;
- mode 2 também remove usuários responsáveis.

## Escalade

Repositório:
`https://github.com/pluginsGLPI/escalade`

Tags homologadas:
- `2.9.18`
- `2.9.19`
- `2.9.20`
- `2.9.21`
- `2.9.22`

Arquivos:
- `setup.php`
- `hook.php`
- `inc/config.class.php`
- `inc/ticket.class.php`

Fatos:
- linha 2.9 suporta GLPI 10;
- `remove_group` representa “Remove old assign group on new group assign”;
- usa `pre_item_update`, `item_update`, `item_add` e `Group_Ticket`;
- pode remover técnicos e alterar outros aspectos de escalada;
- processa remoção de grupos após adição.

Não usar `pluginsGLPI/escalade` 2.10.x para implementação GLPI 10.
Não usar `ddurieux/glpi_escalation` como provider da v1.

## Documentação fornecida

- `glpi-developer-documentation-readthedocs-io-en-master.pdf`
- `glpi-plugins-readthedocs-io-en-latest.pdf`

Pontos relevantes:
- padrão de diretórios;
- `/src` + PSR-4 disponível no GLPI 10;
- hooks;
- configuração;
- logging;
- Escalade;
- padrões de plugin/publicação.

## DeepWiki

`https://deepwiki.com/glpi-project/glpi`

Pode ser usado para localizar classes/fluxos.
Toda conclusão deve ser reconfirmada no código oficial da tag.
