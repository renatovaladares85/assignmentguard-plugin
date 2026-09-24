# Assignment Guard

Plugin GLPI 10 que normaliza, somente em memória e antes das regras nativas, a troca inequívoca de um único grupo responsável em Tickets. O Guard não altera core, plugins terceiros, SLA, tabelas de atores nem dispara segunda atualização.

## Compatibilidade

- GLPI 10.0.20 a 10.0.26;
- PHP com sintaxe compatível com 7.4;
- Behaviors 2.7.8 e Escalade 2.9.18 a 2.9.22, quando a integração é explicitamente autorizada.

## Instalação e configuração

Ainda não há release ou pacote publicado. Para desenvolvimento, clone o repositório diretamente no diretório de chave do plugin:

```bash
cd <GLPI>/plugins
git clone https://github.com/renatovaladares85/assignmentguard-plugin.git assignmentguard
```

Para testar uma branch específica, informe-a no clone:

```bash
git clone -b <branch> https://github.com/renatovaladares85/assignmentguard-plugin.git assignmentguard
```

Confirme que o arquivo está em `<GLPI>/plugins/assignmentguard/setup.php` antes de instalar e ativar pelo GLPI. Em **Configuração > Plugins > Assignment Guard**, habilite somente as integrações que devem ser consultadas como fonte de política. Sem Behaviors/Escalade ativos, a política standalone vem habilitada por padrão.

Cada atualização de Ticket observada gera uma linha JSON em `files/_log/assignmentguard.log`, com IDs e código de decisão, sem dados de conteúdo ou identificação pessoal.

## Desenvolvimento

`composer test` executa a matriz unitária autocontida. A validação funcional GLPI/SLA e a matriz real de versões/DB permanecem necessárias antes de uma release; veja `docs/TEST_MATRIX.md`.

O workflow-base de CI usa GLPI `10.0.x`, PHP 7.4, MySQL 5.7 e MariaDB 10.2. A homologação das versões exatas 10.0.20–10.0.26 permanece pendente; a declaração não substitui evidência de execução concluída.
