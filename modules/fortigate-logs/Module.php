<?php

namespace Modules\FortigateLogs;

use APP,
    CMenuItem,
    Zabbix\Core\CModule;

class Module extends CModule {
	public function init(): void {
		APP::Component()->get('menu.main')
			->findOrAdd(_('Analyzer'))
			->setIcon(ZBX_ICON_APM)
			->getSubmenu()
			->add((new CMenuItem(_('Traffic Logs')))->setAction('fortigate.traffic'))
			->add((new CMenuItem(_('Event System Logs')))->setAction('fortigate.system'));
	}
}
