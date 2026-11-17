<?php

/** @var CView $this */
/** @var array $data */

$this->addJsFile('class.calendar.js');
$this->addJsFile('gtlc.js');
$is_system = ($data['log_type'] ?? 'traffic') === 'system';
$action_name = $data['action_name'] ?? ($is_system ? 'fortigate.system' : 'fortigate.traffic');

$severity_options = [
	['label' => _('Unspecified'), 'value' => 'unspecified'],
	['label' => _('Trace'), 'value' => 'trace'],
	['label' => _('Debug'), 'value' => 'debug'],
	['label' => _('Info'), 'value' => 'info'],
	['label' => _('Warning'), 'value' => 'warning'],
	['label' => _('Error'), 'value' => 'error'],
	['label' => _('Fatal'), 'value' => 'fatal']
];

$filter_left = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
	->addItem([
		new CLabel(_('Host'), 'host'),
		new CFormField((new CTextBox('host', $data['host'], false, 64))
			->setAttribute('placeholder', _('Zabbix host or FortiGate device name')))
	])
	->addItem([
		new CLabel(_('Serial number'), 'serial'),
		new CFormField((new CTextBox('serial', $data['serial'], false, 64))
			->setAttribute('placeholder', _('FortiGate serial (devid)')))
	])
	;
if (!$is_system) {
	$filter_left
		->addItem([new CLabel(_('Service'), 'service'), new CFormField(new CTextBox('service', $data['service'], false, 64))])
		->addItem([new CLabel(_('Destination'), 'destination'), new CFormField((new CTextBox('destination', $data['destination'], false, 64))
			->setAttribute('placeholder', _('IP address or port')))]);
}

$filter_right = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
	->addItem([
		new CLabel(_('Source'), 'source'),
		new CFormField((new CTextBox('source', $data['source'], false, 64))
			->setAttribute('placeholder', $is_system ? _('IP address or interface') : _('IP address or port')))
	])
	->addItem([
		new CLabel(_('Severity'), 'severity'),
		new CFormField((new CCheckBoxList('severity'))
			->setOptions($severity_options)
			->setChecked($data['severity'])
			->setColumns(3)
			->setVertical()
			->setAttribute('data-field-type', 'array')
			->setAttribute('data-field-name', 'severity'))
	])
	;
if (!$is_system) {
	$filter_right->addItem([
		new CLabel(_('Action'), 'log_action'),
		new CFormField(new CTextBox('log_action', $data['action'], false, 64))
	]);
}
$filter_right->addItem([
		new CLabel(_('Contains'), 'search'),
		new CFormField(new CTextBox('search', $data['search'], false, 200))
	]);

$filter_fields = (new CDiv([$filter_left, $filter_right]))
	->addClass(ZBX_STYLE_GRID_COLUMNS)
	->addClass(ZBX_STYLE_GRID_COLUMNS_2);

$filter = (new CFilter())
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', $action_name))
	->setProfile($data['timeline']['profileIdx'], $data['timeline']['profileIdx2'])
	->setActiveTab($data['active_tab'])
	->addTimeSelector($data['timeline']['from'], $data['timeline']['to'], ZBX_DATE_TIME)
	->addFilterTab(_('Filter'), [$filter_fields])
	->addVar('action', $action_name);

$table_headers = $is_system ? [
		_('Time'), _('FortiGate host'), _('FortiGate serial'), _('Severity'), _('Event'), _('Interface'), _('Event details')
	] : [
		_('Time'), _('FortiGate host'), _('FortiGate serial'), _('Severity'), _('Type / subtype'), _('Action / event'),
		_('Source / interface'), _('Destination'), _('Service'), _('Event details')
	];
$table = (new CTableInfo($is_system ? _('No FortiGate system logs found for this period.') : _('No FortiGate traffic logs found for this period.')))
	->setId('fgt-logs-table')
	->setHeader($table_headers);

$pager_url = (new CUrl('zabbix.php'))
	->setArgument('action', $action_name)
	->setArgument('from', $data['timeline']['from'])
	->setArgument('to', $data['timeline']['to'])
	->setArgument('profileIdx', $data['timeline']['profileIdx'])
	->setArgument('profileIdx2', $data['timeline']['profileIdx2'])
	->setArgument('host', $data['host'])
	->setArgument('serial', $data['serial'])
	->setArgument('service', $data['service'])
	->setArgument('destination', $data['destination'])
	->setArgument('source', $data['source'])
	->setArgument('log_action', $data['action'])
	->setArgument('severity', $data['severity'])
	->setArgument('search', $data['search']);
