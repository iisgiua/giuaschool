<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Tests\UnitTest\Controller;

use App\Controller\OAuth2Controller;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;


/**
 * Unit test dell'avvio dell'autenticazione tramite provider esterni
 *
 * @author Antonello Dessì
 */
class OAuth2ControllerTest extends TestCase {


  //==================== METODI DELLA CLASSE ====================

  /**
   * Test dell'avvio dell'autenticazione SPID/CIE tramite gateway MIM.
   *
   */
  public function testMimSpid(): void {
    // sessione e richiesta
    $sessione = new Session(new MockArraySessionStorage());
    $request = new Request();
    $request->setSession($sessione);
    // client OAuth2: controlla gli argomenti della redirezione
    $nonce = null;
    $client = $this->createMock(OAuth2ClientInterface::class);
    $client->expects($this->once())->method('redirect')
      ->with([], $this->callback(function (array $opzioni) use (&$nonce): bool {
        $nonce = $opzioni['nonce'] ?? null;
        return is_string($nonce) && preg_match('/^[0-9a-f]{64}$/', $nonce) === 1;
      }))
      ->willReturn(new RedirectResponse('https://eid.istruzione.it/eid-gateway-oidc/oauth2/authorize'));
    // registro dei client
    $registry = $this->createMock(ClientRegistry::class);
    $registry->expects($this->once())->method('getClient')->with('mimspid')->willReturn($client);
    // controller
    $controller = new OAuth2Controller($this->createMock(EntityManagerInterface::class), new RequestStack());
    $response = $controller->mimSpid($request, $registry);
    // verifica risposta e nonce salvato in sessione
    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertSame('https://eid.istruzione.it/eid-gateway-oidc/oauth2/authorize',
      $response->getTargetUrl());
    $this->assertIsString($nonce);
    $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $nonce);
    $this->assertSame($nonce, $sessione->get('_mim_oidc_nonce'));
  }

}
