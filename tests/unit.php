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
    public $users = [];
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

    public function getUsers($type)
    {
        return $this->users;
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
    public static $installed = [];
    public static $getInfoCalls = 0;
    public static function isPluginActive($name) { return !empty(self::$active[$name]); }
    public static function getInfo($name, $key = null) { self::$getInfoCalls++; return self::$info[$name][$key] ?? null; }
    public function isInstalled($name) { return !empty(self::$installed[$name]); }
}

class PluginBehaviorsConfig
{
    public static $mode = 0;
    public static $getInstanceCalls = 0;
    public static $throwOnGetInstance = false;
    public static $throwOnGetField = false;
    public static function getInstance() {
        self::$getInstanceCalls++;
        if (self::$throwOnGetInstance) { throw new RuntimeException('simulated config access failure'); }
        return new self();
    }
    public function getField($name) {
        if (self::$throwOnGetField) { throw new RuntimeException('simulated config read failure'); }
        return self::$mode;
    }
}

class Toolbox
{
    public static $lines = [];
    public static function logInFile($name, $line, $append) { self::$lines[] = [$name, $line, $append]; }
}

require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/setup.php';
require_once dirname(__DIR__) . '/hook.php';

use GlpiPlugin\Assignmentguard\AssignmentGuardHookHandler;
use GlpiPlugin\Assignmentguard\ActorInputParser;
use GlpiPlugin\Assignmentguard\AssignmentDecision;
use GlpiPlugin\Assignmentguard\DecisionLogger;
use GlpiPlugin\Assignmentguard\GroupInputNormalizer;
use GlpiPlugin\Assignmentguard\IntegrationStatus;
use GlpiPlugin\Assignmentguard\PluginConfig;
use GlpiPlugin\Assignmentguard\PolicyResolver;

function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function makeTicket($groups, $input, $users = [])
{
    $ticket = new Ticket();
    $ticket->fields = ['id' => 42];
    $ticket->groups = $groups;
    $ticket->users = $users;
    $ticket->input = $input;
    return $ticket;
}

function parseWithoutMutation($parser, $groups, $input, $message)
{
    $ticket = makeTicket($groups, $input);
    $snapshot = $ticket->input;
    $delta = $parser->parse($ticket, $ticket->input);
    expect($ticket->input === $snapshot, $message . ' must not mutate ticket input');
    return $delta;
}

