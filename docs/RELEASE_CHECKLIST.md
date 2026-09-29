# Checklist de release

## Código

- [ ] Sem mudanças em core/terceiros.
- [ ] PHP 7.4 lint.
- [ ] Coding standards.
- [ ] PHPStan/análise estática compatível.
- [ ] Testes unitários.
- [ ] Testes funcionais.
- [ ] Matriz GLPI 10.0.20–10.0.26.
- [ ] Teste MySQL.
- [ ] Teste MariaDB.

## Plugin GLPI

- [ ] Diretório `assignmentguard`.
- [ ] `setup.php`.
- [ ] `hook.php`.
- [ ] `csrf_compliant`.
- [ ] requirements GLPI min/max corretos.
- [ ] `/src` PSR-4.
- [ ] configuração sem tabela própria.
- [ ] página de configuração.
- [x] traduções preparadas (`pt_BR` via gettext).
- [ ] logging em `files/_log/assignmentguard.log`.

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
- [ ] nome/diretório corretos.
- [x] arquivo de licença definido (`GPL-3.0-or-later`).
- [x] autor definido (Renato Valadares).
- [x] homepage/repositório definido.
- [ ] pacote não contém `.git`, caches, logs, vendor desnecessário ou arquivos locais.
- [ ] instalação limpa testada a partir do pacote.
- [ ] upgrade da versão anterior, quando aplicável.
- [ ] desinstalação testada.
