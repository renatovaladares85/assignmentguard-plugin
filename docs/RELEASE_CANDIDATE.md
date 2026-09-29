# Release candidate local

## Procedimento reproduzível

No checkout do commit a validar, gere o artefato em um diretório fora do repositório:

```bash
output_dir=$(mktemp -d)
bash tools/build-release-package.sh --output "$output_dir"
bash tools/verify-release-package.sh "$output_dir"/assignmentguard-0.1.0-rc.1.tar.gz
```

O construtor lê os arquivos distribuídos diretamente dos blobs de `HEAD`, usa ordem lexical, timestamp Unix zero, proprietário/grupo numéricos zero, permissões explícitas e `gzip -n`. Assim, o mesmo commit e os mesmos argumentos geram o mesmo nome, tamanho e SHA-256, sem depender da conversão de fim de linha do checkout. A CI cria dois candidates e exige bytes idênticos. A saída registra `NAME`, `SIZE_BYTES` e `SHA256`; esses três valores devem ser copiados para a evidência da validação do candidate, sem publicar o arquivo.

## Conteúdo permitido

O arquivo contém apenas `assignmentguard/` e os arquivos operacionais:

- `setup.php`, `hook.php`, `plugin.xml`, `LICENSE`, `README.md` e `CHANGELOG.md`;
- `src/*.php`, `front/config.form.php` e os catálogos `pt_BR`;
- nenhum `vendor`, `.git`, `tests`, `docs`, cache, log, `var` ou artefato local.

`tools/verify-release-package.sh` confirma a raiz técnica, os arquivos obrigatórios, a chave XML, a versão declarada e o lint dos PHP extraídos.

## Ciclo de validação

A CI cria o candidate, extrai-o em `plugins/assignmentguard`, instala e ativa o plugin em GLPI 10.0.20–10.0.26, executa o smoke same-write/SLA e então desativa, reativa, desativa e desinstala. A matriz de integração executa o smoke homologado Behaviors/Escalade contra o mesmo conteúdo extraído.

Os testes usados pela CI são copiados para uma área temporária fora do candidate: eles exercitam o plugin extraído, mas não transformam testes ou dependências de desenvolvimento em conteúdo distribuído. A instalação, ativação e desinstalação usam o lifecycle nativo do GLPI; o teste unitário cobre que o uninstall remove apenas a configuração `plugin:assignmentguard` e preserva os demais contextos.

## Upgrade

**Não aplicável ao release candidate 0.1.0.** Não há versão pública anterior do Assignment Guard para instalar e atualizar. Esse teste passa a ser obrigatório antes de qualquer release posterior que declare upgrade suportado.
