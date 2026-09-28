<?php

namespace tests\units\GlpiPlugin\Assignmentguard;

use DbTestCase;
use GlpiPlugin\Assignmentguard\AssignmentDecision;
use GlpiPlugin\Assignmentguard\DecisionLogger;
use GlpiPlugin\Assignmentguard\PluginConfig;

/**
 * Executed in CI with Behaviors 2.7.8 and an exact supported Escalade tag.
 * It reads the third-party plugins' real GLPI configuration, but never calls
 * their corrective methods directly.
 */
class PolicyResolver extends DbTestCase
{
    public function testReadsSupportedExternalPoliciesAndSafeCombinedUpdate(): void
    {
        $this->preparePlugins();
        $delta = $this->simpleDelta();

        $this->setIntegrationPlugins(true, false);
        $this->configureBehaviors(0);
        $this->configureGuard(true, false);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['policy'])->isIdenticalTo(AssignmentDecision::POLICY_ALLOW_MULTIPLE);

        $this->configureBehaviors(1);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['policy'])->isIdenticalTo(AssignmentDecision::POLICY_REPLACE);

        $this->configureBehaviors(2);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['policy'])->isIdenticalTo(AssignmentDecision::POLICY_COUPLED_ACTORS);

        $this->configureBehaviors(1);
        $this->configureGuard(false, false);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['reason'])->isIdenticalTo(AssignmentDecision::NOT_ACTED_INTEGRATION_DISABLED);

        $this->setIntegrationPlugins(false, true);
        $this->configureGuard(false, true);
        $this->configureEscalade([
            'remove_group' => 0,
        ]);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['policy'])->isIdenticalTo(AssignmentDecision::POLICY_ALLOW_MULTIPLE);

        $this->configureEscalade([
            'remove_group' => 1,
        ]);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['policy'])->isIdenticalTo(AssignmentDecision::POLICY_REPLACE);

        $this->configureEscalade([
            'remove_tech' => 1,
        ]);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['policy'])->isIdenticalTo(AssignmentDecision::POLICY_COUPLED_ACTORS);

        $this->configureEscalade([
            'remove_tech'               => 0,
            'reassign_group_from_cat'   => 1,
        ]);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve(['itilcategories_id' => 1], $delta);
        $this->string($policy['policy'])->isIdenticalTo(AssignmentDecision::POLICY_COUPLED_ACTORS);

        $this->configureEscalade([
            'reassign_group_from_cat' => 0,
            'use_assign_user_group'   => 1,
            'use_assign_user_group_modification' => 1,
        ]);
        $userDelta = $delta;
        $userDelta['assign_users_changed'] = true;
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $userDelta);
        $this->string($policy['policy'])->isIdenticalTo(AssignmentDecision::POLICY_COUPLED_ACTORS);

        $this->setIntegrationPlugins(true, true);
        $this->configureEscalade([
            'use_assign_user_group'              => 0,
            'use_assign_user_group_modification' => 0,
        ]);
        $this->configureGuard(true, true);
        $this->configureBehaviors(1);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['policy'])->isIdenticalTo(AssignmentDecision::POLICY_REPLACE);
        $this->string($policy['source'])->isIdenticalTo('combined');

        $this->configureEscalade([
            'remove_group' => 0,
        ]);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['reason'])->isIdenticalTo(AssignmentDecision::NOT_ACTED_POLICY_CONFLICT);

        $this->configureEscalade([
            'remove_group' => 1,
        ]);
        $this->configureBehaviors(2);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['reason'])->isIdenticalTo(AssignmentDecision::NOT_ACTED_COUPLED_ACTORS);

        $this->configureBehaviors(1);
        $this->configureGuard(true, false);
        $policy = (new \GlpiPlugin\Assignmentguard\PolicyResolver())->resolve([], $delta);
        $this->string($policy['reason'])->isIdenticalTo(AssignmentDecision::NOT_ACTED_INTEGRATION_DISABLED);

        $this->configureGuard(true, true);
        $this->assertSafeCombinedTicketUpdate();
    }

    private function preparePlugins(): void
    {
        $this->login();
        $plugin = new \Plugin();
        foreach (['assignmentguard', 'behaviors', 'escalade'] as $directory) {
            $plugin->checkPluginState($directory);
            $this->boolean($plugin->getFromDBByDir($directory))->isTrue();
            $this->boolean(\Plugin::isPluginActive($directory))->isTrue();
        }
        $plugin->init(true);

        $this->string((string) \Plugin::getInfo('behaviors', 'version'))->isIdenticalTo('2.7.8');
        $expectedEscalade = getenv('ASSIGNMENTGUARD_ESCALADE_VERSION');
        $this->string((string) $expectedEscalade)->isNotEmpty();
        $this->string((string) \Plugin::getInfo('escalade', 'version'))->isIdenticalTo($expectedEscalade);
    }

    private function setIntegrationPlugins(bool $behaviors, bool $escalade): void
    {
        $plugin = new \Plugin();
        foreach ([
            'behaviors' => $behaviors,
            'escalade'  => $escalade,
        ] as $directory => $enabled) {
            $this->boolean($plugin->getFromDBByDir($directory))->isTrue();
            if (\Plugin::isPluginActive($directory) === $enabled) {
                continue;
            }
            $changed = $enabled
                ? $plugin->activate($plugin->getID())
                : $plugin->unactivate($plugin->getID());
            $this->boolean($changed)->isTrue();
        }
        $plugin->init(true);
    }

    private function configureGuard(bool $behaviors, bool $escalade): void
    {
        PluginConfig::save([
            'integration_behaviors_enabled' => $behaviors,
            'integration_escalade_enabled'  => $escalade,
        ]);
    }

    private function configureBehaviors(int $mode): void
    {
        $config = \PluginBehaviorsConfig::getInstance();
        $this->integer((int) $config->getID())->isGreaterThan(0);
        $this->boolean($config->update([
            'id'               => $config->getID(),
            'single_tech_mode' => $mode,
        ]))->isTrue();
    }

    private function configureEscalade(array $changes): void
    {
        $config = new \PluginEscaladeConfig();
        $this->boolean($config->getFromDB(1))->isTrue();
        $safe = [
            'remove_group'                       => 1,
            'remove_tech'                        => 0,
            'remove_requester'                   => 0,
            'use_assign_user_group'              => 0,
            'use_assign_user_group_modification' => 0,
            'reassign_group_from_cat'            => 0,
            'reassign_tech_from_cat'             => 0,
            'solve_return_group'                 => 0,
            'ticket_last_status'                 => -1,
        ];
        $this->boolean($config->update(array_merge([
            'id' => $config->getID(),
        ], $safe, $changes)))->isTrue();
        \PluginEscaladeConfig::loadInSession();
    }

    private function simpleDelta(): array
    {
        return [
            'existing_groups'      => [101],
            'input_groups'         => [101, 202],
            'added_groups'         => [202],
            'removed_groups'       => [],
            'assign_users_changed' => false,
        ];
    }

    private function assertSafeCombinedTicketUpdate(): void
    {
        $entityId = $this->getTestRootEntity(true);
        $groupA = $this->createItem(\Group::class, [
            'name'        => $this->getUniqueString(),
            'entities_id' => $entityId,
            'is_assign'   => 1,
        ]);
        $groupB = $this->createItem(\Group::class, [
            'name'        => $this->getUniqueString(),
            'entities_id' => $entityId,
            'is_assign'   => 1,
        ]);
        $ticket = $this->createItem(\Ticket::class, [
            'name'                => $this->getUniqueString(),
            'content'             => 'Assignment Guard external integration regression',
            'entities_id'         => $entityId,
            '_users_id_requester' => \Session::getLoginUserID(),
            '_groups_id_assign'   => $groupA->getID(),
        ]);

        $records = [];
        DecisionLogger::setWriterForTests(static function ($line, $decision) use (&$records): void {
            $records[] = $decision;
        });
        try {
            $updated = $ticket->update([
                'id'      => $ticket->getID(),
                '_actors' => [
                    'requester' => [[
                        'itemtype' => 'User',
                        'items_id' => \Session::getLoginUserID(),
                    ]],
                    'observer'  => [],
                    'assign'    => [
                        ['itemtype' => 'Group', 'items_id' => $groupA->getID()],
                        ['itemtype' => 'Group', 'items_id' => $groupB->getID()],
                    ],
                ],
            ]);
        } finally {
            DecisionLogger::setWriterForTests(null);
        }

        $this->boolean($updated)->isTrue();
        $this->array($records)->hasSize(1);
        $this->string($records[0]['decision'])->isIdenticalTo(AssignmentDecision::ACTED_GROUP_REPLACEMENT);
        $this->boolean($records[0]['acted'])->isTrue();

        $ticket->loadActors();
        $groups = array_map(static function (array $group): int {
            return (int) $group['groups_id'];
        }, $ticket->getGroups(\CommonITILActor::ASSIGN));
        $this->array($groups)->isIdenticalTo([(int) $groupB->getID()]);
    }
}
