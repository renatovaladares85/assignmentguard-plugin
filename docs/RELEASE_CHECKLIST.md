# Checklist de release

## Código

- [x] Sem mudanças em core/terceiros.
- [x] PHP 7.4 lint.
- [x] Coding standards.
- [x] PHPStan/análise estática compatível.
- [x] Testes unitários.
- [x] Testes funcionais.
- [x] Matriz GLPI 10.0.20–10.0.26.
- [x] Teste MySQL.
- [x] Teste MariaDB.

## Plugin GLPI

- [x] Diretório `assignmentguard`.
- [x] `setup.php`.
- [x] `hook.php`.
- [x] `csrf_compliant`.
- [x] requirements GLPI min/max corretos.
- [x] `/src` PSR-4.
- [x] configuração sem tabela própria.
- [x] página de configuração.
- [x] traduções preparadas (`pt_BR` via gettext).
- [x] logging em `files/_log/assignmentguard.log`.

## Documentação

- [x] README com objetivo/instalação/configuração.
- [x] matriz de compatibilidade.
- [x] comportamento standalone.
- [x] regras Behaviors/Escalade.
- [x] códigos de decisão/log.
- [x] troubleshooting.
- [x] CHANGELOG.

## Empacotamento/publicação

- [x] `plugin.xml` consistente com a versão e os metadados públicos.
- [x] nome/diretório corretos.
- [x] arquivo de licença definido (`GPL-3.0-or-later`).
- [x] autor definido (Renato Valadares).
- [x] homepage/repositório definido.
- [x] pacote não contém `.git`, caches, logs, vendor desnecessário ou arquivos locais.
- [x] instalação limpa testada a partir do pacote.
- [x] upgrade `0.1.0` -> `0.1.1` validado no banco normal, incluindo preservação da configuração, HTTP/CSRF e same-write/SLA na CI `36667852245`.
- [x] desinstalação testada.

Auditoria final de `0.1.0`: CI pós-merge `36607599278` aprovada em `main@2935d2ca92ce43d76d3b42c5b1602c122b84bd24`. O procedimento do release candidate, incluindo nome, tamanho, hash e ciclo de validação, está em `docs/RELEASE_CANDIDATE.md`.
