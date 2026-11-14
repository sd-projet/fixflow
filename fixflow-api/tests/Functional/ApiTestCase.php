<?php

namespace App\Tests\Functional;

use App\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Base des tests fonctionnels : base propre à chaque test, utilisateurs de test, helpers JSON. */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $em = $this->em();
        $tool = new SchemaTool($em);
        $meta = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($meta);
        $tool->createSchema($meta);
    }

    /**
     * Démarre un conteneur neuf : chaque requête (et chaque accès à l'EntityManager) part d'un état propre,
     * comme en production. Évite qu'une modification rejetée (403) reste « sale » en mémoire et soit
     * flushée par la requête suivante, et qu'une session fuite d'un client à l'autre.
     */
    private function freshContainer(): void
    {
        static::$kernel->shutdown();
        static::$kernel->boot();
    }

    protected function em(): EntityManagerInterface
    {
        $this->freshContainer();
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function newClient(): KernelBrowser
    {
        $client = new KernelBrowser(static::$kernel);
        $client->disableReboot();
        return $client;
    }

    protected function makeUser(string $role, string $email, bool $active = true): User
    {
        $em = $this->em();
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $u = (new User())->setEmail($email)->setFullName(ucfirst(strstr($email, '@', true)))->setRoles([$role])->setActive($active);
        $u->setPassword($hasher->hashPassword($u, 'password'));
        $em->persist($u);
        $em->flush();
        return $u;
    }

    /** Nouveau client authentifié (session propre) pour un utilisateur existant. */
    protected function loginAs(User $user): KernelBrowser
    {
        $client = $this->newClient();
        $this->request($client, 'POST', '/api/login', ['email' => $user->getEmail(), 'password' => 'password']);
        return $client;
    }

    protected function request(KernelBrowser $client, string $method, string $uri, ?array $body = null): array
    {
        $this->freshContainer();
        $headers = ['HTTP_ACCEPT' => 'application/ld+json', 'CONTENT_TYPE' => $method === 'PATCH' ? 'application/merge-patch+json' : 'application/json'];
        $client->request($method, $uri, [], [], $headers, $body !== null ? json_encode($body) : null);
        $content = $client->getResponse()->getContent();
        return $content ? (json_decode($content, true) ?? []) : [];
    }

    protected function createTicket(KernelBrowser $client, array $override = []): array
    {
        return $this->request($client, 'POST', '/api/tickets', $override + [
            'title' => 'Bug de test', 'description' => 'Description', 'category' => 'bug', 'priority' => 'normal',
        ]);
    }

    protected function ticketIri(array $ticket): string
    {
        return '/api/tickets/' . $ticket['id'];
    }
}
