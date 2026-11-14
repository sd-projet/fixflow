<?php

namespace App\Tests\Functional;

final class CommentApiTest extends ApiTestCase
{
    public function testCommentsFollowTicketVisibility(): void
    {
        $dev = $this->makeUser('ROLE_DEV', 'dev@test.local');
        $otherDev = $this->makeUser('ROLE_DEV', 'dev2@test.local');
        $aliceClient = $this->loginAs($this->makeUser('ROLE_USER', 'alice@test.local'));
        $bobClient = $this->loginAs($this->makeUser('ROLE_USER', 'bob@test.local'));
        $t = $this->createTicket($aliceClient);
        $assignedId = $t['assignee']['id'];

        // Créateur : peut commenter.
        $this->request($aliceClient, 'POST', '/api/comments', ['ticket' => $this->ticketIri($t), 'content' => 'Voici plus de détails']);
        $this->assertResponseStatusCodeSame(201);

        // Autre utilisateur : refusé.
        $this->request($bobClient, 'POST', '/api/comments', ['ticket' => $this->ticketIri($t), 'content' => 'Intrus']);
        $this->assertResponseStatusCodeSame(403);

        // Développeur non assigné : refusé ; assigné : accepté.
        [$assigned, $other] = $assignedId === $dev->getId() ? [$dev, $otherDev] : [$otherDev, $dev];
        $this->request($this->loginAs($other), 'POST', '/api/comments', ['ticket' => $this->ticketIri($t), 'content' => 'Pas mon ticket']);
        $this->assertResponseStatusCodeSame(403);
        $this->request($this->loginAs($assigned), 'POST', '/api/comments', ['ticket' => $this->ticketIri($t), 'content' => 'Je m’en occupe']);
        $this->assertResponseStatusCodeSame(201);

        $list = $this->request($aliceClient, 'GET', '/api/comments?ticket=' . $this->ticketIri($t));
        $this->assertSame(2, $list['hydra:totalItems']);
    }

    public function testOnlyTheAuthorEditsAndCannotMoveAComment(): void
    {
        $this->makeUser('ROLE_DEV', 'dev@test.local');
        $aliceClient = $this->loginAs($this->makeUser('ROLE_USER', 'alice@test.local'));
        $bobClient = $this->loginAs($this->makeUser('ROLE_USER', 'bob@test.local'));
        $t1 = $this->createTicket($aliceClient);
        $t2 = $this->createTicket($bobClient);
        $c = $this->request($aliceClient, 'POST', '/api/comments', ['ticket' => $this->ticketIri($t1), 'content' => 'Premier']);

        $this->request($bobClient, 'PATCH', '/api/comments/' . $c['id'], ['content' => 'Piraté']);
        $this->assertResponseStatusCodeSame(403);

        $edited = $this->request($aliceClient, 'PATCH', '/api/comments/' . $c['id'], ['content' => 'Modifié', 'ticket' => $this->ticketIri($t2)]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Modifié', $edited['content']);
        $this->assertNotNull($edited['updatedAt']);
        $this->assertSame($this->ticketIri($t1), $edited['ticket'], 'Le ticket d’un commentaire ne peut pas changer.');
    }

    public function testEmptyCommentIsRejected(): void
    {
        $this->makeUser('ROLE_DEV', 'dev@test.local');
        $client = $this->loginAs($this->makeUser('ROLE_USER', 'alice@test.local'));
        $t = $this->createTicket($client);
        $this->request($client, 'POST', '/api/comments', ['ticket' => $this->ticketIri($t), 'content' => ' ']);
        $this->assertResponseStatusCodeSame(422);
    }
}
