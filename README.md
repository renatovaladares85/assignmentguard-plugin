# Assignment Guard

Plugin GLPI 10 que normaliza, somente em memória e antes das regras nativas, a troca inequívoca de um único grupo responsável em Tickets. O Guard não altera core, plugins terceiros, SLA, tabelas de atores nem dispara segunda atualização.

## Compatibilidade

- GLPI 10.0.20 a 10.0.26;
- PHP com sintaxe compatível com 7.4;
- MySQL 5.7 e MariaDB 10.2 na matriz de CI;
- Behaviors 2.7.8 e Escalade 2.9.18 a 2.9.22, quando a integração é explicitamente autorizada.

GLPI fora dessa faixa e integrações externas fora das versões listadas não são suportados na v1. A matriz detalhada está em [docs/COMPATIBILITY_MATRIX.md](docs/COMPATIBILITY_MATRIX.md).

## Instalação e configuração

A última versão pública é a [`0.1.0`](https://github.com/renatovaladares85/assignmentguard-plugin/releases/tag/0.1.0). O hotfix `0.1.1` está preparado como candidate e só deve ser tratado como release pública após a publicação do respectivo artefato. Para instalar uma release publicada, baixe o arquivo oficial correspondente e extraia-o diretamente em `<GLPI>/plugins`:

```bash
tar -xzf assignmentguard-<versao>.tar.gz -C <GLPI>/plugins
```

Confirme que o arquivo está em `<GLPI>/plugins/assignmentguard/setup.php` antes de instalar e ativar pelo GLPI. Em **Configuração > Plugins > Assignment Guard**, habilite somente as integrações que devem ser consultadas como fonte de política. Sem Behaviors/Escalade ativos, a política standalone vem habilitada por padrão.

Para desenvolvimento, clone o repositório diretamente no diretório de chave do plugin:

```bash
cd <GLPI>/plugins
git clone https://github.com/renatovaladares85/assignmentguard-plugin.git assignmentguard
```

Para testar uma branch específica, informe-a no clone:

```bash
git clone -b <branch> https://github.com/renatovaladares85/assignmentguard-plugin.git assignmentguard
```

Para validar uma futura branch ou release candidate local, gere o pacote em um diretório temporário e extraia-o diretamente em `<GLPI>/plugins`. O arquivo já contém a raiz técnica `assignmentguard/` e não deve ser extraído dentro de outro diretório com esse nome:

```bash
output_dir=$(mktemp -d)
bash tools/build-release-package.sh --output "$output_dir"
bash tools/verify-release-package.sh "$output_dir"/assignmentguard-0.1.1-rc.1.tar.gz
tar -xzf "$output_dir"/assignmentguard-0.1.1-rc.1.tar.gz -C <GLPI>/plugins
```

O procedimento, a lista de conteúdo e os campos de integridade do candidate estão em [docs/RELEASE_CANDIDATE.md](docs/RELEASE_CANDIDATE.md).

Cada atualização de Ticket observada gera uma linha JSON em `files/_log/assignmentguard.log`, com IDs e código de decisão, sem dados de conteúdo ou identificação pessoal.

## Comportamento standalone

Sem Behaviors ou Escalade ativos, a política standalone pode atuar somente quando o Ticket existente possui exatamente um grupo responsável A e o input reconhecido contém exatamente um novo grupo B. Nesse cenário, o Guard prepara o input para que o processamento nativo veja B na mesma gravação.

Múltiplos grupos, atores acoplados, formatos desconhecidos, integração não autorizada, versão não suportada ou qualquer ambiguidade resultam em NO-OP. Em erro interno, o Guard restaura o input original e permite que o GLPI continue o fluxo nativo.

## Integrações externas

Integrações são fontes declarativas de política; o Guard não executa a lógica de Behaviors ou Escalade.

| Integração | Política | Resultado do Guard |
|---|---|---|
| Behaviors 2.7.8, `single_tech_mode = 0` | múltiplos grupos permitidos | NO-OP |
| Behaviors 2.7.8, `single_tech_mode = 1` | substituição no caso simples | pode normalizar |
| Behaviors 2.7.8, `single_tech_mode = 2` | altera usuários responsáveis | NO-OP |
| Escalade 2.9.18–2.9.22, `remove_group = 0` | preserva grupos | NO-OP |
| Escalade 2.9.18–2.9.22, `remove_group = 1` sem efeitos acoplados | substituição | pode normalizar |

No Escalade, remoção de técnico/solicitante, alteração de status, ou autoatribuição relacionada a técnico ou categoria bloqueiam a atuação na v1. Se duas integrações estiverem ativas, ambas precisam ser autorizadas, suportadas e concordar na política; conflito também é NO-OP. Consulte [docs/INTEGRATION_POLICIES.md](docs/INTEGRATION_POLICIES.md) para o detalhamento confirmado.

## Decisões, log e troubleshooting

Toda passagem do hook monitorado de um Ticket gera JSON Lines em `files/_log/assignmentguard.log`, usando apenas dados técnicos mínimos. Códigos importantes incluem:

- `ACTED_GROUP_REPLACEMENT`: substituição inequívoca normalizada;
- `NOT_ACTED_NO_GROUP_CHANGE`, `NOT_ACTED_NO_EXISTING_GROUP`, `NOT_ACTED_MULTIPLE_EXISTING_GROUPS` e `NOT_ACTED_MULTIPLE_NEW_GROUPS`: cenário sem atuação segura;
- `NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT` ou `NOT_ACTED_UNSUPPORTED_CONTEXT`: formato ou contexto não comprovado;
- `NOT_ACTED_INTEGRATION_DISABLED`, `NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED`, `NOT_ACTED_POLICY_CONFLICT` e `NOT_ACTED_COUPLED_ACTORS`: política externa impede atuação;
- `ERROR_INTERNAL`: input restaurado; o GLPI segue o comportamento nativo.

Se o grupo não for normalizado, consulte o campo `decision` no log. NO-OP é o resultado esperado quando a segurança não pode ser demonstrada. Para integrações ativas, confirme sua autorização na tela do Guard. Uma integração ativa mas desabilitada não recebe fallback standalone; uma versão externa fora da matriz deve permanecer em NO-OP.

## Desenvolvimento

`composer test` executa a matriz unitária autocontida. A CI já validou a instalação limpa pelo pacote, o lifecycle, o cenário same-write/SLA e a matriz real de versões/DB. Veja `docs/TEST_MATRIX.md`.

O workflow de CI executa metadata Composer, testes, lint PHP 7.4, PHP-CS-Fixer e PHPStan, além da matriz GLPI 10.0.20–10.0.26, MySQL 5.7, MariaDB 10.2 e integrações homologadas. Veja `docs/TEST_MATRIX.md` e `docs/RELEASE_CHECKLIST.md` para os limites da validação.

## Metadados públicos

- Autor: Renato Valadares
- Repositório: https://github.com/renatovaladares85/assignmentguard-plugin
- Candidate preparado: `0.1.1`
- Última versão pública: `0.1.0`
- Licença: [GPL-3.0-or-later](LICENSE)
