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
		_('Time'), _('FortiGate host'), _('FortiGate serial'), _('Severity'), _('Event'), _('Message'), _('Event details')
	] : [
		_('Time'), _('FortiGate host'), _('FortiGate serial'), _('Severity'), _('Type / subtype'), _('Action / event'),
		_('Source / interface'), _('Destination'), _('Service'), _('Event details')
	];
$column_keys = $is_system
	? ['time', 'host', 'serial', 'severity', 'event', 'message', 'details']
	: ['time', 'host', 'serial', 'severity', 'type', 'action', 'source', 'destination', 'service', 'details'];
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
		$message !== '' ? $message : '—',
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
$page->addItem((new CDiv([
	(new CButtonIcon(ZBX_ICON_COG_FILLED))
		->setId('fgt-columns-button')
		->setAttribute('aria-label', _('Customize table columns'))
		->setAttribute('title', _('Customize table columns')),
	(new CDiv())->setId('fgt-columns-menu')->addClass('fgt-columns-menu')->addClass('overlay-dialogue')->setAttribute('hidden', 'hidden')
]))->addClass('fgt-table-toolbar'));

$details_panel = (new CDiv())
	->setId('fgt-log-overlay')
	->addClass('fgt-log-overlay')
	->setAttribute('hidden', 'hidden')
	->addItem((new CDiv())
		->addClass('fgt-log-panel')
		->addClass('overlay-dialogue')
		->addItem((new CDiv([
			new CTag('strong', true, _('Log details')),
			(new CButtonIcon(ZBX_ICON_CLOSE))
				->setId('fgt-log-close')
				->setAttribute('aria-label', _('Close'))
				->setAttribute('title', _('Close'))
		]))->addClass('fgt-log-panel-header')->addClass('overlay-dialogue-header'))
		->addItem((new CDiv())->setId('fgt-log-groups')->addClass('fgt-log-groups')));
$page->addItem($details_panel);

