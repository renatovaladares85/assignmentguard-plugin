# Assignment Guard

Plugin GLPI 10 que normaliza, somente em memória e antes das regras nativas, a troca inequívoca de um único grupo responsável em Tickets. O Guard não altera core, plugins terceiros, SLA, tabelas de atores nem dispara segunda atualização.

## Compatibilidade

- GLPI 10.0.20 a 10.0.26;
- PHP com sintaxe compatível com 7.4;
- Behaviors 2.7.8 e Escalade 2.9.18 a 2.9.22, quando a integração é explicitamente autorizada.

## Instalação e configuração

Copie o diretório para `plugins/assignmentguard`, instale e ative pelo GLPI. Em **Configuração > Plugins > Assignment Guard**, habilite somente as integrações que devem ser consultadas como fonte de política. Sem Behaviors/Escalade ativos, a política standalone vem habilitada por padrão.

Cada atualização de Ticket observada gera uma linha JSON em `files/_log/assignmentguard.log`, com IDs e código de decisão, sem dados de conteúdo ou identificação pessoal.

## Desenvolvimento

`composer test` executa a matriz unitária autocontida. A validação funcional GLPI/SLA e a matriz real de versões/DB permanecem necessárias antes de uma release; veja `docs/TEST_MATRIX.md`.

O workflow de CI declara GLPI 10.0.20–10.0.26, PHP 7.4, MySQL 5.7 e MariaDB 10.2. A declaração não substitui evidência de execução concluída.
