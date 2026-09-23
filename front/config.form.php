<?php

include '../../../inc/includes.php';

use GlpiPlugin\Assignmentguard\BehaviorsPolicyProvider;
use GlpiPlugin\Assignmentguard\EscaladePolicyProvider;
use GlpiPlugin\Assignmentguard\PluginConfig;

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    Session::checkCSRF($_POST);
    PluginConfig::save($_POST);
    Html::back();
}

$config = PluginConfig::getAll();
$plugins = [
    'behaviors' => [__('Behaviours', 'behaviors'), new BehaviorsPolicyProvider()],
    'escalade' => [__('Escalation', 'escalade'), new EscaladePolicyProvider()],
];

Html::header(__('Assignment Guard', 'assignmentguard'), '', 'config', 'plugins');
echo "<form method='post' action='config.form.php'>";
echo "<table class='tab_cadre_fixe'>";
echo '<tr><th colspan="2">' . __('Assignment Guard', 'assignmentguard') . '</th></tr>';
$labels = [
    'standalone_group_replacement' => __('Enable standalone group replacement', 'assignmentguard'),
    'integration_behaviors_enabled' => __('Authorize Behaviors as a policy source', 'assignmentguard'),
    'integration_escalade_enabled' => __('Authorize Escalade as a policy source', 'assignmentguard'),
    'diagnostic_logging' => __('Enable diagnostic logging', 'assignmentguard'),
];
foreach ($labels as $key => $label) {
    echo '<tr class="tab_bg_1"><td>' . $label . '</td><td>';
    Dropdown::showYesNo($key, (int) $config[$key]);
    echo '</td></tr>';
}
echo '<tr><th colspan="2">' . __('Integration status', 'assignmentguard') . '</th></tr>';
foreach ($plugins as $key => $definition) {
    $plugin = new Plugin();
    $installed = $plugin->isInstalled($key);
    $active = Plugin::isPluginActive($key);
    $version = $active ? Plugin::getInfo($key, 'version') : '-';
    $result = $active ? $definition[1]->resolve([]) : ['policy' => 'UNKNOWN'];
    $supported = $active && ($result['reason'] ?? '') !== 'NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED';
    $authorized = $config['integration_' . $key . '_enabled'] === '1';
    if (!$installed || !$active) {
        $state = __('Warning');
    } elseif (!$authorized || !$supported || $result['policy'] === 'UNKNOWN' || $result['policy'] === 'COUPLED_ACTORS') {
        $state = __('Blocked', 'assignmentguard');
    } else {
        $state = __('OK');
    }
    echo '<tr class="tab_bg_1"><td>' . $definition[0] . '</td><td>';
    echo sprintf(
        __('Installed: %1$s; active: %2$s; version: %3$s; supported: %4$s; authorized: %5$s; policy: %6$s; state: %7$s', 'assignmentguard'),
        $installed ? __('Yes') : __('No'),
        $active ? __('Yes') : __('No'),
        Html::clean($version),
        $supported ? __('Yes') : __('No'),
        $authorized ? __('Yes') : __('No'),
        Html::clean($result['policy']),
        $state
    );
    echo '</td></tr>';
}
echo '<tr class="tab_bg_2"><td colspan="2" class="center">';
echo '<input type="submit" name="update" class="btn btn-primary" value="' . _sx('button', 'Save') . '">';
Html::closeForm();
echo '</td></tr></table></form>';
Html::footer();
