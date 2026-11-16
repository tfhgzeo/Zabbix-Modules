<?php

namespace Modules\FortigateLogs\Actions;

class FortiGateSystemLogs extends FortiGateLogs {
	protected function getSelectedLogType(): string {
		return 'system';
	}

	protected function matchesSelectedLog(array $fields): bool {
		return ($fields['type'] ?? '') === 'event' && ($fields['subtype'] ?? '') === 'system';
	}

	protected function getRouteActionName(): string {
		return 'fortigate.system';
	}
}
