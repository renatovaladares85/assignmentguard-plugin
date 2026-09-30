# Release candidate local

## Procedimento reproduzível

No checkout do commit a validar, gere o artefato em um diretório fora do repositório:

```bash
output_dir=$(mktemp -d)
bash tools/build-release-package.sh --output "$output_dir"
bash tools/verify-release-package.sh "$output_dir"/assignmentguard-0.1.1-rc.1.tar.gz
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

## Upgrade 0.1.0 para 0.1.1

O candidate `0.1.1` exige uma regressao de upgrade real em GLPI 10.0.20 e 10.0.26. A CI baixa o artefato publico `assignmentguard-0.1.0.tar.gz`, valida antes o SHA-256 `e87109cd5907c536d83f3e3804ee7d9f3d8d735b6efdfbe2b680b7379f6656aa` e o instala pelo lifecycle nativo do GLPI.

O teste grava uma configuracao nao-default no banco normal, troca o diretorio do plugin pelo candidate, executa o update nativo e confirma a versao `0.1.1` e a preservacao da configuracao. Em seguida, executa a regressao HTTP/CSRF e o smoke same-write/SLA do candidate antes do teardown. Esse fluxo nao usa `glpi_test` para representar o upgrade.