$page_logs = $data['logs'];
$paging = CPagerHelper::paginate($data['page'], $page_logs, ZBX_SORT_DOWN, $pager_url);
$page_logs = array_values($page_logs);

$detail_records = [];
foreach ($page_logs as $log_index => $log) {
	$fields = $log['fields'];
	// FortiGate eventtime is epoch nanoseconds. Fall back to the OpenTelemetry
	// timestamp, then let Zabbix format it in the current user's time zone.
	$timestamp = (string) ($fields['eventtime'] ?? $log['timestamp']);
	if (ctype_digit($timestamp)) {
		$seconds = (int) (strlen($timestamp) > 10 ? substr($timestamp, 0, 10) : $timestamp);
	}
	else {
		$parsed_timestamp = strtotime($timestamp);
		$seconds = $parsed_timestamp === false ? 0 : $parsed_timestamp;
	}
	$time = $seconds > 0 ? zbx_date2str('Y-m-d H:i:s', $seconds) : '';
	$type = trim(($fields['type'] ?? '').' / '.($fields['subtype'] ?? ''), ' /');
	$severity = $log['severity'] !== '' ? $log['severity'] : ($fields['level'] ?? '');
	$event_message = $fields['msg'] ?? '';
	if ($event_message === ($fields['logdesc'] ?? null)) {
		$event_message = '';
	}
	$groups = $is_system ? [
		_('General') => [
			_('Absolute Date/Time') => $time,
			_('Device') => $fields['devname'] ?? '',
			_('Serial Number') => $fields['devid'] ?? '',
			_('VDOM') => $fields['vd'] ?? '',
			_('Log Description') => $fields['logdesc'] ?? ''
		],
		_('Source') => [
			_('Interface') => $fields['interface'] ?? '',
			_('User') => $fields['user'] ?? ''
		],
		_('Action') => [
			_('Action') => $fields['action'] ?? ''
		],
		_('Security') => [
			_('Level') => $severity
		],
		_('Event') => [
			_('Config Path') => $fields['cfgpath'] ?? '',
			_('Config Object') => $fields['cfgobj'] ?? '',
			_('Config Attributes') => $fields['cfgattr'] ?? '',
			_('Total') => $fields['total'] ?? '',
			_('Used') => $fields['used'] ?? '',
			_('Message') => $event_message,
			_('Reason') => $fields['reason'] ?? ''
		],
		_('Other') => [
			_('Log ID') => $fields['logid'] ?? '',
			_('Type') => $fields['type'] ?? '',
			_('Sub Type') => $fields['subtype'] ?? '',
			_('Timezone') => $fields['tz'] ?? '',
			_('Syslog priority') => $fields['syslog.priority'] ?? ''
		]
	] : [
		_('General') => [
			_('Absolute Date/Time') => $time,
			_('Device') => $fields['devname'] ?? '',
			_('Serial Number') => $fields['devid'] ?? '',
			_('VDOM') => $fields['vd'] ?? '',
			_('Log Description') => $type
		],
		_('Source') => [
			_('User') => $fields['user'] ?? '',
			_('Source IP') => $fields['srcip'] ?? '',
			_('Source Port') => $fields['srcport'] ?? '',
			_('Source Interface') => $fields['srcintf'] ?? '',
			_('Source Country') => $fields['srccountry'] ?? ''
		],
		_('Action') => [
			_('Action') => $fields['action'] ?? '',
			_('Service') => $fields['service'] ?? '',
			_('Policy') => $fields['policyname'] ?? '',
			_('Policy ID') => $fields['policyid'] ?? ''
		],
		_('Security') => [
			_('Level') => $severity,
			_('Application') => $fields['app'] ?? '',
			_('Application Category') => $fields['appcat'] ?? ''
		],
		_('Event') => [
			_('Destination IP') => $fields['dstip'] ?? '',
			_('Destination Port') => $fields['dstport'] ?? '',
			_('Destination Interface') => $fields['dstintf'] ?? '',
			_('Destination Country') => $fields['dstcountry'] ?? '',
			_('Protocol') => $fields['proto'] ?? '',
			_('Duration') => $fields['duration'] ?? '',
			_('Bytes sent') => $fields['sentbyte'] ?? '',
			_('Bytes received') => $fields['rcvdbyte'] ?? '',
			_('Packets sent') => $fields['sentpkt'] ?? '',
			_('Packets received') => $fields['rcvdpkt'] ?? '',
			_('Session ID') => $fields['sessionid'] ?? ''
		],
		_('Other') => [
			_('Log ID') => $fields['logid'] ?? '',
			_('Type') => $fields['type'] ?? '',
			_('Sub Type') => $fields['subtype'] ?? '',
			_('Timezone') => $fields['tz'] ?? '',
			_('NAT') => $fields['trandisp'] ?? '',
			_('Translated IP') => $fields['transip'] ?? '',
			_('SD-WAN Rule') => $fields['vwlname'] ?? '',
			_('Syslog priority') => $fields['syslog.priority'] ?? ''
		]
	];
	foreach ($groups as $group_name => &$group_fields) {
		$group_fields = array_filter($group_fields, static fn($value) => $value !== '');
		if (!$group_fields) {
			unset($groups[$group_name]);
		}
	}
	unset($group_fields);
	$detail_records[$log_index] = [
		'groups' => $groups
	];
	$source = trim(($fields['srcip'] ?? '').(isset($fields['srcport']) ? ':'.$fields['srcport'] : ''), ':');
	if ($source === '') {
		$source = $fields['srcintf'] ?? $fields['interface'] ?? '';
	}
	$interface = $fields['srcintf'] ?? $fields['interface'] ?? '';
	$destination = trim(($fields['dstip'] ?? '').(isset($fields['dstport']) ? ':'.$fields['dstport'] : ''), ':');
	$action = $fields['action'] ?? '';
	$service = $fields['service'] ?? $fields['app'] ?? '';
	$event_details = $fields['logdesc'] ?? '';
	$message = $fields['msg'] ?? '';
	if ($message !== '' && $message !== $event_details) {
		$event_details .= ($event_details !== '' ? ' · ' : '').$message;
	}
	$context_fields = ['total', 'used', 'duration', 'sentbyte', 'rcvdbyte', 'policyname', 'user', 'reason'];
	foreach ($context_fields as $context_field) {
		if (isset($fields[$context_field]) && $fields[$context_field] !== '') {
			$event_details .= ($event_details !== '' ? ' · ' : '').$context_field.'='.$fields[$context_field];
		}
	}
	$severity_level = strtolower(trim($severity));
	$severity_number = (int) $log['severity_number'];
	if ($severity_number >= 21 || in_array($severity_level, ['fatal', 'emergency', 'alert'], true)) {
		$severity_class = 'fatal';
	}
	elseif ($severity_number >= 17 || in_array($severity_level, ['error', 'critical'], true)) {
		$severity_class = 'error';
	}
	elseif ($severity_number >= 13 || in_array($severity_level, ['warn', 'warning'], true)) {
		$severity_class = 'warning';
	}
	elseif ($severity_number >= 9 || in_array($severity_level, ['info', 'informational', 'notice', 'notification'], true)) {
		$severity_class = 'info';
	}
	elseif ($severity_number >= 5 || $severity_level === 'debug') {
		$severity_class = 'debug';
	}
	elseif ($severity_number >= 1 || $severity_level === 'trace') {
		$severity_class = 'trace';
	}
	else {
		$severity_class = 'unspecified';
	}
	$severity_badge = $severity !== ''
		? (new CTag('span', true, $severity))->addClass('fgt-apm-severity')->addClass('fgt-apm-severity-'.$severity_class)
		: '';
	$fortigate_serial = $fields['devid'] ?? $fields['serial'] ?? $fields['serial_number'] ?? '';
	$fortigate_host = [];
	foreach ($log['fortigate_hosts'] as $host) {
		if ($fortigate_host) {
			$fortigate_host[] = ', ';
		}
		$fortigate_host[] = (new CLinkAction($host['name']))
			->setMenuPopup(CMenuPopupHelper::getHost($host['hostid']));
	}
	if (!$fortigate_host) {
		$fortigate_host = $fortigate_serial !== '' ? _('Not linked') : '';
	}

	$row_cells = $is_system ? [
		$time,
		$fortigate_host,
		$fortigate_serial,
		$severity_badge,
		$fields['logdesc'] ?? $type,
		$interface !== '' ? $interface : '—',
		$event_details !== '' ? $event_details : '—'
	] : [
		$time,
		$fortigate_host,
		$fortigate_serial,
		$severity_badge,
		$type,
		$action !== '' ? $action : '—',
		$source !== '' ? $source : '—',
		$destination !== '' ? $destination : '—',
		$service !== '' ? $service : '—',
		$event_details !== '' ? $event_details : '—'
	];
	$table_row = (new CRow($row_cells))
		->addClass('fgt-log-row')
		->setAttribute('data-log-index', (string) $log_index)
		->setAttribute('tabindex', '0')
		->setAttribute('aria-label', _('Open log details'));
	$table->addRow($table_row);
}

