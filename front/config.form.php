<?php

include '../../../inc/includes.php';

use GlpiPlugin\Assignmentguard\BehaviorsPolicyProvider;
use GlpiPlugin\Assignmentguard\EscaladePolicyProvider;
use GlpiPlugin\Assignmentguard\IntegrationStatus;
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
    'behaviors' => [__('Behaviors', 'assignmentguard'), new BehaviorsPolicyProvider()],
    'escalade' => [__('Escalade', 'assignmentguard'), new EscaladePolicyProvider()],
];

$yesNo = static function (bool $value): string {
    return $value ? __('Yes') : __('No');
};
$policyLabels = [
    'REPLACE' => __('Replace group', 'assignmentguard'),
    'ALLOW_MULTIPLE' => __('Allow multiple groups', 'assignmentguard'),
    'UNKNOWN' => __('Unknown', 'assignmentguard'),
    'COUPLED_ACTORS' => __('Coupled actors', 'assignmentguard'),
];
$stateLabels = [
    'not_installed' => __('Not installed', 'assignmentguard'),
    'inactive' => __('Inactive', 'assignmentguard'),
    'unsupported' => __('Unsupported', 'assignmentguard'),
    'not_authorized' => __('Not authorized', 'assignmentguard'),
    'blocked' => __('Blocked', 'assignmentguard'),
    'ready' => __('Ready', 'assignmentguard'),
];

Html::header(__('Assignment Guard', 'assignmentguard'), '', 'config', 'plugins');
echo "<form method='post' action='config.form.php'>";
echo "<table class='tab_cadre_fixe'>";
echo '<tr><th colspan="2">' . __('Assignment Guard', 'assignmentguard') . '</th></tr>';
$labels = [
    'standalone_group_replacement' => [
        __('Enable standalone group replacement', 'assignmentguard'),
        __('Yes allows standalone replacement only when no active integration blocks the decision. No disables standalone normalization.', 'assignmentguard'),
    ],
    'integration_behaviors_enabled' => [
        __('Authorize Behaviors as a policy source', 'assignmentguard'),
        __('Yes allows the Guard to read a supported Behaviors configuration. No keeps an active Behaviors integration unauthorized and blocks normalization safely.', 'assignmentguard'),
    ],
    'integration_escalade_enabled' => [
        __('Authorize Escalade as a policy source', 'assignmentguard'),
        __('Yes allows the Guard to read a supported Escalade configuration. No keeps an active Escalade integration unauthorized and blocks normalization safely.', 'assignmentguard'),
    ],
    'diagnostic_logging' => [
        __('Enable diagnostic logging', 'assignmentguard'),
        __('Yes enables optional diagnostic details according to the Guard logging policy. No disables those details without changing the functional rule.', 'assignmentguard'),
    ],
];
foreach ($labels as $key => $definition) {
    echo '<tr class="tab_bg_1"><td>' . $definition[0];
    echo Html::showToolTip($definition[1], ['display' => false, 'awesome-class' => 'fa-info-circle']);
    echo '</td><td>';
    Dropdown::showYesNo($key, (int) $config[$key]);
    echo '</td></tr>';
}
echo '<tr><th colspan="2">' . __('Integration status', 'assignmentguard') . '</th></tr>';
foreach ($plugins as $key => $definition) {
    $plugin = new Plugin();
    $installed = $plugin->isInstalled($key);
    $active = Plugin::isPluginActive($key);
    $version = $installed ? Plugin::getInfo($key, 'version') : null;
    $authorized = $config['integration_' . $key . '_enabled'] === '1';
    $status = IntegrationStatus::resolve($key, $installed, $active, $version, $authorized, $definition[1]);
    echo '<tr><th colspan="2">' . $definition[0] . '</th></tr>';
    $fields = [
        __('Installed', 'assignmentguard') => $yesNo($status['installed']),
        __('Active', 'assignmentguard') => $yesNo($status['active']),
        __('Version', 'assignmentguard') => $status['version'] === null ? __('Not available', 'assignmentguard') : Html::clean($status['version']),
        __('Supported', 'assignmentguard') => $yesNo($status['supported']),
        __('Authorized', 'assignmentguard') => $yesNo($status['authorized']),
        __('Effective policy', 'assignmentguard') => $policyLabels[$status['policy']] ?? Html::clean($status['policy']),
        __('Final state', 'assignmentguard') => $stateLabels[$status['state']],
    ];
    foreach ($fields as $label => $value) {
        echo '<tr class="tab_bg_1"><td>' . $label . '</td><td>' . $value . '</td></tr>';
    }
}
echo '<tr class="tab_bg_2"><td colspan="2" class="center">';
echo '<input type="submit" name="update" class="btn btn-primary" value="' . _sx('button', 'Save') . '">';
echo '</td></tr></table>';
Html::closeForm();
Html::footer();
