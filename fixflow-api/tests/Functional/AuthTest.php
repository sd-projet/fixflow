<?php

namespace App\Tests\Functional;

final class AuthTest extends ApiTestCase
{
    public function testAnonymousIsRejected(): void
    {
        $this->request($this->client, 'GET', '/api/tickets');
        $this->assertResponseStatusCodeSame(401);
        $this->request($this->client, 'GET', '/api/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginAndMe(): void
    {
        $user = $this->makeUser('ROLE_DEV', 'dev@test.local');
        $client = $this->loginAs($user);
        $this->assertResponseIsSuccessful();
        $me = $this->request($client, 'GET', '/api/me');
        $this->assertSame('dev@test.local', $me['email']);
        $this->assertContains('ROLE_DEV', $me['roles']);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->makeUser('ROLE_USER', 'user@test.local');
        $this->request($this->client, 'POST', '/api/login', ['email' => 'user@test.local', 'password' => 'nope']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDisabledAccountCannotLogin(): void
    {
        $this->makeUser('ROLE_USER', 'off@test.local', active: false);
        $this->request($this->client, 'POST', '/api/login', ['email' => 'off@test.local', 'password' => 'password']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testOnlyAdminCanListOrCreateUsers(): void
    {
        $admin = $this->makeUser('ROLE_ADMIN', 'admin@test.local');
        $dev = $this->makeUser('ROLE_DEV', 'dev@test.local');

        $devClient = $this->loginAs($dev);
        $this->request($devClient, 'GET', '/api/users');
        $this->assertResponseStatusCodeSame(403);

        $adminClient = $this->loginAs($admin);
        $created = $this->request($adminClient, 'POST', '/api/users', [
            'email' => 'new@test.local', 'fullName' => 'New', 'roles' => ['ROLE_USER'], 'plainPassword' => 'longpassword',
        ]);
        $this->assertResponseStatusCodeSame(201);
        $this->assertArrayNotHasKey('password', $created);
        $this->assertArrayNotHasKey('plainPassword', $created);
    }

    public function testUserCannotPromoteThemselves(): void
    {
        $user = $this->makeUser('ROLE_USER', 'user@test.local');
        $client = $this->loginAs($user);
        $this->request($client, 'PATCH', '/api/users/' . $user->getId(), ['roles' => ['ROLE_ADMIN']]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreatingUserWithoutPasswordIsRejected(): void
    {
        $client = $this->loginAs($this->makeUser('ROLE_ADMIN', 'admin@test.local'));
        $this->request($client, 'POST', '/api/users', ['email' => 'x@test.local', 'fullName' => 'X', 'roles' => ['ROLE_USER']]);
        $this->assertResponseStatusCodeSame(422);
    }
}
