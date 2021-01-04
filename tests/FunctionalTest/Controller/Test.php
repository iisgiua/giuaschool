<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Tests\FunctionalTest\Controller;

//-- use App\DataFixtures\ClasseFixtures;
//-- use App\DataFixtures\DocenteFixtures;
//-- use App\Tests\UnitTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


class PostControllerTest extends WebTestCase {

    public function testShowPost() {
// bisogna caricare config!!! Altrimenti non sono presenti dati in sessioni
        $client = static::createClient();

        $crawler = $client->request('GET', '/login/form/');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertContains('Accesso al Registro Elettronico', $client->getResponse()->getContent());
    }

}
