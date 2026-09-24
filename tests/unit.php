<?php

class CommonITILActor
{
    public const ASSIGN = 2;
}

class Ticket
{
    public $input = [];
    public $fields = [];
    public $groups = [];
    public $new = false;

    public function isNewItem()
    {
        return $this->new;
    }

    public function loadActors()
    {
    }

    public function getGroups($type)
    {
        return $this->groups;
    }
}

class Config
{
    public static $values = [];
    public static function getConfigurationValues($context, $names = []) {
        $values = self::$values[$context] ?? [];
        return $names ? array_intersect_key($values, array_flip($names)) : $values;
    }
    public static function setConfigurationValues($context, $values = []) {
        self::$values[$context] = array_merge(self::$values[$context] ?? [], $values);
    }
    public static function deleteConfigurationValues($context, $values = []) {
        foreach ($values as $value) { unset(self::$values[$context][$value]); }
    }
}

class Plugin
{
    public static $active = [];
    public static $info = [];
    public static function isPluginActive($name) { return !empty(self::$active[$name]); }
    public static function getInfo($name, $key = null) { return self::$info[$name][$key] ?? null; }
}

class PluginBehaviorsConfig
{
    public static $mode = 0;
    public static function getInstance() { return new self(); }
    public function getField($name) { return self::$mode; }
}

class Toolbox
{
    public static $lines = [];
    public static function logInFile($name, $line, $append) { self::$lines[] = [$name, $line]; }
}

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/setup.php';
require_once dirname(__DIR__) . '/hook.php';

use GlpiPlugin\Assignmentguard\AssignmentGuardHookHandler;
use GlpiPlugin\Assignmentguard\DecisionLogger;
use GlpiPlugin\Assignmentguard\PluginConfig;
use GlpiPlugin\Assignmentguard\PolicyResolver;

function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function makeTicket($groups, $input)
{
    $ticket = new Ticket();
    $ticket->fields = ['id' => 42];
    $ticket->groups = $groups;
    $ticket->input = $input;
    return $ticket;
}

$PLUGIN_HOOKS = [];
plugin_init_assignmentguard();
expect($PLUGIN_HOOKS['csrf_compliant']['assignmentguard'] === true, 'P1 CSRF hook');
expect($PLUGIN_HOOKS['config_page']['assignmentguard'] === 'front/config.form.php', 'P1 config page hook');
expect($PLUGIN_HOOKS['pre_item_update']['assignmentguard']['Ticket'] === 'plugin_assignmentguard_pre_item_update', 'P1 ticket hook callback');

$version = plugin_version_assignmentguard();
expect($version['requirements']['glpi']['min'] === '10.0.20', 'P1 GLPI minimum');
expect($version['requirements']['glpi']['max'] === '10.0.27', 'P1 GLPI exclusive maximum');
expect(!isset($version['requirements']['database']), 'P1 must not require a database version');
expect($version['author'] === '' && $version['license'] === '' && $version['homepage'] === '', 'P1 public metadata remains undecided');
expect(plugin_assignmentguard_check_prerequisites(), 'P1 prerequisites');
expect(plugin_assignmentguard_check_config(), 'P1 configuration check');

Config::$values = [
    'plugin:other' => ['third_party_setting' => 'preserved'],
];
expect(plugin_assignmentguard_install(), 'P1 install');
expect(Config::$values[PluginConfig::CONTEXT]['standalone_group_replacement'] === '1', 'P1 install default');
PluginConfig::save(['diagnostic_logging' => '1', 'third_party_setting' => '1']);
expect(Config::$values[PluginConfig::CONTEXT]['diagnostic_logging'] === '1', 'P1 save own configuration');
expect(Config::$values['plugin:other']['third_party_setting'] === 'preserved', 'P1 must not write third-party configuration');
expect(plugin_assignmentguard_uninstall(), 'P1 uninstall');
expect(Config::$values[PluginConfig::CONTEXT] === [], 'P1 uninstall removes plugin configuration');
expect(Config::$values['plugin:other'] === ['third_party_setting' => 'preserved'], 'P1 uninstall preserves another context');

Config::$values = [
    PluginConfig::CONTEXT => [
        'standalone_group_replacement' => '1',
        'integration_behaviors_enabled' => '0',
        'integration_escalade_enabled' => '0',
        'diagnostic_logging' => '0',
    ],
];
Plugin::$active = [];

$events = [];
DecisionLogger::setWriterForTests(static function ($line, $decision) use (&$events) { $events[] = $decision; });

$actorA = ['itemtype' => 'Group', 'items_id' => 10];
$actorB = ['itemtype' => 'Group', 'items_id' => 20];
$groupsA = [['id' => 100, 'groups_id' => 10]];

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, $actorB]]]);
AssignmentGuardHookHandler::handle($ticket);
expect(count($ticket->input['_actors']['assign']) === 1, 'A1 should normalize actor list');
expect((int) $ticket->input['_actors']['assign'][0]['items_id'] === 20, 'A1 should retain B');
expect(end($events)['decision'] === 'ACTED_GROUP_REPLACEMENT', 'A1 decision');