class CountingPolicyProvider
{
    public $calls = 0;
    public $policy = 'REPLACE';
    public function resolve(array $input): array
    {
        $this->calls++;
        return ['policy' => $this->policy];
    }
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

$statusProvider = new CountingPolicyProvider();
$status = IntegrationStatus::resolve('behaviors', true, true, '2.7.8', false, $statusProvider);
expect($status['state'] === 'not_authorized' && $status['severity'] === 'blocked' && $status['policy'] === 'UNKNOWN', 'P4 unauthorized integration status');
expect($statusProvider->calls === 0, 'P4 must not read an unauthorized provider');
$status = IntegrationStatus::resolve('behaviors', true, true, '2.7.8', true, $statusProvider);
expect($status['state'] === 'ready' && $status['severity'] === 'ok' && $status['policy'] === 'REPLACE', 'P4 authorized integration status');
expect($statusProvider->calls === 1, 'P4 may read an authorized provider');
$status = IntegrationStatus::resolve('escalade', true, true, '2.10.0', true, $statusProvider);
expect($status['state'] === 'unsupported' && $status['severity'] === 'blocked' && $statusProvider->calls === 1, 'P4 unsupported integration status');
$status = IntegrationStatus::resolve('behaviors', true, false, '2.7.8', false, $statusProvider);
expect($status['supported'] === true && $status['severity'] === 'warning', 'P4 inactive supported version status');
$status = IntegrationStatus::resolve('behaviors', false, false, null, false, $statusProvider);
expect($status['state'] === 'not_installed' && $status['severity'] === 'warning', 'P4 missing integration status');
$statusProvider->policy = 'UNKNOWN';
$status = IntegrationStatus::resolve('behaviors', true, true, '2.7.8', true, $statusProvider);
expect($status['state'] === 'blocked' && $status['severity'] === 'blocked', 'P4 unknown policy severity');
$statusProvider->policy = 'COUPLED_ACTORS';
$status = IntegrationStatus::resolve('behaviors', true, true, '2.7.8', true, $statusProvider);
expect($status['state'] === 'blocked' && $status['severity'] === 'blocked', 'P4 coupled policy severity');
$configPage = file_get_contents(dirname(__DIR__) . '/front/config.form.php');
expect(strpos($configPage, 'Html::showToolTip') !== false, 'P4 config help structure');
expect(strpos($configPage, 'IntegrationStatus::resolve') !== false, 'P4 config status structure');
expect(strpos($configPage, "__('Condition', 'assignmentguard')") !== false, 'P4 condition label');
expect(is_file(dirname(__DIR__) . '/locales/pt_BR.po'), 'P4 pt_BR catalog');
expect(is_file(dirname(__DIR__) . '/locales/pt_BR.mo'), 'P4 compiled pt_BR catalog');

$events = [];
$lines = [];
DecisionLogger::setWriterForTests(static function ($line, $decision) use (&$events, &$lines) {
    $events[] = $decision;
    $lines[] = $line;
});

$actorA = ['itemtype' => 'Group', 'items_id' => 10];
$actorB = ['itemtype' => 'Group', 'items_id' => 20];
$actorTechnician = ['itemtype' => 'User', 'items_id' => 7];
$groupsA = [['id' => 100, 'groups_id' => 10]];
$usersTechnician = [['id' => 200, 'users_id' => 7]];

$parser = new ActorInputParser();
$delta = parseWithoutMutation($parser, $groupsA, ['_actors' => ['assign' => [$actorA, $actorB]]], 'A1');
expect($delta['recognized'] && $delta['format'] === 'actors' && $delta['added_groups'] === [20], 'A1 parser delta');
$simpleDelta = $delta;
$standaloneSimple = (new PolicyResolver())->resolve([], $delta);
expect($standaloneSimple['policy'] === AssignmentDecision::POLICY_REPLACE && $standaloneSimple['source'] === 'standalone', 'A1 standalone policy');
expect($standaloneSimple['acted'] === true && $standaloneSimple['reason'] === AssignmentDecision::ACTED_GROUP_REPLACEMENT, 'A1 standalone decision');
expect(Plugin::$getInfoCalls === 0 && PluginBehaviorsConfig::$getInstanceCalls === 0, 'Standalone must not execute external providers');

$normalizerInput = [
    'name' => 'preserve',
    'itilcategories_id' => 12,
    '_actors' => ['assign' => [$actorA, $actorTechnician, $actorB]],
];
$normalizerBefore = $normalizerInput;
$normalized = (new GroupInputNormalizer())->normalize($normalizerInput, $simpleDelta);
expect($normalizerInput === $normalizerBefore, 'A1 normalizer must build a separate input');
expect($normalized['name'] === 'preserve' && $normalized['itilcategories_id'] === 12, 'A1 normalizer preserves unrelated fields');
expect($normalized['_actors']['assign'] === [$actorTechnician, $actorB], 'A1 normalizer removes only persisted group A');

$delta = parseWithoutMutation($parser, [], ['_actors' => ['assign' => [$actorB]]], 'A2');
expect($delta['recognized'] && $delta['existing_groups'] === [] && $delta['added_groups'] === [20], 'A2 parser delta');
$policy = (new PolicyResolver())->resolve([], $delta);
expect($policy['policy'] === AssignmentDecision::POLICY_UNKNOWN && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_NO_EXISTING_GROUP, 'A2 standalone decision');

$delta = parseWithoutMutation($parser, $groupsA, ['_actors' => ['assign' => [$actorA]]], 'A3');
expect($delta['recognized'] && !$delta['changed'] && $delta['reason'] === 'NOT_ACTED_NO_GROUP_CHANGE', 'A3 parser delta');
$policy = (new PolicyResolver())->resolve([], $delta);
expect($policy['policy'] === AssignmentDecision::POLICY_UNKNOWN && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_NO_GROUP_CHANGE, 'A3 standalone decision');

$delta = parseWithoutMutation($parser, $groupsA, ['_actors' => ['assign' => [$actorA, $actorB, ['itemtype' => 'Group', 'items_id' => 30]]]], 'A4');
expect($delta['recognized'] && $delta['added_groups'] === [20, 30], 'A4 parser delta');
$policy = (new PolicyResolver())->resolve([], $delta);
expect($policy['policy'] === AssignmentDecision::POLICY_UNKNOWN && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_MULTIPLE_NEW_GROUPS, 'A4 standalone decision');

$groupsAB = [['id' => 100, 'groups_id' => 10], ['id' => 101, 'groups_id' => 11]];
$delta = parseWithoutMutation($parser, $groupsAB, ['_actors' => ['assign' => [$actorA, ['itemtype' => 'Group', 'items_id' => 11], $actorB]]], 'A5');
expect($delta['recognized'] && $delta['existing_groups'] === [10, 11] && $delta['added_groups'] === [20], 'A5 parser delta');
$policy = (new PolicyResolver())->resolve([], $delta);
expect($policy['policy'] === AssignmentDecision::POLICY_UNKNOWN && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_MULTIPLE_EXISTING_GROUPS, 'A5 standalone decision');

$delta = parseWithoutMutation($parser, $groupsA, ['_actors' => ['assign' => [$actorB]]], 'A6');
expect($delta['recognized'] && $delta['added_groups'] === [20] && $delta['removed_groups'] === [10], 'A6 parser delta');
$policy = (new PolicyResolver())->resolve([], $delta);
expect($policy['policy'] === AssignmentDecision::POLICY_UNKNOWN && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_ALREADY_REPLACED, 'A6 standalone decision');

$delta = parseWithoutMutation($parser, $groupsA, ['_actors' => ['assign' => [['itemtype' => 'Group']]]], 'A7');
expect(!$delta['recognized'] && $delta['reason'] === 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT', 'A7 parser delta');
$policy = (new PolicyResolver())->resolve([], $delta);
expect($policy['policy'] === AssignmentDecision::POLICY_UNKNOWN && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT, 'A7 standalone decision');

$delta = parseWithoutMutation($parser, $groupsA, [], 'A8');
expect($delta['recognized'] && !$delta['changed'] && $delta['reason'] === 'NOT_ACTED_NO_GROUP_CHANGE', 'A8 parser delta');
$policy = (new PolicyResolver())->resolve([], $delta);
expect($policy['policy'] === AssignmentDecision::POLICY_UNKNOWN && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_NO_GROUP_CHANGE, 'A8 standalone decision');

Config::$values[PluginConfig::CONTEXT]['standalone_group_replacement'] = '0';
$policy = (new PolicyResolver())->resolve([], $simpleDelta);
expect($policy['policy'] === AssignmentDecision::POLICY_ALLOW_MULTIPLE && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_POLICY_ALLOWS_MULTIPLE, 'Standalone disabled decision');
Config::$values[PluginConfig::CONTEXT]['standalone_group_replacement'] = '1';
$policy = (new PolicyResolver())->resolve([]);
expect($policy['policy'] === AssignmentDecision::POLICY_UNKNOWN && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_UNSUPPORTED_CONTEXT, 'Standalone unsupported decision');

$delta = parseWithoutMutation($parser, $groupsA, ['_groups_id_assign' => [10, 20]], 'Legacy parser');
expect($delta['recognized'] && $delta['format'] === 'legacy' && $delta['added_groups'] === [20], 'Legacy parser delta');

$delta = parseWithoutMutation($parser, $groupsA, ['_groups_id_assign' => '20.5'], 'Unknown legacy parser');
expect(!$delta['recognized'] && $delta['reason'] === 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT', 'Unknown legacy parser delta');

$ticket = makeTicket($groupsA, [
    'name' => 'preserve',
    'itilcategories_id' => 12,
    '_actors' => ['assign' => [$actorA, $actorTechnician, $actorB]],
], $usersTechnician);
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input['name'] === 'preserve' && $ticket->input['itilcategories_id'] === 12, 'A1 must preserve unrelated input');
expect($ticket->input['_actors']['assign'] === [$actorTechnician, $actorB], 'A1 should retain B and preserve technician');
expect(end($events)['decision'] === 'ACTED_GROUP_REPLACEMENT', 'A1 decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, $actorTechnician, $actorB]]]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Technician addition must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_COUPLED_ACTORS', 'Technician addition decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, $actorB]]], $usersTechnician);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Technician removal must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_COUPLED_ACTORS', 'Technician removal decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, ['itemtype' => 'User', 'items_id' => 8], $actorB]]], $usersTechnician);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Technician replacement must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_COUPLED_ACTORS', 'Technician replacement decision');

$ticket = makeTicket([], ['_actors' => ['assign' => [$actorB]]]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'A2 must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_NO_EXISTING_GROUP', 'A2 decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA]]]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'A3 must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_NO_GROUP_CHANGE', 'A3 decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, $actorB, ['itemtype' => 'Group', 'items_id' => 30]]]]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'A4 must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_MULTIPLE_NEW_GROUPS', 'A4 decision');

