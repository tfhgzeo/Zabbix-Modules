# Módulos globais do frontend Zabbix

Esta pasta reúne módulos de frontend do Zabbix mantidos neste repositório. Cada módulo fica em seu próprio subdiretório e deve conter seu próprio `manifest.json`, `Module.php` e arquivos de implementação.

## Módulos disponíveis

| Diretório | Descrição | Documentação |
| --- | --- | --- |
| [`fortigate-logs`](modules/fortigate-logs/) | Analyzer para logs de tráfego e eventos de sistema do FortiGate recebidos pelo APM do Zabbix. | [`README do módulo`](modules/fortigate-logs/README.md) |

## Estrutura

```text
modules/
├── README.md
└── <id-do-modulo>/
    ├── manifest.json
    ├── Module.php
    ├── actions/
    └── views/
```

O diretório do módulo deve corresponder ao identificador definido no `manifest.json`. Mantenha documentação específica e dependências dentro da pasta do próprio módulo.

## Instalar os módulos no frontend

Copie o diretório de cada módulo para o diretório `modules` configurado no frontend Zabbix. Defina os caminhos da sua instalação antes de executar. Exemplo:

```bash
REPO_DIR="/path/to/Zabbix-Modules"
ZABBIX_MODULES_DIR="/path/to/zabbix/modules"

sudo cp -a "$REPO_DIR/modules/fortigate-logs" "$ZABBIX_MODULES_DIR/"
```

Substitua os caminhos de exemplo pelos diretórios do repositório e dos módulos do seu frontend. Para instalar outro módulo, substitua `fortigate-logs` pelo nome do diretório correspondente.

Depois da cópia, no frontend Zabbix:

1. Acesse **Administration → General → Modules**.
2. Clique em **Scan directory**.
3. Habilite o módulo desejado.
4. Recarregue a página para atualizar o menu.

Em Docker, prefira manter o diretório do host como bind mount persistente. Se o mount estiver somente leitura, atualize os arquivos na origem do volume; alterações feitas dentro do container podem ser bloqueadas ou perdidas quando ele for recriado.

## Convenções para novos módulos

- Use um subdiretório próprio por módulo.
- Dê ao diretório o mesmo identificador definido no manifesto.
- Inclua um README com requisitos, instalação, permissões e comportamento.
- Não coloque segredos, senhas ou tokens nesta pasta.
- Atualize a tabela **Módulos disponíveis** quando adicionar um novo módulo.
