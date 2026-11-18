<?php

namespace Modules\FortigateLogs\Actions;

use API,
	CController,
	CControllerResponseData,
	CProfile,
	CRangeTimeParser;

class FortiGateLogs extends CController {
	private const FETCH_LIMIT = 5000;
	private const SEVERITY_CATEGORIES = ['unspecified', 'trace', 'debug', 'info', 'warning', 'error', 'fatal'];

	protected function getSelectedLogType(): string {
		return 'traffic';
	}

	protected function matchesSelectedLog(array $fields): bool {
		return ($fields['type'] ?? '') === $this->getSelectedLogType();
	}

	protected function getRouteActionName(): string {
		return $this->getSelectedLogType() === 'system' ? 'fortigate.system' : 'fortigate.traffic';
	}

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'from' => 'string',
			'to' => 'string',
			'host' => 'string',
			'serial' => 'string',
			'service' => 'string',
			'destination' => 'string',
			'source' => 'string',
			'event' => 'string',
			'log_action' => 'string',
			'severity' => 'array',
			'search' => 'string',
			'page' => 'ge 1'
		];

		$ret = $this->validateInput($fields);
		if ($ret && $this->hasInput('severity')) {
			$ret = is_array($this->getInput('severity'))
				&& !array_diff($this->getInput('severity'), self::SEVERITY_CATEGORIES);
		}
		if ($ret) {
			$ret = $this->validateTimeSelectorPeriod();
		}
		if (!$ret) {
			$this->setResponse(new CControllerResponseData([
				'error' => _('Invalid filter values.'),
				'logs' => [],
				'log_type' => $this->getSelectedLogType(),
				'action_name' => $this->getRouteActionName(),
				'timeline' => [
					'from' => 'now-1d',
					'to' => 'now',
				'profileIdx' => 'web.toptriggers.filter',
					'profileIdx2' => 0
				],
				'active_tab' => 1,
				'host' => '',
				'serial' => '',
				'service' => '',
				'destination' => '',
				'source' => '',
				'event' => '',
				'action' => '',
				'severity' => [],
				'search' => '',
				'page' => 1,
				'total' => 0,
				'host_lookup_error' => false
			]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$log_type = $this->getSelectedLogType();
		$is_traffic = $log_type === 'traffic';
		$action_name = $this->getRouteActionName();
		$host = trim($this->getInput('host', ''));
		$serial = trim($this->getInput('serial', ''));
		$service = trim($this->getInput('service', ''));
		$destination = trim($this->getInput('destination', ''));
		$source = trim($this->getInput('source', ''));
		$event = trim($this->getInput('event', ''));
		$action = trim($this->getInput('log_action', ''));
		$severity = $this->getInput('severity', []);
		$search = trim($this->getInput('search', ''));
		$page = max(1, (int) $this->getInput('page', 1));
		$timeselector_options = [
			// Zabbix 8.0 only accepts core profile indexes in its time-selector
			// update controller. Reuse the top-triggers slot for this module.
			'profileIdx' => 'web.toptriggers.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);
		$timeline = getTimeSelectorPeriod($timeselector_options);
		$time_from = $this->parseTimeSelectorValue($timeline['from'], true);
		$time_till = $this->parseTimeSelectorValue($timeline['to'], false);
		$params = [
			'output' => 'extend',
			'time_from' => $time_from,
			'time_till' => $time_till,
			'sortfield' => 'timestamp',
			'sortorder' => 'DESC',
			'limit' => self::FETCH_LIMIT
		];

		if ($search !== '') {
			$params['search'] = ['body' => $search];
		}

		$error = null;
		$previous_error_handler = null;
		$previous_error_handler = set_error_handler(
			static function (int $severity, string $message, string $file, int $line) use (&$previous_error_handler): bool {
				if ($severity === E_DEPRECATED
						&& str_contains($message, 'curl_close() is deprecated')
						&& basename($file) === 'ApmDbClickHouse.php') {
					return true;
				}

				if (is_callable($previous_error_handler)) {
					return (bool) call_user_func($previous_error_handler, $severity, $message, $file, $line);
				}

				return false;
			},
			E_DEPRECATED
		);
		try {
			$logs = API::ApmLog()->get($params);
		}
		catch (\Throwable $e) {
			$logs = [];
			$error = _('Could not retrieve APM logs. Check the user role permissions and telemetry data source.');
		}
		finally {
			restore_error_handler();
		}

		$rows = [];
		$serials = [];
		foreach ($logs as $log) {
			$fields = $this->parseKeyValueLog((string) ($log['body'] ?? ''));
			$device_serial = $fields['devid'] ?? $fields['serial'] ?? $fields['serial_number'] ?? '';
			if ($device_serial !== '') {
				$serials[strtoupper($device_serial)] = true;
			}
		}
		$fortigate_hosts = $this->getFortiGateHosts(array_keys($serials));
		$host_lookup_error = ($fortigate_hosts === null);
		$fortigate_hosts = $fortigate_hosts ?? [];

		foreach ($logs as $log) {
			$body = (string) ($log['body'] ?? '');
			$fields = $this->parseKeyValueLog($body);
			if (!$this->matchesSelectedLog($fields)) {
				continue;
			}
			// FortiGate syslog records include the device identifier (devid).
			if (!isset($fields['devid']) && !isset($fields['serial']) && !isset($fields['serial_number'])) {
				continue;
			}
			if ($serial !== '' && !$this->matchesAny($serial, [
				$fields['devid'] ?? '', $fields['serial'] ?? '', $fields['serial_number'] ?? ''
			])) {
				continue;
			}
			$device_serial = strtoupper($fields['devid'] ?? $fields['serial'] ?? $fields['serial_number'] ?? '');
			$matched_hosts = $fortigate_hosts[$device_serial] ?? [];
			if ($host !== '' && !$this->matchesAny($host, array_merge(
				array_column($matched_hosts, 'name'), [$fields['devname'] ?? '']
			))) {
				continue;
			}
			if ($is_traffic && $service !== '' && !$this->matchesAny($service, [$fields['service'] ?? '', $fields['app'] ?? ''])) {
				continue;
			}
			if ($is_traffic && $destination !== '' && !$this->matchesAny($destination, [
				$fields['dstip'] ?? '', $fields['dstport'] ?? ''
			])) {
				continue;
			}
			if ($is_traffic && $source !== '' && !$this->matchesAny($source, [
				$fields['srcip'] ?? '', $fields['srcport'] ?? '', $fields['srcintf'] ?? '', $fields['interface'] ?? ''
			])) {
				continue;
			}
			$event_column_value = (string) ($fields['logdesc'] ?? trim(
				($fields['type'] ?? '').' / '.($fields['subtype'] ?? ''), ' /'
			));
			if (!$is_traffic && $event !== '' && !$this->matchesAny($event, [$event_column_value])) {
				continue;
			}
			if ($is_traffic && $action !== '' && !$this->matchesAny($action, [$fields['action'] ?? ''])) {
				continue;
			}
			$severity_category = $this->getSeverityCategory(
				(string) ($log['severity_text'] ?? $fields['level'] ?? ''),
				(int) ($log['severity_number'] ?? 0)
			);
			if ($severity && !in_array($severity_category, $severity, true)) {
				continue;
			}
			$rows[] = [
				'timestamp' => (string) ($log['timestamp'] ?? ''),
				'severity' => (string) ($log['severity_text'] ?? ''),
				'severity_number' => (string) ($log['severity_number'] ?? ''),
				'traceid' => (string) ($log['traceid'] ?? ''),
				'spanid' => (string) ($log['spanid'] ?? ''),
				'trace_flags' => (string) ($log['trace_flags'] ?? ''),
				'service_name' => (string) ($log['service_name'] ?? ''),
				'resource_schema_url' => (string) ($log['resource_schema_url'] ?? ''),
				'scope_schema_url' => (string) ($log['scope_schema_url'] ?? ''),
				'resource_attributes' => (array) ($log['resource_attributes'] ?? []),
				'scope_name' => (string) ($log['scope_name'] ?? ''),
				'scope_version' => (string) ($log['scope_version'] ?? ''),
				'scope_attributes' => (array) ($log['scope_attributes'] ?? []),
				'log_attributes' => (array) ($log['log_attributes'] ?? []),
				'event_name' => (string) ($log['event_name'] ?? ''),
				'body' => $body,
				'fields' => $fields,
				'fortigate_hosts' => $matched_hosts
			];
		}
		$total = count($rows);

		$this->setResponse(new CControllerResponseData([
			'logs' => $rows,
			'log_type' => $log_type,
			'action_name' => $action_name,
			'error' => $error,
			'timeline' => $timeline,
			'active_tab' => CProfile::get($timeselector_options['profileIdx'].'.active', 1),
			'host' => $host,
			'serial' => $serial,
			'service' => $service,
			'destination' => $destination,
			'source' => $source,
			'event' => $event,
			'action' => $action,
			'severity' => $severity,
			'search' => $search,
			'page' => $page,
			'total' => $total,
			'fetch_limit' => self::FETCH_LIMIT,
			'host_lookup_error' => $host_lookup_error
		]));
	}

	private function parseTimeSelectorValue(string $value, bool $is_from): int {
		$parser = new CRangeTimeParser();
		$parser->parse($value);

		return $parser->getDateTime($is_from)->getTimestamp();
	}

	private function getFortiGateHosts(array $serials): ?array {
		if (!$serials) {
			return [];
		}

		try {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'selectTags' => ['tag', 'value'],
				'tags' => [[
					'tag' => 'fortigate_serial',
					'value' => '',
					'operator' => 4
				]]
			]);
		}
		catch (\Throwable $e) {
			return null;
		}

		$hosts_by_serial = [];
		$wanted = array_fill_keys($serials, true);
		foreach ($hosts as $host) {
			foreach ($host['tags'] ?? [] as $tag) {
				if (strtolower($tag['tag'] ?? '') !== 'fortigate_serial') {
					continue;
				}

				$serial = strtoupper(trim($tag['value'] ?? ''));
				if ($serial !== '' && isset($wanted[$serial])) {
					$hosts_by_serial[$serial][] = [
						'hostid' => $host['hostid'],
						'name' => $host['name']
					];
				}
			}
		}

		foreach ($hosts_by_serial as &$matched_hosts) {
			$unique_hosts = [];
			foreach ($matched_hosts as $host) {
				$unique_hosts[$host['hostid']] = $host;
			}
			$matched_hosts = array_values($unique_hosts);
		}
		unset($matched_hosts);

		return $hosts_by_serial;
	}

	private function matchesAny(string $needle, array $values): bool {
		foreach ($values as $value) {
			if ($value !== '' && stripos((string) $value, $needle) !== false) {
				return true;
			}
		}

		return false;
	}

	private function getSeverityCategory(string $severity_text, int $severity_number): string {
		$severity_text = strtolower(trim($severity_text));

		if ($severity_number >= 21 || in_array($severity_text, ['fatal', 'emergency', 'alert'], true)) {
			return 'fatal';
		}
		if ($severity_number >= 17 || in_array($severity_text, ['error', 'critical'], true)) {
			return 'error';
		}
		if ($severity_number >= 13 || in_array($severity_text, ['warn', 'warning'], true)) {
			return 'warning';
		}
		if ($severity_number >= 9 || in_array($severity_text, ['info', 'informational', 'notice', 'notification'], true)) {
			return 'info';
		}
		if ($severity_number >= 5 || $severity_text === 'debug') {
			return 'debug';
		}
		if ($severity_number >= 1 || $severity_text === 'trace') {
			return 'trace';
		}

		return 'unspecified';
	}

	private function parseKeyValueLog(string $body): array {
		preg_match_all('/(?:^|\\s)([A-Za-z0-9_.-]+)=("(?:\\\\.|[^"\\\\])*"|\\S+)/', $body, $matches, PREG_SET_ORDER);
		$fields = [];
		if (preg_match('/^<([0-9]{1,3})>/', $body, $priority_match)) {
			$fields['syslog.priority'] = $priority_match[1];
		}
		foreach ($matches as $match) {
			$value = $match[2];
			if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
				$value = stripcslashes(substr($value, 1, -1));
			}
			$fields[$match[1]] = $value;
		}

		return $fields;
	}
}