$ticket = makeTicket([['id' => 100, 'groups_id' => 10], ['id' => 101, 'groups_id' => 11]], ['_actors' => ['assign' => [$actorA, ['itemtype' => 'Group', 'items_id' => 11], $actorB]]]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'A5 must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_MULTIPLE_EXISTING_GROUPS', 'A5 decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorB]]]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'A6 must preserve already-replaced input');
expect(end($events)['decision'] === 'NOT_ACTED_ALREADY_REPLACED', 'A6 decision');

$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [['itemtype' => 'Group']]]]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'A7 must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_UNRECOGNIZED_ACTOR_INPUT', 'A7 decision');

$ticket = makeTicket($groupsA, []);
$before = $ticket->input;
$eventsBefore = count($events);
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'A8 must preserve input');
expect(count($events) === $eventsBefore + 1, 'A8 must write exactly one decision');
expect(end($events)['decision'] === 'NOT_ACTED_NO_GROUP_CHANGE', 'A8 decision');
$record = json_decode(end($lines), true);
expect($record['ticket_id'] === 42 && $record['acted'] === false, 'A8 minimum technical data');
expect(count(array_diff(array_keys($record), [
    'ticket_id', 'acted', 'policy_source', 'existing_groups', 'input_groups', 'added_groups',
    'removed_groups', 'decision', 'timestamp', 'plugin_version', 'glpi_version',
])) === 0, 'A8 log must contain technical fields only');

