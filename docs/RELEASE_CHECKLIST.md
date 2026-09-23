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
- [ ] traduções preparadas.
- [ ] logging em `files/_log/assignmentguard.log`.

## Documentação

- [ ] README com objetivo/instalação/configuração.
- [ ] matriz de compatibilidade.
- [ ] comportamento standalone.
- [ ] regras Behaviors/Escalade.
- [ ] códigos de decisão/log.
- [ ] troubleshooting.
- [ ] CHANGELOG.

## Empacotamento/publicação

- [ ] `plugin.xml` consistente com a versão.
- [ ] nome/diretório corretos.
- [ ] arquivo de licença definido.
- [ ] autor definido.
- [ ] homepage/repositório definido.
- [ ] pacote não contém `.git`, caches, logs, vendor desnecessário ou arquivos locais.
- [ ] instalação limpa testada a partir do pacote.
- [ ] upgrade da versão anterior, quando aplicável.
- [ ] desinstalação testada.
