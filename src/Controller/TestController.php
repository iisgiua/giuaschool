<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;


/**
 * TestController - pagine informative
 */
class TestController extends AbstractController {

  /**
   * Test
   *
   * @Route("/test/test", name="test",
   *    methods={"GET"})
   */
  public function testAction(EntityManagerInterface $em) {
    //----
    $user = $em->getRepository('App:Docente')->findOneByUsername('x');
    $n = $user->getNotifica();
    $n['tema'] = 'new';
    $user->setNotifica($n);
    $em->flush();


    print("<pre>");print_r($n);die;

    //----
    return $this->render('info/notelegali.html.twig', array(
      'pagina_titolo' => 'page.test',
    ));
  }

}