$ticket = makeTicket($groupsA, ['_groups_id_assign' => [10, 20]]);
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input['_groups_id_assign'] === [20], 'Legacy format should retain B');
expect(count($ticket->input['_groups_id_assign_deleted']) === 1, 'Legacy format should explicitly delete A');
expect(end($events)['decision'] === 'ACTED_GROUP_REPLACEMENT', 'Legacy decision');

$ticket = makeTicket($groupsA, [
    '_groups_id_assign' => [10, 20],
    '_users_id_assign_deleted' => [['id' => 200, 'users_id' => 7]],
]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Legacy technician removal must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_COUPLED_ACTORS', 'Legacy technician removal decision');

Plugin::$active = ['behaviors' => true];
Plugin::$info = ['behaviors' => ['version' => '2.7.8']];
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '1';
PluginBehaviorsConfig::$mode = 0;
expect((new PolicyResolver())->resolve([])['policy'] === 'ALLOW_MULTIPLE', 'Behaviors mode 0');
PluginBehaviorsConfig::$mode = 1;
expect((new PolicyResolver())->resolve([])['policy'] === 'REPLACE', 'Behaviors mode 1');
PluginBehaviorsConfig::$mode = 2;
expect((new PolicyResolver())->resolve([])['policy'] === 'COUPLED_ACTORS', 'Behaviors mode 2');
PluginBehaviorsConfig::$mode = 'invalid';
expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_POLICY_UNKNOWN', 'Behaviors invalid mode gate');
PluginBehaviorsConfig::$throwOnGetInstance = true;
expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_POLICY_UNKNOWN', 'Behaviors inaccessible configuration gate');
PluginBehaviorsConfig::$throwOnGetInstance = false;
PluginBehaviorsConfig::$throwOnGetField = true;
expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_POLICY_UNKNOWN', 'Behaviors unreadable configuration value gate');
PluginBehaviorsConfig::$throwOnGetField = false;
Plugin::$info['behaviors']['version'] = '2.7.7';
expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED', 'Behaviors version gate');
expect(PluginBehaviorsConfig::$getInstanceCalls === 6, 'Behaviors unsupported version must not read configuration');
Plugin::$info['behaviors']['version'] = '2.7.8';
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '0';
expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_DISABLED', 'Behaviors authorization gate');
expect(PluginBehaviorsConfig::$getInstanceCalls === 6, 'Behaviors disabled integration must not read configuration');
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '1';
PluginBehaviorsConfig::$mode = 1;
$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, $actorB]]]);
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input['_actors']['assign'] === [$actorB], 'Behaviors mode 1 normalizes only the simple group replacement');
expect(end($events)['decision'] === 'ACTED_GROUP_REPLACEMENT', 'Behaviors mode 1 decision');
PluginBehaviorsConfig::$mode = 2;
$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, $actorTechnician, $actorB]]], $usersTechnician);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Behaviors mode 2 must not normalize coupled actors');
expect(end($events)['decision'] === 'NOT_ACTED_COUPLED_ACTORS', 'Behaviors mode 2 decision');

