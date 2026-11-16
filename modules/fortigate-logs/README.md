# FortiGate Log Analyzer para Zabbix

Módulo de frontend para pesquisar e analisar logs FortiGate recebidos pelo APM do Zabbix. Ele usa a sessão e as permissões do usuário conectado e consulta os registros pela API `apmlog.get`. O módulo não acessa o ClickHouse diretamente nem armazena credenciais.

## Requisitos

- Zabbix 8.0 com suporte a APM habilitado.
- Uma fonte de dados de telemetria/APM configurada e acessível no Zabbix.
- Logs FortiGate já ingeridos no APM, normalmente por um OpenTelemetry Collector configurado para receber syslog e encaminhar os registros ao provedor de telemetria do Zabbix.
- Permissão do usuário para consultar logs APM e ler os hosts associados.

O módulo apresenta logs que contenham `devid`, `serial` ou `serial_number` no corpo. A correlação com hosts é feita pelo `devid` (serial do equipamento).

## Instalação

1. Copie a pasta `fortigate-logs` para o diretório de módulos do frontend. Exemplo:

   ```text
   /usr/share/zabbix/modules/fortigate-logs
   ```

2. No frontend, abra **Administration → General → Modules**.
3. Clique em **Scan directory** e habilite **FortiGate Logs**.
4. Recarregue a página do Zabbix. O menu **Analyzer** aparecerá com as telas **Traffic Logs** e **Event System Logs**.

Para uma instalação Docker, monte o diretório do módulo como volume persistente no serviço do frontend. Se o volume estiver como somente leitura, atualize os arquivos no diretório de origem do bind mount; copiar para dentro do container não funcionará.

## Associar logs a hosts do Zabbix

No host do Zabbix que representa o FortiGate, adicione a tag abaixo:

```text
Tag: fortigate_serial
Value: <serial do FortiGate>
```

O valor deve corresponder ao `devid` do log. A comparação ignora maiúsculas/minúsculas e espaços no início ou no fim. Se a serial não corresponder a nenhum host, o módulo mostra **Not linked**. Se houver mais de um host com a mesma serial, todos serão listados.

Ao clicar no nome do host, o Zabbix abre o menu contextual padrão do host.

## Telas

### Traffic Logs

Mostra registros `type=traffic`, incluindo origem, destino, serviço, ação, política e resumo do evento. Filtros disponíveis: host, serial, serviço, destino, origem, severidade, ação, texto e período.

### Event System Logs

Mostra registros `type=event` e `subtype=system`, incluindo interface, descrição do evento e campos presentes, como `total`, `used`, `user` e `reason`. Filtros disponíveis: host, serial, interface/origem, severidade, texto e período.

As duas telas usam o seletor nativo de período do Zabbix. A tabela exibe a severidade como badge e abre os detalhes ao clicar em qualquer parte da linha.

## Detalhes do log

O painel organiza os campos em grupos expansíveis:

- **General**: horário, equipamento, serial, VDOM e descrição.
- **Source**: usuário, endereço e interface de origem ou interface do evento.
- **Action**: ação, serviço e política, quando presentes.
- **Security**: nível/severidade e informações da aplicação, quando aplicável.
- **Event**: destino, protocolo, duração, contadores, mensagem e dados de configuração.
- **Other**: ID, tipo/subtipo, timezone e dados auxiliares relevantes.

Para eventos de configuração, o painel mostra `Config Path` (`cfgpath`), `Config Object` (`cfgobj`) e `Config Attributes` (`cfgattr`) quando esses campos existem no registro.

Campos vazios são omitidos. A data/hora é formatada pelo frontend do Zabbix no fuso horário do usuário.

## Diagnóstico

Se uma tela não mostrar registros, verifique:

1. Se os logs aparecem na tela de APM do Zabbix no mesmo período.
2. Se o intervalo selecionado inclui o horário dos registros.
3. Se os registros contêm `devid`, `serial` ou `serial_number` no corpo.
4. Se o usuário tem permissão para consultar logs APM e visualizar os hosts.
5. Se o módulo está habilitado em **Administration → General → Modules**.

O módulo limita a busca a 5.000 registros recentes retornados pelo APM antes de aplicar filtros específicos do FortiGate. Em ambientes com volume muito alto de logs, resultados mais antigos podem não aparecer dentro desse limite.

## Arquivos

```text
fortigate-logs/
├── actions/
│   ├── FortiGateLogs.php
│   ├── FortiGateSystemLogs.php
│   └── FortiGateTrafficLogs.php
├── views/
│   └── fortigate.logs.php
├── manifest.json
└── Module.php
```