$page = (new CHtmlPage())->setTitle($is_system ? _('FortiGate system logs') : _('FortiGate traffic logs'));
$page->addItem((new CDiv($filter))->setId('fortigate_logs'));

$details_panel = (new CDiv())
	->setId('fgt-log-overlay')
	->addClass('fgt-log-overlay')
	->setAttribute('hidden', 'hidden')
	->addItem((new CDiv())
		->addClass('fgt-log-panel')
		->addItem((new CDiv([
			new CTag('strong', true, _('Log details')),
			(new CButtonIcon(ZBX_ICON_CLOSE))
				->setId('fgt-log-close')
				->setAttribute('aria-label', _('Close'))
				->setAttribute('title', _('Close'))
		]))->addClass('fgt-log-panel-header'))
		->addItem((new CDiv())->setId('fgt-log-groups')->addClass('fgt-log-groups')));
$page->addItem($details_panel);

$page->addItem(new CTag('style', true, '
	.fgt-log-overlay[hidden] { display: none; }
	.fgt-log-overlay:not([hidden]) { position: fixed; inset: 0; z-index: 1200; display: flex; justify-content: flex-end; background: rgba(0,0,0,.38); }
	.fgt-log-panel { box-sizing: border-box; width: min(580px, 92vw); height: 100%; overflow-y: auto; padding: 16px; background: var(--body-bg-color, #292929); color: var(--font-color, #eee); box-shadow: -4px 0 16px rgba(0,0,0,.35); }
	.fgt-log-panel-header { display: flex; align-items: center; justify-content: space-between; position: sticky; top: -16px; z-index: 1; padding: 12px 0; background: var(--body-bg-color, #292929); border-bottom: 1px solid rgba(128,128,128,.3); }
	#fgt-log-close { box-sizing: border-box; width: 32px; min-width: 32px; height: 32px; padding: 0; }
	#fgt-log-close::before { display: inline-block; width: 12px; height: 12px; font-size: 12px !important; line-height: 12px; }
	.fgt-log-section { border-bottom: 1px solid rgba(128,128,128,.25); }
	.fgt-log-group-toggle { display: flex; align-items: center; gap: 8px; width: 100%; min-height: 34px; padding: 6px 8px; border: 0; background: rgba(128,128,128,.16); color: inherit; text-align: left; cursor: pointer; }
	.fgt-log-group-icon { display: inline-flex; width: 13px; height: 13px; align-items: center; justify-content: center; border-radius: 2px; background: rgba(128,128,128,.5); color: var(--body-bg-color, #292929); font-size: 12px; font-weight: 700; line-height: 1; }
	.fgt-log-group-body { padding: 8px 6px; }
	.fgt-log-row { cursor: pointer; }
	.fgt-log-row:hover { background: rgba(128,128,128,.12); }
	.fgt-apm-severity { display: inline-block; padding: 2px 9px; border-radius: 12px; font-size: .9em; font-weight: 600; line-height: 1.4; white-space: nowrap; }
	.fgt-apm-severity-unspecified, .fgt-apm-severity-trace, .fgt-apm-severity-debug { background: #667085; color: #fff; }
	.fgt-apm-severity-info { background: #2f80c9; color: #fff; }
	.fgt-apm-severity-warning { background: #f2c744; color: #242424; }
	.fgt-apm-severity-error { background: #e55353; color: #fff; }
	.fgt-apm-severity-fatal { background: #9b1c31; color: #fff; }
	.fgt-log-pair { display: grid; grid-template-columns: minmax(145px, 44%) minmax(0, 1fr); gap: 12px; padding: 5px 8px; }
	.fgt-log-key { color: var(--font-alt-color, #999); }
	.fgt-log-value { text-align: left; overflow-wrap: anywhere; white-space: pre-wrap; }
	@media (max-width: 700px) { .fgt-log-panel { width: 100%; } }
'));

if (!empty($data['error'])) {
	$page->addItem(new CWarning($data['error']));
}
if (!empty($data['host_lookup_error'])) {
	$page->addItem(new CWarning(_('Could not look up FortiGate hosts. Check host read permissions for this Zabbix user.')));
}
$page->addItem($table);
$page->addItem($paging);

// Register the native Zabbix time selector so its quick-range controls update
// the page and submit the selected from/to interval.
$time_control_data = [
	'timeline' => $data['timeline'],
	'active_tab' => $data['active_tab']
];
$time_control_object = [
	'id' => 'timeline_1',
	'domid' => 'fortigate_logs',
	'loadSBox' => 0,
	'loadImage' => 0,
	'dynamic' => 0,
	'mainObject' => 1
];
zbx_add_post_js(
	'timeControl.addObject("fortigate_logs", '.zbx_jsvalue($time_control_data).', '.zbx_jsvalue($time_control_object).');'
);
zbx_add_post_js('timeControl.processObjects();');

$log_details_js = '(function() {
	const records = '.zbx_jsvalue($detail_records).';
	const table = document.getElementById("fgt-logs-table");
	const overlay = document.getElementById("fgt-log-overlay");
	const closeButton = document.getElementById("fgt-log-close");
	if (!table || !overlay || !closeButton) return;

	const fillPairs = function(container, entries) {
		container.replaceChildren();
		Object.entries(entries).forEach(function(entry) {
			const row = document.createElement("div");
			row.className = "fgt-log-pair";
			const key = document.createElement("span");
			key.className = "fgt-log-key";
			key.textContent = entry[0];
			const value = document.createElement("span");
			value.className = "fgt-log-value";
			const raw = entry[1];
			value.textContent = raw === null || typeof raw === "undefined" ? "" :
				(typeof raw === "object" ? JSON.stringify(raw) : String(raw));
			row.append(key, value);
			container.append(row);
		});
	};

	const openRecord = function(index) {
			const record = records[index];
			if (!record) return;
			const groups = document.getElementById("fgt-log-groups");
			groups.replaceChildren();
			Object.entries(record.groups).forEach(function(entry) {
				const section = document.createElement("section");
				section.className = "fgt-log-section";
				const toggle = document.createElement("button");
				toggle.type = "button";
				toggle.className = "fgt-log-group-toggle";
				toggle.setAttribute("aria-expanded", "true");
				const icon = document.createElement("span");
				icon.className = "fgt-log-group-icon";
				icon.textContent = "−";
				const title = document.createElement("span");
				title.textContent = entry[0];
				toggle.append(icon, title);
				const content = document.createElement("div");
				content.className = "fgt-log-group-body";
				fillPairs(content, entry[1]);
				toggle.addEventListener("click", function() {
					const expanded = toggle.getAttribute("aria-expanded") === "true";
					toggle.setAttribute("aria-expanded", expanded ? "false" : "true");
					content.hidden = expanded;
					icon.textContent = expanded ? "+" : "−";
				});
				section.append(toggle, content);
				groups.append(section);
			});
			overlay.hidden = false;
			closeButton.focus();
	};
	table.addEventListener("click", function(event) {
		const row = event.target.closest("tr.fgt-log-row");
		if (!row || event.target.closest("a, button")) return;
		openRecord(row.dataset.logIndex);
	});
	table.addEventListener("keydown", function(event) {
		const row = event.target.closest("tr.fgt-log-row");
		if (row && (event.key === "Enter" || event.key === " ")) {
			event.preventDefault();
			openRecord(row.dataset.logIndex);
		}
	});

	const close = function() { overlay.hidden = true; };
	closeButton.addEventListener("click", close);
	overlay.addEventListener("click", function(event) { if (event.target === overlay) close(); });
	document.addEventListener("keydown", function(event) { if (event.key === "Escape" && !overlay.hidden) close(); });
})();';
zbx_add_post_js($log_details_js);

$page->show();
