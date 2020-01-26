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
use App\Entity\DefinizioneScrutinio;


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

    //-- // set TEMA nuovo per utente
    //-- $user = $em->getRepository('App:Docente')->findOneByUsername('x');
    //-- $n = $user->getNotifica();
    //-- $n['tema'] = 'new';
    //-- $user->setNotifica($n);
    //-- $em->flush();

    //-- // set definizione scrutinio
    //-- $a = [
      //-- 1 => 'Andamento didattico/disciplinare',
      //-- 2 => 'Scrutini primo trimestre',
      //-- 3 => 'Individuazione interventi di recupero e situazioni particolari da segnalare',
      //-- 4 => 'Indicazione, da parte dei C.d.C. del triennio, dei nomi dei TUTOR PCTO',
      //-- 5 => 'Adesione ai viaggi di istruzione'];
    //-- $s = [
      //-- 1 => ['ScrutinioInizio', false, []],
      //-- 2 => ['Argomento', true, ['sezione' => 'Punto primo', 'argomento' => 1]],
      //-- 3 => ['ScrutinioSvolgimento', false, ['sezione' => 'Punto secondo', 'argomento' => 2]],
      //-- 4 => ['Argomento', true, ['sezione' => 'Punto terzo', 'argomento' => 3]],
      //-- 5 => ['Argomento', true, ['sezione' => 'Punto quarto', 'argomento' => 4]],
      //-- 6 => ['Argomento', true, ['sezione' => 'Punto quinto', 'argomento' => 5]],
      //-- 7 => ['ScrutinioFine', false, []] ];
    //-- $def = (new DefinizioneScrutinio())
      //-- ->setData(\DateTime::createFromFormat('Y-m-d', '2019-12-16'))
      //-- ->setArgomenti($a)
      //-- ->setPeriodo('P')
      //-- ->setDataProposte(\DateTime::createFromFormat('Y-m-d', '2019-12-08'))
      //-- ->setStruttura($s);
    //-- $em->persist($def);
    //-- $em->flush();
    //-- print("<pre>");print_r($def);die;


    //----
    return $this->render('info/notelegali.html.twig', array(
      'pagina_titolo' => 'page.test',
    ));
  }

}
