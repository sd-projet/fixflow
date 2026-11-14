<?php

namespace App\Tests\Functional;

final class TicketApiTest extends ApiTestCase
{
    public function testUserCreatesTicketWhichIsAutoAssignedToADeveloper(): void
    {
        $dev = $this->makeUser('ROLE_DEV', 'dev@test.local');
        $user = $this->makeUser('ROLE_USER', 'user@test.local');

        $t = $this->createTicket($this->loginAs($user), ['status' => 'closed']);
        $this->assertResponseStatusCodeSame(201);
        $this->assertMatchesRegularExpression('/^FF-\d{4}-[0-9A-F]{6}$/', $t['reference']);
        $this->assertSame('new', $t['status'], 'Un utilisateur ne peut pas choisir le statut initial.');
        $this->assertSame($dev->getId(), $t['assignee']['id']);
        $this->assertSame($user->getId(), $t['creator']['id']);
    }

    public function testAutoAssignmentBalancesTheLoad(): void
    {
        $dev1 = $this->makeUser('ROLE_DEV', 'dev1@test.local');
        $dev2 = $this->makeUser('ROLE_DEV', 'dev2@test.local');
        $client = $this->loginAs($this->makeUser('ROLE_USER', 'user@test.local'));

        $a = $this->createTicket($client);
        $b = $this->createTicket($client);
        $this->assertNotSame($a['assignee']['id'], $b['assignee']['id']);
        $this->assertEqualsCanonicalizing([$dev1->getId(), $dev2->getId()], [$a['assignee']['id'], $b['assignee']['id']]);
    }

    public function testVisibilityDependsOnRole(): void
    {
        $dev = $this->makeUser('ROLE_DEV', 'dev@test.local');
        $this->makeUser('ROLE_DEV', 'dev2@test.local');
        $alice = $this->makeUser('ROLE_USER', 'alice@test.local');
        $bob = $this->makeUser('ROLE_USER', 'bob@test.local');
        $admin = $this->makeUser('ROLE_ADMIN', 'admin@test.local');

        $aliceClient = $this->loginAs($alice);
        $t = $this->createTicket($aliceClient);
        $this->createTicket($this->loginAs($bob));

        $this->assertSame(1, $this->request($aliceClient, 'GET', '/api/tickets')['hydra:totalItems']);
        $this->assertSame(2, $this->request($this->loginAs($admin), 'GET', '/api/tickets')['hydra:totalItems']);

        // Bob ne peut pas lire le ticket d'Alice.
        $bobClient = $this->loginAs($bob);
        $this->request($bobClient, 'GET', $this->ticketIri($t));
        $this->assertResponseStatusCodeSame(403);

        // Le développeur ne voit que les tickets qui lui sont attribués.
        $devClient = $this->loginAs($dev);
        $mine = $this->request($devClient, 'GET', '/api/tickets')['hydra:member'];
        foreach ($mine as $row) {
            $this->assertSame($dev->getId(), $row['assignee']['id']);
        }
    }

    public function testUserCannotChangePriorityStatusOrAssignee(): void
    {
        $this->makeUser('ROLE_DEV', 'dev@test.local');
        $client = $this->loginAs($this->makeUser('ROLE_USER', 'user@test.local'));
        $t = $this->createTicket($client);

        foreach ([['priority' => 'critical'], ['status' => 'resolved'], ['archived' => true]] as $body) {
            $this->request($client, 'PATCH', $this->ticketIri($t), $body);
            $this->assertResponseStatusCodeSame(403, 'Interdit : ' . json_encode($body));
        }
        $this->request($client, 'PATCH', $this->ticketIri($t), ['description' => 'Précisions ajoutées']);
        $this->assertResponseIsSuccessful();
    }

    public function testDeveloperChangesStatusAndHistoryIsRecorded(): void
    {
        $dev = $this->makeUser('ROLE_DEV', 'dev@test.local');
        $t = $this->createTicket($this->loginAs($this->makeUser('ROLE_USER', 'user@test.local')));
        $devClient = $this->loginAs($dev);

        $this->request($devClient, 'PATCH', $this->ticketIri($t), ['title' => 'Titre réécrit']);
        $this->assertResponseStatusCodeSame(403, 'Un développeur ne modifie pas le contenu.');

        $updated = $this->request($devClient, 'PATCH', $this->ticketIri($t), ['status' => 'resolved']);
        $this->assertResponseIsSuccessful();
        $this->assertSame('resolved', $updated['status']);
        $this->assertNotNull($updated['resolvedAt']);

        $history = $this->request($devClient, 'GET', '/api/ticket_histories?ticket=' . $this->ticketIri($t))['hydra:member'];
        $fields = array_column($history, 'field');
        $this->assertContains('created', $fields);
        $this->assertContains('status', $fields);
        $status = $history[array_search('status', $fields, true)];
        $this->assertSame('new', $status['oldValue']);
        $this->assertSame('resolved', $status['newValue']);
        $this->assertSame($dev->getId(), $status['actor']['id']);
    }