$page->addItem(new CTag('style', true, '
	.fgt-log-overlay[hidden] { display: none; }
	.fgt-log-overlay:not([hidden]) { position: fixed; inset: 0; z-index: 1200; display: flex; justify-content: flex-end; background: rgba(0,0,0,.38); }
	.fgt-log-panel { position: relative; box-sizing: border-box; width: min(580px, 92vw); height: 100%; overflow-y: auto; padding: 16px; }
	.fgt-log-panel-header { display: flex; align-items: center; justify-content: space-between; position: sticky; top: -16px; z-index: 1; padding: 12px 0; }
	#fgt-log-close { box-sizing: border-box; width: 32px; min-width: 32px; height: 32px; padding: 0; }
	#fgt-log-close::before { display: inline-block; width: 12px; height: 12px; font-size: 12px !important; line-height: 12px; }
	.fgt-log-section { border-bottom: 1px solid rgba(128,128,128,.25); }
	.fgt-log-group-toggle { display: flex; align-items: center; gap: 8px; width: 100%; min-height: 34px; padding: 6px 8px; border: 0; background: rgba(128,128,128,.16); color: inherit; text-align: left; cursor: pointer; }
	.fgt-log-group-icon { display: inline-flex; width: 13px; height: 13px; align-items: center; justify-content: center; border-radius: 2px; background: rgba(128,128,128,.5); color: var(--body-bg-color, #292929); font-size: 12px; font-weight: 700; line-height: 1; }
	.fgt-log-group-body { padding: 8px 6px; }
	.fgt-log-row { cursor: pointer; }
	.fgt-log-row:hover { background: rgba(128,128,128,.12); }
	.fgt-table-toolbar { display: flex; justify-content: center; position: relative; width: 30px; height: 100%; }
	#fgt-columns-button { box-sizing: border-box; width: 30px; min-width: 30px; height: 30px; padding: 0; border: 0; border-radius: 0; background: transparent; }
	#fgt-columns-button:hover, #fgt-columns-button.is-active { background: rgba(128,128,128,.2); }
	#fgt-columns-button .zi-cog-filled { display: none; }
	#fgt-columns-button::before { content: ""; display: block; width: 14px; height: 14px; margin: auto; background: radial-gradient(circle at 2px 4px, #6f9da8 0 2px, transparent 2.5px), radial-gradient(circle at 7px 9px, #6f9da8 0 2px, transparent 2.5px), radial-gradient(circle at 12px 5px, #6f9da8 0 2px, transparent 2.5px), linear-gradient(#899194, #899194) 1.5px 1px / 1px 12px no-repeat, linear-gradient(#899194, #899194) 6.5px 1px / 1px 12px no-repeat, linear-gradient(#899194, #899194) 11.5px 1px / 1px 12px no-repeat; }
	.fgt-columns-menu { position: fixed; top: 0; left: 0; right: auto; z-index: 1201; box-sizing: border-box; width: 208px; max-height: min(70vh, 520px); overflow: auto; padding: 10px; }
	.fgt-columns-menu[hidden] { display: none; }
	.fgt-columns-menu:not([hidden]) { display: block; }
	.fgt-columns-title { margin: 2px 0 8px; font-weight: 600; }
	.fgt-columns-option { display: flex; min-height: 30px; gap: 7px; align-items: center; padding: 0 5px; cursor: grab; }
	.fgt-columns-option:hover, .fgt-columns-option.fgt-column-dragging { background: rgba(128,128,128,.16); }
	.fgt-columns-grip { width: 13px; flex: 0 0 13px; color: #6f9da8; font-size: 16px; line-height: 1; cursor: grab; }
	.fgt-columns-option input { margin: 0; }
	.fgt-columns-option-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.fgt-columns-menu .fgt-columns-reset { width: 100%; margin-top: 10px; padding: 4px 8px; border: 1px solid currentColor; border-radius: 3px; background: transparent; color: inherit; text-align: left; }
	.fgt-columns-menu .fgt-columns-reset:hover { background: rgba(128,128,128,.16); }
	#fgt-logs-table th { position: relative; }
	#fgt-logs-table { width: 100%; table-layout: fixed; }
	#fgt-logs-table th.fgt-column-tools, #fgt-logs-table td.fgt-column-tools { width: 30px; min-width: 30px; max-width: 30px; padding: 0; }
	#fgt-logs-table td { overflow-wrap: anywhere; }
	#fgt-logs-table th.fgt-column-draggable { cursor: grab; }
	#fgt-logs-table th.fgt-column-dragging { opacity: .45; }
	.fgt-column-resizer { position: absolute; top: 0; right: -2px; z-index: 2; width: 5px; height: 100%; cursor: col-resize; touch-action: none; }
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

$table_layout_js = '(function() {
	const table = document.getElementById("fgt-logs-table");
	const button = document.getElementById("fgt-columns-button");
	const menu = document.getElementById("fgt-columns-menu");
	if (!table || !button || !menu) return;
	document.body.append(menu);
	const positionMenu = function() {
		if (menu.hidden) return;
		const rect = button.getBoundingClientRect();
		const menuWidth = menu.getBoundingClientRect().width || 208;
		const left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));
		const menuHeight = menu.getBoundingClientRect().height;
		const top = rect.bottom + menuHeight > window.innerHeight - 8
			? Math.max(8, rect.top - menuHeight)
			: rect.bottom;
		menu.style.left = left + "px";
		menu.style.top = top + "px";
	};

	const keys = '.zbx_jsvalue($column_keys).';
	const storageKey = "zabbix.fortigate.columns." + '.zbx_jsvalue($action_name).';
	const head = table.tHead && table.tHead.rows[0];
	if (!head) return;
	const headerByKey = {};
	const cellsByKey = {};
	const headers = Array.from(head.cells);
	const rows = Array.from(table.querySelectorAll("tbody tr.fgt-log-row"));
	keys.forEach(function(key, index) {
		headerByKey[key] = headers[index];
		cellsByKey[key] = rows.map(function(row) { return row.cells[index]; });
		headerByKey[key].classList.add("fgt-column-draggable");
		headerByKey[key].setAttribute("draggable", "true");
	});
	const toolsHeader = document.createElement("th");
	toolsHeader.className = "fgt-column-tools";
	toolsHeader.setAttribute("aria-label", '.zbx_jsvalue(_('Table settings')).');
	head.append(toolsHeader);
	const toolsCells = rows.map(function(row) {
		const cell = document.createElement("td");
		cell.className = "fgt-column-tools";
		row.append(cell);
		return cell;
	});
	toolsHeader.append(button.parentElement);

	let state = {order: keys.slice(), hidden: [], widths: {}};
	try {
		const saved = JSON.parse(localStorage.getItem(storageKey) || "null");
		if (saved && Array.isArray(saved.order)) {
			const savedOrder = saved.order.filter(function(key) { return keys.includes(key); });
			state.order = savedOrder.concat(keys.filter(function(key) { return !savedOrder.includes(key); }));
			state.hidden = Array.isArray(saved.hidden) ? saved.hidden.filter(function(key) { return keys.includes(key); }) : [];
			state.widths = saved.widths && typeof saved.widths === "object" ? saved.widths : {};
		}
	}
	catch (error) {}

	const colgroup = document.createElement("colgroup");
	const colByKey = {};
	keys.forEach(function(key) {
		const col = document.createElement("col");
		col.dataset.columnKey = key;
		colByKey[key] = col;
		colgroup.append(col);
	});
	const toolsCol = document.createElement("col");
	toolsCol.style.width = "30px";
	colgroup.append(toolsCol);
	table.insertBefore(colgroup, table.firstChild);

	const save = function() {
		try { localStorage.setItem(storageKey, JSON.stringify(state)); }
		catch (error) {}
	};
	const updateEmptyState = function() {
		const emptyCell = table.querySelector("tbody td[colspan]");
		if (emptyCell) emptyCell.colSpan = state.order.filter(function(key) { return !state.hidden.includes(key); }).length + 1;
	};
	const applyState = function() {
		state.order.forEach(function(key) {
			head.append(headerByKey[key]);
			colgroup.append(colByKey[key]);
			cellsByKey[key].forEach(function(cell) {
				if (cell) cell.parentElement.append(cell);
			});
		});
		head.append(toolsHeader);
		toolsCells.forEach(function(cell) { cell.parentElement.append(cell); });
		colgroup.append(toolsCol);
		state.order.forEach(function(key) {
			const hidden = state.hidden.includes(key);
			headerByKey[key].hidden = hidden;
			colByKey[key].hidden = hidden;
			cellsByKey[key].forEach(function(cell) { if (cell) cell.hidden = hidden; });
			if (state.widths[key]) colByKey[key].style.width = state.widths[key] + "px";
		});
		updateEmptyState();
	};

	const renderMenu = function() {
		menu.replaceChildren();
		menu.hidden = false;
		const title = document.createElement("div");
		title.className = "fgt-columns-title";
		title.textContent = '.zbx_jsvalue(_('Column list')).';
		menu.append(title);
		state.order.forEach(function(key) {
			const option = document.createElement("div");
			option.className = "fgt-columns-option";
			option.draggable = true;
			option.dataset.columnKey = key;
			const grip = document.createElement("span");
			grip.className = "fgt-columns-grip";
			grip.textContent = "⠿";
			grip.setAttribute("aria-hidden", "true");
			const checkbox = document.createElement("input");
			checkbox.type = "checkbox";
			checkbox.setAttribute("aria-label", headerByKey[key].textContent.trim());
			checkbox.checked = !state.hidden.includes(key);
			checkbox.addEventListener("change", function() {
				state.hidden = checkbox.checked
					? state.hidden.filter(function(item) { return item !== key; })
					: state.hidden.concat([key]);
				applyState();
				save();
			});
			const text = document.createElement("span");
			text.className = "fgt-columns-option-text";
			text.textContent = headerByKey[key].textContent.trim();
			option.append(grip, checkbox, text);
			option.addEventListener("dragstart", function(event) {
				draggedKey = key;
				option.classList.add("fgt-column-dragging");
				event.dataTransfer.effectAllowed = "move";
				event.dataTransfer.setData("text/plain", key);
			});
			option.addEventListener("dragend", function() { option.classList.remove("fgt-column-dragging"); });
			option.addEventListener("dragover", function(event) { event.preventDefault(); });
			option.addEventListener("drop", function(event) {
				event.preventDefault();
				const moving = draggedKey || event.dataTransfer.getData("text/plain");
				if (!moving || moving === key) return;
				const oldIndex = state.order.indexOf(moving);
				let targetIndex = state.order.indexOf(key);
				const after = event.clientY > option.getBoundingClientRect().top + option.getBoundingClientRect().height / 2;
				state.order.splice(oldIndex, 1);
				if (oldIndex < targetIndex) targetIndex--;
				state.order.splice(targetIndex + (after ? 1 : 0), 0, moving);
				applyState();
				save();
				renderMenu();
			});
			menu.append(option);
		});
		const reset = document.createElement("button");
		reset.type = "button";
		reset.className = "button fgt-columns-reset";
		reset.textContent = '.zbx_jsvalue(_('Reset to default')).';
		reset.addEventListener("click", function() {
			state = {order: keys.slice(), hidden: [], widths: {}};
			applyState();
			save();
			renderMenu();
		});
		menu.append(reset);
		positionMenu();
	};

	button.addEventListener("click", function(event) {
		event.stopPropagation();
		if (menu.hidden) renderMenu(); else menu.hidden = true;
		button.classList.toggle("is-active", !menu.hidden);
	});
	window.addEventListener("resize", positionMenu);
	window.addEventListener("scroll", positionMenu, true);
	document.addEventListener("click", function(event) {
		if (!menu.contains(event.target) && !button.contains(event.target)) {
			menu.hidden = true;
			button.classList.remove("is-active");
		}
	});

	let draggedKey = null;
	keys.forEach(function(key) {
		const header = headerByKey[key];
		header.addEventListener("dragstart", function(event) {
			draggedKey = key;
			header.classList.add("fgt-column-dragging");
			event.dataTransfer.effectAllowed = "move";
			event.dataTransfer.setData("text/plain", key);
		});
		header.addEventListener("dragend", function() {
			header.classList.remove("fgt-column-dragging");
		});
		header.addEventListener("dragover", function(event) { event.preventDefault(); });
		header.addEventListener("drop", function(event) {
			event.preventDefault();
			const moving = draggedKey || event.dataTransfer.getData("text/plain");
			if (!moving || moving === key) return;
			const oldIndex = state.order.indexOf(moving);
			let newIndex = state.order.indexOf(key);
			const insertAfter = event.clientX > header.getBoundingClientRect().left + header.getBoundingClientRect().width / 2;
			state.order.splice(oldIndex, 1);
			if (oldIndex < newIndex) newIndex--;
			state.order.splice(newIndex + (insertAfter ? 1 : 0), 0, moving);
			applyState();
			save();
			if (!menu.hidden) renderMenu();
		});

		const resizer = document.createElement("span");
		resizer.className = "fgt-column-resizer";
		resizer.setAttribute("aria-hidden", "true");
		header.append(resizer);
		resizer.addEventListener("pointerdown", function(event) {
			event.preventDefault();
			event.stopPropagation();
			const startX = event.clientX;
			const startWidth = header.getBoundingClientRect().width;
			const move = function(moveEvent) {
				const width = Math.max(70, Math.round(startWidth + moveEvent.clientX - startX));
				state.widths[key] = width;
				colByKey[key].style.width = width + "px";
			};
			const end = function() {
				window.removeEventListener("pointermove", move);
				window.removeEventListener("pointerup", end);
				save();
			};
			window.addEventListener("pointermove", move);
			window.addEventListener("pointerup", end);
		});
		resizer.addEventListener("dragstart", function(event) { event.preventDefault(); });
	});
	applyState();
})();';
zbx_add_post_js($table_layout_js);

$page->show();
