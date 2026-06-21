<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RouteSmokeTest extends WebTestCase
{
    public function testAnonymousPublicPagesRender(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/register');
        self::assertResponseIsSuccessful();
    }

    public function testProtectedAccountPageRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/account');

        self::assertResponseRedirects('/login');
    }
}
