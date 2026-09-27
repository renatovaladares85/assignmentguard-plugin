<?php

namespace tests\units;

use DbTestCase;
use GlpiPlugin\Assignmentguard\DecisionLogger;

/**
 * Executed by the GLPI 10.0.20 lifecycle CI job with the plugin enabled.
 */
class Ticket extends DbTestCase
{
    public function testGroupReplacementChangesSlaInOneNativeTicketUpdate(): void
    {
        $this->login();
        $plugin = new \Plugin();
        $plugin->checkPluginState('assignmentguard');
        $this->boolean($plugin->getFromDBByDir('assignmentguard'))->isTrue();
        $pluginId = (int) $plugin->fields['id'];
        $plugin->install($pluginId);
        $this->boolean($plugin->activate($pluginId))->isTrue();
        $plugin->init(true);

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
        $slaA = $this->createItem(\SLA::class, [
            'name'         => $this->getUniqueString(),
            'type'         => \SLM::TTR,
            'calendars_id' => 0,
            'number_time'  => 4,
            'definition_time' => 'hour',
        ]);
        $slaB = $this->createItem(\SLA::class, [
            'name'         => $this->getUniqueString(),
            'type'         => \SLM::TTR,
            'calendars_id' => 0,
            'number_time'  => 4,
            'definition_time' => 'hour',
        ]);

        $this->createRule((new \RuleBuilder($this->getUniqueString()))
            ->setCondtion(\RuleTicket::ONADD | \RuleTicket::ONUPDATE)
            ->setEntity($entityId)
            ->addCriteria('_groups_id_assign', \Rule::PATTERN_IS, $groupA->getID())
            ->addAction('assign', 'slas_id_ttr', $slaA->getID()));
        $this->createRule((new \RuleBuilder($this->getUniqueString()))
            ->setCondtion(\RuleTicket::ONADD | \RuleTicket::ONUPDATE)
            ->setEntity($entityId)
            ->addCriteria('_groups_id_assign', \Rule::PATTERN_IS, $groupB->getID())
            ->addAction('assign', 'slas_id_ttr', $slaB->getID()));

        $ticket = $this->createItem(\Ticket::class, [
            'name'                  => $this->getUniqueString(),
            'content'               => 'Assignment Guard same-write regression',
            'entities_id'           => $entityId,
            '_users_id_requester'   => \Session::getLoginUserID(),
            '_groups_id_assign'     => $groupA->getID(),
        ]);
        $this->integer((int) $ticket->fields['slas_id_ttr'])->isIdenticalTo((int) $slaA->getID());

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
        $this->string($records[0]['decision'])->isIdenticalTo('ACTED_GROUP_REPLACEMENT');
        $this->boolean($records[0]['acted'])->isTrue();

        $this->boolean($ticket->getFromDB($ticket->getID()))->isTrue();
        $this->integer((int) $ticket->fields['slas_id_ttr'])->isIdenticalTo((int) $slaB->getID());
        $ticket->loadActors();
        $groups = array_map(static function (array $group): int {
            return (int) $group['groups_id'];
        }, $ticket->getGroups(\CommonITILActor::ASSIGN));
        $this->array($groups)->isIdenticalTo([(int) $groupB->getID()]);
    }
}
