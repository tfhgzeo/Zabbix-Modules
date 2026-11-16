<?php

namespace Modules\FortigateLogs\Actions;

class FortiGateTrafficLogs extends FortiGateLogs {
	protected function getSelectedLogType(): string {
		return 'traffic';
	}
}