Plugin::$active = ['glpi_escalation' => true];
Plugin::$info = ['glpi_escalation' => ['version' => '9.5.0']];
expect((new PolicyResolver())->resolve([], $simpleDelta)['source'] === 'standalone', 'Legacy Escalade must not be an integration source');

Plugin::$active = ['escalade' => true];
Plugin::$info = ['escalade' => ['version' => '2.9.22']];
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '0';
Config::$values[PluginConfig::CONTEXT]['integration_escalade_enabled'] = '1';
$safeEscaladeConfig = [
    'remove_group' => 1,
    'remove_tech' => 0,
    'remove_requester' => 0,
    'ticket_last_status' => -1,
    'use_assign_user_group' => 0,
    'use_assign_user_group_modification' => 0,
    'reassign_group_from_cat' => 0,
];
$_SESSION['glpi_plugins']['escalade']['config'] = $safeEscaladeConfig;
foreach (['2.9.18', '2.9.19', '2.9.20', '2.9.21', '2.9.22'] as $version) {
    Plugin::$info['escalade']['version'] = $version;
    expect((new PolicyResolver())->resolve([])['policy'] === 'REPLACE', 'Escalade supported version ' . $version);
}
expect((new PolicyResolver())->resolve([])['policy'] === 'REPLACE', 'Escalade safe replace');
$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, $actorB]]]);
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input['_actors']['assign'] === [$actorB], 'Escalade remove_group 1 normalizes the simple group replacement');
expect(end($events)['decision'] === 'ACTED_GROUP_REPLACEMENT', 'Escalade remove_group 1 decision');
$_SESSION['glpi_plugins']['escalade']['config']['remove_group'] = 0;
expect((new PolicyResolver())->resolve([])['policy'] === 'ALLOW_MULTIPLE', 'Escalade remove_group 0');
$_SESSION['glpi_plugins']['escalade']['config']['remove_group'] = 1;
$_SESSION['glpi_plugins']['escalade']['config']['remove_group'] = 'unexpected';
expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_POLICY_UNKNOWN', 'Escalade unexpected remove_group gate');
$_SESSION['glpi_plugins']['escalade']['config'] = $safeEscaladeConfig;
unset($_SESSION['glpi_plugins']['escalade']['config']);
expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_POLICY_UNKNOWN', 'Escalade missing configuration gate');
Config::$values[PluginConfig::CONTEXT]['integration_escalade_enabled'] = '0';
expect((new PolicyResolver())->resolve([], $simpleDelta)['reason'] === 'NOT_ACTED_INTEGRATION_DISABLED', 'Escalade authorization gate');
Config::$values[PluginConfig::CONTEXT]['integration_escalade_enabled'] = '1';
$_SESSION['glpi_plugins']['escalade']['config'] = $safeEscaladeConfig;
foreach (['2.9.17', '2.10.0'] as $version) {
    Plugin::$info['escalade']['version'] = $version;
    expect((new PolicyResolver())->resolve([])['reason'] === 'NOT_ACTED_INTEGRATION_VERSION_UNSUPPORTED', 'Escalade unsupported version ' . $version);
}
Plugin::$info['escalade']['version'] = '2.9.22';
$_SESSION['glpi_plugins']['escalade']['config']['remove_tech'] = 1;
expect((new PolicyResolver())->resolve([])['policy'] === 'COUPLED_ACTORS', 'Escalade coupled actor gate');