$ticket = makeTicket([], ['_actors' => ['assign' => [$actorB]]]);
AssignmentGuardHookHandler::handle($ticket);
expect(end($events)['decision'] === 'NOT_ACTED_NO_EXISTING_GROUP', 'A2 decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA]]]);
AssignmentGuardHookHandler::handle($ticket);
expect(end($events)['decision'] === 'NOT_ACTED_NO_GROUP_CHANGE', 'A3 decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, $actorB, ['itemtype' => 'Group', 'items_id' => 30]]]]);
AssignmentGuardHookHandler::handle($ticket);
expect(end($events)['decision'] === 'NOT_ACTED_MULTIPLE_NEW_GROUPS', 'A4 decision');

$ticket = makeTicket([['id' => 100, 'groups_id' => 10], ['id' => 101, 'groups_id' => 11]], ['_actors' => ['assign' => [$actorA, ['itemtype' => 'Group', 'items_id' => 11], $actorB]]]);
AssignmentGuardHookHandler::handle($ticket);
expect(end($events)['decision'] === 'NOT_ACTED_MULTIPLE_EXISTING_GROUPS', 'A5 decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorB]]]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'A6 must preserve already-replaced input');
expect(end($events)['decision'] === 'NOT_ACTED_ALREADY_REPLACED', 'A6 decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [['itemtype' => 'Group']]]]);
AssignmentGuardHookHandler::handle($ticket);
expect(end($events)['decision'] === 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT', 'A7 decision');

$ticket = makeTicket($groupsA, []);
AssignmentGuardHookHandler::handle($ticket);
expect(end($events)['decision'] === 'NOT_ACTED_NO_GROUP_CHANGE', 'A8 decision');

$ticket = makeTicket($groupsA, ['_groups_id_assign' => [10, 20]]);
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input['_groups_id_assign'] === [20], 'Legacy format should retain B');
expect(count($ticket->input['_groups_id_assign_deleted']) === 1, 'Legacy format should explicitly delete A');
expect(end($events)['decision'] === 'ACTED_GROUP_REPLACEMENT', 'Legacy decision');

Plugin::$active = ['behaviors' => true];
Plugin::$info = ['behaviors' => ['version' => '2.7.8']];
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '1';
PluginBehaviorsConfig::$mode = 0;
expect((new PolicyResolver())->resolve([])['policy'] === 'ALLOW_MULTIPLE', 'Behaviors mode 0');
PluginBehaviorsConfig::$mode = 1;
expect((new PolicyResolver())->resolve([])['policy'] === 'REPLACE', 'Behaviors mode 1');
PluginBehaviorsConfig::$mode = 2;
expect((new PolicyResolver())->resolve([])['policy'] === 'COUPLED_ACTORS', 'Behaviors mode 2');
Plugin::$info['behaviors']['version'] = '2.7.7';
expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED', 'Behaviors version gate');
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '0';
expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_DISABLED', 'Behaviors authorization gate');

Plugin::$active = ['escalade' => true];
Plugin::$info = ['escalade' => ['version' => '2.9.22']];
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '0';
Config::$values[PluginConfig::CONTEXT]['integration_escalade_enabled'] = '1';
$_SESSION['glpi_plugins']['escalade']['config'] = [
    'remove_group' => 1,
    'remove_tech' => 0,
    'remove_requester' => 0,
    'ticket_last_status' => -1,
    'use_assign_user_group' => 0,
    'use_assign_user_group_modification' => 0,
    'reassign_group_from_cat' => 0,
];
expect((new PolicyResolver())->resolve([])['policy'] === 'REPLACE', 'Escalade safe replace');
$_SESSION['glpi_plugins']['escalade']['config']['remove_group'] = 0;
expect((new PolicyResolver())->resolve([])['policy'] === 'ALLOW_MULTIPLE', 'Escalade remove_group 0');
$_SESSION['glpi_plugins']['escalade']['config']['remove_group'] = 1;
$_SESSION['glpi_plugins']['escalade']['config']['remove_tech'] = 1;
expect((new PolicyResolver())->resolve([])['policy'] === 'COUPLED_ACTORS', 'Escalade coupled actor gate');

Plugin::$active = ['behaviors' => true, 'escalade' => true];
Plugin::$info = ['behaviors' => ['version' => '2.7.8'], 'escalade' => ['version' => '2.9.22']];
PluginBehaviorsConfig::$mode = 1;
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '1';
$_SESSION['glpi_plugins']['escalade']['config']['remove_tech'] = 0;
$_SESSION['glpi_plugins']['escalade']['config']['remove_group'] = 0;
expect((new PolicyResolver())->resolve([])['policy'] === 'CONFLICT', 'Combined providers conflict');

class ThrowingTicket extends Ticket
{
    public function getGroups($type) { throw new RuntimeException('simulated parser failure'); }
}
$ticket = new ThrowingTicket();
$ticket->fields = ['id' => 99];
$ticket->input = ['_actors' => ['assign' => [$actorA, $actorB]]];
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Fail-open must restore the original input');
expect(end($events)['decision'] === 'ERROR_INTERNAL', 'Fail-open decision');

echo "OK: " . count($events) . " decision cases validated\n";