    public function testAdminCanReassignButOnlyToADeveloper(): void
    {
        $dev = $this->makeUser('ROLE_DEV', 'dev@test.local');
        $user = $this->makeUser('ROLE_USER', 'user@test.local');
        $admin = $this->loginAs($this->makeUser('ROLE_ADMIN', 'admin@test.local'));
        $t = $this->createTicket($this->loginAs($user));

        $this->request($admin, 'PATCH', $this->ticketIri($t), ['assignee' => '/api/users/' . $user->getId()]);
        $this->assertResponseStatusCodeSame(422);

        $ok = $this->request($admin, 'PATCH', $this->ticketIri($t), ['assignee' => '/api/users/' . $dev->getId(), 'priority' => 'high']);
        $this->assertResponseIsSuccessful();
        $this->assertSame('high', $ok['priority']);
    }

    public function testArchivedTicketsAreHiddenByDefault(): void
    {
        $this->makeUser('ROLE_DEV', 'dev@test.local');
        $admin = $this->loginAs($this->makeUser('ROLE_ADMIN', 'admin@test.local'));
        $t = $this->createTicket($admin);

        $this->request($admin, 'PATCH', $this->ticketIri($t), ['archived' => true]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $this->request($admin, 'GET', '/api/tickets')['hydra:totalItems']);
        $this->assertSame(1, $this->request($admin, 'GET', '/api/tickets?archived=true')['hydra:totalItems']);
    }

    public function testOnlyAdminCanDelete(): void
    {
        $this->makeUser('ROLE_DEV', 'dev@test.local');
        $userClient = $this->loginAs($this->makeUser('ROLE_USER', 'user@test.local'));
        $t = $this->createTicket($userClient);

        $this->request($userClient, 'DELETE', $this->ticketIri($t));
        $this->assertResponseStatusCodeSame(403);
        $this->request($this->loginAs($this->makeUser('ROLE_ADMIN', 'admin@test.local')), 'DELETE', $this->ticketIri($t));
        $this->assertResponseStatusCodeSame(204);
    }

    public function testSearchAndFilters(): void
    {
        $this->makeUser('ROLE_DEV', 'dev@test.local');
        $client = $this->loginAs($this->makeUser('ROLE_ADMIN', 'admin@test.local'));
        $a = $this->createTicket($client, ['title' => 'Erreur de paiement', 'priority' => 'high']);
        $this->createTicket($client, ['title' => 'Lenteur du tableau de bord']);

        $this->assertSame(1, $this->request($client, 'GET', '/api/tickets?q=paiement')['hydra:totalItems']);
        $this->assertSame(1, $this->request($client, 'GET', '/api/tickets?q=' . $a['reference'])['hydra:totalItems']);
        $this->assertSame(1, $this->request($client, 'GET', '/api/tickets?priority=high')['hydra:totalItems']);
        $this->assertSame(2, $this->request($client, 'GET', '/api/tickets')['hydra:totalItems']);
    }

    public function testServerSidePagination(): void
    {
        $this->makeUser('ROLE_DEV', 'dev@test.local');
        $client = $this->loginAs($this->makeUser('ROLE_USER', 'user@test.local'));
        foreach (range(1, 3) as $i) {
            $this->createTicket($client, ['title' => "Ticket $i"]);
        }
        $page = $this->request($client, 'GET', '/api/tickets?itemsPerPage=2&page=2');
        $this->assertCount(1, $page['hydra:member']);
        $this->assertSame(3, $page['hydra:totalItems']);
    }

    public function testValidationErrors(): void
    {
        $this->makeUser('ROLE_DEV', 'dev@test.local');
        $client = $this->loginAs($this->makeUser('ROLE_USER', 'user@test.local'));
        $this->request($client, 'POST', '/api/tickets', ['title' => '', 'description' => '']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testDashboardIsScopedToTheUser(): void
    {
        $this->makeUser('ROLE_DEV', 'dev@test.local');
        $alice = $this->loginAs($this->makeUser('ROLE_USER', 'alice@test.local'));
        $bob = $this->loginAs($this->makeUser('ROLE_USER', 'bob@test.local'));
        $this->createTicket($alice, ['priority' => 'critical']);
        $this->createTicket($bob);
        $this->createTicket($bob);

        $a = $this->request($alice, 'GET', '/api/dashboard');
        $this->assertSame(1, $a['total']);
        $this->assertSame(1, $a['critical']);
        $this->assertSame(2, $this->request($bob, 'GET', '/api/dashboard')['total']);
    }
}
