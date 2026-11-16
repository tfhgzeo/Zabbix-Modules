# Módulos globais do frontend Zabbix

Esta pasta reúne módulos de frontend do Zabbix mantidos neste repositório. Cada módulo fica em seu próprio subdiretório e deve conter seu próprio `manifest.json`, `Module.php` e arquivos de implementação.

## Módulos disponíveis

| Diretório | Descrição | Documentação |
| --- | --- | --- |
| [`fortigate-logs`](fortigate-logs/) | Analyzer para logs de tráfego e eventos de sistema do FortiGate recebidos pelo APM do Zabbix. | [`fortigate-logs/README.md`](fortigate-logs/README.md) |

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

Copie o diretório de cada módulo para o diretório `modules` configurado no frontend Zabbix. Por exemplo, para este repositório e a instalação Docker local:

```bash
sudo cp -a /home/gmoreira/Documents/ChatGPT/Fortigate/modules/fortigate-logs \
  /home/gmoreira/Documents/zabbix-docker/zbx_env/usr/share/zabbix/modules/
```

Para instalar outro módulo, substitua `fortigate-logs` pelo nome do diretório correspondente.

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