Plugin::$active = ['behaviors' => true, 'escalade' => true];
Plugin::$info = ['behaviors' => ['version' => '2.7.8'], 'escalade' => ['version' => '2.9.22']];
PluginBehaviorsConfig::$mode = 1;
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '1';
$_SESSION['glpi_plugins']['escalade']['config']['remove_tech'] = 0;
$_SESSION['glpi_plugins']['escalade']['config']['remove_group'] = 0;
$policy = (new PolicyResolver())->resolve([]);
expect($policy['policy'] === AssignmentDecision::POLICY_UNKNOWN && $policy['resolution'] === AssignmentDecision::RESOLUTION_CONFLICT && $policy['acted'] === false && $policy['reason'] === AssignmentDecision::NOT_ACTED_POLICY_CONFLICT, 'Combined providers conflict');

class ThrowingTicket extends Ticket
{
    public function getGroups($type) { throw new RuntimeException('simulated parser failure'); }
}

class UnreadableUsersTicket extends Ticket
{
    public function getUsers($type) { throw new RuntimeException('simulated users failure'); }
}
$ticket = new UnreadableUsersTicket();
$ticket->fields = ['id' => 98];
$ticket->groups = $groupsA;
$ticket->input = ['_actors' => ['assign' => [$actorA, $actorB]]];
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Unreadable technicians must preserve input');
expect(end($events)['decision'] === 'NOT_ACTED_UNSUPPORTED_CONTEXT', 'Unreadable technicians decision');

$ticket = new ThrowingTicket();
$ticket->fields = ['id' => 99];
$ticket->input = ['_actors' => ['assign' => [$actorA, $actorB]]];
$before = $ticket->input;
$eventsBefore = count($events);
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Fail-open must restore the original input');
expect(count($events) === $eventsBefore + 1, 'Fail-open must write exactly one decision');
expect(end($events)['decision'] === 'ERROR_INTERNAL', 'Fail-open decision');

DecisionLogger::setWriterForTests(static function () { throw new RuntimeException('simulated log failure'); });
$ticket = new ThrowingTicket();
$ticket->fields = ['id' => 100];
$ticket->input = ['_actors' => ['assign' => [$actorA, $actorB]]];
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Logger failure must not block native flow');

$writeAttempts = [];
$failFirstWrite = true;
Plugin::$active = [];
Config::$values[PluginConfig::CONTEXT]['integration_behaviors_enabled'] = '0';
Config::$values[PluginConfig::CONTEXT]['integration_escalade_enabled'] = '0';
DecisionLogger::setWriterForTests(static function ($line, $decision) use (&$writeAttempts, &$failFirstWrite) {
    $writeAttempts[] = $decision['decision'];
    if ($failFirstWrite) {
        $failFirstWrite = false;
        throw new RuntimeException('simulated acted logging failure');
    }
});
$ticket = makeTicket($groupsA, ['_actors' => ['assign' => [$actorA, $actorB]]]);
$before = $ticket->input;
AssignmentGuardHookHandler::handle($ticket);
expect($ticket->input === $before, 'Acted logging failure must restore the original input');
expect($writeAttempts === ['ACTED_GROUP_REPLACEMENT', 'ERROR_INTERNAL'], 'Acted logging failure must attempt ERROR_INTERNAL');

DecisionLogger::setWriterForTests(null);
Toolbox::$lines = [];
$ticket = makeTicket($groupsA, []);
AssignmentGuardHookHandler::handle($ticket);
expect(count(Toolbox::$lines) === 1, 'GLPI logger must receive one decision');
expect(Toolbox::$lines[0][0] === 'assignmentguard' && Toolbox::$lines[0][2] === true, 'GLPI logger target');
expect(is_array(json_decode(Toolbox::$lines[0][1], true)), 'GLPI logger JSON line');

echo "OK: " . count($events) . " decision cases validated\n";
